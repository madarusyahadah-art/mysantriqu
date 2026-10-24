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

$nama = trim((string) ($data['nama'] ?? ''));
$noKtm = trim((string) ($data['no_ktm'] ?? ''));
$asal = trim((string) ($data['asal'] ?? ''));
$angkatan = trim((string) ($data['angkatan'] ?? ''));
$semester = filter_var($data['semester_aktif'] ?? 1, FILTER_VALIDATE_INT);

if ($nama === '' || mb_strlen($nama) > 255 || mb_strlen($asal) > 255 ||
    mb_strlen($angkatan) > 50 || strlen($noKtm) > 50 ||
    $semester === false || $semester < 1 || $semester > 6) {
    json_response(["success" => false, "error" => "Nama, nomor KTM, atau semester tidak valid"], 400);
}

$stmt = $conn->prepare("INSERT INTO santri (nama, no_ktm, asal, angkatan, semester_aktif) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    error_log('add-santri.php prepare error: ' . $conn->error);
    json_response(["success" => false, "error" => "Data Mahasantri gagal disiapkan"], 500);
}
$stmt->bind_param("ssssi", $nama, $noKtm, $asal, $angkatan, $semester);

if (!$stmt->execute()) {
    error_log('add-santri.php execute error: ' . $stmt->error);
    $stmt->close();
    json_response(["success" => false, "error" => "Data Mahasantri gagal disimpan"], 500);
}

$insertId = $stmt->insert_id;
$stmt->close();
$conn->close();
json_response(["success" => true, "id" => $insertId]);
?>
