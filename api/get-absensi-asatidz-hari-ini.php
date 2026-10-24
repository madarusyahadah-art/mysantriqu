<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$kegiatan_id = $_GET['kegiatan_id'] ?? '';
$tanggal = $_GET['tanggal'] ?? '';

if (empty($kegiatan_id) || empty($tanggal)) {
    echo json_encode(["error" => "kegiatan_id dan tanggal wajib diisi"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, asatidz_id, nama_asatidz, mata_pelajaran, peran, status, keterangan, tindak_lanjut
    FROM absensi_asatidz
    WHERE kegiatan_id = ? AND tanggal = ? AND status <> 'hadir'
    ORDER BY nama_asatidz ASC
");
$stmt->bind_param("is", $kegiatan_id, $tanggal);
$stmt->execute();
$asatidz = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(["success" => true, "asatidz" => $asatidz]);

$stmt->close();
$conn->close();
?>