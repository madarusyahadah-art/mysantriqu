<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
}

$mailFrom = trim((string) (getenv('MAIL_FROM') ?: ''));
if (!function_exists('mail') || $mailFrom === '' || !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
    error_log('Reset password belum dapat mengirim email: fungsi mail atau MAIL_FROM belum dikonfigurasi.');
    json_response([
        "success" => false,
        "error" => "Email reset belum dikonfigurasi. Admin perlu mengatur MAIL_FROM dan layanan SMTP/mail pada server.",
    ], 503);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim((string) (is_array($data) ? ($data['email'] ?? '') : '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    json_response(["success" => false, "error" => "Masukkan alamat email yang valid"], 400);
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        error_log('Reset password ditolak karena HTTP_HOST tidak valid.');
        json_response(["success" => false, "error" => "Alamat aplikasi tidak valid untuk membuat link reset"], 500);
    }
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/forgot-password.php';
    $apiPos = strpos($scriptName, '/api/');
    $basePath = $apiPos === false ? '' : substr($scriptName, 0, $apiPos);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocol = ($https || !in_array(explode(':', $host)[0], ['localhost', '127.0.0.1'], true)) ? 'https' : 'http';
    $configuredBaseUrl = trim((string) (getenv('APP_BASE_URL') ?: ''));
    $baseUrl = $configuredBaseUrl !== ''
        ? rtrim($configuredBaseUrl, '/')
        : $protocol . '://' . $host . $basePath;

    if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
        error_log('Reset password gagal karena APP_BASE_URL tidak valid.');
        json_response(["success" => false, "error" => "APP_BASE_URL belum dikonfigurasi dengan benar"], 500);
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $userId = (int) $user['id'];
    $conn->begin_transaction();
    $delete = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
    $delete->bind_param("i", $userId);
    if (!$delete->execute()) {
        $delete->close();
        $conn->rollback();
        json_response(["success" => false, "error" => "Link reset gagal dibuat"], 500);
    }
    $delete->close();

    $insert = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $insert->bind_param("is", $userId, $tokenHash);
    if (!$insert->execute()) {
        $insert->close();
        $conn->rollback();
        error_log('Pembuatan token reset password gagal.');
        json_response(["success" => false, "error" => "Link reset gagal dibuat"], 500);
    }
    $insert->close();
    $conn->commit();

    $resetUrl = $baseUrl . '/reset-password.html?token=' . urlencode($token);
    $subject = 'Atur ulang kata sandi MySantriQu';
    $body = "Kami menerima permintaan untuk mengatur ulang kata sandi akun ini.\r\n\r\n"
        . "Gunakan link berikut dalam 1 jam:\r\n" . $resetUrl . "\r\n\r\n"
        . "Jika Anda tidak meminta reset, abaikan email ini.";
    $headers = "From: MySantriQu <" . $mailFrom . ">\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "X-Mailer: PHP/" . PHP_VERSION;
    if (!mail($email, $subject, $body, $headers)) {
        $deleteToken = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $deleteToken->bind_param("i", $userId);
        $deleteToken->execute();
        $deleteToken->close();
        error_log('Mail server menolak email reset password.');
        json_response(["success" => false, "error" => "Email reset gagal dikirim. Periksa konfigurasi SMTP/mail server."], 503);
    }
}

json_response([
    "success" => true,
    "message" => "Jika email terdaftar, link reset akan dikirim dan berlaku selama 1 jam.",
]);
?>
