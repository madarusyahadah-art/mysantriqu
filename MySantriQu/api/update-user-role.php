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

$user_id = $data['user_id'] ?? '';
$role = $data['role'] ?? '';
$santri_id = $data['santri_id'] ?? null;

if (empty($user_id) || empty($role)) {
    http_response_code(400);
    echo json_encode(["error" => "user_id dan role wajib diisi"]);
    exit;
}

// Validasi role
$validRoles = ['admin', 'kajur', 'mahasantri', 'musyrif', "mas'ul"];
if (!in_array($role, $validRoles, true)) {
    json_response(["success" => false, "error" => "Role tidak valid"], 400);
}

$santri_id = ($role === 'mahasantri' && $santri_id !== '') ? (int) $santri_id : null;
if ($role === 'mahasantri' && $santri_id === null) {
    json_response(["success" => false, "error" => "Pilih profil Mahasantri untuk role Mahasantri"], 400);
}

$stmt = $conn->prepare("UPDATE users SET role = ?, santri_id = ?, role_selected = 1 WHERE id = ?");

if (!$stmt) {
    echo json_encode(["success" => false, "error" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("sii", $role, $santri_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$stmt->close();
$conn->close();
?>
