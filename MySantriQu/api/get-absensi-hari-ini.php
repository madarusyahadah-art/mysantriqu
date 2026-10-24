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

// Absensi lama belum memiliki sesi lengkap sehingga status hadirnya tidak dapat diasumsikan.
$session_stmt = $conn->prepare("SELECT id FROM absensi_sesi WHERE kegiatan_id = ? AND tanggal = ?");
$session_stmt->bind_param("is", $kegiatan_id, $tanggal);
$session_stmt->execute();
$session_active = $session_stmt->get_result()->num_rows > 0;
$session_stmt->close();

// Tampilkan hanya ketidakhadiran agar daftar tetap ringkas setelah status hadir disimpan.
$absensi_stmt = $conn->prepare("
    SELECT a.id, a.santri_id, a.status, a.keterangan, a.tindak_lanjut,
           s.nama, s.angkatan, a.input_by
    FROM absensi a
    LEFT JOIN santri s ON a.santri_id = s.id
    WHERE a.kegiatan_id = ? AND a.tanggal = ? AND a.status <> 'hadir'
    ORDER BY s.nama ASC
");
$absensi_stmt->bind_param("is", $kegiatan_id, $tanggal);
$absensi_stmt->execute();
$tidak_hadir = $absensi_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$hadir = 0;
$total_santri = 0;
if ($session_active) {
    $summary_stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS hadir
        FROM absensi
        WHERE kegiatan_id = ? AND tanggal = ?
    ");
    $summary_stmt->bind_param("is", $kegiatan_id, $tanggal);
    $summary_stmt->execute();
    $summary = $summary_stmt->get_result()->fetch_assoc();
    $total_santri = (int) $summary['total'];
    $hadir = (int) $summary['hadir'];
    $summary_stmt->close();
} else {
    $total_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM santri s
        WHERE NOT EXISTS (SELECT 1 FROM kegiatan_semester WHERE kegiatan_id = ?)
           OR EXISTS (
                SELECT 1 FROM kegiatan_semester ks
                WHERE ks.kegiatan_id = ? AND ks.semester = s.semester_aktif
           )
    ");
    $total_stmt->bind_param("ii", $kegiatan_id, $kegiatan_id);
    $total_stmt->execute();
    $total_santri = (int) $total_stmt->get_result()->fetch_assoc()['total'];
    $total_stmt->close();
}

$rosterStmt = $conn->prepare("SELECT santri_id FROM absensi WHERE kegiatan_id = ? AND tanggal = ?");
$rosterStmt->bind_param("is", $kegiatan_id, $tanggal);
$rosterStmt->execute();
$rosterIds = array_map('intval', array_column($rosterStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'santri_id'));
$rosterStmt->close();

$reportStmt = $conn->prepare("SELECT status, dilaporkan_oleh FROM laporan_kegiatan WHERE kegiatan_id = ? AND tanggal = ?");
$reportStmt->bind_param("is", $kegiatan_id, $tanggal);
$reportStmt->execute();
$report = $reportStmt->get_result()->fetch_assoc();
$reportStmt->close();

echo json_encode([
    "success" => true,
    "session_active" => $session_active,
    "total_santri" => $total_santri,
    "roster_santri_ids" => $rosterIds,
    "hadir" => $session_active ? $hadir : null,
    "tidak_hadir" => $tidak_hadir,
    "report" => $report,
    "can_edit_report" => !$report || $currentUser['role'] === 'admin' || (int) $report['dilaporkan_oleh'] === (int) $currentUser['id']
]);

$conn->close();
?>