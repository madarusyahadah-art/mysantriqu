<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(['success' => false, 'error' => 'Data login tidak valid.'], 400);
}

$identifier = trim((string) ($data['identifier'] ?? ''));
$password = (string) ($data['password'] ?? '');
if ($identifier === '' || strlen($identifier) > 255 || $password === '' || strlen($password) > 1024) {
    json_response(['success' => false, 'error' => 'Email/nomor HP atau kata sandi salah.'], 401);
}

$email = strtolower($identifier);
$phone = filter_var($email, FILTER_VALIDATE_EMAIL) ? null : normalize_phone_number($identifier);
if ($phone === null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'error' => 'Masukkan email yang valid atau nomor HP Indonesia yang valid.'], 400);
}

try {
    $connection = db_connection();
    if ($phone !== null) {
        $statement = $connection->prepare(
            'SELECT id, name, email, phone_number, photo, role, santri_id, role_selected, password_hash
             FROM users
             WHERE phone_number = ? AND phone_verified_at IS NOT NULL
             LIMIT 1'
        );
        $statement->bind_param('s', $phone);
    } else {
        $statement = $connection->prepare(
            'SELECT id, name, email, phone_number, photo, role, santri_id, role_selected, password_hash
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $statement->bind_param('s', $email);
    }

    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();
} catch (Throwable $error) {
    error_log('Password login query failed: ' . $error->getMessage());
    json_response([
        'success' => false,
        'error' => 'Login belum dapat diproses. Pastikan skema database login sudah diterapkan.',
    ], 503);
}

if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    json_response(['success' => false, 'error' => 'Email/nomor HP atau kata sandi salah.'], 401);
}

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $connection->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->bind_param('si', $passwordHash, $user['id']);
    $update->execute();
    $update->close();
    $user['password_hash'] = $passwordHash;
}

unset($user['phone_number']);
set_authenticated_session($user, !empty($data['remember_me']));
json_response(['success' => true, 'user' => $_SESSION['user']]);
