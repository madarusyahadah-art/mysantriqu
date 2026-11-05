<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['POST', 'PUT'], true)) {
    header('Allow: POST, PUT');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$user = current_user();
if ($user === null) {
    json_response(['success' => false, 'error' => 'Silakan masuk kembali.'], 401);
}
$role = strtolower((string) ($user['role'] ?? ''));
if (!in_array($role, ['admin', 'musyrif', 'supervisor', "mas'ul"], true)) {
    json_response(['success' => false, 'error' => 'Akun ini tidak memiliki akses untuk mencatat pelanggaran.'], 403);
}
$userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
if ($userId === false || $userId === null || $userId < 1) {
    json_response(['success' => false, 'error' => 'Akun tidak valid. Silakan masuk kembali.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Data pelanggaran tidak valid.'], 400);
}

$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
$studentId = filter_var($payload['santri_id'] ?? null, FILTER_VALIDATE_INT);
$typeIdInput = $payload['jenis_pelanggaran_id'] ?? null;
$typeId = $typeIdInput === null || $typeIdInput === ''
    ? null : filter_var($typeIdInput, FILTER_VALIDATE_INT);
$dateInput = $payload['tanggal'] ?? null;
$points = filter_var($payload['poin'] ?? null, FILTER_VALIDATE_INT);
$descriptionInput = $payload['keterangan'] ?? '';
$followupInput = $payload['status_tindak_lanjut'] ?? 'belum';
$counselingInput = $payload['catatan_bk'] ?? '';

if (($method === 'PUT' && ($id === false || $id === null || $id < 1))
    || $studentId === false || $studentId === null || $studentId < 1
    || ($typeId !== null && ($typeId === false || $typeId < 1))
    || !is_string($dateInput) || $points === false || $points < 0 || $points > 1000
    || !is_string($descriptionInput) || !is_string($followupInput) || !is_string($counselingInput)) {
    json_response(['success' => false, 'error' => 'Santri, jenis, tanggal, poin, atau isian pelanggaran tidak valid.'], 400);
}

$date = trim($dateInput);
date_default_timezone_set('Asia/Jakarta');
$dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || $dateValue === false || $dateValue->format('Y-m-d') !== $date
    || $date > date('Y-m-d')) {
    json_response(['success' => false, 'error' => 'Tanggal pelanggaran tidak valid atau belum terjadi.'], 400);
}

$description = trim($descriptionInput);
$followup = trim($followupInput);
$counselingNote = trim($counselingInput);
$allowedFollowup = ['belum', 'diproses', 'selesai'];
if (strlen($description) > 4000 || strlen($counselingNote) > 4000
    || !in_array($followup, $allowedFollowup, true)
    || ($followup === 'belum' && $counselingNote !== '')) {
    json_response(['success' => false, 'error' => 'Status tindak lanjut atau panjang keterangan tidak valid. Catatan BK hanya diisi untuk status diproses atau selesai.'], 400);
}

try {
    $connection = db_connection();
    $studentCheck = $connection->prepare('SELECT id FROM santri WHERE id = ? LIMIT 1');
    $studentCheck->bind_param('i', $studentId);
    $studentCheck->execute();
    $studentExists = $studentCheck->get_result()->num_rows > 0;
    $studentCheck->close();
    if (!$studentExists) {
        json_response(['success' => false, 'error' => 'Data santri tidak ditemukan.'], 404);
    }

    $typeName = '';
    if ($typeId !== null) {
        $typeCheckSql = 'SELECT nama FROM jenis_pelanggaran WHERE id = ? AND is_active = 1 LIMIT 1';
        $typeNameFromExistingRecord = false;
        if ($method === 'PUT') {
            $existingStatement = $connection->prepare(
                'SELECT p.jenis_pelanggaran_id, p.jenis_pelanggaran, jp.is_active
                 FROM pelanggaran p
                 LEFT JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
                 WHERE p.id = ? LIMIT 1'
            );
            $existingStatement->bind_param('i', $id);
            $existingStatement->execute();
            $existingRecord = $existingStatement->get_result()->fetch_assoc();
            $existingStatement->close();
            if ($existingRecord === null) {
                json_response(['success' => false, 'error' => 'Catatan pelanggaran tidak ditemukan.'], 404);
            }
            if ((int) ($existingRecord['jenis_pelanggaran_id'] ?? 0) === $typeId
                && !(bool) ($existingRecord['is_active'] ?? false)) {
                $typeName = (string) $existingRecord['jenis_pelanggaran'];
                $typeNameFromExistingRecord = true;
            }
        }

        if (!$typeNameFromExistingRecord) {
            $typeCheck = $connection->prepare($typeCheckSql);
            $typeCheck->bind_param('i', $typeId);
            $typeCheck->execute();
            $type = $typeCheck->get_result()->fetch_assoc();
            $typeCheck->close();
            if ($type === null) {
                json_response(['success' => false, 'error' => 'Jenis pelanggaran tidak ditemukan atau sudah nonaktif.'], 404);
            }
            $typeName = (string) $type['nama'];
        }
    } else {
        $legacyNameInput = $payload['jenis_pelanggaran'] ?? null;
        if (!is_string($legacyNameInput) || trim($legacyNameInput) === '' || strlen(trim($legacyNameInput)) > 255) {
            json_response(['success' => false, 'error' => 'Pilih jenis pelanggaran yang valid.'], 400);
        }
        $typeName = trim($legacyNameInput);
    }

    if ($method === 'POST') {
        $statement = $connection->prepare(
            'INSERT INTO pelanggaran
               (santri_id, jenis_pelanggaran_id, tanggal, jenis_pelanggaran, poin, keterangan, status_tindak_lanjut, catatan_bk, input_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->bind_param(
            'iississsi',
            $studentId,
            $typeId,
            $date,
            $typeName,
            $points,
            $description,
            $followup,
            $counselingNote,
            $userId
        );
        $statement->execute();
        $newId = $statement->insert_id;
        $statement->close();
        json_response(['success' => true, 'id' => $newId], 201);
    }

    $statement = $connection->prepare(
        'UPDATE pelanggaran
         SET santri_id = ?, jenis_pelanggaran_id = ?, tanggal = ?, jenis_pelanggaran = ?, poin = ?,
             keterangan = ?, status_tindak_lanjut = ?, catatan_bk = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );
    $statement->bind_param(
        'iississsi',
        $studentId,
        $typeId,
        $date,
        $typeName,
        $points,
        $description,
        $followup,
        $counselingNote,
        $id
    );
    $statement->execute();
    $affected = $statement->affected_rows;
    $statement->close();
    if ($affected === 0) {
        $check = $connection->prepare('SELECT id FROM pelanggaran WHERE id = ?');
        $check->bind_param('i', $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            json_response(['success' => false, 'error' => 'Catatan pelanggaran tidak ditemukan.'], 404);
        }
    }
    json_response(['success' => true]);
} catch (mysqli_sql_exception | RuntimeException $error) {
    error_log('Save violation failed: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Catatan pelanggaran gagal disimpan. Periksa koneksi lalu coba lagi.'], 503);
}