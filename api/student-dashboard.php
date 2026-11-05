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

$role = strtolower((string) ($user['role'] ?? ''));
$studentId = (int) ($user['santri_id'] ?? 0);
if (!in_array($role, ['santri', 'mahasantri'], true) || $studentId < 1) {
    json_response(['success' => false, 'error' => 'Dashboard ini hanya tersedia untuk akun santri yang telah terhubung.'], 403);
}

date_default_timezone_set('Asia/Jakarta');
$month = (string) ($_GET['bulan'] ?? date('Y-m'));
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone('Asia/Jakarta'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
    || $monthDate === false || $monthDate->format('Y-m') !== $month) {
    json_response(['success' => false, 'error' => 'Bulan yang dipilih tidak valid.'], 400);
}

$monthStart = $monthDate->format('Y-m-01');
$nextMonth = $monthDate->modify('first day of next month')->format('Y-m-d');
$daysInMonth = (int) $monthDate->format('t');

try {
    $connection = db_connection();

    $studentStatement = $connection->prepare(
        'SELECT nama, semester_aktif, angkatan FROM santri WHERE id = ? LIMIT 1'
    );
    $studentStatement->bind_param('i', $studentId);
    $studentStatement->execute();
    $student = $studentStatement->get_result()->fetch_assoc();
    $studentStatement->close();
    if (!$student) {
        json_response(['success' => false, 'error' => 'Data santri pada akun ini tidak ditemukan.'], 404);
    }

    $attendanceStatement = $connection->prepare(
        "SELECT COUNT(*) AS recorded,
                COALESCE(SUM(LOWER(TRIM(status)) = 'hadir'), 0) AS attended
         FROM absensi
         WHERE santri_id = ? AND tanggal >= ? AND tanggal < ?"
    );
    $attendanceStatement->bind_param('iss', $studentId, $monthStart, $nextMonth);
    $attendanceStatement->execute();
    $attendance = $attendanceStatement->get_result()->fetch_assoc() ?: [];
    $attendanceStatement->close();
    $recorded = (int) ($attendance['recorded'] ?? 0);
    $attended = (int) ($attendance['attended'] ?? 0);

    $violationStatement = $connection->prepare(
        'SELECT tanggal, COUNT(*) AS total
         FROM pelanggaran
         WHERE santri_id = ? AND tanggal >= ? AND tanggal < ?
         GROUP BY tanggal
         ORDER BY tanggal ASC'
    );
    $violationStatement->bind_param('iss', $studentId, $monthStart, $nextMonth);
    $violationStatement->execute();
    $violationRows = $violationStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $violationStatement->close();
    $violationsByDate = [];
    $violationTotal = 0;
    foreach ($violationRows as $row) {
        $count = (int) $row['total'];
        $violationsByDate[(string) $row['tanggal']] = $count;
        $violationTotal += $count;
    }

    $dailyViolations = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = $monthDate->setDate(
            (int) $monthDate->format('Y'),
            (int) $monthDate->format('m'),
            $day
        )->format('Y-m-d');
        $dailyViolations[] = ['date' => $date, 'total' => $violationsByDate[$date] ?? 0];
    }

    $calendarResult = $connection->query(
        "SELECT id FROM academic_calendar_imports
         WHERE status = 'published'
         ORDER BY published_at DESC, id DESC
         LIMIT 1"
    );
    $calendar = $calendarResult->fetch_assoc();
    $nextEvent = null;
    $mahadEvents = [];
    $latestUpdate = null;
    if ($calendar !== null) {
        $calendarId = (int) $calendar['id'];
        $eventStatement = $connection->prepare(
            'SELECT id, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai,
                    nama_kegiatan, keterangan, kategori
             FROM academic_calendar_events
             WHERE import_id = ? AND is_cancelled = 0
               AND (tanggal_selesai > CURDATE()
                    OR (tanggal_selesai = CURDATE() AND (jam_selesai IS NULL OR jam_selesai >= CURTIME())))
             ORDER BY tanggal_mulai ASC, jam_mulai IS NULL ASC, jam_mulai ASC, id ASC
             LIMIT 1'
        );
        $eventStatement->bind_param('i', $calendarId);
        $eventStatement->execute();
        $nextEvent = $eventStatement->get_result()->fetch_assoc() ?: null;
        $eventStatement->close();

        $mahadStatement = $connection->prepare(
            'SELECT id, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai,
                    nama_kegiatan, keterangan, kategori
             FROM academic_calendar_events
             WHERE import_id = ? AND is_cancelled = 0 AND kategori = ?
               AND (tanggal_selesai > CURDATE()
                    OR (tanggal_selesai = CURDATE() AND (jam_selesai IS NULL OR jam_selesai >= CURTIME())))
             ORDER BY tanggal_mulai ASC, jam_mulai IS NULL ASC, jam_mulai ASC, id ASC
             LIMIT 4'
        );
        $mahadCategory = "Kegiatan Ma'had";
        $mahadStatement->bind_param('is', $calendarId, $mahadCategory);
        $mahadStatement->execute();
        $mahadEvents = $mahadStatement->get_result()->fetch_all(MYSQLI_ASSOC);
        $mahadStatement->close();

        $updateStatement = $connection->prepare(
            'SELECT change_type, old_value, new_value, reason, created_at
             FROM academic_calendar_changes
             WHERE import_id = ? ORDER BY id DESC LIMIT 1'
        );
        $updateStatement->bind_param('i', $calendarId);
        $updateStatement->execute();
        $latestUpdate = $updateStatement->get_result()->fetch_assoc() ?: null;
        $updateStatement->close();
        if ($latestUpdate !== null) {
            $latestUpdate['old_value'] = $latestUpdate['old_value'] === null
                ? null : json_decode($latestUpdate['old_value'], true);
            $latestUpdate['new_value'] = $latestUpdate['new_value'] === null
                ? null : json_decode($latestUpdate['new_value'], true);
        }
    }

    json_response([
        'success' => true,
        'month' => $month,
        'student' => [
            'name' => (string) $student['nama'],
            'semester' => (int) $student['semester_aktif'],
            'cohort' => (string) $student['angkatan'],
        ],
        'attendance' => [
            'attended' => $attended,
            'recorded' => $recorded,
            'percentage' => $recorded > 0 ? (int) round(($attended / $recorded) * 100) : null,
        ],
        'violations' => [
            'total' => $violationTotal,
            'daily' => $dailyViolations,
        ],
        'next_event' => $nextEvent,
        'mahad_events' => $mahadEvents,
        'latest_update' => $latestUpdate,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Student dashboard failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Ringkasan beranda belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}
