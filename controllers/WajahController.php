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
 */

declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

class WajahController
{
    // Jumlah angka yang harus ada di setiap embedding MobileFaceNet.
    // Kalau nanti ganti model dengan output dimensi lain, ubah nilai ini.
    private const EMBEDDING_LENGTH = 192;

    // Ambang batas cosine similarity untuk dianggap "cocok".
    // 1.0 = identik sempurna, 0.0 = tidak mirip sama sekali.
    // Nilai 0.6 adalah titik awal yang wajar untuk MobileFaceNet —
    // sesuaikan lagi (naik/turun) setelah beberapa kali testing nyata.
    private const MATCH_THRESHOLD = 0.6;

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

        $error = $this->validasiEmbedding($embedding);
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

        $error = $this->validasiEmbedding($embedding);
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

        if (!is_array($referensi) || count($referensi) !== self::EMBEDDING_LENGTH) {
            // Data referensi di database korup/rusak — kasus langka tapi
            // penting ditangani supaya tidak fatal error ke user.
            jsonError('Data wajah referensi tidak valid, silakan daftar ulang', 500);
        }

        $score = $this->cosineSimilarity($embedding, $referensi);
        $match = $score >= self::MATCH_THRESHOLD;

        // Kirim response sukses — perhatikan: "tidak cocok" BUKAN error
        // HTTP, tetap success:true supaya Flutter bisa baca field "match"
        // secara normal (lihat komentar di ApiConfig.verifyFace Flutter).
        jsonSuccess('Verifikasi wajah selesai diproses', [
            'match' => $match,
            'score' => round($score, 4),
        ]);
    }

    // Validasi bentuk embedding: harus array angka sepanjang EMBEDDING_LENGTH
    private function validasiEmbedding($embedding): ?string
    {
        if (!is_array($embedding)) {
            return 'Field embedding wajib berupa array angka';
        }

        if (count($embedding) !== self::EMBEDDING_LENGTH) {
            return 'Panjang embedding tidak valid (harus '
                . self::EMBEDDING_LENGTH . ' angka)';
        }

        foreach ($embedding as $nilai) {
            if (!is_int($nilai) && !is_float($nilai)) {
                return 'Semua elemen embedding harus berupa angka';
            }
        }

        return null; // valid
    }

    // Hitung cosine similarity antara 2 vector embedding.
    // Hasil mendekati 1.0 = sangat mirip, mendekati 0.0 = tidak mirip.
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $panjang = count($a);
        for ($i = 0; $i < $panjang; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0; // hindari pembagian dengan nol
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
