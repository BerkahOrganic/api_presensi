<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-TOKEN, Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$token = "$A!BC@D#E$F%G^H&I*J(K)L+M-N=O_P{Q}R[S]T|U:V;W<X>Y?Z";

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
