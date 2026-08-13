<?php

/**:
 * - checkin()   -> POST /api/absensi/checkin
 * - checkout()  -> PUT  /api/absensi/checkout
 * - today()     -> GET  /api/absensi/today
 * - riwayat()   -> GET  /api/absensi/riwayat
 * - detail()    -> GET  /api/absensi/detail
 *
 * Semua endpoint di sini membutuhkan login (JWT).
 * NIK, ID_unit, ID_jabatan SELALU diambil dari payload token
 * requireAuth()
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

class AbsensiController
{
    // Absen Masuk
    public function checkin(): void
    {
        // Verifikasi token, ambil identitas user dari payload JWT
        $authUser = requireAuth();

        //Ambil dan decode body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $latitude   = trim((string) ($input['latitude'] ?? ''));
        $longitude  = trim((string) ($input['longitude'] ?? ''));
        $keterangan = $input['keterangan'] ?? null;

        // Validasi input
        if ($latitude === '' || $longitude === '') {
            jsonError('Latitude dan longitude wajib diisi', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Cek apakah user sudah check-in hari ini
        $cekStmt = $pdo->prepare(
            'SELECT ID_absensi FROM absensi WHERE NIK = ? AND Tanggal = CURDATE()'
        );
        $cekStmt->execute([$authUser['nik']]);

        if ($cekStmt->fetch() !== false) {
            jsonError('Anda sudah melakukan check-in hari ini', 409);
        }

        // Insert record absensi baru
        $insertStmt = $pdo->prepare(
            'INSERT INTO absensi
                (Tanggal, NIK, ID_unit, ID_jabatan, Masuk, Absensi, Keterangan, Longitude, Latitude)
             VALUES
                (CURDATE(), ?, ?, ?, CURTIME(), ?, ?, ?, ?)'
        );
        $insertStmt->execute([
            $authUser['nik'],
            $authUser['id_unit'],
            $authUser['id_jabatan'],
            'H',
            $keterangan,
            $longitude,
            $latitude,
        ]);

        $idAbsensi = (int) $pdo->lastInsertId();

        // Ambil kembali data yang baru diinsert untuk response
        $dataStmt = $pdo->prepare(
            'SELECT ID_absensi, Tanggal, Masuk, Absensi
             FROM absensi
             WHERE ID_absensi = ?'
        );
        $dataStmt->execute([$idAbsensi]);
        $data = $dataStmt->fetch();

        // Kirim response sukses
        jsonSuccess('Check-in berhasil', [
            'id_absensi' => (int) $data['ID_absensi'],
            'tanggal'    => $data['Tanggal'],
            'masuk'      => $data['Masuk'],
            'absensi'    => $data['Absensi'],
        ], 201);
    }

    // Absen Keluar
    public function checkout(): void
    {
        // Verifikasi token
        $authUser = requireAuth();

        // Ambil dan decode body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $latitude  = trim((string) ($input['latitude'] ?? ''));
        $longitude = trim((string) ($input['longitude'] ?? ''));

        // Validasi input — lat-long WAJIB dikirim baru saat checkout
        if ($latitude === '' || $longitude === '') {
            jsonError('Latitude dan longitude wajib diisi', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Cari record absensi hari ini milik user ini
        $cekStmt = $pdo->prepare(
            'SELECT ID_absensi, Keluar FROM absensi WHERE NIK = ? AND Tanggal = CURDATE()'
        );
        $cekStmt->execute([$authUser['nik']]);
        $record = $cekStmt->fetch();

        if ($record === false) {
            jsonError('Anda belum melakukan check-in hari ini', 404);
        }

        if ($record['Keluar'] !== null) {
            jsonError('Anda sudah melakukan check-out hari ini', 409);
        }

        // Update jam keluar + lokasi baru
        $updateStmt = $pdo->prepare(
            'UPDATE absensi
             SET Keluar = CURTIME(), Longitude = ?, Latitude = ?
             WHERE NIK = ? AND Tanggal = CURDATE()'
        );
        $updateStmt->execute([$longitude, $latitude, $authUser['nik']]);

        // Ambil kembali data terbaru untuk response
        $dataStmt = $pdo->prepare(
            'SELECT ID_absensi, Tanggal, Masuk, Keluar
             FROM absensi
             WHERE ID_absensi = ?'
        );
        $dataStmt->execute([$record['ID_absensi']]);
        $data = $dataStmt->fetch();

        // Kirim response sukses
        jsonSuccess('Check-out berhasil', [
            'id_absensi' => (int) $data['ID_absensi'],
            'tanggal'    => $data['Tanggal'],
            'masuk'      => $data['Masuk'],
            'keluar'     => $data['Keluar'],
        ]);
    }

    // Data absensi hari ini
    public function today(): void
    {
        // Verifikasi token
        $authUser = requireAuth();

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Ambil record absensi hari ini milik user ini
        $stmt = $pdo->prepare(
            'SELECT ID_absensi, Tanggal, Masuk, Keluar, Absensi, Keterangan, Longitude, Latitude
             FROM absensi
             WHERE NIK = ? AND Tanggal = CURDATE()'
        );
        $stmt->execute([$authUser['nik']]);
        $data = $stmt->fetch();

        if ($data === false) {
            jsonSuccess('Belum ada absensi hari ini', null);
        }

        // Kirim data yang ditemukan
        jsonSuccess('Data absensi hari ini ditemukan', [
            'id_absensi' => (int) $data['ID_absensi'],
            'tanggal'    => $data['Tanggal'],
            'masuk'      => $data['Masuk'],
            'keluar'     => $data['Keluar'],
            'absensi'    => $data['Absensi'],
            'keterangan' => $data['Keterangan'],
            'longitude'  => $data['Longitude'],
            'latitude'   => $data['Latitude'],
        ]);
    }

    // Riwayat absensi
    public function riwayat(): void
    {
        // Verifikasi token
        $authUser = requireAuth();

        // Ambil query parameter
        $dari   = $_GET['dari'] ?? null;
        $sampai = $_GET['sampai'] ?? null;

        // Validasi (Keduanya harus diisi)
        if (($dari !== null && $sampai === null) || ($dari === null && $sampai !== null)) {
            jsonError('Parameter dari dan sampai harus diisi bersamaan', 400);
        }

        // Validasi format tanggal jika diisi
        if ($dari !== null && !$this->isValidDate($dari)) {
            jsonError('Format tanggal dari tidak valid (harus YYYY-MM-DD)', 400);
        }
        if ($sampai !== null && !$this->isValidDate($sampai)) {
            jsonError('Format tanggal sampai tidak valid (harus YYYY-MM-DD)', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Query dengan atau tanpa filter tanggal
        if ($dari !== null && $sampai !== null) {
            $stmt = $pdo->prepare(
                'SELECT ID_absensi, Tanggal, Masuk, Keluar, Absensi, Keterangan
                 FROM absensi
                 WHERE NIK = ? AND Tanggal BETWEEN ? AND ?
                 ORDER BY Tanggal DESC'
            );
            $stmt->execute([$authUser['nik'], $dari, $sampai]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT ID_absensi, Tanggal, Masuk, Keluar, Absensi, Keterangan
                 FROM absensi
                 WHERE NIK = ?
                 ORDER BY Tanggal DESC'
            );
            $stmt->execute([$authUser['nik']]);
        }

        $rows = $stmt->fetchAll();

        // Bentuk ulang array agar key konsisten (lowercase) dengan endpoint lain
        $data = array_map(static function (array $row): array {
            return [
                'id_absensi' => (int) $row['ID_absensi'],
                'tanggal'    => $row['Tanggal'],
                'masuk'      => $row['Masuk'],
                'keluar'     => $row['Keluar'],
                'absensi'    => $row['Absensi'],
                'keterangan' => $row['Keterangan'],
            ];
        }, $rows);

        // Kirim response 
        jsonSuccess('Berhasil mengambil riwayat absensi', $data);
    }

    // Detail absensi
    public function detail(): void
    {
        // Verifikasi token
        $authUser = requireAuth();

        // Ambil query parameter id
        $id = $_GET['id'] ?? null;

        // Validasi: id wajib ada dan harus angka
        if ($id === null || !ctype_digit((string) $id)) {
            jsonError('ID absensi tidak valid', 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Ambil data 
        $stmt = $pdo->prepare(
            'SELECT ID_absensi, Tanggal, Masuk, Keluar, Absensi, Keterangan, Longitude, Latitude
             FROM absensi
             WHERE ID_absensi = ? AND NIK = ?'
        );
        $stmt->execute([(int) $id, $authUser['nik']]);
        $data = $stmt->fetch();

        if ($data === false) {
            jsonError('Data absensi tidak ditemukan', 404);
        }

        // Kirim response sukses
        jsonSuccess('Berhasil mengambil detail absensi', [
            'id_absensi' => (int) $data['ID_absensi'],
            'tanggal'    => $data['Tanggal'],
            'masuk'      => $data['Masuk'],
            'keluar'     => $data['Keluar'],
            'absensi'    => $data['Absensi'],
            'keterangan' => $data['Keterangan'],
            'longitude'  => $data['Longitude'],
            'latitude'   => $data['Latitude'],
        ]);
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
