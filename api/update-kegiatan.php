<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON data"]);
    exit;
}

$id = $data['id'] ?? '';
$nama_kegiatan = $data['nama_kegiatan'] ?? '';
$kategori = $data['kategori'] ?? 'wajib';
$frekuensi = $data['frekuensi'] ?? 'harian';
$is_active = $data['is_active'] ?? 1;
$semesterProvided = array_key_exists('semester_eligible', $data);
$semesters = $data['semester_eligible'] ?? [];
if (!is_array($semesters)) {
    json_response(["success" => false, "error" => "Daftar semester kegiatan tidak valid"], 400);
}
$semesters = array_values(array_unique(array_map('intval', $semesters)));
if (array_filter($semesters, static function ($semester) { return $semester < 1 || $semester > 6; })) {
    json_response(["success" => false, "error" => "Semester kegiatan tidak valid"], 400);
}

if (empty($id) || empty($nama_kegiatan)) {
    http_response_code(400);
    echo json_encode(["error" => "ID dan nama kegiatan wajib diisi"]);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE kegiatan SET nama_kegiatan = ?, kategori = ?, frekuensi = ?, is_active = ? WHERE id = ?");
    $stmt->bind_param("sssii", $nama_kegiatan, $kategori, $frekuensi, $is_active, $id);
    if (!$stmt->execute()) throw new RuntimeException("Kegiatan gagal diperbarui");
    $stmt->close();
    if ($semesterProvided) {
        $deleteStmt = $conn->prepare("DELETE FROM kegiatan_semester WHERE kegiatan_id = ?");
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $deleteStmt->close();
        if ($semesters) {
            $semesterStmt = $conn->prepare("INSERT INTO kegiatan_semester (kegiatan_id, semester) VALUES (?, ?)");
            foreach ($semesters as $semester) {
                $semesterStmt->bind_param("ii", $id, $semester);
                $semesterStmt->execute();
            }
            $semesterStmt->close();
        }
    }
    $conn->commit();
    json_response(["success" => true]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('update-kegiatan.php error: ' . $error->getMessage());
    json_response(["success" => false, "error" => "Kegiatan gagal diperbarui"], 500);
}

$conn->close();
?>