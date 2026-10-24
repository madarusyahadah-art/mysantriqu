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

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON data"]);
    exit;
}

$kegiatan_id = filter_var($data['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
$tanggal = $data['tanggal'] ?? '';
$santri_id = filter_var($data['santri_id'] ?? null, FILTER_VALIDATE_INT);
$status = $data['status'] ?? '';
$keterangan = $data['keterangan'] ?? '';
$tindak_lanjut = $data['tindak_lanjut'] ?? '';
$user_id = (int) $currentUser['id'];

$date = is_string($tanggal) ? DateTime::createFromFormat('!Y-m-d', $tanggal) : false;
if (
    !$kegiatan_id || $kegiatan_id < 1 ||
    !$santri_id || $santri_id < 1 ||
    !$date || $date->format('Y-m-d') !== $tanggal ||
    !in_array($status, ['izin', 'sakit', 'alpha', 'izin_menginap'], true)
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Data absensi tidak valid"]);
    exit;
}

$session_stmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
$session_stmt->bind_param("is", $kegiatan_id, $tanggal);
$session_stmt->execute();
$session_active = $session_stmt->get_result()->num_rows > 0;
$session_stmt->close();
if (!$session_active) {
    http_response_code(409);
    echo json_encode(["success" => false, "error" => "Siapkan sesi absensi lengkap terlebih dahulu"]);
    exit;
}

$eligibleStmt = $conn->prepare("SELECT id FROM absensi WHERE kegiatan_id = ? AND tanggal = ? AND santri_id = ?");
$eligibleStmt->bind_param("isi", $kegiatan_id, $tanggal, $santri_id);
$eligibleStmt->execute();
$eligible = $eligibleStmt->get_result()->num_rows > 0;
$eligibleStmt->close();
if (!$eligible) {
    json_response(["success" => false, "error" => "Santri tidak termasuk roster sesi absensi ini"], 400);
}

// Cek apakah sudah ada (untuk update)
$check_stmt = $conn->prepare("SELECT id, input_by FROM absensi WHERE kegiatan_id = ? AND tanggal = ? AND santri_id = ?");
$check_stmt->bind_param("isi", $kegiatan_id, $tanggal, $santri_id);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    if ($currentUser['role'] !== 'admin') {
        $reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
        $reportStmt->bind_param("is", $kegiatan_id, $tanggal);
        $reportStmt->execute();
        $report = $reportStmt->get_result()->fetch_assoc();
        $reportStmt->close();
        if (($report && (int) $report['dilaporkan_oleh'] !== $user_id) ||
            (!$report && !$session_active && (int) $existing['input_by'] !== $user_id)) {
            json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah laporan miliknya setelah dikirim"], 403);
        }
    }
    $stmt = $conn->prepare("UPDATE absensi SET status = ?, keterangan = ?, tindak_lanjut = ?, input_by = ? WHERE id = ?");
    $stmt->bind_param("sssii", $status, $keterangan, $tindak_lanjut, $user_id, $existing['id']);
} else {
    // Insert
    $stmt = $conn->prepare("INSERT INTO absensi (kegiatan_id, tanggal, santri_id, status, keterangan, tindak_lanjut, input_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $kegiatan_id, $tanggal, $santri_id, $status, $keterangan, $tindak_lanjut, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>