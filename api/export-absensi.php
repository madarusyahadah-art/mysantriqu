<?php

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'kepala', 'santri', 'supervisor']);

if (!isset($conn)) {
    die("Database connection failed");
}

$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
$kegiatan_id = $_GET['kegiatan_id'] ?? '';
$angkatan = $_GET['angkatan'] ?? '';
$santriId = $_GET['santri_id'] ?? '';
$mode = $_GET['mode'] ?? 'summary';
$validModes = ['summary', 'issues', 'detail'];
if (!in_array($mode, $validModes, true)) {
    http_response_code(400);
    exit("Mode ekspor tidak valid.");
}

if ($currentUser['role'] === 'mahasantri') {
    if (empty($currentUser['santri_id'])) {
        http_response_code(403);
        exit("Akun Santri belum dihubungkan oleh admin.");
    }
    $santriId = $currentUser['santri_id'];
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

$filename = "Laporan_Absensi_" . $tanggal_mulai . "_sampai_" . $tanggal_selesai . "_" . $mode . ".csv";
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

function safe_csv_value($value)
{
    $value = (string) ($value ?? '');
    return preg_match('/^[\t\r ]*[=+\-@]/', $value) ? "'" . $value : $value;
}

if ($mode === 'summary') {
    $sql = "
        SELECT
            s.id,
            s.nama,
            s.angkatan,
            COUNT(a.id) AS total_tercatat,
            SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS alpha,
            SUM(CASE WHEN a.status = 'izin_menginap' THEN 1 ELSE 0 END) AS izin_menginap,
            SUM(CASE WHEN a.id IS NOT NULL AND sesi.id IS NULL THEN 1 ELSE 0 END) AS data_lama
        FROM santri s
        LEFT JOIN absensi a ON a.santri_id = s.id
            AND a.tanggal BETWEEN ? AND ?
    ";
    $params = [$tanggal_mulai, $tanggal_selesai];
    $types = "ss";
    if (!empty($kegiatan_id)) {
        $sql .= " AND a.kegiatan_id = ?";
        $params[] = $kegiatan_id;
        $types .= "i";
    }
    $sql .= " LEFT JOIN absensi_sesi sesi ON sesi.kegiatan_id = a.kegiatan_id AND sesi.tanggal = a.tanggal WHERE 1 = 1";
    if (!empty($angkatan)) {
        $sql .= " AND s.angkatan = ?";
        $params[] = $angkatan;
        $types .= "s";
    }
    if (!empty($santriId)) {
        $sql .= " AND s.id = ?";
        $params[] = $santriId;
        $types .= "i";
    }
    $sql .= " GROUP BY s.id, s.nama, s.angkatan ORDER BY s.angkatan ASC, s.nama ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    fputcsv($output, ['No', 'Nama Santri', 'Angkatan', 'Total Catatan', 'Hadir', 'Izin', 'Sakit', 'Alpha', 'Izin Menginap', 'Keterangan Kelengkapan'], ';');

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            safe_csv_value($row['nama']),
            safe_csv_value($row['angkatan']),
            $row['total_tercatat'],
            $row['hadir'] ?? 0,
            $row['izin'] ?? 0,
            $row['sakit'] ?? 0,
            $row['alpha'] ?? 0,
            $row['izin_menginap'] ?? 0,
            (int) $row['data_lama'] > 0 ? 'Ada data lama yang belum lengkap' : 'Tidak ada catatan lama pada filter'
        ], ';');
    }
} else {
    $sql = "
        SELECT a.tanggal, s.nama AS nama_santri, s.angkatan, k.nama_kegiatan,
               a.status, a.keterangan, a.tindak_lanjut,
               CASE WHEN sesi.id IS NULL THEN 'Data lama (kelengkapan tidak diketahui)' ELSE 'Sesi lengkap' END AS kelengkapan
        FROM absensi a
        INNER JOIN santri s ON s.id = a.santri_id
        LEFT JOIN kegiatan k ON k.id = a.kegiatan_id
        LEFT JOIN absensi_sesi sesi ON sesi.kegiatan_id = a.kegiatan_id AND sesi.tanggal = a.tanggal
        WHERE a.tanggal BETWEEN ? AND ?
    ";
    $params = [$tanggal_mulai, $tanggal_selesai];
    $types = "ss";
    if (!empty($kegiatan_id)) {
        $sql .= " AND a.kegiatan_id = ?";
        $params[] = $kegiatan_id;
        $types .= "i";
    }
    if (!empty($angkatan)) {
        $sql .= " AND s.angkatan = ?";
        $params[] = $angkatan;
        $types .= "s";
    }
    if (!empty($santriId)) {
        $sql .= " AND a.santri_id = ?";
        $params[] = $santriId;
        $types .= "i";
    }
    if ($mode === 'issues') {
        $sql .= " AND (a.status <> 'hadir' OR TRIM(COALESCE(a.keterangan, '')) <> '' OR TRIM(COALESCE(a.tindak_lanjut, '')) <> '')";
    }
    $sql .= " ORDER BY a.tanggal DESC, s.angkatan ASC, s.nama ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    fputcsv($output, ['No', 'Tanggal', 'Nama Santri', 'Angkatan', 'Kegiatan', 'Status', 'Keterangan', 'Tindak Lanjut', 'Kelengkapan Data'], ';');

    $no = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            $row['tanggal'],
            safe_csv_value($row['nama_santri']),
            safe_csv_value($row['angkatan']),
            safe_csv_value($row['nama_kegiatan']),
            $row['status'],
            safe_csv_value($row['keterangan']),
            safe_csv_value($row['tindak_lanjut']),
            $row['kelengkapan']
        ], ';');
    }
}

fclose($output);
$stmt->close();
$conn->close();
exit;
?>