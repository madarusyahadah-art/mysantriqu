<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function login_base_url(): string
{
    $appUrl = getenv('APP_URL');
    if ($appUrl) {
        return rtrim($appUrl, '/');
    }

    $rawHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (strpos($rawHost, ',') !== false) {
        $parts = explode(',', $rawHost);
        $rawHost = trim($parts[0]);
    }

    $hostParts = explode(':', $rawHost);
    $hostName = $hostParts[0];
    $isIp = filter_var($hostName, FILTER_VALIDATE_IP) !== false;
    $isLocalhost = in_array($hostName, ['localhost', '127.0.0.1'], true);

    if ($isIp && !$isLocalhost) {
        $host = getenv('APP_DOMAIN') ?: 'mads.guru.darsya.com';
    } else {
        $host = $rawHost;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');

    $hostMain = explode(':', $host)[0];
    $protocol = ($https || !in_array($hostMain, ['localhost', '127.0.0.1'], true)) ? 'https' : 'http';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/login.php';
    $apiPosition = strpos($scriptName, '/api/');
    $basePath = $apiPosition === false ? '' : substr($scriptName, 0, $apiPosition);

    return $protocol . '://' . $host . $basePath;
}

function redirect_login_error(string $message): never
{
    header('Location: ' . login_base_url() . '/index.html?error=' . rawurlencode($message));
    exit;
}

function post_google_form(string $url, array $fields): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL PHP belum aktif di server.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if (!is_string($response) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google tidak dapat menyelesaikan autentikasi.' . ($error !== '' ? ' ' . $error : ''));
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons autentikasi Google tidak valid.');
    }
    return $data;
}

function get_google_user(string $accessToken): array
{
    $curl = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $data = is_string($response) ? json_decode($response, true) : null;
    if ($status !== 200 || !is_array($data) || empty($data['email']) || ($data['email_verified'] ?? false) !== true) {
        throw new RuntimeException('Google tidak mengembalikan email terverifikasi.');
    }
    return $data;
}

$oauthConfig = require __DIR__ . '/oauth-config.php';
$clientId = (string) $oauthConfig['client_id'];
$clientSecret = (string) $oauthConfig['client_secret'];
$baseUrl = login_base_url();
$redirectUri = (string) $oauthConfig['redirect_uri'];
if ($redirectUri === '') {
    $redirectUri = $baseUrl . '/api/login.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'google') {
    if ($clientId === '' || $clientSecret === '') {
        redirect_login_error('Login Google belum dikonfigurasi. Admin perlu mengatur GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.');
    }

    start_app_session();
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_started_at'] = time();
    $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ]);
    header('Location: ' . $authorizationUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || (!isset($_GET['code']) && !isset($_GET['error']))) {
    json_response(['success' => false, 'error' => 'Permintaan login tidak valid.'], 405);
}

start_app_session();
$returnedState = (string) ($_GET['state'] ?? '');
$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
$startedAt = (int) ($_SESSION['google_oauth_started_at'] ?? 0);
unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_started_at']);
if ($expectedState === '' || $startedAt < time() - 600 || !hash_equals($expectedState, $returnedState)) {
    redirect_login_error('Verifikasi login Google kedaluwarsa atau tidak valid. Silakan coba lagi.');
}

if (isset($_GET['error'])) {
    redirect_login_error('Login Google dibatalkan.');
}

if ($clientId === '' || $clientSecret === '') {
    redirect_login_error('Login Google belum dikonfigurasi oleh admin.');
}

try {
    $token = post_google_form('https://oauth2.googleapis.com/token', [
        'code' => (string) $_GET['code'],
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]);
    if (empty($token['access_token'])) {
        throw new RuntimeException('Token akses Google tidak tersedia.');
    }
    $googleUser = get_google_user((string) $token['access_token']);
    $email = strtolower(trim((string) $googleUser['email']));

    $connection = db_connection();
    $statement = $connection->prepare(
        'SELECT id, name, email, phone_number, photo, role, santri_id, role_selected, password_hash
         FROM users WHERE email = ? LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$user) {
        redirect_login_error('Email Google ini belum terdaftar. Hubungi admin untuk mendapatkan akses.');
    }

    if (empty($user['photo']) && !empty($googleUser['picture'])) {
        $update = $connection->prepare('UPDATE users SET photo = ? WHERE id = ?');
        $update->bind_param('si', $googleUser['picture'], $user['id']);
        $update->execute();
        $update->close();
        $user['photo'] = $googleUser['picture'];
    }

    unset($user['phone_number']);
    set_authenticated_session($user);
    header('Location: ' . destination_for_user($user, $baseUrl));
    exit;
} catch (Throwable $error) {
    error_log('Google OAuth login failed: ' . $error->getMessage());
    redirect_login_error('Login Google gagal diselesaikan. Pastikan konfigurasi OAuth dan database benar, lalu coba lagi.');
}

function destination_for_user(array $user, string $baseUrl): string
{
    return $baseUrl . (!empty($user['role_selected']) ? '/dashboard.html' : '/pilih-role.html');
}
