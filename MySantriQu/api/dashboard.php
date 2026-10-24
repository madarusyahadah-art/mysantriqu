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

$user_id = (int) $currentUser['id'];

if (empty($user_id)) {
    echo json_encode(["error" => "User ID tidak ditemukan"]);
    exit;
}

// Ambil data user
$user_stmt = $conn->prepare("SELECT id, name, email, role, photo FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    echo json_encode(["error" => "User tidak ditemukan"]);
    exit;
}

$user = $user_result->fetch_assoc();
$user['role'] = $currentUser['role'];
$user['santri_id'] = $currentUser['santri_id'];

// Ambil total santri
$total_santri_stmt = $conn->prepare(
    $currentUser['role'] === 'mahasantri'
        ? "SELECT COUNT(*) as total FROM santri WHERE id = ?"
        : "SELECT COUNT(*) as total FROM santri"
);
if ($currentUser['role'] === 'mahasantri') {
    if (empty($currentUser['santri_id'])) {
        json_response(["success" => false, "error" => "Akun Santri belum dihubungkan oleh admin"], 403);
    }
    $total_santri_stmt->bind_param("i", $currentUser['santri_id']);
}
$total_santri_stmt->execute();
$total_santri = $total_santri_stmt->get_result()->fetch_assoc()['total'];

// Kehadiran tersimpan lengkap mulai sesi absensi baru; statistik ketidakhadiran tetap hanya menghitung pengecualian.
$absensiSql = "
    SELECT 
        SUM(CASE WHEN status <> 'hadir' THEN 1 ELSE 0 END) as total_tidak_hadir,
        SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha,
        SUM(CASE WHEN status = 'izin_menginap' THEN 1 ELSE 0 END) as izin_menginap
    FROM absensi
    WHERE " . ($currentUser['role'] === 'mahasantri' ? "santri_id = ?" : "input_by = ?");
$absensi_stmt = $conn->prepare($absensiSql);
$statsId = $currentUser['role'] === 'mahasantri' ? (int) $currentUser['santri_id'] : $user_id;
$absensi_stmt->bind_param("i", $statsId);
$absensi_stmt->execute();
$absensi_stats = $absensi_stmt->get_result()->fetch_assoc();

// Hitung hadir = total absensi - tidak hadir
// Untuk dashboard, kita tampilkan total kegiatan yang diabsen
$total_kegiatan_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT CONCAT(kegiatan_id, '-', tanggal)) as total_kegiatan
    FROM absensi
    WHERE " . ($currentUser['role'] === 'mahasantri' ? "santri_id = ?" : "input_by = ?") . "
");
$total_kegiatan_stmt->bind_param("i", $statsId);
$total_kegiatan_stmt->execute();
$total_kegiatan = $total_kegiatan_stmt->get_result()->fetch_assoc()['total_kegiatan'];

// Statistik pelanggaran
$pelanggaran_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_pelanggaran,
        COALESCE(SUM(poin), 0) as total_poin
    FROM pelanggaran
    WHERE " . ($currentUser['role'] === 'mahasantri' ? "santri_id = ?" : "input_by = ?") . "
");
$pelanggaran_stmt->bind_param("i", $statsId);
$pelanggaran_stmt->execute();
$pelanggaran_stats = $pelanggaran_stmt->get_result()->fetch_assoc();

// Riwayat absensi terakhir (5 data)
$riwayat_stmt = $conn->prepare("
    SELECT a.tanggal, k.nama_kegiatan, a.status, a.keterangan 
    FROM absensi a
    LEFT JOIN kegiatan k ON a.kegiatan_id = k.id
    WHERE " . ($currentUser['role'] === 'mahasantri' ? "a.santri_id = ?" : "a.input_by = ?") . "
    ORDER BY a.tanggal DESC, a.id DESC
    LIMIT 5
");
$riwayat_stmt->bind_param("i", $statsId);
$riwayat_stmt->execute();
$riwayat = $riwayat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "success" => true,
    "user" => $user,
    "total_santri" => $total_santri,
    "total_kegiatan_diabsen" => $total_kegiatan,
    "absensi_stats" => [
        "total_tidak_hadir" => $absensi_stats['total_tidak_hadir'] ?? 0,
        "izin" => $absensi_stats['izin'] ?? 0,
        "sakit" => $absensi_stats['sakit'] ?? 0,
        "alpha" => $absensi_stats['alpha'] ?? 0,
        "izin_menginap" => $absensi_stats['izin_menginap'] ?? 0
    ],
    "pelanggaran_stats" => $pelanggaran_stats,
    "riwayat_absensi" => $riwayat
]);

$conn->close();
?>