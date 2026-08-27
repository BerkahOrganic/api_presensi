<?php
// controllers/AppVersionController.php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';

class AppVersionController
{
    // Info versi terbaru — TIDAK butuh login (dicek sebelum/tanpa
    // tergantung status login user), jadi sengaja tidak panggil requireAuth().
    public function getVersion(): void
    {
        $pdo = require __DIR__ . '/../config/database.php';

        $stmt = $pdo->query(
            'SELECT version_code, version_name, apk_url, is_mandatory, changelog
             FROM app_version
             ORDER BY version_code DESC
             LIMIT 1'
        );
        $data = $stmt->fetch();

        if ($data === false) {
            jsonError('Informasi versi belum tersedia', 404);
        }

        jsonSuccess('Berhasil mengambil informasi versi', [
            'version_code' => (int) $data['version_code'],
            'version_name' => $data['version_name'],
            'apk_url'      => $data['apk_url'],
            'is_mandatory' => (bool) $data['is_mandatory'],
            'changelog'    => $data['changelog'],
        ]);
    }
}