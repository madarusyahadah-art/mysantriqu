<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$angkatan = $_GET['angkatan'] ?? '';

if ($angkatan) {
    $stmt = $conn->prepare("SELECT id, nama, asal, angkatan, semester_aktif FROM santri WHERE angkatan = ? ORDER BY nama ASC");
    $stmt->bind_param("s", $angkatan);
} else {
    $stmt = $conn->prepare("SELECT id, nama, asal, angkatan, semester_aktif FROM santri ORDER BY nama ASC");
}

$stmt->execute();
$result = $stmt->get_result();

$santri = [];
while ($row = $result->fetch_assoc()) {
    $santri[] = $row;
}

echo json_encode(["success" => true, "santri" => $santri]);

$stmt->close();
$conn->close();
?>