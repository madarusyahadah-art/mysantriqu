<?php
header("Content-Type: application/json; charset=UTF-8");

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
$jenis_pelanggaran = $_GET['jenis_pelanggaran'] ?? '';
$santri_id = $_GET['santri_id'] ?? '';

if ($currentUser['role'] === 'mahasantri') {
    if (empty($currentUser['santri_id'])) {
        json_response(["success" => false, "error" => "Akun Santri belum dihubungkan oleh admin"], 403);
    }
    $santri_id = $currentUser['santri_id'];
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

// 1. Ranking Santri (Total Poin Tertinggi)
$sql_ranking = "
    SELECT 
        s.id as santri_id,
        s.nama,
        s.angkatan,
        COUNT(p.id) as total_pelanggaran,
        COALESCE(SUM(p.poin), 0) as total_poin
    FROM santri s
    LEFT JOIN pelanggaran p ON s.id = p.santri_id 
        AND p.tanggal BETWEEN ? AND ?
";

$params = [$tanggal_mulai, $tanggal_selesai];
$types = "ss";

if (!empty($jenis_pelanggaran)) {
    $sql_ranking .= " AND p.jenis_pelanggaran = ?";
    $params[] = $jenis_pelanggaran;
    $types .= "s";
}

$sql_ranking .= " WHERE 1=1";

if (!empty($santri_id)) {
    $sql_ranking .= " AND s.id = ?";
    $params[] = $santri_id;
    $types .= "i";
}

$sql_ranking .= " GROUP BY s.id, s.nama, s.angkatan 
                 ORDER BY total_poin DESC, total_pelanggaran DESC";

$stmt_ranking = $conn->prepare($sql_ranking);
$stmt_ranking->bind_param($types, ...$params);
$stmt_ranking->execute();
$result_ranking = $stmt_ranking->get_result();

$ranking = [];
while ($row = $result_ranking->fetch_assoc()) {
    $ranking[] = $row;
}

// 2. Persentase per jenis dibanding semua pelanggaran dalam populasi terpilih.
$sql_total_jenis = "SELECT COUNT(*) AS total FROM pelanggaran WHERE tanggal BETWEEN ? AND ?";
$params_total_jenis = [$tanggal_mulai, $tanggal_selesai];
$types_total_jenis = "ss";
if (!empty($santri_id)) {
    $sql_total_jenis .= " AND santri_id = ?";
    $params_total_jenis[] = $santri_id;
    $types_total_jenis .= "i";
}
$stmt_total_jenis = $conn->prepare($sql_total_jenis);
$stmt_total_jenis->bind_param($types_total_jenis, ...$params_total_jenis);
$stmt_total_jenis->execute();
$total_jenis = (int) $stmt_total_jenis->get_result()->fetch_assoc()['total'];
$stmt_total_jenis->close();

$sql_jenis = "
    SELECT 
        p.jenis_pelanggaran,
        COUNT(*) as jumlah
    FROM pelanggaran p
    WHERE p.tanggal BETWEEN ? AND ?
";

$params_jenis = [$tanggal_mulai, $tanggal_selesai];
$types_jenis = "ss";

if (!empty($santri_id)) {
    $sql_jenis .= " AND p.santri_id = ?";
    $params_jenis[] = $santri_id;
    $types_jenis .= "i";
}

$sql_jenis .= " GROUP BY p.jenis_pelanggaran ORDER BY jumlah DESC";

$stmt_jenis = $conn->prepare($sql_jenis);
$stmt_jenis->bind_param($types_jenis, ...$params_jenis);
$stmt_jenis->execute();
$result_jenis = $stmt_jenis->get_result();

$persentase_jenis = [];
while ($row = $result_jenis->fetch_assoc()) {
    $row['persentase'] = $total_jenis > 0
        ? round(((int) $row['jumlah'] * 100) / $total_jenis, 2)
        : 0;
    $persentase_jenis[] = $row;
}

// 2b. Perkembangan jumlah pelanggaran per hari, pekan, atau bulan sesuai rentang.
$rangeDays = (int) ((strtotime($tanggal_selesai) - strtotime($tanggal_mulai)) / 86400) + 1;
if ($rangeDays <= 31) {
    $trendBucket = "DATE(p.tanggal)";
    $trendGranularity = 'hari';
    $periodStep = 'P1D';
    $periodStart = new DateTimeImmutable($tanggal_mulai);
} elseif ($rangeDays <= 180) {
    $trendBucket = "DATE_SUB(p.tanggal, INTERVAL WEEKDAY(p.tanggal) DAY)";
    $trendGranularity = 'pekan';
    $periodStep = 'P1W';
    $periodStart = (new DateTimeImmutable($tanggal_mulai))->modify('monday this week');
} else {
    $trendBucket = "DATE_FORMAT(p.tanggal, '%Y-%m-01')";
    $trendGranularity = 'bulan';
    $periodStep = 'P1M';
    $periodStart = new DateTimeImmutable((new DateTimeImmutable($tanggal_mulai))->format('Y-m-01'));
}

$sql_trend = "SELECT $trendBucket AS periode, COUNT(*) AS jumlah FROM pelanggaran p
    WHERE p.tanggal BETWEEN ? AND ?";
$params_trend = [$tanggal_mulai, $tanggal_selesai];
$types_trend = "ss";
if (!empty($jenis_pelanggaran)) {
    $sql_trend .= " AND p.jenis_pelanggaran = ?";
    $params_trend[] = $jenis_pelanggaran;
    $types_trend .= "s";
}
if (!empty($santri_id)) {
    $sql_trend .= " AND p.santri_id = ?";
    $params_trend[] = $santri_id;
    $types_trend .= "i";
}
$sql_trend .= " GROUP BY periode ORDER BY periode ASC";
$stmt_trend = $conn->prepare($sql_trend);
$stmt_trend->bind_param($types_trend, ...$params_trend);
$stmt_trend->execute();
$result_trend = $stmt_trend->get_result();
$trendCounts = [];
while ($row = $result_trend->fetch_assoc()) {
    $trendCounts[$row['periode']] = (int) $row['jumlah'];
}

$perkembangan = [];
$periodEnd = new DateTimeImmutable($tanggal_selesai);
for ($period = $periodStart; $period <= $periodEnd; $period = $period->add(new DateInterval($periodStep))) {
    $periodKey = $period->format('Y-m-d');
    $perkembangan[] = [
        "periode" => $periodKey,
        "jumlah" => $trendCounts[$periodKey] ?? 0
    ];
}

// 3. Detail Pelanggaran (untuk tabel lengkap)
$sql_detail = "
    SELECT 
        p.id,
        p.santri_id,
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

$params_detail = [$tanggal_mulai, $tanggal_selesai];
$types_detail = "ss";

if (!empty($jenis_pelanggaran)) {
    $sql_detail .= " AND p.jenis_pelanggaran = ?";
    $params_detail[] = $jenis_pelanggaran;
    $types_detail .= "s";
}

if (!empty($santri_id)) {
    $sql_detail .= " AND p.santri_id = ?";
    $params_detail[] = $santri_id;
    $types_detail .= "i";
}

$sql_detail .= " ORDER BY p.tanggal DESC, p.id DESC";

$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param($types_detail, ...$params_detail);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

$detail_pelanggaran = [];
while ($row = $result_detail->fetch_assoc()) {
    $detail_pelanggaran[] = $row;
}

// 4. Daftar santri untuk filter
if ($currentUser['role'] === 'mahasantri') {
    $santri_stmt = $conn->prepare("SELECT id, nama FROM santri WHERE id = ? ORDER BY nama ASC");
    $santri_stmt->bind_param("i", $santri_id);
} else {
    $santri_stmt = $conn->prepare("SELECT id, nama FROM santri ORDER BY nama ASC");
}
$santri_stmt->execute();
$santri_list = $santri_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. Daftar jenis pelanggaran (unik)
$jenis_stmt = $conn->prepare("SELECT DISTINCT jenis_pelanggaran FROM pelanggaran ORDER BY jenis_pelanggaran ASC");
$jenis_stmt->execute();
$jenis_list = $jenis_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    "success" => true,
    "ranking" => $ranking,
    "persentase_jenis" => $persentase_jenis,
    "perkembangan" => $perkembangan,
    "trend_granularity" => $trendGranularity,
    "detail_pelanggaran" => $detail_pelanggaran,
    "santri_list" => $santri_list,
    "jenis_list" => $jenis_list,
    "filter" => [
        "tanggal_mulai" => $tanggal_mulai,
        "tanggal_selesai" => $tanggal_selesai,
        "jenis_pelanggaran" => $jenis_pelanggaran,
        "santri_id" => $santri_id
    ]
]);

$stmt_ranking->close();
$stmt_jenis->close();
$stmt_trend->close();
$stmt_detail->close();
$conn->close();
?>