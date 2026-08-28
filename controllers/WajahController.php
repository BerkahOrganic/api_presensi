<?php

/**
 * controllers/WajahController.php
 *
 * Berisi logic untuk endpoint-endpoint terkait face recognition:
 * - enroll()    -> POST /api/wajah/enroll        (daftarkan wajah referensi)
 * - verifikasi() -> POST /api/absensi/verify-face  (cocokkan wajah saat absen)
 *
 * Face embedding (vector 192 angka) DIHITUNG DI FLUTTER (on-device, pakai
 * model MobileFaceNet). PHP di sini TIDAK memproses gambar sama sekali —
 * cuma menyimpan & membandingkan angka-angka embedding yang sudah jadi.
 *
 * Semua endpoint di sini membutuhkan login (JWT).
 * NIK SELALU diambil dari payload token requireAuth(), bukan dari body.
 *
 * Validasi embedding & perhitungan cosine similarity dipakai bersama
 * dengan alur "Lupa Password via Wajah" di AuthController, jadi
 * logic-nya ditaruh satu tempat di helpers/face.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/face.php';
require_once __DIR__ . '/../middleware/auth.php';

class WajahController
{
    // Daftarkan / perbarui wajah referensi milik user yang login
    public function enroll(): void
    {
        // Verifikasi token, ambil NIK dari payload JWT
        $authUser = requireAuth();

        // Ambil dan decode body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $embedding = $input['embedding'] ?? null;

        $error = validasiEmbedding($embedding);
        if ($error !== null) {
            jsonError($error, 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Simpan sebagai JSON text. INSERT ... ON DUPLICATE KEY UPDATE
        // supaya bisa dipanggil ulang kalau user mau daftar ulang wajahnya
        // (misal ganti gaya rambut drastis, dsb) tanpa perlu endpoint terpisah.
        $embeddingJson = json_encode($embedding);

        $stmt = $pdo->prepare(
            'INSERT INTO wajah_referensi (nik, embedding)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE embedding = VALUES(embedding)'
        );
        $stmt->execute([$authUser['nik'], $embeddingJson]);

        // Kirim response sukses
        jsonSuccess('Wajah referensi berhasil didaftarkan');
    }

    // Cocokkan embedding wajah saat ini dengan wajah referensi yang tersimpan
    public function verifikasi(): void
    {
        // Verifikasi token, ambil NIK dari payload JWT
        $authUser = requireAuth();

        // Ambil dan decode body JSON
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            jsonError('Body request tidak valid (harus JSON)', 400);
        }

        $embedding = $input['embedding'] ?? null;

        $error = validasiEmbedding($embedding);
        if ($error !== null) {
            jsonError($error, 400);
        }

        // Ambil koneksi database
        $pdo = require __DIR__ . '/../config/database.php';

        // Ambil embedding referensi milik user ini
        $stmt = $pdo->prepare('SELECT embedding FROM wajah_referensi WHERE nik = ?');
        $stmt->execute([$authUser['nik']]);
        $row = $stmt->fetch();

        if ($row === false) {
            jsonError(
                'Wajah referensi belum terdaftar. Silakan daftarkan wajah '
                    . 'terlebih dahulu lewat halaman Profil.',
                404
            );
        }

        $referensi = json_decode($row['embedding'], true);

        if (!is_array($referensi) || count($referensi) !== FACE_EMBEDDING_LENGTH) {
            // Data referensi di database korup/rusak — kasus langka tapi
            // penting ditangani supaya tidak fatal error ke user.
            jsonError('Data wajah referensi tidak valid, silakan daftar ulang', 500);
        }

        $score = cosineSimilarity($embedding, $referensi);
        $match = $score >= FACE_MATCH_THRESHOLD;

        // Kirim response sukses — perhatikan: "tidak cocok" BUKAN error
        // HTTP, tetap success:true supaya Flutter bisa baca field "match"
        // secara normal (lihat komentar di ApiConfig.verifyFace Flutter).
        jsonSuccess('Verifikasi wajah selesai diproses', [
            'match' => $match,
            'score' => round($score, 4),
        ]);
    }
}
