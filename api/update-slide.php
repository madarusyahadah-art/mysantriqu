<?php
require_once __DIR__ . '/config.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data slide tidak valid"], 400);
}

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$title = trim((string) ($data['slide_title'] ?? ''));
$description = trim((string) ($data['slide_description'] ?? ''));
$image = trim((string) ($data['slide_image'] ?? ''));
$order = filter_var($data['slide_order'] ?? null, FILTER_VALIDATE_INT);

if (!$id || $order === false || $order < 0 || $title === '' || mb_strlen($title) > 100 || mb_strlen($description) > 1000 || strlen($image) > 500) {
    json_response(["success" => false, "error" => "Judul, urutan, atau isi slide tidak valid"], 400);
}

if ($image !== '') {
    $parsed = parse_url($image);
    $isWebUrl = filter_var($image, FILTER_VALIDATE_URL) && in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true);
    $isLocalAsset = strpos($image, '/') === 0 && strpos($image, '//') !== 0 && strpos($image, '\\') === false;
    if (!$isWebUrl && !$isLocalAsset) {
        json_response(["success" => false, "error" => "Gambar harus menggunakan URL HTTPS/HTTP atau path aset lokal"], 400);
    }
}

$stmt = $conn->prepare("UPDATE kegiatan SET slide_title = ?, slide_description = ?, slide_image = ?, slide_order = ? WHERE id = ?");
$stmt->bind_param("sssii", $title, $description, $image, $order, $id);
if (!$stmt->execute()) {
    $stmt->close();
    json_response(["success" => false, "error" => "Perubahan slide gagal disimpan"], 500);
}
$affected = $stmt->affected_rows;
$stmt->close();

$check = $conn->prepare("SELECT id FROM kegiatan WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();
if (!$exists) {
    json_response(["success" => false, "error" => "Kegiatan untuk slide tidak ditemukan"], 404);
}

json_response(["success" => true, "changed" => $affected > 0]);
?>
