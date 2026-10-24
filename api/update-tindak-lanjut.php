<?php
header("Access-Control-Allow-Origin: *");
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
$status = $data['status_tindak_lanjut'] ?? '';
$catatan_bk = $data['catatan_bk'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["error" => "ID pelanggaran wajib diisi"]);
    exit;
}

// Validasi status
$validStatus = ['belum', 'diproses', 'selesai'];
if (!empty($status) && !in_array($status, $validStatus)) {
    http_response_code(400);
    echo json_encode(["error" => "Status tidak valid"]);
    exit;
}

$sql = "UPDATE pelanggaran SET ";
$params = [];
$types = "";

if (!empty($status)) {
    $sql .= "status_tindak_lanjut = ?, ";
    $params[] = $status;
    $types .= "s";
}

if ($catatan_bk !== null) {
    $sql .= "catatan_bk = ?, ";
    $params[] = $catatan_bk;
    $types .= "s";
}

// Hapus koma dan spasi terakhir
$sql = rtrim($sql, ', ');
$sql .= " WHERE id = ?";
$params[] = $id;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>
