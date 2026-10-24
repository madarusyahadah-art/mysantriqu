<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$user = require_login();

if (!empty($user['role_selected'])) {
    json_response(["success" => false, "error" => "Role akun sudah ditentukan"], 409);
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$role = $data['role'] ?? '';
if ($role !== 'mahasantri') {
    json_response(["success" => false, "error" => "Pendaftar baru hanya dapat memilih role Mahasantri. Hubungi admin untuk role lainnya."], 400);
}

$stmt = $conn->prepare("UPDATE users SET role = ?, role_selected = 1 WHERE id = ? AND role_selected = 0");
$stmt->bind_param("si", $role, $user['id']);
if (!$stmt->execute() || $stmt->affected_rows !== 1) {
    $error = $stmt->error ?: "Role akun gagal disimpan";
    $stmt->close();
    json_response(["success" => false, "error" => $error], 500);
}
$stmt->close();

$_SESSION['user']['role'] = $role;
$_SESSION['user']['role_selected'] = 1;

json_response(["success" => true, "role" => $role]);
?>
