<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/config.php';

$email = trim((string) getenv('MYSANTRIQU_ADMIN_EMAIL'));
$name = trim((string) getenv('MYSANTRIQU_ADMIN_NAME'));
$password = (string) getenv('MYSANTRIQU_ADMIN_PASSWORD');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || mb_strlen($name) > 255 ||
    strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set MYSANTRIQU_ADMIN_EMAIL, MYSANTRIQU_ADMIN_NAME, and a 12-72 character MYSANTRIQU_ADMIN_PASSWORD before running this command.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    fwrite(STDERR, "Admin password could not be secured.\n");
    exit(1);
}

$lockResult = $conn->query("SELECT GET_LOCK('mysantriqu_bootstrap_admin', 10) AS acquired");
$lockRow = $lockResult ? $lockResult->fetch_assoc() : null;
if ($lockResult) $lockResult->free();
if (!$lockRow || (int) $lockRow['acquired'] !== 1) {
    fwrite(STDERR, "Could not acquire the admin bootstrap lock. Try again.\n");
    exit(1);
}
$releaseBootstrapLock = static function () use ($conn) {
    if (!$conn->query("SELECT RELEASE_LOCK('mysantriqu_bootstrap_admin')")) {
        error_log("Admin bootstrap lock could not be released explicitly.");
    }
};

$adminCheck = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$adminCheck->execute();
$hasAdmin = $adminCheck->get_result()->num_rows > 0;
$adminCheck->close();
if ($hasAdmin) {
    $releaseBootstrapLock();
    fwrite(STDERR, "An admin account already exists. Bootstrap is only available before the first admin is created.\n");
    exit(1);
}

$stmt = $conn->prepare("
    INSERT INTO users (name, email, role, santri_id, role_selected, password_hash)
    VALUES (?, ?, 'admin', NULL, 1, ?)
");
$stmt->bind_param('sss', $name, $email, $passwordHash);
if (!$stmt->execute()) {
    $stmt->close();
    $releaseBootstrapLock();
    fwrite(STDERR, "Admin account could not be created. Check whether the email is already registered.\n");
    exit(1);
}

$stmt->close();
$releaseBootstrapLock();
$conn->close();
fwrite(STDOUT, "Initial admin account created successfully.\n");
