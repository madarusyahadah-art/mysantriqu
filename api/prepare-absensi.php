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
if (!in_array(strtolower((string) ($user['role'] ?? '')), ['admin', 'musyrif', 'supervisor', "mas'ul"], true)) {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses untuk mencatat kehadiran.'], 403);
}

date_default_timezone_set('Asia/Jakarta');
$date = (string) ($_GET['tanggal'] ?? date('Y-m-d'));
$dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || $dateValue === false || $dateValue->format('Y-m-d') !== $date
    || $date > date('Y-m-d')) {
    json_response(['success' => false, 'error' => 'Tanggal absensi tidak valid atau belum terjadi.'], 400);
}

$activityId = null;
if (isset($_GET['kegiatan_id']) && $_GET['kegiatan_id'] !== '') {
    $activityId = filter_var($_GET['kegiatan_id'], FILTER_VALIDATE_INT);
    if ($activityId === false || $activityId < 1) {
        json_response(['success' => false, 'error' => 'Kegiatan yang dipilih tidak valid.'], 400);
    }
}

try {
    $connection = db_connection();
    $activityResult = $connection->query(
        'SELECT id, nama_kegiatan, kategori, frekuensi
         FROM kegiatan
         WHERE is_active = 1
         ORDER BY nama_kegiatan ASC, id ASC'
    );
    $activities = $activityResult->fetch_all(MYSQLI_ASSOC);
    foreach ($activities as &$activity) {
        $activity['id'] = (int) $activity['id'];
    }
    unset($activity);

    $selectedActivity = null;
    $attendanceByStudent = [];
    if ($activityId !== null) {
        $activityStatement = $connection->prepare(
            'SELECT id FROM kegiatan WHERE id = ? AND is_active = 1 LIMIT 1'
        );
        $activityStatement->bind_param('i', $activityId);
        $activityStatement->execute();
        $selectedActivity = $activityStatement->get_result()->fetch_assoc();
        $activityStatement->close();
        if ($selectedActivity === null) {
            json_response(['success' => false, 'error' => 'Kegiatan tidak ditemukan atau sudah tidak aktif.'], 404);
        }

        $attendanceStatement = $connection->prepare(
            'SELECT santri_id, status, keterangan
             FROM absensi
             WHERE kegiatan_id = ? AND tanggal = ?'
        );
        $attendanceStatement->bind_param('is', $activityId, $date);
        $attendanceStatement->execute();
        $attendanceRows = $attendanceStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $attendanceStatement->close();
        foreach ($attendanceRows as $row) {
            $attendanceByStudent[(int) $row['santri_id']] = [
                'status' => (string) $row['status'],
                'keterangan' => (string) ($row['keterangan'] ?? ''),
            ];
        }
    }

    $studentResult = $connection->query(
        'SELECT id, nama, no_ktm, angkatan
         FROM santri
         ORDER BY nama ASC, id ASC'
    );
    $students = $studentResult->fetch_all(MYSQLI_ASSOC);
    foreach ($students as &$student) {
        $student['id'] = (int) $student['id'];
        $student['attendance'] = $attendanceByStudent[$student['id']] ?? null;
    }
    unset($student);

    json_response([
        'success' => true,
        'tanggal' => $date,
        'kegiatan' => $activities,
        'santri' => $students,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Prepare attendance input failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Data kehadiran belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}