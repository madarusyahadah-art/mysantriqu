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

$user_id = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
$role = $data['role'] ?? '';
$santri_id = $data['santri_id'] ?? null;

if ($user_id === false || $user_id < 1 || !is_string($role) || $role === '') {
    json_response(["success" => false, "error" => "user_id dan role wajib diisi"], 400);
}

// Validasi role
$validRoles = ['admin', 'kajur', 'mahasantri', 'musyrif', "mas'ul"];
if (!in_array($role, $validRoles, true)) {
    json_response(["success" => false, "error" => "Role tidak valid"], 400);
}

$santriId = null;
if ($role === 'mahasantri') {
    $santriId = filter_var($santri_id, FILTER_VALIDATE_INT);
    if ($santriId === false || $santriId < 1) {
        json_response(["success" => false, "error" => "Pilih profil Mahasantri untuk role Mahasantri"], 400);
    }
}

$stmt = $conn->prepare("UPDATE users SET role = ?, santri_id = NULLIF(?, 0), role_selected = 1 WHERE id = ?");

if (!$stmt) {
    error_log('update-user-role.php prepare error: ' . $conn->error);
    json_response(["success" => false, "error" => "Role akun gagal diperbarui"], 500);
}

$santriIdParameter = $santriId ?? 0;
$stmt->bind_param("sii", $role, $santriIdParameter, $user_id);

if (!$stmt->execute()) {
    error_log('update-user-role.php execute error: ' . $stmt->error);
    $stmt->close();
    json_response(["success" => false, "error" => "Role akun gagal diperbarui"], 500);
}

$stmt->close();
json_response(["success" => true]);
?>
