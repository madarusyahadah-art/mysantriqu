<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_response(["success" => false, "error" => "Pilih gambar yang valid untuk diunggah"], 400);
}

$file = $_FILES['image'];
if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
    json_response(["success" => false, "error" => "Ukuran gambar maksimal 5 MB"], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$imageInfo = @getimagesize($file['tmp_name']);
if (!isset($extensions[$mime]) || !$imageInfo || $imageInfo['mime'] !== $mime ||
    $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
    json_response(["success" => false, "error" => "Format gambar harus JPG, PNG, atau WebP yang valid"], 400);
}

$relativeDirectory = 'uploads/slides';
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'slides';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    error_log("Direktori gambar slide gagal dibuat.");
    json_response(["success" => false, "error" => "Penyimpanan gambar belum tersedia"], 500);
}

$fileName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
$destination = $directory . DIRECTORY_SEPARATOR . $fileName;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log("Gambar slide gagal disimpan.");
    json_response(["success" => false, "error" => "Gambar gagal disimpan. Silakan coba lagi."], 500);
}

$imagePath = $relativeDirectory . '/' . $fileName;

$oldImage = trim((string) ($_POST['old_image'] ?? ''));
if (preg_match('#^uploads/slides/[a-f0-9]{48}\.(?:jpg|png|webp)$#', $oldImage)) {
    $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldImage);
    if ($oldPath !== $destination && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

json_response(["success" => true, "image" => $imagePath]);
?>
