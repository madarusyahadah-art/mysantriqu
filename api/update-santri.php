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
$nama = $data['nama'] ?? '';
$noKtm = trim((string) ($data['no_ktm'] ?? ''));
$asal = $data['asal'] ?? '';
$angkatan = $data['angkatan'] ?? '';
$semester = filter_var($data['semester_aktif'] ?? null, FILTER_VALIDATE_INT);

if (empty($id) || empty($nama) || strlen($noKtm) > 50 || $semester === false || $semester < 1 || $semester > 6) {
    json_response(["success" => false, "error" => "ID, nama, nomor KTM, atau semester tidak valid"], 400);
    exit;
}

$stmt = $conn->prepare("UPDATE santri SET nama = ?, no_ktm = ?, asal = ?, angkatan = ?, semester_aktif = ? WHERE id = ?");
$stmt->bind_param("ssssii", $nama, $noKtm, $asal, $angkatan, $semester, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>