<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'kepala', 'santri']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$santri_id = $_GET['santri_id'] ?? '';

if ($currentUser['role'] !== 'admin') {
    if (empty($currentUser['santri_id'])) {
        json_response(["success" => false, "error" => "Profil belum dihubungkan oleh admin"], 403);
    }
    $santri_id = $currentUser['santri_id'];
}

if (empty($santri_id)) {
    http_response_code(400);
    echo json_encode(["error" => "santri_id wajib diisi"]);
    exit;
}

// Ambil data santri
$stmt = $conn->prepare("SELECT id, nama, asal, angkatan, created_at FROM santri WHERE id = ?");
$stmt->bind_param("i", $santri_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "Santri tidak ditemukan"]);
    exit;
}

$santri = $result->fetch_assoc();

// Ambil statistik absensi
$absensi_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_absensi,
        SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha,
        SUM(CASE WHEN status = 'izin_menginap' THEN 1 ELSE 0 END) as izin_menginap,
        SUM(CASE WHEN sesi.id IS NULL THEN 1 ELSE 0 END) as legacy_count
    FROM absensi a
    LEFT JOIN absensi_sesi sesi ON sesi.kegiatan_id = a.kegiatan_id AND sesi.tanggal = a.tanggal
    WHERE a.santri_id = ?
");
$absensi_stmt->bind_param("i", $santri_id);
$absensi_stmt->execute();
$absensi_stats = $absensi_stmt->get_result()->fetch_assoc();

// Ambil statistik pelanggaran
$pelanggaran_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_pelanggaran,
        COALESCE(SUM(poin), 0) as total_poin
    FROM pelanggaran 
    WHERE santri_id = ?
");
$pelanggaran_stmt->bind_param("i", $santri_id);
$pelanggaran_stmt->execute();
$pelanggaran_stats = $pelanggaran_stmt->get_result()->fetch_assoc();

// Ambil seluruh riwayat absensi untuk kebutuhan profil dan cetak.
$riwayat_absensi_stmt = $conn->prepare("
    SELECT a.tanggal, k.nama_kegiatan, a.status, a.keterangan, a.tindak_lanjut
    FROM absensi a
    LEFT JOIN kegiatan k ON a.kegiatan_id = k.id
    WHERE a.santri_id = ? 
    ORDER BY a.tanggal DESC, a.id DESC
");
$riwayat_absensi_stmt->bind_param("i", $santri_id);
$riwayat_absensi_stmt->execute();
$riwayat_absensi = $riwayat_absensi_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil seluruh riwayat pelanggaran dan catatan BK untuk kebutuhan profil dan cetak.
$riwayat_pelanggaran_stmt = $conn->prepare("
    SELECT p.tanggal, p.jenis_pelanggaran, p.poin, p.keterangan, p.status_tindak_lanjut,
           p.catatan_bk, u.name as input_by_name
    FROM pelanggaran p
    LEFT JOIN users u ON p.input_by = u.id
    WHERE p.santri_id = ? 
    ORDER BY p.tanggal DESC, p.id DESC
");
$riwayat_pelanggaran_stmt->bind_param("i", $santri_id);
$riwayat_pelanggaran_stmt->execute();
$riwayat_pelanggaran = $riwayat_pelanggaran_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "success" => true,
    "santri" => $santri,
    "absensi_stats" => $absensi_stats,
    "pelanggaran_stats" => $pelanggaran_stats,
    "riwayat_absensi" => $riwayat_absensi,
    "riwayat_pelanggaran" => $riwayat_pelanggaran
]);

$stmt->close();
$conn->close();
?>