<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'DELETE') {
    header('Allow: DELETE');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat menghapus catatan pelanggaran.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
$id = is_array($payload) ? filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT) : false;
if ($id === false || $id === null || $id < 1) {
    json_response(['success' => false, 'error' => 'ID catatan pelanggaran tidak valid.'], 400);
}

try {
    $connection = db_connection();
    $recordStatement = $connection->prepare(
        'SELECT absensi_id FROM pelanggaran WHERE id = ? LIMIT 1'
    );
    $recordStatement->bind_param('i', $id);
    $recordStatement->execute();
    $record = $recordStatement->get_result()->fetch_assoc();
    $recordStatement->close();
    if ($record === null) {
        json_response(['success' => false, 'error' => 'Catatan pelanggaran tidak ditemukan.'], 404);
    }
    if ($record['absensi_id'] !== null) {
        json_response([
            'success' => false,
            'error' => 'Catatan alpa otomatis mengikuti status absensi. Perbarui statusnya melalui halaman Input Kehadiran.',
        ], 409);
    }
    $statement = $connection->prepare('DELETE FROM pelanggaran WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    $deleted = $statement->affected_rows;
    $statement->close();
    if ($deleted === 0) {
        json_response(['success' => false, 'error' => 'Catatan pelanggaran tidak ditemukan.'], 404);
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Delete violation failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Catatan pelanggaran gagal dihapus. Coba lagi.'], 503);
}
