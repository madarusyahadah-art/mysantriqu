<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    header('Allow: POST, PUT, PATCH');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola jenis pelanggaran.'], 403);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Data jenis pelanggaran tidak valid.'], 400);
}

$nameInput = $payload['nama'] ?? null;
if (!is_string($nameInput)) {
    json_response(['success' => false, 'error' => 'Nama jenis pelanggaran wajib diisi.'], 400);
}
$name = trim($nameInput);
$points = filter_var($payload['poin_default'] ?? null, FILTER_VALIDATE_INT);
$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
if ($name === '' || strlen($name) > 255 || $points === false || $points < 0 || $points > 1000) {
    json_response(['success' => false, 'error' => 'Nama jenis wajib diisi dan poin default harus antara 0 sampai 1000.'], 400);
}
if ($method !== 'POST' && ($id === false || $id === null || $id < 1)) {
    json_response(['success' => false, 'error' => 'ID jenis pelanggaran tidak valid.'], 400);
}

try {
    $connection = db_connection();
    if ($method === 'POST') {
        $statement = $connection->prepare(
            'INSERT INTO jenis_pelanggaran (nama, poin_default, is_active) VALUES (?, ?, 1)'
        );
        $statement->bind_param('si', $name, $points);
        $statement->execute();
        $newId = $statement->insert_id;
        $statement->close();
        json_response(['success' => true, 'id' => $newId], 201);
    }

    if ($method === 'PUT') {
        $statement = $connection->prepare(
            'UPDATE jenis_pelanggaran SET nama = ?, poin_default = ? WHERE id = ?'
        );
        $statement->bind_param('sii', $name, $points, $id);
        $statement->execute();
        $affected = $statement->affected_rows;
        $statement->close();
        if ($affected === 0) {
            $check = $connection->prepare('SELECT id FROM jenis_pelanggaran WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                json_response(['success' => false, 'error' => 'Jenis pelanggaran tidak ditemukan.'], 404);
            }
        }
        json_response(['success' => true]);
    }

    $active = filter_var($payload['is_active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($active === null) {
        json_response(['success' => false, 'error' => 'Status aktif jenis pelanggaran tidak valid.'], 400);
    }
    $statement = $connection->prepare('UPDATE jenis_pelanggaran SET is_active = ? WHERE id = ?');
    $activeValue = (int) $active;
    $statement->bind_param('ii', $activeValue, $id);
    $statement->execute();
    $affected = $statement->affected_rows;
    $statement->close();
    if ($affected === 0) {
        $check = $connection->prepare('SELECT id FROM jenis_pelanggaran WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            json_response(['success' => false, 'error' => 'Jenis pelanggaran tidak ditemukan.'], 404);
        }
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception $error) {
    error_log('Manage violation type failed: ' . $error->getMessage());
    if ($error->getCode() === 1062) {
        json_response(['success' => false, 'error' => 'Nama jenis pelanggaran tersebut sudah digunakan.'], 409);
    }
    json_response(['success' => false, 'error' => 'Jenis pelanggaran gagal disimpan. Coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('Violation type database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}