<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin']);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}

$nama = is_string($data['nama'] ?? null) ? trim($data['nama']) : '';
$mapel = is_string($data['mata_pelajaran'] ?? null) ? trim($data['mata_pelajaran']) : '';
$jabatan = is_string($data['jabatan'] ?? null) ? trim($data['jabatan']) : '';
$penugasan = $data['penugasan'] ?? [];
$roles = ['masul', 'musyrif', 'pengampu'];

if ($nama === '' || mb_strlen($nama) > 255 || mb_strlen($mapel) > 150 || mb_strlen($jabatan) > 150) {
    json_response(["success" => false, "error" => "Nama wajib diisi; panjang nama, mata pelajaran, atau jabatan melebihi batas"], 400);
}
if (!is_array($penugasan)) {
    json_response(["success" => false, "error" => "Format penugasan tidak valid"], 400);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO asatidz (nama, mata_pelajaran, jabatan) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama, $mapel, $jabatan);
    $stmt->execute();
    $asatidzId = $stmt->insert_id;
    $stmt->close();

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
        $insert->bind_param("iis", $kegiatanId, $asatidzId, $peran);
        $insert->execute();
    }
    $insert->close();
    $conn->commit();
    json_response(["success" => true, "id" => $asatidzId]);
} catch (Throwable $error) {
    $conn->rollback();
    $status = $error instanceof InvalidArgumentException ? 400 : 500;
    json_response(["success" => false, "error" => $status === 400 ? $error->getMessage() : "Data asatidz gagal disimpan"], $status);
}
