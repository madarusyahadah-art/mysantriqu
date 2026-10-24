<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor']);

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}

$kegiatanId = filter_var($data['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
$asatidzId = filter_var($data['asatidz_id'] ?? null, FILTER_VALIDATE_INT);
$tanggal = is_string($data['tanggal'] ?? null) ? $data['tanggal'] : '';
$status = is_string($data['status'] ?? null) ? $data['status'] : '';
$keterangan = is_string($data['keterangan'] ?? null) ? trim($data['keterangan']) : '';
$tindakLanjut = is_string($data['tindak_lanjut'] ?? null) ? trim($data['tindak_lanjut']) : '';
$allowedStatuses = ['izin', 'sakit', 'berhalangan', 'lainnya'];
$date = DateTime::createFromFormat('!Y-m-d', $tanggal);

if (!$kegiatanId || !$asatidzId || !$date || $date->format('Y-m-d') !== $tanggal || !in_array($status, $allowedStatuses, true)) {
    json_response(["success" => false, "error" => "Kegiatan, tanggal, asatidz, atau status tidak valid"], 400);
}

$activityStmt = $conn->prepare("SELECT id FROM kegiatan WHERE id = ?");
$activityStmt->bind_param("i", $kegiatanId);
$activityStmt->execute();
$activityExists = $activityStmt->get_result()->num_rows > 0;
$activityStmt->close();
if (!$activityExists) {
    json_response(["success" => false, "error" => "Kegiatan tidak ditemukan"], 404);
}

$personStmt = $conn->prepare("SELECT nama, mata_pelajaran FROM asatidz WHERE id = ?");
$personStmt->bind_param("i", $asatidzId);
$personStmt->execute();
$person = $personStmt->get_result()->fetch_assoc();
$personStmt->close();
if (!$person) {
    json_response(["success" => false, "error" => "Asatidz tidak ditemukan"], 404);
}

$assignmentStmt = $conn->prepare("SELECT peran FROM kegiatan_asatidz WHERE kegiatan_id = ? AND asatidz_id = ?");
$assignmentStmt->bind_param("ii", $kegiatanId, $asatidzId);
$assignmentStmt->execute();
$assignment = $assignmentStmt->get_result()->fetch_assoc();
$assignmentStmt->close();
$existingRosterStmt = $conn->prepare("
    SELECT id, peran, input_by
    FROM absensi_asatidz
    WHERE kegiatan_id = ? AND tanggal = ? AND asatidz_id = ?
");
$existingRosterStmt->bind_param("isi", $kegiatanId, $tanggal, $asatidzId);
$existingRosterStmt->execute();
$existingRoster = $existingRosterStmt->get_result()->fetch_assoc();
$existingRosterStmt->close();
$sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
$sessionStmt->bind_param("is", $kegiatanId, $tanggal);
$sessionStmt->execute();
$sessionActiveForRoster = $sessionStmt->get_result()->num_rows > 0;
$sessionStmt->close();
if ($sessionActiveForRoster && !$existingRoster) {
    json_response(["success" => false, "error" => "Asatidz tidak termasuk roster sesi absensi ini"], 400);
}
if (!$assignment && !$existingRoster) {
    json_response(["success" => false, "error" => "Asatidz tidak ditugaskan pada kegiatan ini"], 400);
}

$peran = $assignment['peran'] ?? $existingRoster['peran'];
$userId = (int) $currentUser['id'];

$legacyStmt = $conn->prepare("
    SELECT id
    FROM absensi_asatidz
    WHERE kegiatan_id = ? AND tanggal = ? AND asatidz_id IS NULL AND nama_asatidz = ?
    ORDER BY id ASC
    LIMIT 1
");
$legacyStmt->bind_param("iss", $kegiatanId, $tanggal, $person['nama']);
$legacyStmt->execute();
$legacyRecord = $legacyStmt->get_result()->fetch_assoc();
$legacyStmt->close();
if ($legacyRecord) {
    $legacyId = (int) $legacyRecord['id'];
    $ownerStmt = $conn->prepare("SELECT input_by FROM absensi_asatidz WHERE id = ?");
    $ownerStmt->bind_param("i", $legacyId);
    $ownerStmt->execute();
    $ownerId = (int) $ownerStmt->get_result()->fetch_assoc()['input_by'];
    $ownerStmt->close();
    $sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
    $sessionStmt->bind_param("is", $kegiatanId, $tanggal);
    $sessionStmt->execute();
    $sessionActive = $sessionStmt->get_result()->num_rows > 0;
    $sessionStmt->close();
    $reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
    $reportStmt->bind_param("is", $kegiatanId, $tanggal);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
    if ($currentUser['role'] !== 'admin' &&
        (($report && (int) $report['dilaporkan_oleh'] !== (int) $currentUser['id']) ||
         (!$report && !$sessionActive && $ownerId !== (int) $currentUser['id']))) {
        json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah catatan yang ia input"], 403);
    }

    $update = $conn->prepare("
        UPDATE absensi_asatidz
        SET asatidz_id = ?, mata_pelajaran = ?, peran = ?, status = ?,
            keterangan = ?, tindak_lanjut = ?, input_by = ?
        WHERE id = ? AND asatidz_id IS NULL
    ");
    $update->bind_param(
        "isssssii",
        $asatidzId,
        $person['mata_pelajaran'],
        $peran,
        $status,
        $keterangan,
        $tindakLanjut,
        $userId,
        $legacyId
    );
    $update->execute();
    $update->close();
    json_response(["success" => true, "updated_legacy_record" => true]);
}

$existingRecord = $existingRoster;
if ($existingRecord && $currentUser['role'] !== 'admin') {
    $sessionStmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
    $sessionStmt->bind_param("is", $kegiatanId, $tanggal);
    $sessionStmt->execute();
    $sessionActive = $sessionStmt->get_result()->num_rows > 0;
    $sessionStmt->close();
    $reportStmt = $conn->prepare("SELECT dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
    $reportStmt->bind_param("is", $kegiatanId, $tanggal);
    $reportStmt->execute();
    $report = $reportStmt->get_result()->fetch_assoc();
    $reportStmt->close();
    if (($report && (int) $report['dilaporkan_oleh'] !== $userId) ||
        (!$report && !$sessionActive && (int) $existingRecord['input_by'] !== $userId)) {
        json_response(["success" => false, "error" => "Supervisor hanya dapat mengubah laporan miliknya setelah dikirim"], 403);
    }
}

$stmt = $conn->prepare("
    INSERT INTO absensi_asatidz
        (kegiatan_id, tanggal, asatidz_id, nama_asatidz, mata_pelajaran, peran, status, keterangan, tindak_lanjut, input_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        keterangan = VALUES(keterangan),
        tindak_lanjut = VALUES(tindak_lanjut),
        input_by = VALUES(input_by)
");
$stmt->bind_param(
    "isissssssi",
    $kegiatanId,
    $tanggal,
    $asatidzId,
    $person['nama'],
    $person['mata_pelajaran'],
    $peran,
    $status,
    $keterangan,
    $tindakLanjut,
    $userId
);
$stmt->execute();
$stmt->close();
json_response(["success" => true]);
?>