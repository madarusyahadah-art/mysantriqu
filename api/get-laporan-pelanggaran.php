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
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses ke data pelanggaran.'], 403);
}

$query = trim((string) ($_GET['q'] ?? ''));
$cohort = trim((string) ($_GET['angkatan'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['dari'] ?? ''));
$to = trim((string) ($_GET['sampai'] ?? ''));
$limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
$offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
$allowedStatuses = ['', 'belum', 'diproses', 'selesai'];

if (strlen($query) > 120 || strlen($cohort) > 50
    || !in_array($status, $allowedStatuses, true)
    || $limit === false || $limit < 1 || $limit > 100
    || $offset === false || $offset < 0 || $offset > 100000) {
    json_response(['success' => false, 'error' => 'Filter data pelanggaran tidak valid.'], 400);
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
        $conditions[] = "(s.nama LIKE ? OR COALESCE(s.no_ktm, '') LIKE ? OR p.jenis_pelanggaran LIKE ? OR COALESCE(p.keterangan, '') LIKE ?)";
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
        $conditions[] = 'p.status_tindak_lanjut = ?';
        $values[] = $status;
        $types .= 's';
    }
    if ($from !== '') {
        $conditions[] = 'p.tanggal >= ?';
        $values[] = $from;
        $types .= 's';
    }
    if ($to !== '') {
        $conditions[] = 'p.tanggal <= ?';
        $values[] = $to;
        $types .= 's';
    }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $countStatement = $connection->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(p.status_tindak_lanjut = 'belum'), 0) AS belum,
                COALESCE(SUM(p.status_tindak_lanjut = 'diproses'), 0) AS diproses,
                COALESCE(SUM(p.status_tindak_lanjut = 'selesai'), 0) AS selesai,
                COALESCE(SUM(p.poin), 0) AS poin
         FROM pelanggaran p INNER JOIN santri s ON s.id = p.santri_id" . $where
    );
    if ($values) {
        $countStatement->bind_param($types, ...$values);
    }
    $countStatement->execute();
    $summary = $countStatement->get_result()->fetch_assoc();
    $countStatement->close();
    foreach (['total', 'belum', 'diproses', 'selesai', 'poin'] as $field) {
        $summary[$field] = (int) ($summary[$field] ?? 0);
    }

    $listStatement = $connection->prepare(
        'SELECT p.id, p.santri_id, p.absensi_id, p.jenis_pelanggaran_id, p.tanggal, p.jenis_pelanggaran,
                p.poin, p.keterangan, p.status_tindak_lanjut, p.catatan_bk,
                s.nama AS nama_santri, s.no_ktm, s.angkatan
         FROM pelanggaran p
         INNER JOIN santri s ON s.id = p.santri_id' . $where . '
         ORDER BY p.tanggal DESC, p.id DESC
         LIMIT ? OFFSET ?'
    );
    $listTypes = $types . 'ii';
    $listValues = $values;
    $listValues[] = $limit;
    $listValues[] = $offset;
    $listStatement->bind_param($listTypes, ...$listValues);
    $listStatement->execute();
    $records = $listStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $listStatement->close();
    foreach ($records as &$record) {
        foreach (['id', 'santri_id', 'poin'] as $field) {
            $record[$field] = (int) $record[$field];
        }
        $record['absensi_id'] = $record['absensi_id'] === null ? null : (int) $record['absensi_id'];
        $record['jenis_pelanggaran_id'] = $record['jenis_pelanggaran_id'] === null
            ? null : (int) $record['jenis_pelanggaran_id'];
    }
    unset($record);

    if ($scopeIds === null) {
        $santri = $connection->query(
            'SELECT id, nama, no_ktm, angkatan FROM santri ORDER BY nama ASC, id ASC'
        )->fetch_all(MYSQLI_ASSOC);
    } elseif ($scopeIds) {
        $scopePlaceholders = implode(',', array_fill(0, count($scopeIds), '?'));
        $santriStatement = $connection->prepare(
            'SELECT id, nama, no_ktm, angkatan FROM santri
             WHERE id IN (' . $scopePlaceholders . ') ORDER BY nama ASC, id ASC'
        );
        $scopeTypes = str_repeat('i', count($scopeIds));
        $santriStatement->bind_param($scopeTypes, ...$scopeIds);
        $santriStatement->execute();
        $santri = $santriStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $santriStatement->close();
    } else {
        $santri = [];
    }
    $cohorts = array_values(array_unique(array_filter(array_map(
        static fn (array $row): string => trim((string) $row['angkatan']),
        $santri
    ))));
    rsort($cohorts, SORT_NATURAL);
    json_response([
        'success' => true,
        'pelanggaran' => $records,
        'ringkasan' => $summary,
        'limit' => $limit,
        'offset' => $offset,
        'angkatan' => $cohorts,
        'santri' => $santri,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Read violation records failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Data pelanggaran belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}