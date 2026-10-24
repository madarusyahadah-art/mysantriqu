<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor']);

$result = $conn->query("SELECT id, nama, poin_default FROM jenis_pelanggaran WHERE is_active = 1 ORDER BY nama");
$types = $result->fetch_all(MYSQLI_ASSOC);
foreach ($types as &$type) {
    $type['id'] = (int) $type['id'];
    $type['poin_default'] = (int) $type['poin_default'];
}
unset($type);
json_response(["success" => true, "jenis_pelanggaran" => $types]);
