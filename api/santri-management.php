<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
    json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola data santri.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
    header('Allow: GET, POST, PUT, DELETE');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

function read_request_data(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_response(['success' => false, 'error' => 'Data yang dikirim tidak valid.'], 400);
    }
    return $data;
}

function validate_student_payload(array $data, bool $includeId): array
{
    $name = trim((string) ($data['nama'] ?? ''));
    $ktm = trim((string) ($data['no_ktm'] ?? ''));
    $origin = trim((string) ($data['asal'] ?? ''));
    $cohort = trim((string) ($data['angkatan'] ?? ''));
    $semester = filter_var($data['semester_aktif'] ?? null, FILTER_VALIDATE_INT);
    $id = $includeId ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : null;

    if ($name === '' || strlen($name) > 255 || strlen($ktm) > 50 || strlen($origin) > 255
        || strlen($cohort) > 50 || $semester === false || $semester < 1 || $semester > 6
        || ($includeId && ($id === false || $id === null || $id < 1))) {
        json_response([
            'success' => false,
            'error' => 'Nama wajib diisi; panjang KTM, asal, angkatan, dan semester harus valid.',
        ], 400);
    }

    return [$id, $name, $ktm, $origin, $cohort, $semester];
}

try {
    $connection = db_connection();

    if ($method === 'GET') {
        $result = $connection->query(
            'SELECT id, nama, no_ktm, asal, angkatan, semester_aktif
             FROM santri
             ORDER BY nama ASC, id ASC'
        );
        $students = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($students as &$student) {
            $student['id'] = (int) $student['id'];
            $student['semester_aktif'] = (int) $student['semester_aktif'];
        }
        unset($student);
        json_response(['success' => true, 'santri' => $students]);
    }

    if ($method === 'POST') {
        [, $name, $ktm, $origin, $cohort, $semester] = validate_student_payload(read_request_data(), false);
        $statement = $connection->prepare(
            'INSERT INTO santri (nama, no_ktm, asal, angkatan, semester_aktif)
             VALUES (?, ?, ?, ?, ?)'
        );
        $statement->bind_param('ssssi', $name, $ktm, $origin, $cohort, $semester);
        $statement->execute();
        $id = $statement->insert_id;
        $statement->close();
        json_response(['success' => true, 'id' => $id], 201);
    }

    $data = read_request_data();
    if ($method === 'PUT') {
        [$id, $name, $ktm, $origin, $cohort, $semester] = validate_student_payload($data, true);
        $statement = $connection->prepare(
            'UPDATE santri
             SET nama = ?, no_ktm = ?, asal = ?, angkatan = ?, semester_aktif = ?
             WHERE id = ?'
        );
        $statement->bind_param('ssssii', $name, $ktm, $origin, $cohort, $semester, $id);
        $statement->execute();
        $updated = $statement->affected_rows;
        $statement->close();
        if ($updated === 0) {
            $check = $connection->prepare('SELECT id FROM santri WHERE id = ?');
            $check->bind_param('i', $id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            if (!$exists) {
                json_response(['success' => false, 'error' => 'Data santri tidak ditemukan.'], 404);
            }
        }
        json_response(['success' => true]);
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id < 1) {
        json_response(['success' => false, 'error' => 'ID santri tidak valid.'], 400);
    }
    $statement = $connection->prepare('DELETE FROM santri WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    $deleted = $statement->affected_rows;
    $statement->close();
    if ($deleted === 0) {
        json_response(['success' => false, 'error' => 'Data santri tidak ditemukan.'], 404);
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception $error) {
    error_log('Student management request failed: ' . $error->getMessage());
    if ($method === 'DELETE' && $error->getCode() === 1451) {
        json_response([
            'success' => false,
            'error' => 'Santri masih terhubung dengan akun atau catatan lain, sehingga tidak dapat dihapus.',
        ], 409);
    }
    json_response(['success' => false, 'error' => 'Permintaan data santri gagal diproses. Silakan coba lagi.'], 500);
} catch (RuntimeException $error) {
    error_log('Student management database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}
