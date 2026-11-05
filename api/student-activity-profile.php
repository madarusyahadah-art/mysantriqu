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
$staffRoles = ['admin', 'musyrif', 'supervisor', "mas'ul", 'kajur'];
if (in_array($role, ['santri', 'mahasantri'], true)) {
    $studentId = filter_var($user['santri_id'] ?? null, FILTER_VALIDATE_INT);
    if ($studentId === false || $studentId === null || $studentId < 1) {
        json_response(['success' => false, 'error' => 'Akun ini belum terhubung dengan data santri. Hubungi admin.'], 403);
    }
} elseif (in_array($role, $staffRoles, true)) {
    $studentId = filter_var($_GET['santri_id'] ?? null, FILTER_VALIDATE_INT);
    if ($studentId === false || $studentId === null || $studentId < 1) {
        json_response(['success' => false, 'error' => 'Pilih data santri yang akan dilihat.'], 400);
    }
} else {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses ke profil santri.'], 403);
}

try {
    $connection = db_connection();
    if (in_array($role, $staffRoles, true)) {
        assert_students_in_scope($connection, $user, [$studentId]);
    }
    $studentStatement = $connection->prepare(
        'SELECT id, nama, no_ktm, asal, angkatan, semester_aktif
         FROM santri WHERE id = ? LIMIT 1'
    );
    $studentStatement->bind_param('i', $studentId);
    $studentStatement->execute();
    $student = $studentStatement->get_result()->fetch_assoc();
    $studentStatement->close();
    if ($student === null) {
        json_response(['success' => false, 'error' => 'Data santri tidak ditemukan.'], 404);
    }
    $student['id'] = (int) $student['id'];
    $student['semester_aktif'] = (int) $student['semester_aktif'];

    $summaryStatement = $connection->prepare(
        "SELECT COUNT(*) AS total,
                COUNT(DISTINCT DATE_FORMAT(h.occurred_on, '%Y-%m')) AS bulan,
                COUNT(DISTINCT h.activity_name) AS jenis_aktivitas
         FROM student_activity_history h
         INNER JOIN student_activity_imports i ON i.id = h.import_id AND i.status = 'completed'
         WHERE h.santri_id = ?"
    );
    $summaryStatement->bind_param('i', $studentId);
    $summaryStatement->execute();
    $summary = $summaryStatement->get_result()->fetch_assoc() ?: [];
    $summaryStatement->close();
    foreach (['total', 'bulan', 'jenis_aktivitas'] as $field) {
        $summary[$field] = (int) ($summary[$field] ?? 0);
    }

    $historyStatement = $connection->prepare(
        "SELECT i.id AS import_id, i.academic_year_start,
                DATE_FORMAT(h.occurred_on, '%Y-%m') AS bulan,
                h.activity_name,
                COUNT(*) AS total,
                GROUP_CONCAT(DAY(h.occurred_on) ORDER BY h.occurred_on, h.slot_number SEPARATOR ', ') AS tanggal
         FROM student_activity_history h
         INNER JOIN student_activity_imports i ON i.id = h.import_id AND i.status = 'completed'
         WHERE h.santri_id = ?
         GROUP BY i.id, i.academic_year_start, DATE_FORMAT(h.occurred_on, '%Y-%m'), h.activity_name
         ORDER BY bulan DESC, h.activity_name ASC"
    );
    $historyStatement->bind_param('i', $studentId);
    $historyStatement->execute();
    $history = $historyStatement->get_result()->fetch_all(MYSQLI_ASSOC);
    $historyStatement->close();
    foreach ($history as &$record) {
        foreach (['import_id', 'academic_year_start', 'total'] as $field) {
            $record[$field] = (int) $record[$field];
        }
    }
    unset($record);

    json_response([
        'success' => true,
        'santri' => $student,
        'ringkasan' => $summary,
        'riwayat' => $history,
    ]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Read student activity profile failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Profil santri belum dapat dimuat. Coba beberapa saat lagi.'], 503);
}
