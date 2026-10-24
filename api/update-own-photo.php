<?php
require_once __DIR__ . '/config.php';

$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

if (!isset($_FILES['photo']) || !is_array($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    json_response(["success" => false, "error" => "Pilih foto yang valid untuk diunggah"], 400);
}

$file = $_FILES['photo'];
if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
    json_response(["success" => false, "error" => "Ukuran foto maksimal 5 MB"], 400);
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
    json_response(["success" => false, "error" => "Format foto harus JPG, PNG, atau WebP yang valid"], 400);
}

$relativeDirectory = 'uploads/profile';
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    error_log("Direktori foto profil gagal dibuat.");
    json_response(["success" => false, "error" => "Penyimpanan foto belum tersedia"], 500);
}

$fileName = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
$destination = $directory . DIRECTORY_SEPARATOR . $fileName;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log("Foto profil gagal disimpan.");
    json_response(["success" => false, "error" => "Foto gagal disimpan. Silakan coba lagi."], 500);
}

$photoPath = $relativeDirectory . '/' . $fileName;
$stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
$stmt->bind_param("si", $photoPath, $user['id']);
if (!$stmt->execute() || $stmt->affected_rows !== 1) {
    $stmt->close();
    @unlink($destination);
    error_log("Foto profil gagal ditautkan ke akun pengguna.");
    json_response(["success" => false, "error" => "Foto gagal diperbarui. Silakan coba lagi."], 500);
}
$stmt->close();

$oldPhoto = (string) ($user['photo'] ?? '');
if (preg_match('#^uploads/profile/[a-f0-9]{48}\.(?:jpg|png|webp)$#', $oldPhoto)) {
    $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPhoto);
    if ($oldPath !== $destination && is_file($oldPath)) {
        @unlink($oldPath);
    }
}

$_SESSION['user']['photo'] = $photoPath;
json_response(["success" => true, "photo" => $photoPath]);
?>
