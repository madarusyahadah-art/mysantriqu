<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin']);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$nama = is_string($data['nama'] ?? null) ? trim($data['nama']) : '';
$mapel = is_string($data['mata_pelajaran'] ?? null) ? trim($data['mata_pelajaran']) : '';
$jabatan = is_string($data['jabatan'] ?? null) ? trim($data['jabatan']) : '';
$penugasan = $data['penugasan'] ?? [];
$roles = ['masul', 'musyrif', 'pengampu'];

if (!$id || $nama === '' || mb_strlen($nama) > 255 || mb_strlen($mapel) > 150 || mb_strlen($jabatan) > 150) {
    json_response(["success" => false, "error" => "ID dan nama wajib valid; panjang data melebihi batas"], 400);
}
if (!is_array($penugasan)) {
    json_response(["success" => false, "error" => "Format penugasan tidak valid"], 400);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE asatidz SET nama = ?, mata_pelajaran = ?, jabatan = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nama, $mapel, $jabatan, $id);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare("SELECT id FROM asatidz WHERE id = ?");
        $check->bind_param("i", $id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            throw new InvalidArgumentException("Asatidz tidak ditemukan");
        }
    }
    $stmt->close();

    $delete = $conn->prepare("DELETE FROM kegiatan_asatidz WHERE asatidz_id = ?");
    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();

    $insert = $conn->prepare("INSERT INTO kegiatan_asatidz (kegiatan_id, asatidz_id, peran) VALUES (?, ?, ?)");
    foreach ($penugasan as $assignment) {
        if (!is_array($assignment)) {
            throw new InvalidArgumentException("Format penugasan tidak valid");
        }
        $kegiatanId = filter_var($assignment['kegiatan_id'] ?? null, FILTER_VALIDATE_INT);
        $peran = $assignment['peran'] ?? '';
        if (!$kegiatanId || !in_array($peran, $roles, true)) {
            throw new InvalidArgumentException("Kegiatan atau peran penugasan tidak valid");
        }
        $insert->bind_param("iis", $kegiatanId, $id, $peran);
        $insert->execute();
    }
    $insert->close();
    $conn->commit();
    json_response(["success" => true]);
} catch (Throwable $error) {
    $conn->rollback();
    $status = $error instanceof InvalidArgumentException ? 400 : 500;
    json_response(["success" => false, "error" => $status === 400 ? $error->getMessage() : "Data asatidz gagal diperbarui"], $status);
}
