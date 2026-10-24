<?php
require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor', 'kepala']);

$start = is_string($_GET['tanggal_mulai'] ?? null) ? $_GET['tanggal_mulai'] : '';
$end = is_string($_GET['tanggal_selesai'] ?? null) ? $_GET['tanggal_selesai'] : '';
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
    SELECT k.nama_kegiatan, lk.tanggal, lk.status AS status_laporan, lk.catatan AS catatan_laporan,
           u.name AS nama_pelapor, s.nama AS nama_peserta, 'Santri' AS jenis_peserta,
           a.status, a.keterangan, a.tindak_lanjut
    FROM laporan_kegiatan lk
    JOIN kegiatan k ON k.id = lk.kegiatan_id
    LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
    LEFT JOIN absensi a ON a.kegiatan_id = lk.kegiatan_id AND a.tanggal = lk.tanggal
    LEFT JOIN santri s ON s.id = a.santri_id
    WHERE lk.tanggal BETWEEN ? AND ? $activityFilter
    UNION ALL
    SELECT k.nama_kegiatan, lk.tanggal, lk.status, lk.catatan, u.name,
           aa.nama_asatidz, 'Asatidz', aa.status, aa.keterangan, aa.tindak_lanjut
    FROM laporan_kegiatan lk
    JOIN kegiatan k ON k.id = lk.kegiatan_id
    LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
    LEFT JOIN absensi_asatidz aa ON aa.kegiatan_id = lk.kegiatan_id AND aa.tanggal = lk.tanggal
    WHERE lk.tanggal BETWEEN ? AND ? $activityFilter
    ORDER BY tanggal DESC, nama_kegiatan ASC, jenis_peserta ASC, nama_peserta ASC
";
$params = array_merge($params, $params);
$types .= $types;
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"laporan-kegiatan.csv\"");
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Kegiatan', 'Tanggal', 'Status Laporan', 'Catatan Laporan', 'Pelapor', 'Jenis Peserta', 'Nama Peserta', 'Status Absensi', 'Keterangan', 'Tindak Lanjut']);
while ($row = $result->fetch_assoc()) {
    $values = array_values($row);
    foreach ($values as &$value) {
        if (is_string($value) && preg_match('/^[\s]*[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
    }
    unset($value);
    fputcsv($output, $values);
}
fclose($output);
$stmt->close();
