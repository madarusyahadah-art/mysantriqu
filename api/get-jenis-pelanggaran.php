<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'musyrif', 'supervisor', "mas'ul"], true)) {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses ke jenis pelanggaran.'], 403);
}

$includeInactive = ($_GET['include_inactive'] ?? '') === '1';
if ($includeInactive && strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat melihat jenis pelanggaran nonaktif.'], 403);
}

try {
    $connection = db_connection();
    $result = $connection->query(
        'SELECT id, nama, poin_default, is_active
         FROM jenis_pelanggaran' . ($includeInactive ? '' : ' WHERE is_active = 1') . '
         ORDER BY is_active DESC, nama ASC, id ASC'
    );
    $types = $result->fetch_all(MYSQLI_ASSOC);
    foreach ($types as &$type) {
        $type['id'] = (int) $type['id'];
        $type['poin_default'] = (int) $type['poin_default'];
        $type['is_active'] = (bool) $type['is_active'];
    }
    unset($type);
    json_response(['success' => true, 'jenis_pelanggaran' => $types]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Read violation types failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Jenis pelanggaran belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}