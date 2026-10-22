<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin']);

$data = json_decode(file_get_contents('php://input'), true);
$id = filter_var(is_array($data) ? ($data['id'] ?? null) : null, FILTER_VALIDATE_INT);
if (!$id) {
    json_response(["success" => false, "error" => "ID asatidz wajib valid"], 400);
}

$stmt = $conn->prepare("DELETE FROM asatidz WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();
if (!$deleted) {
    json_response(["success" => false, "error" => "Asatidz tidak ditemukan"], 404);
}
json_response(["success" => true]);
