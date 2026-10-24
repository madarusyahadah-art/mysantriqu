<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Method tidak diizinkan"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}

$activityId = filter_var($data['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
$dateText = is_string($data['tanggal'] ?? null) ? $data['tanggal'] : '';
$status = $data['status'] ?? 'dilaporkan';
$note = is_string($data['catatan'] ?? null) ? trim($data['catatan']) : '';
$date = DateTime::createFromFormat('!Y-m-d', $dateText);
if (!$activityId || !$date || $date->format('Y-m-d') !== $dateText ||
    !in_array($status, ['dilaporkan', 'tidak_berlangsung'], true)) {
    json_response(["success" => false, "error" => "Kegiatan, tanggal, atau status laporan tidak valid"], 400);
}

$activityStmt = $conn->prepare("SELECT id, frekuensi FROM kegiatan WHERE id = ? AND is_active = 1");
$activityStmt->bind_param("i", $activityId);
$activityStmt->execute();
$activity = $activityStmt->get_result()->fetch_assoc();
$activityStmt->close();
if (!$activity) {
    json_response(["success" => false, "error" => "Kegiatan tidak ditemukan atau tidak aktif"], 404);
}
if ($status === 'tidak_berlangsung' && $activity['frekuensi'] !== 'harian') {
    json_response(["success" => false, "error" => "Penanda tidak berlangsung hanya tersedia untuk kegiatan harian"], 400);
}

$conn->begin_transaction();
try {
$existingStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ? FOR UPDATE");
$existingStmt->bind_param("is", $activityId, $dateText);
$existingStmt->execute();
$existingReport = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();
if ($existingReport && $currentUser['role'] !== 'admin' &&
    (int) $existingReport['dilaporkan_oleh'] !== (int) $currentUser['id']) {
    $conn->rollback();
    json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah laporan yang ia kirim"], 403);
}

if ($status === 'dilaporkan') {
    $sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
    $sessionStmt->bind_param("is", $activityId, $dateText);
    $sessionStmt->execute();
    $sessionActive = $sessionStmt->get_result()->num_rows > 0;
    $sessionStmt->close();
    if (!$sessionActive) {
        $conn->rollback();
        json_response(["success" => false, "error" => "Siapkan dan lengkapi roster absensi sebelum mengirim laporan"], 409);
    }
} else {
    $sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
    $sessionStmt->bind_param("is", $activityId, $dateText);
    $sessionStmt->execute();
    $sessionActive = $sessionStmt->get_result()->num_rows > 0;
    $sessionStmt->close();
    if ($sessionActive) {
        $conn->rollback();
        json_response(["success" => false, "error" => "Sesi absensi sudah disiapkan; kirim laporan kehadiran, bukan menandai kegiatan tidak berlangsung"], 409);
    }
    $countStmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM absensi WHERE kegiatan_id = ? AND tanggal = ? AND status <> 'hadir') +
            (SELECT COUNT(*) FROM absensi_asatidz WHERE kegiatan_id = ? AND tanggal = ? AND status <> 'hadir') AS total
    ");
    $countStmt->bind_param("isis", $activityId, $dateText, $activityId, $dateText);
    $countStmt->execute();
    $issues = (int) $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    if ($issues > 0) {
        $conn->rollback();
        json_response(["success" => false, "error" => "Masih ada ketidakhadiran tercatat. Koreksi absensi sebelum menandai kegiatan tidak berlangsung."], 409);
    }
}

$userId = (int) $currentUser['id'];
$stmt = $conn->prepare("
    INSERT INTO laporan_kegiatan (kegiatan_id, tanggal, status, dilaporkan_oleh, catatan)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status), dilaporkan_oleh = VALUES(dilaporkan_oleh),
        catatan = VALUES(catatan), updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("issis", $activityId, $dateText, $status, $userId, $note);
if (!$stmt->execute()) throw new RuntimeException("Laporan kegiatan gagal disimpan");
$stmt->close();
$conn->commit();
json_response(["success" => true, "status" => $status]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('complete-activity-report.php error: ' . $error->getMessage());
    json_response(["success" => false, "error" => "Laporan kegiatan gagal disimpan"], 500);
}
