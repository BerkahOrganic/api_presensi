<?php

/**
 * helpers/response.php
 *
 * Helper untuk mengirim response JSON dengan format konsisten
 * ke seluruh aplikasi Flutter.
 *
 * Format sukses:
 * {
 *     "success": true,
 *     "message": "...",
 *     "data": {...}
 * }
 *
 * Format error:
 * {
 *     "success": false,
 *     "message": "...",
 *     "data": null
 * }
 */

declare(strict_types=1);

/**
 * Kirim response sukses lalu hentikan eksekusi script.
 *
 * @param string $message  Pesan yang menjelaskan hasil operasi
 * @param mixed  $data     Data yang dikembalikan (array, object, atau null)
 * @param int    $statusCode HTTP status code (default 200)
 */
function jsonSuccess(string $message, $data = null, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

/**
 * Kirim response error lalu hentikan eksekusi script.
 *
 * @param string $message  Pesan error yang aman ditampilkan ke user
 * @param int    $statusCode HTTP status code (400, 401, 404, 409, dst)
 */
function jsonError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'data'    => null,
    ]);
    exit;
}
