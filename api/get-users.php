<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat melihat daftar pengguna.'], 403);
}

try {
    $connection = db_connection();
    $userResult = $connection->query(
        'SELECT u.id, u.name, u.email, u.role, u.santri_id,
                s.nama AS santri_nama, s.angkatan
         FROM users u
         LEFT JOIN santri s ON s.id = u.santri_id
         ORDER BY u.name ASC, u.id ASC'
    );
    $users = $userResult->fetch_all(MYSQLI_ASSOC);
    foreach ($users as &$account) {
        $account['id'] = (int) $account['id'];
        $account['santri_id'] = $account['santri_id'] === null ? null : (int) $account['santri_id'];
    }
    unset($account);

    $studentResult = $connection->query(
        'SELECT id, nama, angkatan
         FROM santri
         ORDER BY nama ASC, id ASC'
    );
    $students = $studentResult->fetch_all(MYSQLI_ASSOC);
    foreach ($students as &$student) {
        $student['id'] = (int) $student['id'];
    }
    unset($student);

    $assignmentResult = $connection->query(
        'SELECT user_id, santri_id FROM staff_santri_assignments ORDER BY user_id, santri_id'
    );
    $assignments = [];
    foreach ($assignmentResult->fetch_all(MYSQLI_ASSOC) as $assignment) {
        $userId = (int) $assignment['user_id'];
        $assignments[$userId][] = (int) $assignment['santri_id'];
    }
    foreach ($users as &$account) {
        $account['assigned_santri_ids'] = $assignments[(int) $account['id']] ?? [];
    }
    unset($account);

    json_response(['success' => true, 'users' => $users, 'santri' => $students]);
} catch (mysqli_sql_exception $error) {
    error_log('User list request failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Daftar pengguna gagal dimuat. Silakan coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('User list database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}