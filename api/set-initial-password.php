<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $pending = $_SESSION['pending_google_account'] ?? null;
    if (!is_array($pending) || empty($pending['email']) || time() - (int) ($pending['verified_at'] ?? 0) > 900) {
        unset($_SESSION['pending_google_account']);
        json_response(["success" => false, "error" => "Verifikasi Google sudah kedaluwarsa. Silakan mulai kembali melalui Google."], 401);
    }

    json_response([
        "success" => true,
        "account" => [
            "email" => $pending['email'],
            "name" => $pending['name'] ?? $pending['email'],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pending = $_SESSION['pending_google_account'] ?? null;
if (!is_array($pending) || empty($pending['email']) || time() - (int) ($pending['verified_at'] ?? 0) > 900) {
    unset($_SESSION['pending_google_account']);
    json_response(["success" => false, "error" => "Verifikasi Google sudah kedaluwarsa. Silakan mulai kembali melalui Google."], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$password = (string) (is_array($data) ? ($data['password'] ?? '') : '');
if (strlen($password) < 10 || strlen($password) > 72) {
    json_response(["success" => false, "error" => "Kata sandi harus memiliki panjang 10 sampai 72 byte"], 400);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    json_response(["success" => false, "error" => "Kata sandi gagal diproses"], 500);
}

$email = strtolower(trim($pending['email']));
$name = trim((string) ($pending['name'] ?? $email));
$photo = (string) ($pending['photo'] ?? '');
$conn->begin_transaction();

$stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email = ? FOR UPDATE");
$stmt->bind_param("s", $email);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->rollback();
    json_response(["success" => false, "error" => "Akun gagal diperiksa. Silakan coba lagi."], 500);
}
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingUser && !empty($existingUser['password_hash'])) {
    $conn->rollback();
    unset($_SESSION['pending_google_account']);
    json_response(["success" => false, "error" => "Akun ini sudah memiliki kata sandi. Silakan login menggunakan email dan kata sandi aplikasi."], 409);
}

if ($existingUser) {
    $update = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND (password_hash IS NULL OR password_hash = '')");
    $update->bind_param("si", $passwordHash, $existingUser['id']);
    if (!$update->execute() || $update->affected_rows !== 1) {
        $update->close();
        $conn->rollback();
        json_response(["success" => false, "error" => "Kata sandi gagal disimpan. Silakan coba lagi."], 500);
    }
    $update->close();
} else {
    $insert = $conn->prepare("INSERT INTO users (name, email, photo, role, role_selected, password_hash) VALUES (?, ?, ?, 'mahasantri', 1, ?)");
    $insert->bind_param("ssss", $name, $email, $photo, $passwordHash);
    if (!$insert->execute()) {
        $errno = $insert->errno;
        $insert->close();
        $conn->rollback();
        if ($errno === 1062) {
            json_response(["success" => false, "error" => "Akun sudah terdaftar. Silakan kembali ke halaman login."], 409);
        }
        error_log("Pembuatan akun Google gagal: database error " . $errno);
        json_response(["success" => false, "error" => "Akun gagal dibuat. Silakan coba lagi."], 500);
    }
    $insert->close();
}

if (!$conn->commit()) {
    $conn->rollback();
    json_response(["success" => false, "error" => "Akun gagal disimpan. Silakan coba lagi."], 500);
}

unset($_SESSION['pending_google_account']);
json_response(["success" => true, "email" => $email]);
?>
