<?php

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'kepala', 'santri', 'supervisor']);

if (!isset($conn)) {
    die("Database connection failed");
}

$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
$jenis_pelanggaran = $_GET['jenis_pelanggaran'] ?? '';
$santri_id = $_GET['santri_id'] ?? '';
$only_with_notes = ($_GET['only_with_notes'] ?? '') === '1';
if ($currentUser['role'] === 'mahasantri') {
    if (empty($currentUser['santri_id'])) {
        http_response_code(403);
        exit("Akun Santri belum dihubungkan oleh admin.");
    }
    $santri_id = $currentUser['santri_id'];
}

if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
    $tanggal_selesai = date('Y-m-d');
    $tanggal_mulai = date('Y-m-d', strtotime('-30 days'));
}

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_selesai) ||
    $tanggal_mulai > $tanggal_selesai ||
    !checkdate((int) substr($tanggal_mulai, 5, 2), (int) substr($tanggal_mulai, 8, 2), (int) substr($tanggal_mulai, 0, 4)) ||
    !checkdate((int) substr($tanggal_selesai, 5, 2), (int) substr($tanggal_selesai, 8, 2), (int) substr($tanggal_selesai, 0, 4))
) {
    http_response_code(400);
    exit("Rentang tanggal tidak valid.");
}

$sql = "
    SELECT 
        p.tanggal,
        s.nama as nama_santri,
        s.angkatan,
        p.jenis_pelanggaran,
        p.poin,
        p.keterangan,
        p.status_tindak_lanjut,
        p.catatan_bk,
        u.name as input_by_name
    FROM pelanggaran p
    LEFT JOIN santri s ON p.santri_id = s.id
    LEFT JOIN users u ON p.input_by = u.id
    WHERE p.tanggal BETWEEN ? AND ?
";

$params = [$tanggal_mulai, $tanggal_selesai];
$types = "ss";

if (!empty($jenis_pelanggaran)) {
    $sql .= " AND p.jenis_pelanggaran = ?";
    $params[] = $jenis_pelanggaran;
    $types .= "s";
}

if (!empty($santri_id)) {
    $sql .= " AND p.santri_id = ?";
    $params[] = $santri_id;
    $types .= "i";
}

if ($only_with_notes) {
    $sql .= " AND (TRIM(COALESCE(p.keterangan, '')) <> '' OR TRIM(COALESCE(p.catatan_bk, '')) <> '')";
}

$sql .= " ORDER BY p.tanggal DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = "Laporan_Pelanggaran_" . ($only_with_notes ? "Dengan_Catatan_" : "") . $tanggal_mulai . "_sampai_" . $tanggal_selesai . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['No', 'Tanggal', 'Nama Santri', 'Angkatan', 'Jenis Pelanggaran', 'Poin', 'Keterangan', 'Status Tindak Lanjut', 'Catatan BK', 'Dilaporkan Oleh'], ';');

function safe_csv_value($value)
{
    $value = (string) ($value ?? '');
    return preg_match('/^[\t\r ]*[=+\-@]/', $value) ? "'" . $value : $value;
}

$no = 1;
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $no++,
        $row['tanggal'],
        safe_csv_value($row['nama_santri']),
        safe_csv_value($row['angkatan']),
        safe_csv_value($row['jenis_pelanggaran']),
        $row['poin'],
        safe_csv_value($row['keterangan']),
        $row['status_tindak_lanjut'],
        safe_csv_value($row['catatan_bk']),
        safe_csv_value($row['input_by_name'])
    ], ';');
}

fclose($output);
$stmt->close();
$conn->close();
exit;
?>