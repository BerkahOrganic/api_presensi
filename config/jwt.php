<?php

declare(strict_types=1);

const JWT_SECRET = '';
const JWT_ALGORITHM = 'HS256';
const JWT_EXPIRY_SECONDS = 8 * 60 * 60;

// Umur token khusus untuk alur "Lupa Password via Wajah" — sengaja jauh
// lebih pendek dari token login biasa karena tujuannya cuma jembatan
// singkat antara "wajah berhasil dicocokkan" -> "set password baru".
const RESET_PASSWORD_TOKEN_EXPIRY_SECONDS = 5 * 60;
