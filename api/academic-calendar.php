<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic-calendar-helpers.php';

$user = require_calendar_user();
$isAdmin = strtolower((string) ($user['role'] ?? '')) === 'admin';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function calendar_record_change(
    mysqli $connection,
    int $importId,
    ?int $eventId,
    string $type,
    ?array $oldValue,
    ?array $newValue,
    string $reason,
    int $userId
): void {
    $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statement = $connection->prepare(
        'INSERT INTO academic_calendar_changes
         (import_id, event_id, change_type, old_value, new_value, reason, changed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('iissssi', $importId, $eventId, $type, $oldJson, $newJson, $reason, $userId);
    $statement->execute();
    $statement->close();
}

function calendar_validate_import_events(mixed $events): array
{
    $categories = ['Akademik', 'Ujian', 'Libur', 'Kegiatan Ma\'had', 'Lainnya'];
    if (!is_array($events) || count($events) < 1 || count($events) > 1000) {
        json_response(['success' => false, 'error' => 'Kalender harus berisi 1 sampai 1.000 kegiatan.'], 400);
    }

    $validated = [];
    foreach ($events as $event) {
        if (!is_array($event)) {
            json_response(['success' => false, 'error' => 'Format salah satu kegiatan tidak valid.'], 400);
        }
        $name = trim((string) ($event['nama_kegiatan'] ?? ''));
        $start = $event['tanggal_mulai'] ?? null;
        $end = $event['tanggal_selesai'] ?? null;
        $startTime = calendar_parse_optional_time($event['jam_mulai'] ?? null);
        $endTime = calendar_parse_optional_time($event['jam_selesai'] ?? null);
        $description = trim((string) ($event['keterangan'] ?? ''));
        $category = trim((string) ($event['kategori'] ?? 'Lainnya'));
        $semesterEnd = $event['akhir_semester'] ?? false;
        if ($name === '' || strlen($name) > 1000 || !calendar_valid_date($start) || !calendar_valid_date($end)
            || $start > $end || strlen($description) > 5000
            || !in_array($category, $categories, true) || !is_bool($semesterEnd)
            || $startTime === false || $endTime === false
            || (($startTime === null) !== ($endTime === null))
            || ($start === $end && $startTime !== null && $startTime > $endTime)
            || ($semesterEnd && ($start === '' || $end === ''))) {
            json_response(['success' => false, 'error' => 'Ada kegiatan dengan nama, tanggal, kategori, atau keterangan yang tidak valid.'], 400);
        }
        $validated[] = [
            'nama_kegiatan' => $name,
            'tanggal_mulai' => $start,
            'tanggal_selesai' => $end,
            'jam_mulai' => $startTime,
            'jam_selesai' => $endTime,
            'keterangan' => $description,
            'kategori' => $category,
            'akhir_semester' => $semesterEnd,
        ];
    }

    return $validated;
}

if ($method === 'GET') {
    $month = (string) ($_GET['bulan'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        json_response(['success' => false, 'error' => 'Bulan kalender tidak valid.'], 400);
    }
    $monthStart = $month . '-01';
    $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
    try {
        $connection = db_connection();
        $processedRollovers = process_academic_semester_rollovers($connection);
        $calendarResult = $connection->query(
            "SELECT id, judul, tahun_ajaran, nama_berkas, created_at, published_at
             FROM academic_calendar_imports
             WHERE status = 'published'
             ORDER BY published_at DESC, id DESC
             LIMIT 1"
        );
        $calendar = $calendarResult->fetch_assoc() ?: null;
        $events = [];
        $changes = [];
        $latestChangeId = 0;
        if ($calendar !== null) {
            $calendarId = (int) $calendar['id'];
            $statement = $connection->prepare(
                'SELECT id, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai,
                        nama_kegiatan, keterangan, kategori,
                        is_semester_end, semester_incremented_at, is_cancelled
                 FROM academic_calendar_events
                 WHERE import_id = ? AND tanggal_mulai <= ? AND tanggal_selesai >= ?
                 ORDER BY tanggal_mulai ASC, nama_kegiatan ASC, id ASC'
            );
            $statement->bind_param('iss', $calendarId, $monthEnd, $monthStart);
            $statement->execute();
            $events = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
            $statement->close();
            $changeStatement = $connection->prepare(
                'SELECT id, event_id, change_type, old_value, new_value, reason, created_at
                 FROM academic_calendar_changes
                 WHERE import_id = ? ORDER BY id DESC LIMIT 30'
            );
            $changeStatement->bind_param('i', $calendarId);
            $changeStatement->execute();
            $changes = $changeStatement->get_result()->fetch_all(MYSQLI_ASSOC);
            $changeStatement->close();
            $latestChangeId = (int) ($connection->query(
                'SELECT COALESCE(MAX(id), 0) AS latest_id FROM academic_calendar_changes WHERE import_id = ' . $calendarId
            )->fetch_assoc()['latest_id'] ?? 0);
            foreach ($changes as &$change) {
                $change['id'] = (int) $change['id'];
                $change['event_id'] = $change['event_id'] === null ? null : (int) $change['event_id'];
                $change['old_value'] = $change['old_value'] === null ? null : json_decode($change['old_value'], true);
                $change['new_value'] = $change['new_value'] === null ? null : json_decode($change['new_value'], true);
            }
            unset($change);
            $changes = array_reverse($changes);
            foreach ($events as &$event) {
                $event['id'] = (int) $event['id'];
                $event['is_semester_end'] = (bool) $event['is_semester_end'];
                $event['is_cancelled'] = (bool) $event['is_cancelled'];
            }
            unset($event);
        }
        $draft = null;
        if ($isAdmin) {
            $draftResult = $connection->query(
                "SELECT i.id, i.judul, i.tahun_ajaran, i.nama_berkas, i.created_at,
                        (SELECT COUNT(*) FROM academic_calendar_events e WHERE e.import_id = i.id) AS event_count
                 FROM academic_calendar_imports i
                 WHERE i.status = 'draft'
                 ORDER BY i.id DESC LIMIT 1"
            );
            $draft = $draftResult->fetch_assoc() ?: null;
        }
        json_response([
            'success' => true,
            'calendar' => $calendar,
            'events' => $events,
            'changes' => $changes,
            'latest_change_id' => $latestChangeId,
            'draft' => $draft,
            'role' => $user['role'] ?? '',
            'processed_rollovers' => $processedRollovers,
        ]);
    } catch (Throwable $error) {
        error_log('Academic calendar read failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Kalender belum siap. Jalankan migrasi kalender akademik dan pastikan database aktif.'], 503);
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}
require_calendar_admin($user);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(['success' => false, 'error' => 'Data permintaan tidak valid.'], 400);
}
$action = (string) ($data['action'] ?? '');
$connection = null;
try {
    $connection = db_connection();
} catch (RuntimeException $error) {
    error_log('Academic calendar database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}
$userId = (int) ($user['id'] ?? 0);

if ($action === 'save_draft') {
    $year = trim((string) ($data['tahun_ajaran'] ?? ''));
    $title = trim((string) ($data['judul'] ?? ''));
    $filename = trim((string) ($data['nama_berkas'] ?? ''));
    $yearParts = preg_match('/^(\d{4})-(\d{4})$/', $year, $match) ? $match : null;
    if (!$yearParts || (int) $yearParts[2] !== (int) $yearParts[1] + 1
        || $title === '' || strlen($title) > 255 || $filename === '' || strlen($filename) > 255) {
        json_response(['success' => false, 'error' => 'Judul dan tahun ajaran harus diisi dengan format yang benar.'], 400);
    }
    $events = calendar_validate_import_events($data['events'] ?? null);
    try {
        $connection->begin_transaction();
        $connection->query("DELETE FROM academic_calendar_imports WHERE status = 'draft'");
        $import = $connection->prepare(
            "INSERT INTO academic_calendar_imports
             (judul, tahun_ajaran, nama_berkas, imported_by, status)
             VALUES (?, ?, ?, ?, 'draft')"
        );
        $import->bind_param('sssi', $title, $year, $filename, $userId);
        $import->execute();
        $importId = (int) $import->insert_id;
        $import->close();
        $insertEvent = $connection->prepare(
            'INSERT INTO academic_calendar_events
             (import_id, tanggal_mulai, tanggal_selesai, jam_mulai, jam_selesai, nama_kegiatan, keterangan, kategori, is_semester_end)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($events as $event) {
            $semesterEnd = (int) $event['akhir_semester'];
            $insertEvent->bind_param(
                'isssssssi',
                $importId,
                $event['tanggal_mulai'],
                $event['tanggal_selesai'],
                $event['jam_mulai'],
                $event['jam_selesai'],
                $event['nama_kegiatan'],
                $event['keterangan'],
                $event['kategori'],
                $semesterEnd
            );
            $insertEvent->execute();
        }
        $insertEvent->close();
        $connection->commit();
        json_response(['success' => true, 'draft_id' => $importId, 'event_count' => count($events)]);
    } catch (Throwable $error) {
        $connection->rollback();
        error_log('Academic calendar draft save failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Draf kalender gagal disimpan. Periksa migrasi dan koneksi database.'], 500);
    }
}

if ($action === 'publish') {
    $draftId = filter_var($data['draft_id'] ?? null, FILTER_VALIDATE_INT);
    if ($draftId === false || $draftId === null || $draftId < 1) {
        json_response(['success' => false, 'error' => 'Draf kalender tidak valid.'], 400);
    }
    try {
        $connection->begin_transaction();
        $lookup = $connection->prepare(
            "SELECT id, judul, tahun_ajaran FROM academic_calendar_imports
             WHERE id = ? AND status = 'draft' FOR UPDATE"
        );
        $lookup->bind_param('i', $draftId);
        $lookup->execute();
        $draft = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$draft) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Draf tidak ditemukan atau sudah diterbitkan.'], 404);
        }
        $oldCalendar = $connection->query(
            "SELECT id, judul, tahun_ajaran FROM academic_calendar_imports WHERE status = 'published' LIMIT 1"
        )->fetch_assoc();
        $connection->query("UPDATE academic_calendar_imports SET status = 'archived' WHERE status = 'published'");
        $publish = $connection->prepare(
            "UPDATE academic_calendar_imports SET status = 'published', published_at = NOW() WHERE id = ?"
        );
        $publish->bind_param('i', $draftId);
        $publish->execute();
        $publish->close();
        $reason = 'Kalender akademik tahun ajaran ' . $draft['tahun_ajaran'] . ' telah diterbitkan.';
        calendar_record_change(
            $connection,
            (int) $draftId,
            null,
            'calendar_published',
            $oldCalendar ?: null,
            ['judul' => $draft['judul'], 'tahun_ajaran' => $draft['tahun_ajaran']],
            $reason,
            $userId
        );
        $connection->commit();
        $pushResult = send_academic_calendar_push($connection, 'Kalender akademik diperbarui', $reason);
        json_response(['success' => true, 'push' => $pushResult]);
    } catch (Throwable $error) {
        $connection->rollback();
        error_log('Academic calendar publish failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Kalender gagal diterbitkan. Silakan coba lagi.'], 500);
    }
}

if ($action === 'update_event' || $action === 'cancel_event') {
    $eventId = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT);
    $reason = trim((string) ($data['reason'] ?? ''));
    if ($eventId === false || $eventId === null || $eventId < 1
        || $reason === '' || strlen($reason) > 1000) {
        json_response(['success' => false, 'error' => 'Pilih kegiatan dan isi alasan perubahan (maksimal 1.000 karakter).'], 400);
    }
    try {
        $connection->begin_transaction();
        $lookup = $connection->prepare(
            "SELECT e.*, i.id AS calendar_id
             FROM academic_calendar_events e
             INNER JOIN academic_calendar_imports i ON i.id = e.import_id
             WHERE e.id = ? AND i.status = 'published' FOR UPDATE"
        );
        $lookup->bind_param('i', $eventId);
        $lookup->execute();
        $event = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$event) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Kegiatan tidak ditemukan.'], 404);
        }
        if ((int) $event['is_cancelled'] === 1) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Kegiatan sudah dibatalkan.'], 409);
        }
        if ((int) $event['is_semester_end'] === 1 && $event['semester_incremented_at'] !== null) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Kegiatan ini sudah memicu kenaikan semester dan tidak dapat diubah.'], 409);
        }
        $oldValue = [
            'nama_kegiatan' => $event['nama_kegiatan'],
            'tanggal_mulai' => $event['tanggal_mulai'],
            'tanggal_selesai' => $event['tanggal_selesai'],
            'jam_mulai' => $event['jam_mulai'],
            'jam_selesai' => $event['jam_selesai'],
            'keterangan' => $event['keterangan'],
            'kategori' => $event['kategori'],
            'akhir_semester' => (bool) $event['is_semester_end'],
            'dibatalkan' => false,
        ];
        if ($action === 'cancel_event') {
            $cancel = $connection->prepare('UPDATE academic_calendar_events SET is_cancelled = 1 WHERE id = ?');
            $cancel->bind_param('i', $eventId);
            $cancel->execute();
            $cancel->close();
            $newValue = $oldValue;
            $newValue['dibatalkan'] = true;
            $changeType = 'cancelled';
            $pushTitle = 'Kegiatan kalender dibatalkan';
            $pushBody = $event['nama_kegiatan'] . ': ' . $reason;
        } else {
            $name = trim((string) ($data['nama_kegiatan'] ?? ''));
            $start = $data['tanggal_mulai'] ?? null;
            $end = $data['tanggal_selesai'] ?? null;
            $startTime = calendar_parse_optional_time($data['jam_mulai'] ?? null);
            $endTime = calendar_parse_optional_time($data['jam_selesai'] ?? null);
            $description = trim((string) ($data['keterangan'] ?? ''));
            $category = trim((string) ($data['kategori'] ?? 'Lainnya'));
            $semesterEnd = $data['akhir_semester'] ?? false;
            if ($name === '' || strlen($name) > 1000 || !calendar_valid_date($start) || !calendar_valid_date($end)
                || $start > $end || strlen($description) > 5000
                || !in_array($category, ['Akademik', 'Ujian', 'Libur', 'Kegiatan Ma\'had', 'Lainnya'], true)
                || !is_bool($semesterEnd) || $startTime === false || $endTime === false
                || (($startTime === null) !== ($endTime === null))
                || ($start === $end && $startTime !== null && $startTime > $endTime)) {
                $connection->rollback();
                json_response(['success' => false, 'error' => 'Data kegiatan atau tanggal perubahan tidak valid.'], 400);
            }
            $newValue = [
                'nama_kegiatan' => $name,
                'tanggal_mulai' => $start,
                'tanggal_selesai' => $end,
                'jam_mulai' => $startTime,
                'jam_selesai' => $endTime,
                'keterangan' => $description,
                'kategori' => $category,
                'akhir_semester' => $semesterEnd,
                'dibatalkan' => false,
            ];
            if ($oldValue === $newValue) {
                $connection->rollback();
                json_response(['success' => false, 'error' => 'Tidak ada perubahan pada kegiatan.'], 409);
            }
            $update = $connection->prepare(
                'UPDATE academic_calendar_events
                 SET tanggal_mulai = ?, tanggal_selesai = ?, jam_mulai = ?, jam_selesai = ?,
                     nama_kegiatan = ?, keterangan = ?, kategori = ?, is_semester_end = ? WHERE id = ?'
            );
            $semesterFlag = (int) $semesterEnd;
            $update->bind_param(
                'sssssssii',
                $start,
                $end,
                $startTime,
                $endTime,
                $name,
                $description,
                $category,
                $semesterFlag,
                $eventId
            );
            $update->execute();
            $update->close();
            $changeType = 'updated';
            $pushTitle = 'Jadwal kegiatan diperbarui';
            $pushBody = $name . ': ' . $reason;
        }
        calendar_record_change(
            $connection,
            (int) $event['calendar_id'],
            $eventId,
            $changeType,
            $oldValue,
            $newValue,
            $reason,
            $userId
        );
        $connection->commit();
        $pushResult = send_academic_calendar_push($connection, $pushTitle, $pushBody);
        json_response(['success' => true, 'push' => $pushResult]);
    } catch (Throwable $error) {
        $connection->rollback();
        error_log('Academic calendar event change failed: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Perubahan jadwal gagal disimpan. Silakan coba lagi.'], 500);
    }
}

json_response(['success' => false, 'error' => 'Aksi kalender tidak dikenal.'], 400);