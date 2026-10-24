<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic-calendar-helpers.php';

$user = require_login();
$academicCalendarWarning = null;
try {
    process_academic_semester_rollovers($conn);
} catch (Throwable $error) {
    error_log('Academic semester rollover is unavailable: ' . $error->getMessage());
    $academicCalendarWarning = "Kalender akademik belum siap. Jalankan migrasi mysantriqu_academic_calendar_migration.sql untuk mengaktifkan kenaikan semester otomatis.";
}

json_response([
    "success" => true,
    "authenticated" => true,
    "user" => $user,
    "academic_calendar_warning" => $academicCalendarWarning,
]);
?>
