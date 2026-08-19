<?php

/**
 * Berisi logic untuk endpoint-endpoint terkait autentikasi:
 * - login()            /login
 * - me()               /me
 * - changePassword()    /change-password
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController
{
    // Login
    public function login(): void
    {
        // Ambil dan decode body JSON dari request
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $username = trim($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');

        // Validasi input dasar
        if ($username === '' || $password === '') {
            jsonError('Username dan password wajib diisi', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Cari user berdasarkan username
        $stmt = $pdo->prepare(
            'SELECT l.password, l.username, k.nik, k.nama, k.status_aktif,
                    k.id_unit, u.nm_unit, k.id_jabatan, j.nm_jabatan
             FROM login l
             JOIN karyawan k ON l.nik = k.nik
             JOIN unit u ON k.id_unit = u.id_unit
             JOIN jabatan j ON k.id_jabatan = j.id_jabatan
             WHERE l.username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Jika username tidak ditemukan ATAU password salah,
        if ($user === false || !password_verify($password, $user['password'])) {
            jsonError('Username atau password salah', 401);
        }

        // Validasi status aktif user
        if ($user['status_aktif'] !== 'Aktif') {
            jsonError('Akun tidak aktif. Silakan hubungi admin', 403);
        }

        // Buat payload JWT dari data user (tanpa password)
        $payload = [
            'nik'        => $user['nik'],
            'username'   => $user['username'],
            'nama'       => $user['nama'],
            'id_unit'    => $user['id_unit'],
            'nm_unit'    => $user['nm_unit'],
            'id_jabatan' => $user['id_jabatan'],
            'nm_jabatan' => $user['nm_jabatan'],
        ];

        $token = generateJWT($payload);

        // Kirim response sukses
        jsonSuccess('Login berhasil', [
            'token' => $token,
            'user'  => [
                'nik'        => $user['nik'],
                'username'   => $user['username'],
                'nama'       => $user['nama'],
                'id_unit'    => $user['id_unit'],
                'nm_unit'    => $user['nm_unit'],
                'id_jabatan' => $user['id_jabatan'],
                'nm_jabatan' => $user['nm_jabatan'],
            ],
        ]);
    }

    // Menampilkan data user
    public function me(): void
    {
        // Verifikasi token, ambil payload (berisi nik dari token)
        $authUser = requireAuth();

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Ambil data terbaru dari database berdasarkan NIK di token
        $stmt = $pdo->prepare(
            'SELECT l.username, k.nik, k.nama,
                    k.id_unit, u.nm_unit, k.id_jabatan, j.nm_jabatan
             FROM login l
             JOIN karyawan k ON l.nik = k.nik
             JOIN unit u ON k.id_unit = u.id_unit
             JOIN jabatan j ON k.id_jabatan = j.id_jabatan
             WHERE k.nik = ?'
        );
        $stmt->execute([$authUser['nik']]);
        $user = $stmt->fetch();

        // Edge case: NIK di token valid, tapi datanya sudah tidak ada di DB
        if ($user === false) {
            jsonError('User tidak ditemukan', 404);
        }

        // Kirim response sukses
        jsonSuccess('Berhasil mengambil data user', [
            'nik'        => $user['nik'],
            'username'   => $user['username'],
            'nama'       => $user['nama'],
            'id_unit'    => $user['id_unit'],
            'nm_unit'   => $user['nm_unit'],
            'id_jabatan' => $user['id_jabatan'],
            'nm_jabatan' => $user['nm_jabatan'],
        ]);
    }

    // Mengganti Password
    public function changePassword(): void
    {
        // Verifikasi token
        $authUser = requireAuth();

        // Ambil dan decode body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $oldPassword = (string) ($input['old_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');

        // Validasi input
        if ($oldPassword === '' || $newPassword === '') {
            jsonError('Password lama dan password baru wajib diisi', 400);
        }

        if (strlen($newPassword) < 6) {
            jsonError('Password baru minimal 6 karakter', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Ambil hash password saat ini milik user (NIK dari token, bukan dari body)
        $stmt = $pdo->prepare('SELECT password FROM login WHERE nik = ?');
        $stmt->execute([$authUser['nik']]);
        $user = $stmt->fetch();

        if ($user === false) {
            jsonError('User tidak ditemukan', 404);
        }

        // Verifikasi password lama sebelum mengizinkan perubahan
        if (!password_verify($oldPassword, $user['Password'])) {
            jsonError('Password lama tidak sesuai', 401);
        }

        // Hash password baru, lalu update
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $pdo->prepare('UPDATE login SET password = ? WHERE nik = ?');
        $updateStmt->execute([$newHash, $authUser['nik']]);

        // Kirim response sukses
        jsonSuccess('Password berhasil diubah');
    }
}
