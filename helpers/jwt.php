<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/jwt.php';

// Encode string/array menjadi base64url
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Decode kembali base64url menjadi string asli
function base64UrlDecode(string $data): string
{
    $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
    return base64_decode(strtr($padded, '-_', '+/'));
}

/**
 * @param array $payload Data yang ingin disimpan di token,
 * @return string Token JWT lengkap (header.payload.signature)
 */
function generateJWT(array $payload): string
{
    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM,
    ];

    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + JWT_EXPIRY_SECONDS;

    $headerEncoded  = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac(
        'sha256',
        $headerEncoded . '.' . $payloadEncoded,
        JWT_SECRET,
        true
    );
    $signatureEncoded = base64UrlEncode($signature);

    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}

/**
 * Memverifikasi JWT: mengecek signature valid dan token belum kadaluarsa.
 *
 * @param string $token Token JWT dari header Authorization
 * @return array|null Payload token jika valid, atau null jika tidak valid/kadaluarsa
 */
function verifyJWT(string $token): ?array
{
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        return null; // format token tidak sesuai JWT
    }

    [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

    // Hitung ulang signature dari header + payload yang diterima
    $expectedSignature = hash_hmac(
        'sha256',
        $headerEncoded . '.' . $payloadEncoded,
        JWT_SECRET,
        true
    );
    $expectedSignatureEncoded = base64UrlEncode($expectedSignature);

    // Bandingkan signature dengan cara yang aman dari timing attack
    if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
        return null; // signature tidak cocok -> token dipalsukan/rusak
    }

    $payload = json_decode(base64UrlDecode($payloadEncoded), true);

    if (!is_array($payload) || !isset($payload['exp'])) {
        return null; // payload rusak / tidak ada klaim exp
    }

    if (time() > $payload['exp']) {
        return null; // token sudah kadaluarsa
    }

    return $payload;
}
