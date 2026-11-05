<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'kajur', 'musyrif', 'supervisor', "mas'ul"], true)) {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses ke laporan kegiatan.'], 403);
}

$query = trim((string) ($_GET['q'] ?? ''));
$cohort = trim((string) ($_GET['angkatan'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$activityId = $_GET['kegiatan_id'] ?? '';
$from = trim((string) ($_GET['dari'] ?? ''));
$to = trim((string) ($_GET['sampai'] ?? ''));
$limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
$offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
$allowedStatuses = ['', 'hadir', 'izin', 'sakit', 'alpa'];

if (strlen($query) > 120 || strlen($cohort) > 50
    || !in_array($status, $allowedStatuses, true)
    || $limit === false || $limit < 1 || $limit > 100
    || $offset === false || $offset < 0 || $offset > 100000) {
    json_response(['success' => false, 'error' => 'Filter laporan kegiatan tidak valid.'], 400);
}
if ($activityId !== '') {
    $activityId = filter_var($activityId, FILTER_VALIDATE_INT);
    if ($activityId === false || $activityId < 1) {
        json_response(['success' => false, 'error' => 'Kegiatan yang dipilih tidak valid.'], 400);
    }
}
foreach (['dari' => $from, 'sampai' => $to] as $dateLabel => $date) {
    if ($date === '') {
        continue;
    }
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        || $dateValue === false || $dateValue->format('Y-m-d') !== $date) {
        json_response(['success' => false, 'error' => 'Rentang tanggal tidak valid.'], 400);
    }
}
if ($from !== '' && $to !== '' && $from > $to) {
    json_response(['success' => false, 'error' => 'Tanggal awal tidak boleh melewati tanggal akhir.'], 400);
}

try {
    $connection = db_connection();
    $conditions = [];
    $types = '';
    $values = [];
    $scopeIds = student_scope_ids($connection, $user);
    if ($scopeIds !== null) {
        if (!$scopeIds) {
            $conditions[] = '1 = 0';
        } else {
            $conditions[] = 's.id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')';
            $types .= str_repeat('i', count($scopeIds));
            array_push($values, ...$scopeIds);
        }
    }
    if ($query !== '') {
        $conditions[] = "(k.nama_kegiatan LIKE ? OR k.kategori LIKE ? OR s.nama LIKE ? OR COALESCE(s.no_ktm, '') LIKE ?)";
        $pattern = '%' . $query . '%';
        array_push($values, $pattern, $pattern, $pattern, $pattern);
        $types .= 'ssss';
    }
    if ($cohort !== '') {
        $conditions[] = 's.angkatan = ?';
        $values[] = $cohort;
        $types .= 's';
    }
    if ($status !== '') {
        $conditions[] = 'a.status = ?';
        $values[] = $status;
        $types .= 's';
    }
    if ($activityId !== '') {
        $conditions[] = 'a.kegiatan_id = ?';
        $values[] = $activityId;
        $types .= 'i';
    }
    if ($from !== '') {
        $conditions[] = 'a.tanggal >= ?';
        $values[] = $from;
        $types .= 's';
    }
    if ($to !== '') {
        $conditions[] = 'a.tanggal <= ?';
        $values[] = $to;
        $types .= 's';
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $fromSql = ' FROM absensi a
                 INNER JOIN kegiatan k ON k.id = a.kegiatan_id
                 INNER JOIN santri s ON s.id = a.santri_id' . $where;

    $summaryStatement = $connection->prepare(
        "SELECT COUNT(DISTINCT CONCAT(a.kegiatan_id, ':', a.tanggal)) AS sesi,
                COUNT(*) AS total,
                COALESCE(SUM(a.status = 'hadir'), 0) AS hadir,
                COALESCE(SUM(a.status = 'izin'), 0) AS izin,
                COALESCE(SUM(a.status = 'sakit'), 0) AS sakit,
                COALESCE(SUM(a.status = 'alpa'), 0) AS alpa" . $fromSql
    );
    if ($values) {
        $summaryStatement->bind_param($types, ...$values);
    }
    $summaryStatement->execute();
    $summary = $summaryStatement->get_result()->fetch_assoc() ?: [];
    $summaryStatement->close();
    foreach (['sesi', 'total', 'hadir', 'izin', 'sakit', 'alpa'] as $field) {
        $summary[$field] = (int) ($summary[$field] ?? 0);
    }

    $countStatement = $connection->prepare(
        'SELECT COUNT(*) AS total FROM (
            SELECT a.kegiatan_id, a.tanggal' . $fromSql . '
            GROUP BY a.kegiatan_id, a.tanggal
         ) AS grouped_sessions'
    );
    if ($values) {
        $countStatement->bind_param($types, ...$values);
    }
    $countStatement->execute();
    $groupCount = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);
    $countStatement->close();

    $listStatement = $connection->prepare(
        "SELECT a.kegiatan_id, a.tanggal, k.nama_kegiatan, k.kategori,
                COUNT(*) AS total,
                COALESCE(SUM(a.status = 'hadir'), 0) AS hadir,
                COALESCE(SUM(a.status = 'izin'), 0) AS izin,
                COALESCE(SUM(a.status = 'sakit'), 0) AS sakit,
                COALESCE(SUM(a.status = 'alpa'), 0) AS alpa
         " . $fromSql . '
         GROUP BY a.kegiatan_id, a.tanggal, k.nama_kegiatan, k.kategori
         ORDER BY a.tanggal DESC, k.nama_kegiatan ASC
         LIMIT ? OFFSET ?'
    );
    $listValues = $values;
    $listValues[] = $limit;
    $listValues[] = $offset;
    $listStatement->bind_param($types . 'ii', ...$listValues);
    $listStatement->execute();
    $sessions = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStatement->close();
    foreach ($sessions as &$session) {
        foreach (['kegiatan_id', 'total', 'hadir', 'izin', 'sakit', 'alpa'] as $field) {
            $session[$field] = (int) $session[$field];
        }
        $session['persentase_hadir'] = $session['total'] > 0
            ? round($session['hadir'] / $session['total'] * 100, 1)
            : 0;
    }
    unset($session);

    $activities = $connection->query(
        'SELECT id, nama_kegiatan, kategori FROM kegiatan ORDER BY nama_kegiatan ASC, id ASC'
    )->fetch_all(MYSQLI_ASSOC);
    foreach ($activities as &$activity) {
        $activity['id'] = (int) $activity['id'];
    }
    unset($activity);
    if ($scopeIds === null) {
        $cohorts = array_map(
            static fn (array $row): string => (string) $row['angkatan'],
            $connection->query(
                "SELECT DISTINCT angkatan FROM santri WHERE TRIM(angkatan) <> '' ORDER BY angkatan DESC"
            )->fetch_all(MYSQLI_ASSOC)
        );
    } elseif ($scopeIds) {
        $cohortStatement = $connection->prepare(
            'SELECT DISTINCT angkatan FROM santri
             WHERE id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')
               AND TRIM(angkatan) <> \'\'
             ORDER BY angkatan DESC'
        );
        $cohortStatement->bind_param(str_repeat('i', count($scopeIds)), ...$scopeIds);
        $cohortStatement->execute();
        $cohorts = array_map(
            static fn (array $row): string => (string) $row['angkatan'],
            $cohortStatement->get_result()->fetch_all(MYSQLI_ASSOC)
        );
        $cohortStatement->close();
    } else {
        $cohorts = [];
    }

    json_response([
        'success' => true,
        'laporan' => $sessions,
        'ringkasan' => $summary,
        'total' => $groupCount,
        'limit' => $limit,
        'offset' => $offset,
        'kegiatan' => $activities,
        'angkatan' => $cohorts,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Read activity report failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Laporan kegiatan belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}