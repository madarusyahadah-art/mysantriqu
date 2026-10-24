<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Tampilkan error untuk debug (hapus di production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error ke browser

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'kepala', 'santri', 'supervisor']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

// Ambil parameter filter
$tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
$kegiatan_id = $_GET['kegiatan_id'] ?? '';
$angkatan = $_GET['angkatan'] ?? '';
$santriId = $_GET['santri_id'] ?? '';

if ($currentUser['role'] === 'mahasantri') {
    if (empty($currentUser['santri_id'])) {
        json_response(["success" => false, "error" => "Akun Santri belum dihubungkan oleh admin"], 403);
    }
    $santriId = $currentUser['santri_id'];
}

// Validasi tanggal
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
    json_response(["success" => false, "error" => "Rentang tanggal tidak valid"], 400);
}

$sql = "
    SELECT
        s.id AS santri_id,
        s.nama,
        s.angkatan,
        COUNT(a.id) AS total_absensi,
        SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status = 'izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status = 'alpha' THEN 1 ELSE 0 END) AS alpha,
        SUM(CASE WHEN a.status = 'izin_menginap' THEN 1 ELSE 0 END) AS izin_menginap,
        SUM(CASE WHEN a.id IS NOT NULL AND sesi.id IS NULL THEN 1 ELSE 0 END) AS catatan_lama
    FROM santri s
    LEFT JOIN absensi a ON s.id = a.santri_id AND a.tanggal BETWEEN ? AND ?
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
$laporan = [];
$data_lama_tidak_lengkap = false;
while ($row = $result->fetch_assoc()) {
    $row['data_lama'] = (int) $row['catatan_lama'] > 0;
    $data_lama_tidak_lengkap = $data_lama_tidak_lengkap || $row['data_lama'];
    unset($row['catatan_lama']);
    foreach (['total_absensi', 'hadir', 'izin', 'sakit', 'alpha', 'izin_menginap'] as $countField) {
        $row[$countField] = (int) ($row[$countField] ?? 0);
    }
    $laporan[] = $row;
}
$stmt->close();

// Ambil daftar kegiatan untuk filter
try {
    $kegiatan_stmt = $conn->prepare("SELECT id, nama_kegiatan FROM kegiatan WHERE is_active = 1 ORDER BY nama_kegiatan ASC");
    $kegiatan_stmt->execute();
    $kegiatan_list = $kegiatan_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $kegiatan_stmt->close();
} catch (Exception $e) {
    $kegiatan_list = [];
}

echo json_encode([
    "success" => true,
    "laporan" => $laporan,
    "data_lama_tidak_lengkap" => $data_lama_tidak_lengkap,
    "filter" => [
        "tanggal_mulai" => $tanggal_mulai,
        "tanggal_selesai" => $tanggal_selesai,
        "kegiatan_id" => $kegiatan_id,
        "angkatan" => $angkatan
    ],
    "kegiatan_list" => $kegiatan_list
]);

$conn->close();
?>