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

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$nama_kegiatan = $data['nama_kegiatan'] ?? '';
$kategori = $data['kategori'] ?? 'wajib';
$frekuensi = $data['frekuensi'] ?? 'harian';
$is_active = $data['is_active'] ?? 1;
$validCategories = ['wajib', 'sunnah', 'opsional'];
$validFrequencies = ['harian', 'mingguan', 'bulanan'];
if (!in_array($kategori, $validCategories, true) ||
    !in_array($frekuensi, $validFrequencies, true) ||
    !in_array($is_active, [0, 1, '0', '1'], true)) {
    json_response(["success" => false, "error" => "Kategori, frekuensi, atau status kegiatan tidak valid"], 400);
}
$is_active = (int) $is_active;
$semesterProvided = array_key_exists('semester_eligible', $data);
$semesters = $data['semester_eligible'] ?? [];
if (!is_array($semesters)) {
    json_response(["success" => false, "error" => "Daftar semester kegiatan tidak valid"], 400);
}
$semesters = array_values(array_unique(array_map('intval', $semesters)));
if (array_filter($semesters, static function ($semester) { return $semester < 1 || $semester > 6; })) {
    json_response(["success" => false, "error" => "Semester kegiatan tidak valid"], 400);
}

if ($id === false || $id < 1 || empty($nama_kegiatan)) {
    http_response_code(400);
    echo json_encode(["error" => "ID dan nama kegiatan wajib diisi"]);
    exit;
}

$conn->begin_transaction();
try {
    $existsStmt = $conn->prepare("SELECT id FROM kegiatan WHERE id = ? FOR UPDATE");
    $existsStmt->bind_param("i", $id);
    if (!$existsStmt->execute()) throw new RuntimeException("Kegiatan gagal diperiksa");
    $exists = $existsStmt->get_result()->num_rows > 0;
    $existsStmt->close();
    if (!$exists) {
        $conn->rollback();
        json_response(["success" => false, "error" => "Kegiatan tidak ditemukan"], 404);
    }

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