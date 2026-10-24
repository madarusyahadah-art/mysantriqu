<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/config.php';
require_role(['admin']);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}
$action = $data['action'] ?? 'save';
if ($action === 'deactivate') {
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) json_response(["success" => false, "error" => "ID jenis pelanggaran tidak valid"], 400);
    $stmt = $conn->prepare("UPDATE jenis_pelanggaran SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if (!$stmt->affected_rows) {
        $stmt->close();
        json_response(["success" => false, "error" => "Jenis pelanggaran tidak ditemukan atau sudah nonaktif"], 404);
    }
    $stmt->close();
    json_response(["success" => true]);
}

$name = trim(is_string($data['nama'] ?? null) ? $data['nama'] : '');
$points = filter_var($data['poin_default'] ?? null, FILTER_VALIDATE_INT);
$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
if ($name === '' || mb_strlen($name) > 150 || $points === false || $points < 0 || $points > 100000) {
    json_response(["success" => false, "error" => "Nama atau poin default tidak valid"], 400);
}
try {
    if ($id) {
        $stmt = $conn->prepare("UPDATE jenis_pelanggaran SET nama = ?, poin_default = ?, is_active = 1 WHERE id = ?");
        $stmt->bind_param("sii", $name, $points, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO jenis_pelanggaran (nama, poin_default) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $points);
    }
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $error) {
    if ((int) $error->getCode() === 1062) {
        json_response(["success" => false, "error" => "Nama jenis pelanggaran sudah digunakan"], 409);
    }
    throw $error;
}
json_response(["success" => true]);
