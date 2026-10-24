<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data reset tidak valid"], 400);
}

$token = (string) ($data['token'] ?? '');
$password = (string) ($data['password'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    json_response(["success" => false, "error" => "Link reset tidak valid atau sudah kedaluwarsa"], 400);
}
if (strlen($password) < 10 || strlen($password) > 72) {
    json_response(["success" => false, "error" => "Kata sandi harus memiliki panjang 10 sampai 72 byte"], 400);
}

$tokenHash = hash('sha256', $token);
$stmt = $conn->prepare("SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$reset = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$reset) {
    json_response(["success" => false, "error" => "Link reset tidak valid atau sudah kedaluwarsa"], 400);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    json_response(["success" => false, "error" => "Kata sandi gagal diproses"], 500);
}

$conn->begin_transaction();
$lock = $conn->prepare("SELECT id FROM password_reset_tokens WHERE id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE");
$lock->bind_param("is", $reset['id'], $tokenHash);
$lock->execute();
$validReset = $lock->get_result()->fetch_assoc();
$lock->close();
if (!$validReset) {
    $conn->rollback();
    json_response(["success" => false, "error" => "Link reset sudah digunakan atau kedaluwarsa"], 409);
}

$updateUser = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$updateUser->bind_param("si", $passwordHash, $reset['user_id']);
if (!$updateUser->execute() || $updateUser->affected_rows !== 1) {
    $updateUser->close();
    $conn->rollback();
    json_response(["success" => false, "error" => "Kata sandi gagal diperbarui"], 500);
}
$updateUser->close();

$used = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
$used->bind_param("i", $reset['id']);
if (!$used->execute()) {
    $used->close();
    $conn->rollback();
    json_response(["success" => false, "error" => "Link reset gagal diproses"], 500);
}
$used->close();

$cleanup = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?");
$cleanup->bind_param("ii", $reset['user_id'], $reset['id']);
if (!$cleanup->execute()) {
    $cleanup->close();
    $conn->rollback();
    json_response(["success" => false, "error" => "Reset kata sandi gagal diselesaikan"], 500);
}
$cleanup->close();
$conn->commit();

json_response(["success" => true, "message" => "Kata sandi berhasil diubah. Silakan login."]);
?>
