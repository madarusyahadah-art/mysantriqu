<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor', 'kepala']);

$start = is_string($_GET['tanggal_mulai'] ?? null) ? $_GET['tanggal_mulai'] : date('Y-m-d', strtotime('-30 days'));
$end = is_string($_GET['tanggal_selesai'] ?? null) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$activityId = filter_var($_GET['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
$dateValid = static function ($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) &&
        checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));
};
if (!$dateValid($start) || !$dateValid($end) || $start > $end) {
    json_response(["success" => false, "error" => "Rentang tanggal tidak valid"], 400);
}

$activityFilter = $activityId ? " AND k.id = ?" : "";
$params = [$start, $end];
$types = "ss";
if ($activityId) {
    $params[] = $activityId;
    $types .= "i";
}
$sql = "
    SELECT k.id AS kegiatan_id, k.nama_kegiatan, lk.tanggal, lk.status AS status_laporan,
           lk.catatan AS catatan_laporan, u.name AS nama_pelapor,
           s.nama AS nama_peserta, 'Santri' AS jenis_peserta, a.status,
           a.keterangan, a.tindak_lanjut
    FROM laporan_kegiatan lk
    JOIN kegiatan k ON k.id = lk.kegiatan_id
    LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
    LEFT JOIN absensi a ON a.kegiatan_id = lk.kegiatan_id AND a.tanggal = lk.tanggal
    LEFT JOIN santri s ON s.id = a.santri_id
    WHERE lk.tanggal BETWEEN ? AND ? $activityFilter
    UNION ALL
    SELECT k.id, k.nama_kegiatan, lk.tanggal, lk.status, lk.catatan, u.name,
           aa.nama_asatidz, 'Asatidz', aa.status, aa.keterangan, aa.tindak_lanjut
    FROM laporan_kegiatan lk
    JOIN kegiatan k ON k.id = lk.kegiatan_id
    LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
    LEFT JOIN absensi_asatidz aa ON aa.kegiatan_id = lk.kegiatan_id AND aa.tanggal = lk.tanggal
    WHERE lk.tanggal BETWEEN ? AND ? $activityFilter
    ORDER BY tanggal DESC, nama_kegiatan ASC, jenis_peserta ASC, nama_peserta ASC
";
$reportParams = array_merge($params, $params);
$reportTypes = $types . $types;
$stmt = $conn->prepare($sql);
$stmt->bind_param($reportTypes, ...$reportParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$activities = $conn->query("SELECT id, nama_kegiatan FROM kegiatan ORDER BY nama_kegiatan")->fetch_all(MYSQLI_ASSOC);
json_response([
    "success" => true,
    "laporan" => $rows,
    "kegiatan_list" => $activities,
    "filter" => ["tanggal_mulai" => $start, "tanggal_selesai" => $end, "kegiatan_id" => $activityId ?: ""]
]);
