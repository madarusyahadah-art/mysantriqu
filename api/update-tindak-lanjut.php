<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin']);

if (!isset($conn)) {
    json_response(["success" => false, "error" => "Database connection not established"], 500);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || !$data) {
    json_response(["success" => false, "error" => "Invalid JSON data"], 400);
}

$id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
$status = $data['status_tindak_lanjut'] ?? null;
$catatan_bk = $data['catatan_bk'] ?? null;

if ($id === false || $id < 1) {
    json_response(["success" => false, "error" => "ID pelanggaran wajib diisi"], 400);
}

// Validasi status
$validStatus = ['belum', 'diproses', 'selesai'];
if ($status !== null && (!is_string($status) || !in_array($status, $validStatus, true))) {
    json_response(["success" => false, "error" => "Status tidak valid"], 400);
}

$updates = [];
$params = [];
$types = "";

if ($status !== null) {
    $updates[] = "status_tindak_lanjut = ?";
    $params[] = $status;
    $types .= "s";
}

if ($catatan_bk !== null) {
    if (!is_string($catatan_bk) || mb_strlen($catatan_bk) > 5000) {
        json_response(["success" => false, "error" => "Catatan tindak lanjut tidak valid"], 400);
    }
    $updates[] = "catatan_bk = ?";
    $params[] = $catatan_bk;
    $types .= "s";
}

if (!$updates) {
    json_response(["success" => false, "error" => "Tidak ada perubahan tindak lanjut yang dikirim"], 400);
}

$sql = "UPDATE pelanggaran SET " . implode(", ", $updates) . " WHERE id = ?";
$params[] = $id;
$types .= "i";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_response(["success" => false, "error" => "Perubahan tindak lanjut gagal disiapkan"], 500);
}
$stmt->bind_param($types, ...$params);

$executed = $stmt->execute();
$affectedRows = $stmt->affected_rows;
$stmt->close();
if (!$executed) {
    json_response(["success" => false, "error" => "Perubahan tindak lanjut gagal disimpan"], 500);
}
if ($affectedRows === 0) {
    $existsStmt = $conn->prepare("SELECT 1 FROM pelanggaran WHERE id = ?");
    $existsStmt->bind_param("i", $id);
    $existsStmt->execute();
    $exists = $existsStmt->get_result()->num_rows > 0;
    $existsStmt->close();
    if (!$exists) {
        json_response(["success" => false, "error" => "Pelanggaran tidak ditemukan"], 404);
    }
}

json_response(["success" => true]);
?>
