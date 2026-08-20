<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Tambahkan OPTIONS di sini
header("Access-Control-Allow-Headers: X-API-TOKEN, Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$token = $_ENV['API_TOKEN'] ?? null;

$headers = apache_request_headers();

// Ubah key header menjadi lowercase atau sesuaikan dengan case-sensitive apache
// Beberapa server membaca X-API-TOKEN menjadi X-Api-Token atau x-api-token
$clientToken = null;
foreach ($headers as $key => $value) {
    if (strcasecmp($key, 'X-API-TOKEN') === 0) {
        $clientToken = $value;
        break;
    }
}

// Validasi token
if ($clientToken === null || $clientToken !== $token) {
    http_response_code(401);
    echo json_encode([
        'status' => false,
        'message' => 'Akses ditolak! Token API tidak valid atau tidak disertakan.'
    ]);
    exit; // Stop proses, jangan izinkan masuk ke routing dan database
}

declare(strict_types=1);

require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AbsensiController.php';

$requestUri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method      = $_SERVER['REQUEST_METHOD'];

//akses root domain
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

//buang base path di index.php
$path = $requestUri;
if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

// Hilangkan trailing slash agar "/api/auth/login/" dan "/api/auth/login"
// dianggap sama.
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// DAFTAR ROUTE
$routes = [
    'POST /api/auth/login'           => [AuthController::class, 'login'], //Login
    'GET /api/auth/me'               => [AuthController::class, 'me'], //Profil
    //'PUT /api/auth/change-password'  => [AuthController::class, 'changePassword'], //Ganti Password

    'POST /api/absensi/checkin'      => [AbsensiController::class, 'checkin'], //Absensi masuk
    'PUT /api/absensi/checkout'      => [AbsensiController::class, 'checkout'], //Absensi pulang
    'GET /api/absensi/today'         => [AbsensiController::class, 'today'], //Status harian
    'GET /api/absensi/riwayat'       => [AbsensiController::class, 'riwayat'], //Riwayat presensi
    'GET /api/absensi/detail'        => [AbsensiController::class, 'detail'], //Detail presensi
];

$routeKey = $method . ' ' . $path;

if (array_key_exists($routeKey, $routes)) {
    [$controllerClass, $methodName] = $routes[$routeKey];
    $controller = new $controllerClass();
    $controller->$methodName();
    exit;
}

jsonError('Endpoint tidak ditemukan', 404);
