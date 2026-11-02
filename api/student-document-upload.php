<?php
require_once __DIR__ . '/config.php';

$user = require_role(['admin', 'mahasantri']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$type = $_POST['type'] ?? '';
if (!in_array($type, ['thesis', 'khs'], true)) {
    json_response(["success" => false, "error" => "Jenis dokumen tidak valid"], 400);
}
if (($type === 'thesis' && $user['role'] !== 'mahasantri') ||
    ($type === 'khs' && $user['role'] !== 'admin')) {
    json_response(["success" => false, "error" => "Anda tidak memiliki akses untuk mengunggah dokumen ini"], 403);
}

if (!isset($_FILES['document']) || !is_array($_FILES['document']) ||
    $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    json_response(["success" => false, "error" => "Pilih file PDF yang valid"], 400);
}
$file = $_FILES['document'];
if ((int) $file['size'] < 1 || (int) $file['size'] > 10 * 1024 * 1024 ||
    !is_uploaded_file($file['tmp_name'])) {
    json_response(["success" => false, "error" => "Ukuran PDF maksimal 10 MB"], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
if ($finfo->file($file['tmp_name']) !== 'application/pdf') {
    json_response(["success" => false, "error" => "Dokumen harus berformat PDF"], 400);
}
$handle = fopen($file['tmp_name'], 'rb');
$signature = $handle ? fread($handle, 5) : false;
if ($handle) fclose($handle);
if ($signature !== '%PDF-') {
    json_response(["success" => false, "error" => "File PDF tidak valid"], 400);
}

$originalName = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'dokumen.pdf')));
$originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName);
if (!is_string($originalName) || $originalName === '' || mb_strlen($originalName) > 255) {
    json_response(["success" => false, "error" => "Nama file tidak valid"], 400);
}

$santriId = 0;
$title = '';
$semester = 0;
if ($type === 'thesis') {
    if (empty($user['santri_id'])) {
        json_response(["success" => false, "error" => "Profil Mahasantri belum dihubungkan oleh admin"], 403);
    }
    $santriId = (int) $user['santri_id'];
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 255) {
        json_response(["success" => false, "error" => "Judul skripsi wajib diisi (maksimal 255 karakter)"], 400);
    }
} else {
    $santriId = filter_var($_POST['santri_id'] ?? null, FILTER_VALIDATE_INT);
    $semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);
    if ($santriId === false || $santriId < 1 || $semester === false || $semester < 1 || $semester > 6) {
        json_response(["success" => false, "error" => "Mahasantri atau semester tidak valid"], 400);
    }
}

$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student-documents';
if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
    error_log("Direktori dokumen akademik gagal dibuat.");
    json_response(["success" => false, "error" => "Penyimpanan dokumen belum tersedia"], 500);
}

$fileName = bin2hex(random_bytes(24)) . '.pdf';
$destination = $directory . DIRECTORY_SEPARATOR . $fileName;
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    error_log("Dokumen akademik gagal disimpan.");
    json_response(["success" => false, "error" => "Dokumen gagal disimpan. Silakan coba lagi."], 500);
}
$relativePath = 'uploads/student-documents/' . $fileName;

if ($type === 'thesis') {
    $stmt = $conn->prepare("
        INSERT INTO student_thesis_submissions (santri_id, title, original_filename, file_path)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        @unlink($destination);
        json_response(["success" => false, "error" => "Pengumpulan skripsi gagal disiapkan"], 500);
    }
    $stmt->bind_param("isss", $santriId, $title, $originalName, $relativePath);
} else {
    $existingStmt = $conn->prepare("
        SELECT file_path FROM student_khs_documents WHERE santri_id = ? AND semester = ?
    ");
    $existingStmt->bind_param("ii", $santriId, $semester);
    if (!$existingStmt->execute()) {
        $existingStmt->close();
        @unlink($destination);
        json_response(["success" => false, "error" => "KHS sebelumnya gagal diperiksa"], 500);
    }
    $existingRow = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();
    $oldPath = $existingRow['file_path'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO student_khs_documents (santri_id, semester, original_filename, file_path, uploaded_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          original_filename = VALUES(original_filename),
          file_path = VALUES(file_path),
          uploaded_by = VALUES(uploaded_by),
          uploaded_at = CURRENT_TIMESTAMP
    ");
    if (!$stmt) {
        @unlink($destination);
        json_response(["success" => false, "error" => "Penyimpanan KHS gagal disiapkan"], 500);
    }
    $stmt->bind_param("iissi", $santriId, $semester, $originalName, $relativePath, $user['id']);
}

if (!$stmt || !$stmt->execute()) {
    if ($stmt) $stmt->close();
    @unlink($destination);
    error_log("Dokumen akademik gagal ditautkan ke database.");
    json_response(["success" => false, "error" => "Dokumen gagal disimpan ke database"], 500);
}
$id = $stmt->insert_id;
$stmt->close();

if ($type === 'khs' && !empty($oldPath) &&
    preg_match('#^uploads/student-documents/[a-f0-9]{48}\.pdf$#', $oldPath) === 1) {
    $oldFullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
    if (is_file($oldFullPath) && !@unlink($oldFullPath)) {
        error_log("KHS lama tidak dapat dihapus: " . basename($oldFullPath));
    }
}

json_response(["success" => true, "id" => $id]);
?>
