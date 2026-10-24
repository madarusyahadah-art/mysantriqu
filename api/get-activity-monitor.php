<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor', 'kepala']);

$date = is_string($_GET['tanggal'] ?? null) ? $_GET['tanggal'] : date('Y-m-d');
$activityId = filter_var($_GET['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
    !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
    json_response(["success" => false, "error" => "Tanggal tidak valid"], 400);
}

if ($activityId) {
    $stmt = $conn->prepare("
        SELECT k.id, k.nama_kegiatan, k.frekuensi, lk.status AS status_laporan,
               lk.catatan, lk.dilaporkan_oleh, u.name AS nama_pelapor,
               (SELECT COUNT(*) FROM absensi a
                WHERE a.kegiatan_id = k.id AND a.tanggal = ? AND a.status <> 'hadir') AS santri_tidak_hadir,
               (SELECT COUNT(*) FROM absensi_asatidz aa
                WHERE aa.kegiatan_id = k.id AND aa.tanggal = ? AND aa.status <> 'hadir'
                  AND aa.asatidz_id IS NOT NULL) AS asatidz_tidak_hadir
        FROM kegiatan k
        LEFT JOIN laporan_kegiatan lk ON lk.kegiatan_id = k.id AND lk.tanggal = ?
        LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
        WHERE k.id = ?
    ");
    $stmt->bind_param("sssi", $date, $date, $date, $activityId);
} else {
    $stmt = $conn->prepare("
        SELECT k.id, k.nama_kegiatan, k.frekuensi, lk.status AS status_laporan,
               lk.catatan, lk.dilaporkan_oleh, u.name AS nama_pelapor,
               (SELECT COUNT(*) FROM absensi a
                WHERE a.kegiatan_id = k.id AND a.tanggal = ? AND a.status <> 'hadir') AS santri_tidak_hadir,
               (SELECT COUNT(*) FROM absensi_asatidz aa
                WHERE aa.kegiatan_id = k.id AND aa.tanggal = ? AND aa.status <> 'hadir'
                  AND aa.asatidz_id IS NOT NULL) AS asatidz_tidak_hadir
        FROM kegiatan k
        LEFT JOIN laporan_kegiatan lk ON lk.kegiatan_id = k.id AND lk.tanggal = ?
        LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
        WHERE k.is_active = 1
          AND (k.frekuensi = 'harian' OR lk.id IS NOT NULL)
        ORDER BY k.slide_order ASC, k.id ASC
    ");
    $stmt->bind_param("sss", $date, $date, $date);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$details = [];
if ($activityId) {
    $santriStmt = $conn->prepare("
        SELECT s.nama, a.status, a.keterangan, a.tindak_lanjut
        FROM absensi a
        JOIN santri s ON s.id = a.santri_id
        WHERE a.kegiatan_id = ? AND a.tanggal = ? AND a.status <> 'hadir'
        ORDER BY s.nama
    ");
    $santriStmt->bind_param("is", $activityId, $date);
    $santriStmt->execute();
    $details['santri'] = $santriStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $santriStmt->close();

    $asatidzStmt = $conn->prepare("
        SELECT nama_asatidz AS nama, mata_pelajaran, peran, status, keterangan, tindak_lanjut
        FROM absensi_asatidz
        WHERE kegiatan_id = ? AND tanggal = ? AND status <> 'hadir' AND asatidz_id IS NOT NULL
        ORDER BY nama_asatidz
    ");
    $asatidzStmt->bind_param("is", $activityId, $date);
    $asatidzStmt->execute();
    $details['asatidz'] = $asatidzStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $asatidzStmt->close();
}

json_response([
    "success" => true,
    "tanggal" => $date,
    "kegiatan" => $activities,
    "ketidakhadiran" => $details,
    "current_user_id" => (int) $currentUser['id']
]);
