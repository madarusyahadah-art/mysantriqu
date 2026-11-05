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
if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'musyrif', 'supervisor', "mas'ul"], true)) {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses untuk mencatat kehadiran.'], 403);
}
$userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId === null || $userId < 1) {
    json_response(['success' => false, 'error' => 'Akun tidak valid. Silakan masuk kembali.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Data kehadiran yang dikirim tidak valid.'], 400);
}

date_default_timezone_set('Asia/Jakarta');
$dateValueInput = $payload['tanggal'] ?? null;
if (!is_string($dateValueInput)) {
    json_response(['success' => false, 'error' => 'Tanggal absensi tidak valid atau belum terjadi.'], 400);
}
$date = trim($dateValueInput);
$dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || $dateValue === false || $dateValue->format('Y-m-d') !== $date
    || $date > date('Y-m-d')) {
    json_response(['success' => false, 'error' => 'Tanggal absensi tidak valid atau belum terjadi.'], 400);
}

$activityId = filter_var($payload['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
if ($activityId === false || $activityId === null || $activityId < 1) {
    json_response(['success' => false, 'error' => 'Pilih kegiatan yang valid.'], 400);
}

$submittedRecords = $payload['records'] ?? null;
if (!is_array($submittedRecords) || count($submittedRecords) < 1 || count($submittedRecords) > 1000) {
    json_response(['success' => false, 'error' => 'Pilih setidaknya satu status kehadiran yang akan disimpan.'], 400);
}

$allowedStatuses = ['hadir', 'izin', 'sakit', 'alpa'];
$records = [];
foreach ($submittedRecords as $record) {
    if (!is_array($record)) {
        json_response(['success' => false, 'error' => 'Format catatan kehadiran tidak valid.'], 400);
    }
    $studentId = filter_var($record['santri_id'] ?? null, FILTER_VALIDATE_INT);
    $statusInput = $record['status'] ?? null;
    $noteInput = $record['keterangan'] ?? '';
    if (!is_string($statusInput) || !is_string($noteInput)) {
        json_response(['success' => false, 'error' => 'Status, keterangan, atau data santri tidak valid. Periksa kembali isian.'], 400);
    }
    $status = strtolower(trim($statusInput));
    $note = trim($noteInput);
    if ($studentId === false || $studentId === null || $studentId < 1
        || ($status !== '' && !in_array($status, $allowedStatuses, true))
        || strlen($note) > 2000 || isset($records[$studentId])) {
        json_response(['success' => false, 'error' => 'Status, keterangan, atau data santri tidak valid. Periksa kembali isian.'], 400);
    }
    $records[$studentId] = ['status' => $status, 'keterangan' => $note];
}

try {
    $connection = db_connection();
    $activityStatement = $connection->prepare(
        'SELECT id FROM kegiatan WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $activityStatement->bind_param('i', $activityId);
    $activityStatement->execute();
    $activityExists = $activityStatement->get_result()->num_rows > 0;
    $activityStatement->close();
    if (!$activityExists) {
        json_response(['success' => false, 'error' => 'Kegiatan tidak ditemukan atau sudah tidak aktif.'], 404);
    }

    $studentIds = array_keys($records);
    $studentCheck = $connection->query(
        'SELECT id FROM santri WHERE id IN (' . implode(',', $studentIds) . ')'
    );
    $validStudentCount = $studentCheck->num_rows;
    if ($validStudentCount !== count($studentIds)) {
        json_response(['success' => false, 'error' => 'Sebagian data santri tidak ditemukan. Muat ulang daftar sebelum menyimpan.'], 400);
    }

    $connection->begin_transaction();
    $saveStatement = $connection->prepare(
        'INSERT INTO absensi (kegiatan_id, tanggal, santri_id, status, keterangan, input_by)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           status = VALUES(status),
           keterangan = VALUES(keterangan),
           input_by = VALUES(input_by),
           updated_at = CURRENT_TIMESTAMP'
    );
    $deleteStatement = $connection->prepare(
        'DELETE FROM absensi WHERE kegiatan_id = ? AND tanggal = ? AND santri_id = ?'
    );
    foreach ($records as $studentId => $record) {
        if ($record['status'] === '') {
            $deleteStatement->bind_param('isi', $activityId, $date, $studentId);
            $deleteStatement->execute();
            continue;
        }
        $saveStatement->bind_param(
            'isissi',
            $activityId,
            $date,
            $studentId,
            $record['status'],
            $record['keterangan'],
            $userId
        );
        $saveStatement->execute();
    }
    $saveStatement->close();
    $deleteStatement->close();
    $connection->commit();

    json_response([
        'success' => true,
        'saved' => count($records),
        'message' => 'Catatan kehadiran berhasil disimpan.',
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    if (isset($connection) && $connection instanceof mysqli) {
        try {
            $connection->rollback();
        } catch (mysqli_sql_exception $rollbackError) {
            error_log('Attendance save rollback failed: ' . $rollbackError->getMessage());
        }
    }
    error_log('Save attendance input failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Catatan kehadiran gagal disimpan. Periksa koneksi lalu coba lagi.'], 503);
}