<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data login tidak valid"], 400);
}

$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255 || $password === '') {
    json_response(["success" => false, "error" => "Email atau kata sandi salah"], 401);
}

$stmt = $conn->prepare("SELECT id, name, email, photo, role, santri_id, role_selected, password_hash FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    json_response(["success" => false, "error" => "Email atau kata sandi salah. Akun Google harus masuk dengan tombol Google."], 401);
}

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->bind_param("si", $newHash, $user['id']);
    if (!$update->execute()) {
        $update->close();
        json_response(["success" => false, "error" => "Login gagal diselesaikan. Silakan coba lagi."], 500);
    }
    $update->close();
}

unset($user['password_hash']);
set_authenticated_session($user, !empty($data['remember_me']));

json_response([
    "success" => true,
    "onboarding_required" => empty($_SESSION['user']['role_selected']),
    "user" => $_SESSION['user'],
]);
?>
