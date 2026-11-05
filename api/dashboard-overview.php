<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}

$role = strtolower((string) ($user['role'] ?? ''));
if (!in_array($role, ['admin', 'kajur', 'supervisor', 'musyrif', "mas'ul", 'mahasantri', 'santri'], true)) {
    json_response(['success' => false, 'error' => 'Anda tidak memiliki akses ke dashboard.'], 403);
}

function dashboard_rows(mysqli $connection, string $sql, ?array $studentScope): array
{
    $statement = $connection->prepare($sql);
    if ($studentScope) {
        $types = str_repeat('i', count($studentScope));
        $statement->bind_param($types, ...$studentScope);
    }
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return $rows;
}

function dashboard_single(mysqli $connection, string $sql, ?array $studentScope): array
{
    $rows = dashboard_rows($connection, $sql, $studentScope);
    return $rows[0] ?? [];
}

function normalize_dashboard_rows(array $rows): array
{
    foreach ($rows as &$row) {
        foreach (['total', 'points'] as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = (int) $row[$field];
            }
        }
        if (isset($row['date'])) {
            $row['date'] = (string) $row['date'];
        }
    }
    unset($row);
    return $rows;
}

try {
    date_default_timezone_set('Asia/Jakarta');
    $connection = db_connection();
    $studentScope = student_scope_ids($connection, $user);
    if (in_array($role, ['mahasantri', 'santri'], true) && !$studentScope) {
        json_response(['success' => false, 'error' => 'Akun santri belum dihubungkan oleh admin.'], 403);
    }
    $scopePlaceholders = $studentScope ? implode(',', array_fill(0, count($studentScope), '?')) : '';
    $whereSql = $studentScope === null ? '' : ($studentScope
        ? ' WHERE santri_id IN (' . $scopePlaceholders . ')'
        : ' WHERE 1 = 0');
    $whereP = $studentScope === null ? '' : ($studentScope
        ? ' WHERE p.santri_id IN (' . $scopePlaceholders . ')'
        : ' WHERE 1 = 0');

    $stats = dashboard_single(
        $connection,
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(tanggal = CURDATE()), 0) AS today,
                COALESCE(SUM(YEARWEEK(tanggal, 1) = YEARWEEK(CURDATE(), 1)), 0) AS week,
                COALESCE(SUM(YEAR(tanggal) = YEAR(CURDATE()) AND MONTH(tanggal) = MONTH(CURDATE())), 0) AS month
         FROM pelanggaran" . $whereSql,
        $studentScope
    );

    $dailyRows = dashboard_rows(
        $connection,
        "SELECT tanggal AS date, COUNT(*) AS total
         FROM pelanggaran" . $whereSql . ($whereSql === '' ? ' WHERE' : ' AND') . " tanggal >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
         GROUP BY tanggal
         ORDER BY tanggal ASC",
        $studentScope
    );
    $dailyCounts = [];
    foreach ($dailyRows as $row) {
        $dailyCounts[(string) $row['date']] = (int) $row['total'];
    }
    $trend = [];
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    for ($offset = 29; $offset >= 0; $offset--) {
        $date = $today->modify('-' . $offset . ' days')->format('Y-m-d');
        $trend[] = ['date' => $date, 'total' => $dailyCounts[$date] ?? 0];
    }

    $categories = dashboard_rows(
        $connection,
        "SELECT jenis_pelanggaran AS name, COUNT(*) AS total
         FROM pelanggaran" . $whereSql . "
         GROUP BY jenis_pelanggaran
         ORDER BY total DESC, name ASC
         LIMIT 6",
        $studentScope
    );

    $topStudents = dashboard_rows(
        $connection,
        "SELECT s.nama AS name, COUNT(*) AS total
         FROM pelanggaran p
         INNER JOIN santri s ON s.id = p.santri_id" . $whereP . "
         GROUP BY s.id, s.nama
         ORDER BY total DESC, s.nama ASC
         LIMIT 5",
        $studentScope
    );

    $cohorts = dashboard_rows(
        $connection,
        "SELECT COALESCE(NULLIF(s.angkatan, ''), 'Belum diisi') AS name, COUNT(*) AS total
         FROM pelanggaran p
         INNER JOIN santri s ON s.id = p.santri_id" . $whereP . "
         GROUP BY s.angkatan
         ORDER BY total DESC, name ASC
         LIMIT 5",
        $studentScope
    );

    $recent = dashboard_rows(
        $connection,
        "SELECT p.tanggal AS date, s.nama AS student, p.jenis_pelanggaran AS type, p.poin AS points
         FROM pelanggaran p
         INNER JOIN santri s ON s.id = p.santri_id" . $whereP . "
         ORDER BY p.tanggal DESC, p.id DESC
         LIMIT 7",
        $studentScope
    );

    foreach (['total', 'today', 'week', 'month'] as $field) {
        $stats[$field] = (int) ($stats[$field] ?? 0);
    }
    $categories = normalize_dashboard_rows($categories);
    $topStudents = normalize_dashboard_rows($topStudents);
    $cohorts = normalize_dashboard_rows($cohorts);
    $recent = normalize_dashboard_rows($recent);

    json_response([
        'success' => true,
        'stats' => $stats,
        'trend' => $trend,
        'categories' => $categories,
        'top_students' => $topStudents,
        'cohorts' => $cohorts,
        'recent' => $recent,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Dashboard overview failed: ' . $error->getMessage());
    json_response([
        'success' => false,
        'error' => 'Data dashboard belum dapat dimuat. Periksa konfigurasi database.',
    ], 503);
}
