<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data pendaftaran tidak valid"], 400);
}

$name = trim((string) ($data['name'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if ($name === '' || strlen($name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    json_response(["success" => false, "error" => "Nama atau alamat email tidak valid"], 400);
}
if (strlen($password) < 10 || strlen($password) > 72) {
    json_response(["success" => false, "error" => "Kata sandi harus memiliki panjang 10 sampai 72 byte"], 400);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($existingUser) {
    json_response(["success" => false, "error" => "Email sudah terdaftar. Silakan login atau gunakan fitur Lupa Password."], 409);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    json_response(["success" => false, "error" => "Kata sandi gagal diproses"], 500);
}

$stmt = $conn->prepare("INSERT INTO users (email, name, role, role_selected, password_hash) VALUES (?, ?, 'mahasantri', 0, ?)");
$stmt->bind_param("sss", $email, $name, $passwordHash);
if (!$stmt->execute()) {
    $errno = $stmt->errno;
    $stmt->close();
    if ($errno === 1062) {
        json_response(["success" => false, "error" => "Email sudah terdaftar. Silakan login."], 409);
    }
    error_log("Pendaftaran user gagal: database error " . $errno);
    json_response(["success" => false, "error" => "Akun gagal dibuat. Silakan coba lagi."], 500);
}

$user = [
    'id' => $stmt->insert_id,
    'name' => $name,
    'email' => $email,
    'role' => 'mahasantri',
    'photo' => '',
    'santri_id' => null,
    'role_selected' => 0,
];
$stmt->close();
set_authenticated_session($user, false);

json_response(["success" => true, "user" => $_SESSION['user']]);
?>
