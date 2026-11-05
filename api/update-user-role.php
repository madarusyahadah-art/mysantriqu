<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$currentUser = current_user();
if ($currentUser === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($currentUser['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengubah pengaturan pengguna.'], 403);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PATCH') {
    header('Allow: PATCH');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(['success' => false, 'error' => 'Data yang dikirim tidak valid.'], 400);
}

$userId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$role = trim((string) ($data['role'] ?? ''));
$allowedRoles = ['admin', 'kajur', 'supervisor', 'musyrif', "mas'ul", 'mahasantri', 'santri'];
$studentRoles = ['mahasantri', 'santri'];
$studentId = null;
if (in_array($role, $studentRoles, true)) {
    $studentId = filter_var($data['santri_id'] ?? null, FILTER_VALIDATE_INT);
}

if ($userId === false || $userId === null || $userId < 1
    || !in_array($role, $allowedRoles, true)
    || (in_array($role, $studentRoles, true) && ($studentId === false || $studentId === null || $studentId < 1))) {
    json_response(['success' => false, 'error' => 'ID akun, role, atau hubungan data santri tidak valid.'], 400);
}
if ((int) ($currentUser['id'] ?? 0) === $userId) {
    json_response(['success' => false, 'error' => 'Role atau hubungan akun Anda sendiri tidak dapat diubah dari halaman ini.'], 403);
}

$studentId = in_array($role, $studentRoles, true) ? (int) $studentId : null;

try {
    $connection = db_connection();
    $connection->begin_transaction();
    $transactionStarted = true;

    $adminResult = $connection->query("SELECT id FROM users WHERE role = 'admin' FOR UPDATE");
    $adminIds = $adminResult->fetch_all(MYSQLI_ASSOC);

    $accountCheck = $connection->prepare('SELECT id, role FROM users WHERE id = ? FOR UPDATE');
    $accountCheck->bind_param('i', $userId);
    $accountCheck->execute();
    $account = $accountCheck->get_result()->fetch_assoc();
    $accountCheck->close();
    if (!$account) {
        $connection->rollback();
        json_response(['success' => false, 'error' => 'Akun tidak ditemukan.'], 404);
    }

    if (strtolower((string) $account['role']) === 'admin' && $role !== 'admin' && count($adminIds) <= 1) {
        $connection->rollback();
        json_response(['success' => false, 'error' => 'Tidak dapat menurunkan role admin terakhir. Tetapkan admin lain terlebih dahulu.'], 409);
    }

    if ($studentId !== null) {
        $studentCheck = $connection->prepare('SELECT id FROM santri WHERE id = ? FOR UPDATE');
        $studentCheck->bind_param('i', $studentId);
        $studentCheck->execute();
        $studentExists = $studentCheck->get_result()->num_rows > 0;
        $studentCheck->close();
        if (!$studentExists) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Data santri yang dipilih tidak ditemukan.'], 404);
        }

        $linkCheck = $connection->prepare(
            'SELECT id FROM users WHERE santri_id = ? AND id <> ? LIMIT 1 FOR UPDATE'
        );
        $linkCheck->bind_param('ii', $studentId, $userId);
        $linkCheck->execute();
        $alreadyLinked = $linkCheck->get_result()->num_rows > 0;
        $linkCheck->close();
        if ($alreadyLinked) {
            $connection->rollback();
            json_response(['success' => false, 'error' => 'Data santri tersebut sudah terhubung dengan akun lain.'], 409);
        }
    }

    $update = $connection->prepare(
        'UPDATE users
         SET role = ?, santri_id = ?, role_selected = 1
         WHERE id = ?'
    );
    $update->bind_param('sii', $role, $studentId, $userId);
    $update->execute();
    $update->close();
    $connection->commit();
    $transactionStarted = false;

    json_response(['success' => true]);
} catch (mysqli_sql_exception $error) {
    if (isset($transactionStarted) && $transactionStarted) {
        $connection->rollback();
    }
    error_log('User role update failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Pengaturan akun gagal diperbarui. Silakan coba lagi.'], 500);
} catch (RuntimeException $error) {
    if (isset($transactionStarted) && $transactionStarted) {
        $connection->rollback();
    }
    error_log('User role update database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}