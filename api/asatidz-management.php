<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola data Asatidzah.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

function read_teacher_request_data(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_response(['success' => false, 'error' => 'Data yang dikirim tidak valid.'], 400);
    }
    return $data;
}

function validate_teacher_payload(array $data, bool $includeId): array
{
    $name = trim((string) ($data['nama'] ?? ''));
    $subject = trim((string) ($data['mata_pelajaran'] ?? ''));
    $position = trim((string) ($data['jabatan'] ?? ''));
    $id = $includeId ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : null;

    if ($name === '' || strlen($name) > 255 || strlen($subject) > 150 || strlen($position) > 150
        || ($includeId && ($id === false || $id === null || $id < 1))) {
        json_response([
            'success' => false,
            'error' => 'Nama wajib diisi; panjang mata pelajaran dan jabatan harus maksimal 150 karakter.',
        ], 400);
    }

    return [$id, $name, $subject, $position];
}

try {
    $connection = db_connection();

    if ($method === 'GET') {
        $result = $connection->query(
            'SELECT id, nama, mata_pelajaran, jabatan
             FROM asatidz
             ORDER BY nama ASC, id ASC'
        );
        $teachers = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($teachers as &$teacher) {
            $teacher['id'] = (int) $teacher['id'];
        }
        unset($teacher);
        json_response(['success' => true, 'asatidz' => $teachers]);
    }

    if ($method === 'POST') {
        [, $name, $subject, $position] = validate_teacher_payload(read_teacher_request_data(), false);
        $statement = $connection->prepare(
            'INSERT INTO asatidz (nama, mata_pelajaran, jabatan)
             VALUES (?, ?, ?)'
        );
        $statement->bind_param('sss', $name, $subject, $position);
        $statement->execute();
        $id = $statement->insert_id;
        $statement->close();
        json_response(['success' => true, 'id' => $id], 201);
    }

    $data = read_teacher_request_data();
    if ($method === 'PUT') {
        [$id, $name, $subject, $position] = validate_teacher_payload($data, true);
        $statement = $connection->prepare(
            'UPDATE asatidz
             SET nama = ?, mata_pelajaran = ?, jabatan = ?
             WHERE id = ?'
        );
        $statement->bind_param('sssi', $name, $subject, $position, $id);
        $statement->execute();
        $updated = $statement->affected_rows;
        $statement->close();
        if ($updated === 0) {
            $check = $connection->prepare('SELECT id FROM asatidz WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                json_response(['success' => false, 'error' => 'Data Asatidzah tidak ditemukan.'], 404);
            }
        }
        json_response(['success' => true]);
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        json_response(['success' => false, 'error' => 'ID Asatidzah tidak valid.'], 400);
    }
    $statement = $connection->prepare('DELETE FROM asatidz WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    $deleted = $statement->affected_rows;
    $statement->close();
    if ($deleted === 0) {
        json_response(['success' => false, 'error' => 'Data Asatidzah tidak ditemukan.'], 404);
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception $error) {
    error_log('Teacher management request failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Permintaan data Asatidzah gagal diproses. Silakan coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('Teacher management database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}
