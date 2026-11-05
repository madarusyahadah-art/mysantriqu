<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengimpor rekap aktivitas.'], 403);
}
$userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId === null || $userId < 1) {
    json_response(['success' => false, 'error' => 'Akun tidak valid. Silakan masuk kembali.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !is_string($payload['action'] ?? null)) {
    json_response(['success' => false, 'error' => 'Data impor tidak valid.'], 400);
}

function activity_month_number(string $sheet): ?int
{
    $months = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'september' => 9, 'sep' => 9,
        'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'des' => 12,
    ];
    $normalized = strtolower(trim($sheet));
    return $months[$normalized] ?? null;
}

function valid_activity_date(string $date, int $year, int $month): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $dateValue !== false
        && $dateValue->format('Y-m-d') === $date
        && (int) $dateValue->format('Y') === $year
        && (int) $dateValue->format('n') === $month;
}

try {
    $connection = db_connection();
    $action = $payload['action'];

    if ($action === 'start') {
        $fileHash = strtolower(trim((string) ($payload['file_hash'] ?? '')));
        $filename = trim((string) ($payload['filename'] ?? ''));
        $yearStart = filter_var($payload['academic_year_start'] ?? null, FILTER_VALIDATE_INT);
        $expectedEvents = filter_var($payload['expected_events'] ?? null, FILTER_VALIDATE_INT);
        if (!preg_match('/^[a-f0-9]{64}$/', $fileHash)
            || $filename === '' || strlen($filename) > 255
            || $yearStart === false || $yearStart < 2000 || $yearStart > 2099
            || $expectedEvents === false || $expectedEvents < 1 || $expectedEvents > 150000) {
            json_response(['success' => false, 'error' => 'Identitas file, tahun ajaran, atau jumlah catatan tidak valid.'], 400);
        }

        $connection->begin_transaction();
        $existingStatement = $connection->prepare(
            'SELECT id, status FROM student_activity_imports WHERE file_hash = ? LIMIT 1 FOR UPDATE'
        );
        $existingStatement->bind_param('s', $fileHash);
        $existingStatement->execute();
        $existing = $existingStatement->get_result()->fetch_assoc();
        $existingStatement->close();
        if ($existing !== null && $existing['status'] === 'completed') {
            $connection->rollback();
            json_response([
                'success' => false,
                'error' => 'File yang sama sudah pernah berhasil diimpor. Impor ulang ditolak agar catatan tidak terhitung ganda.',
            ], 409);
        }

        if ($existing !== null) {
            $importId = (int) $existing['id'];
            $deleteStatement = $connection->prepare(
                'DELETE FROM student_activity_history WHERE import_id = ?'
            );
            $deleteStatement->bind_param('i', $importId);
            $deleteStatement->execute();
            $deleteStatement->close();
            $resetStatement = $connection->prepare(
                "UPDATE student_activity_imports
                 SET source_filename = ?, academic_year_start = ?, expected_events = ?,
                     imported_events = 0, created_by = ?, created_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = 'processing'"
            );
            $resetStatement->bind_param('siiii', $filename, $yearStart, $expectedEvents, $userId, $importId);
            $resetStatement->execute();
            $resetStatement->close();
        } else {
            $insertStatement = $connection->prepare(
                "INSERT INTO student_activity_imports
                    (file_hash, source_filename, academic_year_start, expected_events, created_by, status)
                 VALUES (?, ?, ?, ?, ?, 'processing')"
            );
            $insertStatement->bind_param('ssiii', $fileHash, $filename, $yearStart, $expectedEvents, $userId);
            $insertStatement->execute();
            $importId = (int) $insertStatement->insert_id;
            $insertStatement->close();
        }
        $connection->commit();
        json_response([
            'success' => true,
            'import_id' => $importId,
            'expected_events' => $expectedEvents,
        ]);
    }

    $importId = filter_var($payload['import_id'] ?? null, FILTER_VALIDATE_INT);
    if ($importId === false || $importId === null || $importId < 1) {
        json_response(['success' => false, 'error' => 'ID impor tidak valid.'], 400);
    }
    if (!in_array($action, ['complete', 'chunk'], true)) {
        json_response(['success' => false, 'error' => 'Aksi impor tidak dikenal.'], 400);
    }
    $connection->begin_transaction();
    $importStatement = $connection->prepare(
        'SELECT id, academic_year_start, expected_events, imported_events, status
         FROM student_activity_imports WHERE id = ? LIMIT 1 FOR UPDATE'
    );
    $importStatement->bind_param('i', $importId);
    $importStatement->execute();
    $import = $importStatement->get_result()->fetch_assoc();
    $importStatement->close();
    if ($import === null) {
        json_response(['success' => false, 'error' => 'Sesi impor tidak ditemukan.'], 404);
    }

    $yearLockName = 'mysantriqu_activity_year_' . (int) $import['academic_year_start'];
    $yearLockStatement = $connection->prepare('SELECT GET_LOCK(?, 5) AS acquired');
    $yearLockStatement->bind_param('s', $yearLockName);
    $yearLockStatement->execute();
    $yearLockAcquired = (int) ($yearLockStatement->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
    $yearLockStatement->close();
    if (!$yearLockAcquired) {
        json_response(['success' => false, 'error' => 'Impor tahun ajaran ini sedang diproses oleh admin lain. Coba lagi sebentar.'], 503);
    }

    if ($action === 'complete') {
        if ($import['status'] !== 'processing'
            || (int) $import['imported_events'] !== (int) $import['expected_events']) {
            json_response(['success' => false, 'error' => 'Belum semua catatan berhasil diterima. Ulangi proses impor.'], 409);
        }
        $completeStatement = $connection->prepare(
            "UPDATE student_activity_imports
             SET status = 'completed', completed_at = CURRENT_TIMESTAMP
             WHERE id = ? AND status = 'processing' AND imported_events = expected_events"
        );
        $completeStatement->bind_param('i', $importId);
        $completeStatement->execute();
        $completed = $completeStatement->affected_rows === 1;
        $completeStatement->close();
        if (!$completed) {
            json_response(['success' => false, 'error' => 'Impor belum dapat diselesaikan. Muat ulang data lalu coba lagi.'], 409);
        }
        $connection->commit();
        $releaseLock = $connection->prepare('SELECT RELEASE_LOCK(?)');
        $releaseLock->bind_param('s', $yearLockName);
        $releaseLock->execute();
        $releaseLock->close();
        json_response(['success' => true, 'imported_events' => (int) $import['imported_events']]);
    }

    if ($import['status'] !== 'processing') {
        json_response(['success' => false, 'error' => 'Sesi impor ini sudah selesai.'], 409);
    }
    $events = $payload['events'] ?? null;
    if (!is_array($events) || count($events) < 1 || count($events) > 500) {
        json_response(['success' => false, 'error' => 'Bagian impor harus berisi 1 sampai 500 catatan.'], 400);
    }

    $validatedEvents = [];
    $studentIds = [];
    $allowedSheets = [];
    foreach (['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        'Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Agu', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'] as $sheetName) {
        $allowedSheets[strtolower($sheetName)] = true;
    }
    foreach ($events as $event) {
        if (!is_array($event)) {
            json_response(['success' => false, 'error' => 'Format catatan aktivitas tidak valid.'], 400);
        }
        $studentId = filter_var($event['santri_id'] ?? null, FILTER_VALIDATE_INT);
        $activity = trim((string) ($event['nama_kegiatan'] ?? ''));
        $date = trim((string) ($event['tanggal'] ?? ''));
        $slot = filter_var($event['slot'] ?? null, FILTER_VALIDATE_INT);
        $sheet = trim((string) ($event['sheet'] ?? ''));
        $row = filter_var($event['row'] ?? null, FILTER_VALIDATE_INT);
        $month = activity_month_number($sheet);
        if ($studentId === false || $studentId === null || $studentId < 1
            || $activity === '' || strlen($activity) > 255
            || $slot === false || $slot < 1 || $slot > 10
            || $row === false || $row < 2 || $row > 10000
            || !isset($allowedSheets[strtolower($sheet)]) || $month === null) {
            json_response(['success' => false, 'error' => 'Data catatan aktivitas tidak valid.'], 400);
        }
        $year = $month >= 7 ? (int) $import['academic_year_start'] : (int) $import['academic_year_start'] + 1;
        if (!valid_activity_date($date, $year, $month)) {
            json_response(['success' => false, 'error' => 'Tanggal aktivitas tidak sesuai dengan sheet bulan dan tahun ajaran.'], 400);
        }
        $studentIds[$studentId] = $studentId;
        $validatedEvents[] = [
            'santri_id' => $studentId,
            'nama_kegiatan' => $activity,
            'tanggal' => $date,
            'slot' => $slot,
            'sheet' => $sheet,
            'row' => $row,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    $studentCheck = $connection->prepare('SELECT id FROM santri WHERE id IN (' . $placeholders . ')');
    $studentTypes = str_repeat('i', count($studentIds));
    $studentValues = array_values($studentIds);
    $studentCheck->bind_param($studentTypes, ...$studentValues);
    $studentCheck->execute();
    $validIds = array_map(
        static fn (array $record): int => (int) $record['id'],
        $studentCheck->get_result()->fetch_all(MYSQLI_ASSOC)
    );
    $studentCheck->close();
    if (count($validIds) !== count($studentIds)) {
        json_response(['success' => false, 'error' => 'Santri yang dipilih tidak lagi tersedia. Muat ulang daftar santri.'], 400);
    }

    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    $duplicateCheck = $connection->prepare(
        "SELECT h.santri_id
         FROM student_activity_history h
         INNER JOIN student_activity_imports i ON i.id = h.import_id
         WHERE i.academic_year_start = ?
           AND i.status IN ('processing', 'completed')
           AND i.id <> ?
           AND h.santri_id IN (" . $studentPlaceholders . ")
         LIMIT 1"
    );
    $duplicateValues = array_merge(
        [(int) $import['academic_year_start'], $importId],
        array_values($studentIds)
    );
    $duplicateTypes = 'ii' . str_repeat('i', count($studentIds));
    $duplicateCheck->bind_param($duplicateTypes, ...$duplicateValues);
    $duplicateCheck->execute();
    $duplicateStudent = $duplicateCheck->get_result()->fetch_assoc();
    $duplicateCheck->close();
    if ($duplicateStudent !== null) {
        json_response([
            'success' => false,
            'error' => 'Salah satu santri sudah memiliki rekap pada tahun ajaran ini. Setiap file harus berisi santri yang berbeda agar riwayat tidak tercatat ganda.',
        ], 409);
    }

    $rowPlaceholders = implode(',', array_fill(0, count($validatedEvents), '(?, ?, ?, ?, ?, ?, ?)'));
    $insertStatement = $connection->prepare(
        'INSERT IGNORE INTO student_activity_history
            (import_id, santri_id, activity_name, occurred_on, slot_number, source_sheet, source_row)
         VALUES ' . $rowPlaceholders
    );
    $types = str_repeat('iissisi', count($validatedEvents));
    $values = [];
    foreach ($validatedEvents as $event) {
        array_push(
            $values,
            $importId,
            $event['santri_id'],
            $event['nama_kegiatan'],
            $event['tanggal'],
            $event['slot'],
            $event['sheet'],
            $event['row']
        );
    }
    $bindArguments = [$types];
    foreach ($values as $index => &$value) {
        $bindArguments[] = &$value;
    }
    unset($value);

    call_user_func_array([$insertStatement, 'bind_param'], $bindArguments);
    $insertStatement->execute();
    $insertedCount = $insertStatement->affected_rows;
    $insertStatement->close();
    $newImportedCount = (int) $import['imported_events'] + $insertedCount;
    if ($newImportedCount > (int) $import['expected_events']) {
        $connection->rollback();
        json_response(['success' => false, 'error' => 'Jumlah catatan yang diterima melebihi hasil pratinjau. Ulangi impor.'], 409);
    }
    $countStatement = $connection->prepare(
        "UPDATE student_activity_imports
         SET imported_events = ?
         WHERE id = ? AND status = 'processing'"
    );
    $countStatement->bind_param('ii', $newImportedCount, $importId);
    $countStatement->execute();
    $countStatement->close();
    $connection->commit();
    $releaseLock = $connection->prepare('SELECT RELEASE_LOCK(?)');
    $releaseLock->bind_param('s', $yearLockName);
    $releaseLock->execute();
    $releaseLock->close();

    json_response([
        'success' => true,
        'imported_events' => $newImportedCount,
        'expected_events' => (int) $import['expected_events'],
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    if (isset($connection) && $connection instanceof mysqli) {
        try {
            $connection->rollback();
        } catch (mysqli_sql_exception $rollbackError) {
            error_log('Student activity import rollback failed: ' . $rollbackError->getMessage());
        }
    }
    error_log('Import student activity history failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Impor riwayat aktivitas gagal diproses. Periksa database lalu coba lagi.'], 503);
}
