<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin', 'mahasantri']);
$type = $_GET['type'] ?? '';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($type, ['thesis', 'khs'], true) || $id === false || $id < 1) {
    json_response(["success" => false, "error" => "Dokumen tidak valid"], 400);
}

if ($type === 'thesis') {
    $stmt = $conn->prepare("SELECT santri_id, original_filename, file_path FROM student_thesis_submissions WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT santri_id, original_filename, file_path FROM student_khs_documents WHERE id = ?");
}
$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    $stmt->close();
    json_response(["success" => false, "error" => "Dokumen gagal dimuat"], 500);
}
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$document || ($user['role'] !== 'admin' && (int) $document['santri_id'] !== (int) ($user['santri_id'] ?? 0))) {
    json_response(["success" => false, "error" => "Dokumen tidak ditemukan"], 404);
}

$relativePath = (string) $document['file_path'];
if (preg_match('#^uploads/student-documents/[a-f0-9]{48}\.pdf$#', $relativePath) !== 1) {
    error_log("Path dokumen akademik tidak valid pada database.");
    json_response(["success" => false, "error" => "File dokumen tidak tersedia"], 500);
}
$filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
if (!is_file($filePath) || !is_readable($filePath)) {
    json_response(["success" => false, "error" => "File dokumen tidak ditemukan"], 404);
}

$filename = preg_replace('/[\r\n"\\\\]/', '_', (string) $document['original_filename']);
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="document.pdf"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
?>
