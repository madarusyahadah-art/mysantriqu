<?php

function process_academic_semester_rollovers(mysqli $conn): int
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $conn->begin_transaction();
    try {
        $eventsStmt = $conn->prepare("
            SELECT id
            FROM academic_calendar_events
            WHERE is_semester_end = 1
              AND semester_incremented_at IS NULL
              AND tanggal_selesai IS NOT NULL
              AND tanggal_selesai < ?
            ORDER BY tanggal_selesai ASC, id ASC
            FOR UPDATE
        ");
        $eventsStmt->bind_param('s', $today);
        $eventsStmt->execute();
        $eventIds = array_column($eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $eventsStmt->close();

        if (!$eventIds) {
            $conn->commit();
            return 0;
        }

        $advanceStmt = $conn->prepare('UPDATE santri SET semester_aktif = LEAST(semester_aktif + 1, 6)');
        $markStmt = $conn->prepare('UPDATE academic_calendar_events SET semester_incremented_at = NOW() WHERE id = ? AND semester_incremented_at IS NULL');
        foreach ($eventIds as $eventId) {
            $advanceStmt->execute();
            $eventId = (int) $eventId;
            $markStmt->bind_param('i', $eventId);
            $markStmt->execute();
        }
        $advanceStmt->close();
        $markStmt->close();
        $conn->commit();

        return count($eventIds);
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Academic semester rollover failed: ' . $error->getMessage());
        throw $error;
    }
}
