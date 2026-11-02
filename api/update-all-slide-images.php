<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data tidak valid"], 400);
}

$image = trim((string) ($data['slide_image'] ?? ''));
if ($image === '') {
    json_response(["success" => false, "error" => "Gambar tidak boleh kosong"], 400);
}

$parsed = parse_url($image);
$isWebUrl = filter_var($image, FILTER_VALIDATE_URL) && in_array(strtolower($parsed['scheme'] ?? ''), ['http', 'https'], true);
$isLocalAsset = strpos($image, '/') === 0 && strpos($image, '//') !== 0 && strpos($image, '\\') === false;
$isUploadPath = preg_match('#^uploads/(?:slides|profile)/[a-f0-9]{48}\.(?:jpg|png|webp)$#', $image) === 1;
if (!$isWebUrl && !$isLocalAsset && !$isUploadPath) {
    json_response(["success" => false, "error" => "Gambar harus menggunakan URL HTTPS/HTTP, path aset lokal, atau file unggahan"], 400);
}
if (strpos($image, '..') !== false || strpos($image, '\\') !== false) {
    json_response(["success" => false, "error" => "Path gambar tidak valid"], 400);
}

// Kumpulkan gambar slide lama; jangan pernah menghapus unggahan profil di sini.
$oldImages = [];
$res = $conn->query("SELECT DISTINCT slide_image FROM kegiatan WHERE slide_image IS NOT NULL AND slide_image <> ''");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $old = (string) $row['slide_image'];
        if (preg_match('#^uploads/slides/[a-f0-9]{48}\.(?:jpg|png|webp)$#', $old) === 1) {
            $oldImages[] = $old;
        }
    }
    $res->free();
}

$stmt = $conn->prepare("UPDATE kegiatan SET slide_image = ? WHERE is_active = 1");
$stmt->bind_param("s", $image);
if (!$stmt->execute()) {
    $stmt->close();
    json_response(["success" => false, "error" => "Gagal menerapkan gambar ke semua slide"], 500);
}
$affected = $stmt->affected_rows;
$stmt->close();

// Hapus file hanya jika tidak ada kegiatan lain yang masih merujuknya.
foreach ($oldImages as $old) {
    if ($old === $image) continue;
    $referenceStmt = $conn->prepare("SELECT id FROM kegiatan WHERE slide_image = ? LIMIT 1");
    if (!$referenceStmt) {
        error_log("Gagal memeriksa referensi gambar slide lama.");
        continue;
    }
    $referenceStmt->bind_param("s", $old);
    if (!$referenceStmt->execute()) {
        $referenceStmt->close();
        error_log("Gagal memeriksa referensi gambar slide lama.");
        continue;
    }
    $stillReferenced = $referenceStmt->get_result()->num_rows > 0;
    $referenceStmt->close();
    if ($stillReferenced) continue;

    $oldPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old);
    if (is_file($oldPath) && !@unlink($oldPath)) {
        error_log("Gambar slide lama tidak dapat dihapus: " . basename($oldPath));
    }
}

json_response(["success" => true, "changed" => $affected]);
?>
