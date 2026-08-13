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
            'SELECT NIK, ID_unit, ID_jabatan, Username, Password
             FROM login
             WHERE Username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Jika username tidak ditemukan ATAU password salah,
        if ($user === false || !password_verify($password, $user['Password'])) {
            jsonError('Username atau password salah', 401);
        }

        // Buat payload JWT dari data user (tanpa password)
        $payload = [
            'nik'        => $user['NIK'],
            'username'   => $user['Username'],
            'id_unit'    => $user['ID_unit'],
            'id_jabatan' => $user['ID_jabatan'],
        ];

        $token = generateJWT($payload);

        // Kirim response sukses
        jsonSuccess('Login berhasil', [
            'token' => $token,
            'user'  => [
                'nik'        => $user['NIK'],
                'username'   => $user['Username'],
                'id_unit'    => $user['ID_unit'],
                'id_jabatan' => $user['ID_jabatan'],
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
            'SELECT NIK, ID_unit, ID_jabatan, Username
             FROM login
             WHERE NIK = ?'
        );
        $stmt->execute([$authUser['nik']]);
        $user = $stmt->fetch();

        // Edge case: NIK di token valid, tapi datanya sudah tidak ada di DB
        if ($user === false) {
            jsonError('User tidak ditemukan', 404);
        }

        // Kirim response sukses
        jsonSuccess('Berhasil mengambil data user', [
            'nik'        => $user['NIK'],
            'username'   => $user['Username'],
            'id_unit'    => $user['ID_unit'],
            'id_jabatan' => $user['ID_jabatan'],
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
        $stmt = $pdo->prepare('SELECT Password FROM login WHERE NIK = ?');
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

        $updateStmt = $pdo->prepare('UPDATE login SET Password = ? WHERE NIK = ?');
        $updateStmt->execute([$newHash, $authUser['nik']]);

        // Kirim response sukses
        jsonSuccess('Password berhasil diubah');
    }

    // Mengganti Username
    public function changeUsername(): void
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
        $stmt = $pdo->prepare('SELECT Password FROM login WHERE NIK = ?');
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

        $updateStmt = $pdo->prepare('UPDATE login SET Password = ? WHERE NIK = ?');
        $updateStmt->execute([$newHash, $authUser['nik']]);

        // Kirim response sukses
        jsonSuccess('Password berhasil diubah');
    }
}
