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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "ID absensi tidak valid"]);
    exit;
}

$row_stmt = $conn->prepare("
    SELECT a.kegiatan_id, a.tanggal
    FROM absensi a
    WHERE a.id = ? AND a.status <> 'hadir'
");
$row_stmt->bind_param("i", $id);
$row_stmt->execute();
$attendance = $row_stmt->get_result()->fetch_assoc();
$row_stmt->close();
if (!$attendance) {
    http_response_code(404);
    echo json_encode(["success" => false, "error" => "Catatan ketidakhadiran tidak ditemukan"]);
    exit;
}
$session_stmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
$session_stmt->bind_param("is", $attendance['kegiatan_id'], $attendance['tanggal']);
$session_stmt->execute();
$session_active = $session_stmt->get_result()->num_rows > 0;
$session_stmt->close();

$ownerStmt = $conn->prepare("SELECT input_by FROM absensi WHERE id = ?");
$ownerStmt->bind_param("i", $id);
$ownerStmt->execute();
$ownerId = (int) $ownerStmt->get_result()->fetch_assoc()['input_by'];
$ownerStmt->close();
if ($currentUser['role'] !== 'admin') {
    $reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
    $reportStmt->bind_param("is", $attendance['kegiatan_id'], $attendance['tanggal']);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
    if (($report && (int) $report['dilaporkan_oleh'] !== (int) $currentUser['id']) ||
        (!$report && !$session_active && $ownerId !== (int) $currentUser['id'])) {
        json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah laporan miliknya setelah dikirim"], 403);
    }
}

if ($session_active) {
    $stmt = $conn->prepare("UPDATE absensi SET status = 'hadir', keterangan = '', tindak_lanjut = '', input_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $currentUser['id'], $id);
} else {
    $stmt = $conn->prepare("DELETE FROM absensi WHERE id = ?");
    $stmt->bind_param("i", $id);
}

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "action" => $session_active ? "marked_present" : "deleted_legacy"
    ]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>