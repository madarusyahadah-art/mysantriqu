<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = $method === 'POST' ? require_role(['admin']) : require_login();

if ($method === 'GET') {
    $month = (string) ($_GET['bulan'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        json_response(["success" => false, "error" => "Bulan kalender tidak valid"], 400);
    }

    $monthStart = $month . '-01';
    $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
    try {
        $importStmt = $conn->prepare("
            SELECT id, judul, tahun_ajaran, nama_berkas, created_at
            FROM academic_calendar_imports
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $importStmt->execute();
        $calendar = $importStmt->get_result()->fetch_assoc();
        $importStmt->close();

        $events = [];
        if ($calendar) {
            $eventsStmt = $conn->prepare("
                SELECT id, tanggal_mulai, tanggal_selesai, nama_kegiatan, is_semester_end, semester_incremented_at
                FROM academic_calendar_events
                WHERE import_id = ?
                  AND (tanggal_mulai IS NULL OR (tanggal_mulai <= ? AND tanggal_selesai >= ?))
                ORDER BY tanggal_mulai IS NULL ASC, tanggal_mulai ASC, nama_kegiatan ASC
            ");
            $eventsStmt->bind_param('iss', $calendar['id'], $monthEnd, $monthStart);
            $eventsStmt->execute();
            $events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $eventsStmt->close();
        }
    } catch (Throwable $error) {
        error_log('academic-calendar.php read error: ' . $error->getMessage());
        json_response(["success" => false, "error" => "Kalender belum siap. Jalankan migrasi mysantriqu_academic_calendar_migration.sql."], 503);
    }

    json_response([
        "success" => true,
        "calendar" => $calendar,
        "events" => $events,
        "role" => $user['role'],
        "academic_calendar_warning" => null,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data impor tidak valid"], 400);
}

$year = trim((string) ($data['tahun_ajaran'] ?? ''));
$title = trim((string) ($data['judul'] ?? ''));
$filename = trim((string) ($data['nama_berkas'] ?? ''));
$events = $data['events'] ?? null;
$yearParts = preg_match('/^(\d{4})-(\d{4})$/', $year, $yearMatch) ? $yearMatch : null;
if (!$yearParts || (int) $yearParts[2] !== (int) $yearParts[1] + 1 || $title === '' || strlen($title) > 255 ||
    $filename === '' || strlen($filename) > 255 || !is_array($events) || !$events || count($events) > 1000) {
    json_response(["success" => false, "error" => "Informasi kalender atau daftar kegiatan tidak valid"], 400);
}

$validDate = static function ($value): bool {
    if ($value === null || $value === '') {
        return true;
    }
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

foreach ($events as $event) {
    if (!is_array($event) || !isset($event['nama_kegiatan']) || !is_string($event['nama_kegiatan']) ||
        trim($event['nama_kegiatan']) === '' || strlen($event['nama_kegiatan']) > 1000 ||
        !$validDate($event['tanggal_mulai'] ?? null) || !$validDate($event['tanggal_selesai'] ?? null)) {
        json_response(["success" => false, "error" => "Ada kegiatan dengan nama atau tanggal yang tidak valid"], 400);
    }
    $start = $event['tanggal_mulai'] ?? null;
    $end = $event['tanggal_selesai'] ?? null;
    if (($start === null) !== ($end === null) || ($start !== null && $start > $end)) {
        json_response(["success" => false, "error" => "Rentang tanggal kegiatan tidak valid"], 400);
    }
    if (!is_bool($event['akhir_semester'] ?? false)) {
        json_response(["success" => false, "error" => "Penanda akhir semester tidak valid"], 400);
    }
    if ($event['akhir_semester'] && (empty($event['tanggal_mulai']) || empty($event['tanggal_selesai']))) {
        json_response(["success" => false, "error" => "Pemicu kenaikan semester harus memiliki tanggal ujian"], 400);
    }
}

$conn->begin_transaction();
try {
    $existingStmt = $conn->prepare('SELECT id FROM academic_calendar_imports WHERE tahun_ajaran = ? FOR UPDATE');
    $existingStmt->bind_param('s', $year);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        $processedStmt = $conn->prepare('SELECT COUNT(*) AS total FROM academic_calendar_events WHERE import_id = ? AND semester_incremented_at IS NOT NULL');
        $processedStmt->bind_param('i', $existing['id']);
        $processedStmt->execute();
        $processed = (int) $processedStmt->get_result()->fetch_assoc()['total'];
        $processedStmt->close();
        if ($processed > 0) {
            throw new DomainException('Kalender tahun ajaran ini sudah memproses kenaikan semester dan tidak dapat ditimpa.');
        }
        $deleteStmt = $conn->prepare('DELETE FROM academic_calendar_imports WHERE id = ?');
        $deleteStmt->bind_param('i', $existing['id']);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $importStmt = $conn->prepare('INSERT INTO academic_calendar_imports (judul, tahun_ajaran, nama_berkas, imported_by) VALUES (?, ?, ?, ?)');
    $userId = (int) $user['id'];
    $importStmt->bind_param('sssi', $title, $year, $filename, $userId);
    $importStmt->execute();
    $importId = $importStmt->insert_id;
    $importStmt->close();

    $eventStmt = $conn->prepare("
        INSERT INTO academic_calendar_events (import_id, tanggal_mulai, tanggal_selesai, nama_kegiatan, is_semester_end)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($events as $event) {
        $start = ($event['tanggal_mulai'] ?? '') !== '' ? $event['tanggal_mulai'] : null;
        $end = ($event['tanggal_selesai'] ?? '') !== '' ? $event['tanggal_selesai'] : null;
        $eventName = trim($event['nama_kegiatan']);
        $isSemesterEnd = $event['akhir_semester'] ? 1 : 0;
        $eventStmt->bind_param('isssi', $importId, $start, $end, $eventName, $isSemesterEnd);
        $eventStmt->execute();
    }
    $eventStmt->close();
    $conn->commit();

    json_response([
        "success" => true,
        "calendar_id" => $importId,
        "jumlah_kegiatan" => count($events),
        "jumlah_pemicu_semester" => count(array_filter($events, static function ($event) { return $event['akhir_semester']; })),
    ]);
} catch (DomainException $error) {
    $conn->rollback();
    json_response(["success" => false, "error" => $error->getMessage()], 409);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('academic-calendar.php import error: ' . $error->getMessage());
    json_response(["success" => false, "error" => "Kalender gagal diimpor. Pastikan migrasi kalender akademik telah dijalankan dan database aktif."], 500);
}
