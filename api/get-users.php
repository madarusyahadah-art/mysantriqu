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

$stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.role, u.santri_id, s.nama AS santri_nama FROM users u LEFT JOIN santri s ON s.id = u.santri_id ORDER BY u.name ASC");
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$santriResult = $conn->query("SELECT id, nama, angkatan FROM santri ORDER BY nama ASC");
$santriOptions = $santriResult->fetch_all(MYSQLI_ASSOC);

echo json_encode(["success" => true, "users" => $users, "santri_options" => $santriOptions]);

$stmt->close();
$conn->close();
?>
