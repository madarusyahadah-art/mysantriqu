<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola kegiatan.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
    header('Allow: GET, POST, PUT, PATCH');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

function read_activity_request_data(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_response(['success' => false, 'error' => 'Data yang dikirim tidak valid.'], 400);
    }
    return $data;
}

function validate_activity_payload(array $data, bool $includeId): array
{
    $name = trim((string) ($data['nama_kegiatan'] ?? ''));
    $category = trim((string) ($data['kategori'] ?? ''));
    $frequency = trim((string) ($data['frekuensi'] ?? ''));
    $id = $includeId ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : null;
    $frequencies = ['harian', 'mingguan', 'bulanan'];

    if ($name === '' || strlen($name) > 255 || $category === '' || strlen($category) > 50
        || !in_array($frequency, $frequencies, true)
        || ($includeId && ($id === false || $id === null || $id < 1))) {
        json_response([
            'success' => false,
            'error' => 'Nama wajib diisi; kategori harus maksimal 50 karakter dan frekuensi harus valid.',
        ], 400);
    }

    return [$id, $name, $category, $frequency];
}

try {
    $connection = db_connection();

    if ($method === 'GET') {
        $result = $connection->query(
            'SELECT id, nama_kegiatan, kategori, frekuensi, is_active
             FROM kegiatan
             ORDER BY is_active DESC, nama_kegiatan ASC, id ASC'
        );
        $activities = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($activities as &$activity) {
            $activity['id'] = (int) $activity['id'];
            $activity['is_active'] = (bool) $activity['is_active'];
            $activity['frekuensi_label'] = ucfirst($activity['frekuensi']);
        }
        unset($activity);
        json_response(['success' => true, 'kegiatan' => $activities]);
    }

    $data = read_activity_request_data();
    if ($method === 'POST') {
        [, $name, $category, $frequency] = validate_activity_payload($data, false);
        $statement = $connection->prepare(
            'INSERT INTO kegiatan (nama_kegiatan, kategori, frekuensi)
             VALUES (?, ?, ?)'
        );
        $statement->bind_param('sss', $name, $category, $frequency);
        $statement->execute();
        $id = $statement->insert_id;
        $statement->close();
        json_response(['success' => true, 'id' => $id], 201);
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        json_response(['success' => false, 'error' => 'ID kegiatan tidak valid.'], 400);
    }

    if ($method === 'PATCH') {
        $active = $data['is_active'] ?? null;
        if (!in_array($active, [true, false, 0, 1, '0', '1'], true)) {
            json_response(['success' => false, 'error' => 'Status kegiatan tidak valid.'], 400);
        }
        $active = (int) filter_var($active, FILTER_VALIDATE_BOOLEAN);
        $statement = $connection->prepare('UPDATE kegiatan SET is_active = ? WHERE id = ?');
        $statement->bind_param('ii', $active, $id);
        $statement->execute();
        $updated = $statement->affected_rows;
        $statement->close();
        if ($updated === 0) {
            $check = $connection->prepare('SELECT id FROM kegiatan WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                json_response(['success' => false, 'error' => 'Data kegiatan tidak ditemukan.'], 404);
            }
        }
        json_response(['success' => true]);
    }

    [$id, $name, $category, $frequency] = validate_activity_payload($data, true);
    $statement = $connection->prepare(
        'UPDATE kegiatan
         SET nama_kegiatan = ?, kategori = ?, frekuensi = ?
         WHERE id = ?'
    );
    $statement->bind_param('sssi', $name, $category, $frequency, $id);
    $statement->execute();
    $updated = $statement->affected_rows;
    $statement->close();
    if ($updated === 0) {
        $check = $connection->prepare('SELECT id FROM kegiatan WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            json_response(['success' => false, 'error' => 'Data kegiatan tidak ditemukan.'], 404);
        }
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception $error) {
    error_log('Activity management request failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Permintaan data kegiatan gagal diproses. Silakan coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('Activity management database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}
