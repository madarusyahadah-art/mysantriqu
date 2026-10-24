<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$id = $data['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID wajib diisi"]);
    exit;
}

$stmt = $conn->prepare("SELECT input_by, kegiatan_id, tanggal FROM absensi_asatidz WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$record) {
    json_response(["success" => false, "error" => "Catatan absensi tidak ditemukan"], 404);
}
$sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
$sessionStmt->bind_param("is", $record['kegiatan_id'], $record['tanggal']);
$sessionStmt->execute();
$sessionActive = $sessionStmt->get_result()->num_rows > 0;
$sessionStmt->close();
$reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
$reportStmt->bind_param("is", $record['kegiatan_id'], $record['tanggal']);
$reportStmt->execute();
$report = $reportStmt->get_result()->fetch_assoc();
$reportStmt->close();
if ($currentUser['role'] !== 'admin' &&
    (($report && (int) $report['dilaporkan_oleh'] !== (int) $currentUser['id']) ||
     (!$report && !$sessionActive && (int) $record['input_by'] !== (int) $currentUser['id']))) {
    json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah laporan miliknya setelah dikirim"], 403);
}

$stmt = $conn->prepare("UPDATE absensi_asatidz SET status = 'hadir', keterangan = '', tindak_lanjut = '', input_by = ? WHERE id = ?");
$stmt->bind_param("ii", $currentUser['id'], $id);
$stmt->execute();
$stmt->close();
json_response(["success" => true]);