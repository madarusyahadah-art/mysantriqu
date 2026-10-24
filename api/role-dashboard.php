<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin', 'kajur', 'mahasantri']);
$monthFrom = (string) ($_GET['bulan_mulai'] ?? date('Y-m'));
$monthTo = (string) ($_GET['bulan_selesai'] ?? $monthFrom);
$santriId = isset($_GET['santri_id']) ? filter_var($_GET['santri_id'], FILTER_VALIDATE_INT) : null;
$search = trim((string) ($_GET['cari'] ?? ''));
$angkatan = trim((string) ($_GET['angkatan'] ?? ''));
$order = (string) ($_GET['urutan'] ?? 'nama');

$isValidMonth = static function ($month) {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m', $month);
    return $date && $date->format('Y-m') === $month;
};

if (!$isValidMonth($monthFrom) || !$isValidMonth($monthTo) || $monthFrom > $monthTo) {
    json_response(["success" => false, "error" => "Rentang bulan tidak valid"], 400);
}

if ($santriId === false || ($santriId !== null && $santriId < 1)) {
    json_response(["success" => false, "error" => "ID Mahasantri tidak valid"], 400);
}
if (strlen($search) > 100 || strlen($angkatan) > 50 || !in_array($order, ['nama', 'angkatan'], true)) {
    json_response(["success" => false, "error" => "Filter daftar Mahasantri tidak valid"], 400);
}

$role = $user['role'];
if ($role === 'mahasantri') {
    if (!empty($user['santri_id']) && $santriId !== null && (int) $santriId !== (int) $user['santri_id']) {
        json_response(["success" => false, "error" => "Anda hanya dapat melihat laporan milik sendiri"], 403);
    }
    $santriId = !empty($user['santri_id']) ? (int) $user['santri_id'] : null;
}

$students = [];
if ($role === 'kajur' || $role === 'admin') {
    $orderBy = $order === 'angkatan' ? "angkatan ASC, nama ASC" : "nama ASC, angkatan ASC";
    $sql = "SELECT id, nama, no_ktm, asal, angkatan, semester_aktif FROM santri WHERE 1 = 1";
    $params = [];
    $types = '';
    if ($search !== '') {
        $sql .= " AND nama LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    if ($angkatan !== '') {
        $sql .= " AND angkatan = ?";
        $params[] = $angkatan;
        $types .= 's';
    }
    $sql .= " ORDER BY $orderBy";
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$activitiesStmt = $conn->prepare("SELECT id, nama_kegiatan, kategori, frekuensi FROM kegiatan WHERE is_active = 1 ORDER BY slide_order ASC, id ASC");
$activitiesStmt->execute();
$activities = $activitiesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activitiesStmt->close();

$studentCountResult = $conn->query("SELECT COUNT(*) AS total FROM santri");
if (!$studentCountResult) {
    error_log('role-dashboard.php student count query error: ' . $conn->error);
    json_response(["success" => false, "error" => "Total Mahasantri gagal dimuat"], 500);
}
$totalStudents = (int) $studentCountResult->fetch_assoc()['total'];
$studentCountResult->free();

$payload = [
    "success" => true,
    "role" => $role,
    "total_santri" => $totalStudents,
    "user" => [
        "name" => $user['name'],
        "email" => $user['email'],
        "photo" => $user['photo'] ?? '',
        "santri_id" => $user['santri_id'] ?? null,
    ],
    "daftar_mahasantri" => $students,
    "kegiatan" => $activities,
    "santri" => null,
    "absensi" => [],
    "pelanggaran" => [],
    "ringkasan" => ["hadir" => 0, "izin" => 0, "sakit" => 0, "alpha" => 0, "izin_menginap" => 0, "lainnya" => 0],
    "rentang" => ["mulai" => $monthFrom . "-01", "selesai" => (new DateTimeImmutable($monthTo . "-01"))->modify('last day of this month')->format('Y-m-d')],
];

if ($santriId !== null) {
    $profileStmt = $conn->prepare("SELECT id, nama, no_ktm, asal, angkatan, semester_aktif FROM santri WHERE id = ?");
    $profileStmt->bind_param("i", $santriId);
    $profileStmt->execute();
    $payload['santri'] = $profileStmt->get_result()->fetch_assoc();
    $profileStmt->close();
    if (!$payload['santri']) {
        json_response(["success" => false, "error" => "Data Mahasantri tidak ditemukan"], 404);
    }

    $startDate = $payload['rentang']['mulai'];
    $endDate = $payload['rentang']['selesai'];
    $attendanceStmt = $conn->prepare("
        SELECT a.id, a.tanggal, a.kegiatan_id, k.nama_kegiatan, a.status, a.keterangan, a.tindak_lanjut,
               u.name AS pencatat
        FROM absensi a
        LEFT JOIN kegiatan k ON k.id = a.kegiatan_id
        LEFT JOIN users u ON u.id = a.input_by
        WHERE a.santri_id = ? AND a.tanggal BETWEEN ? AND ?
        ORDER BY a.tanggal DESC, k.nama_kegiatan ASC, a.id DESC
    ");
    $attendanceStmt->bind_param("iss", $santriId, $startDate, $endDate);
    $attendanceStmt->execute();
    $payload['absensi'] = $attendanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $attendanceStmt->close();

    foreach ($payload['absensi'] as $record) {
        $status = strtolower((string) $record['status']);
        $key = array_key_exists($status, $payload['ringkasan']) ? $status : 'lainnya';
        $payload['ringkasan'][$key]++;
    }

    $violationStmt = $conn->prepare("
        SELECT p.id, p.tanggal, p.jenis_pelanggaran, p.poin, p.keterangan,
               p.status_tindak_lanjut, p.catatan_bk, u.name AS pencatat
        FROM pelanggaran p
        LEFT JOIN users u ON u.id = p.input_by
        WHERE p.santri_id = ? AND p.tanggal BETWEEN ? AND ?
        ORDER BY p.tanggal DESC, p.id DESC
    ");
    $violationStmt->bind_param("iss", $santriId, $startDate, $endDate);
    $violationStmt->execute();
    $payload['pelanggaran'] = $violationStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $violationStmt->close();
}

json_response($payload);
?>
