<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/response.php';

/**
 * Verifikasi token JWT dari header Authorization.
 * Mengembalikan payload token jika valid.
 *
 * @return array Payload JWT (nik, username, id_unit, id_jabatan, iat, exp)
 */
function requireAuth(): array
{
    $headers = getallheaders();

    $authHeader = null;
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }

    if ($authHeader === null || $authHeader === '') {
        jsonError('Token tidak ditemukan', 401);
    }

    // Format header yang diharapkan: "Bearer <token>"
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        jsonError('Format token tidak valid', 401);
    }

    $token = $matches[1];

    $payload = verifyJWT($token);

    if ($payload === null) {
        jsonError('Token tidak valid atau telah kadaluarsa', 401);
    }

    return $payload;
}
