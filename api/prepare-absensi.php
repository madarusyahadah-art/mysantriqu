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

$kegiatanId = filter_var($data['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
$tanggal = $data['tanggal'] ?? '';

$date = is_string($tanggal) ? DateTime::createFromFormat('!Y-m-d', $tanggal) : false;
if (!$kegiatanId || $kegiatanId < 1 || !$date || $date->format('Y-m-d') !== $tanggal) {
    json_response(["success" => false, "error" => "Kegiatan dan tanggal yang valid wajib dipilih"], 400);
}

$activityStmt = $conn->prepare("SELECT id FROM kegiatan WHERE id = ? AND is_active = 1");
$activityStmt->bind_param("i", $kegiatanId);
$activityStmt->execute();
$activityExists = $activityStmt->get_result()->num_rows > 0;
$activityStmt->close();
if (!$activityExists) {
    json_response(["success" => false, "error" => "Kegiatan tidak ditemukan atau tidak aktif"], 404);
}

$reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
$reportStmt->bind_param("is", $kegiatanId, $tanggal);
$reportStmt->execute();
$existingReport = $reportStmt->get_result()->fetch_assoc();
$reportStmt->close();
if ($existingReport && $currentUser['role'] !== 'admin' &&
    (int) $existingReport['dilaporkan_oleh'] !== (int) $currentUser['id']) {
    json_response(["success" => false, "error" => "Supervisor hanya dapat menyiapkan ulang laporan yang ia kirim"], 403);
}

$conn->begin_transaction();
try {
    $existingSessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
    $existingSessionStmt->bind_param("is", $kegiatanId, $tanggal);
    $existingSessionStmt->execute();
    $sessionCreated = $existingSessionStmt->get_result()->num_rows === 0;
    $existingSessionStmt->close();
    if (!$sessionCreated) {
        $conn->commit();
        json_response(["success" => true, "session_created" => false, "new_attendance_rows" => 0, "new_asatidz_rows" => 0]);
    }

    $sessionStmt = $conn->prepare("
        INSERT INTO absensi_sesi (kegiatan_id, tanggal, created_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");
    $userId = (int) $currentUser['id'];
    $sessionStmt->bind_param("isi", $kegiatanId, $tanggal, $userId);
    $sessionStmt->execute();
    $sessionStmt->close();

    $seedStmt = $conn->prepare("
        INSERT IGNORE INTO absensi
            (kegiatan_id, tanggal, santri_id, status, keterangan, tindak_lanjut, input_by)
        SELECT ?, ?, s.id, 'hadir', '', '', ?
        FROM santri s
        WHERE NOT EXISTS (SELECT 1 FROM kegiatan_semester WHERE kegiatan_id = ?)
           OR EXISTS (
                SELECT 1 FROM kegiatan_semester ks
                WHERE ks.kegiatan_id = ? AND ks.semester = s.semester_aktif
           )
    ");
    $seedStmt->bind_param("isiii", $kegiatanId, $tanggal, $userId, $kegiatanId, $kegiatanId);
    $seedStmt->execute();
    $createdCount = $seedStmt->affected_rows;
    $seedStmt->close();

    $seedAsatidzStmt = $conn->prepare("
        INSERT IGNORE INTO absensi_asatidz
            (kegiatan_id, tanggal, asatidz_id, nama_asatidz, mata_pelajaran, peran, status, keterangan, tindak_lanjut, input_by)
        SELECT ?, ?, a.id, a.nama, a.mata_pelajaran, ka.peran, 'hadir', '', '', ?
        FROM kegiatan_asatidz ka
        JOIN asatidz a ON a.id = ka.asatidz_id
        WHERE ka.kegiatan_id = ?
    ");
    $seedAsatidzStmt->bind_param("isii", $kegiatanId, $tanggal, $userId, $kegiatanId);
    $seedAsatidzStmt->execute();
    $createdAsatidzCount = $seedAsatidzStmt->affected_rows;
    $seedAsatidzStmt->close();

    $conn->commit();
    json_response([
        "success" => true,
        "session_created" => $sessionCreated,
        "new_attendance_rows" => $createdCount,
        "new_asatidz_rows" => $createdAsatidzCount
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('prepare-absensi.php error: ' . $e->getMessage());
    json_response(["success" => false, "error" => "Gagal menyiapkan absensi. Periksa konfigurasi database."], 500);
}
