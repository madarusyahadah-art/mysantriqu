<?php
// Keep PHP errors in the server log instead of exposing them in the browser.
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load shared OAuth credentials for the authorization request and callback.
$oauthConfig = require __DIR__ . '/oauth-config.php';
$GOOGLE_CLIENT_ID = $oauthConfig['client_id'];
$GOOGLE_CLIENT_SECRET = $oauthConfig['client_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'google') {
    date_default_timezone_set('Asia/Jakarta');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $_SESSION['remember_login'] = ($_GET['remember'] ?? '') === '1';
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = $state;
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/login.php';
    $apiPos = strpos($scriptName, '/api/');
    $basePath = $apiPos === false ? '' : substr($scriptName, 0, $apiPos);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $protocol = ($https || !in_array(explode(':', $host)[0], ['localhost', '127.0.0.1'], true)) ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $host . $basePath;
    
    if ($GOOGLE_CLIENT_SECRET === '' || strpos($GOOGLE_CLIENT_SECRET, 'GANTI_DENGAN') !== false) {
        header('Location: ' . $baseUrl . '/index.html?error=' . urlencode('Login Google belum dikonfigurasi. Admin perlu mengatur GOOGLE_CLIENT_SECRET di server.'));
        exit;
    }
    
    $redirectUri = $baseUrl . '/api/login.php';
    $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
    ]);

    header('Location: ' . $authorizationUrl);
    exit;
}

require_once __DIR__ . '/config.php';

// FALLBACK: Jika fungsi json_response tidak ada di config.php, buat di sini
if (!function_exists('json_response')) {
    function json_response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// FALLBACK: Jika fungsi set_authenticated_session tidak ada di config.php, buat di sini
if (!function_exists('set_authenticated_session')) {
    function set_authenticated_session($user, $rememberMe) {
        session_start();
        $_SESSION['user'] = $user;
        $_SESSION['authenticated'] = true;
        if ($rememberMe) {
            ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30); // 30 hari
        }
    }
}

function find_or_create_user($conn, $email, $name, $photo)
{
    $stmt = $conn->prepare("SELECT id, name, email, role, photo, santri_id, role_selected FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (empty($user['photo']) && !empty($photo)) {
            $updateStmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
            $updateStmt->bind_param("si", $photo, $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $user['photo'] = $photo;
        }
        $stmt->close();
        return $user;
    }

    $insertStmt = $conn->prepare("INSERT INTO users (name, email, photo, role, role_selected) VALUES (?, ?, ?, 'mahasantri', 0)");
    $insertStmt->bind_param("sss", $name, $email, $photo);
    $insertStmt->execute();

    $user = [
        'id' => $insertStmt->insert_id,
        'name' => $name,
        'email' => $email,
        'role' => 'mahasantri',
        'photo' => $photo,
        'santri_id' => null,
        'role_selected' => 0,
    ];

    $insertStmt->close();
    $stmt->close();
    return $user;
}

function fetch_google_user_from_code($code, $redirectUri, $clientId, $clientSecret)
{
    if ($clientSecret === '' || strpos($clientSecret, 'GANTI_DENGAN') !== false) {
        throw new Exception('Google Client Secret belum dikonfigurasi dengan benar.');
    }

    $tokenData = [
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        throw new Exception('Gagal menukar kode OAuth. HTTP ' . $httpCode . ($curlError ? ' - ' . $curlError : ''));
    }

    $tokenResult = json_decode($response, true);
    if (empty($tokenResult['access_token'])) {
        throw new Exception('Access token tidak ditemukan. Response: ' . $response);
    }

    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenResult['access_token']]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $userResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Gagal mengambil data user dari Google.');
    }

    $googleUser = json_decode($userResponse, true);
    if (!$googleUser || empty($googleUser['email'])) {
        throw new Exception('Data user Google tidak valid.');
    }

    return $googleUser;
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/login.php';
$apiPos = strpos($scriptName, '/api/');
$basePath = $apiPos === false ? '' : substr($scriptName, 0, $apiPos);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$protocol = ($https || !in_array(explode(':', $host)[0], ['localhost', '127.0.0.1'], true)) ? 'https' : 'http';
$baseUrl = $protocol . '://' . $host . $basePath;
$redirectUri = $baseUrl . '/api/login.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['code'])) {
        session_start(); // Pastikan session dimulai
        $returnedState = $_GET['state'] ?? '';
        $expectedState = $_SESSION['google_oauth_state'] ?? '';
        unset($_SESSION['google_oauth_state']);
        
        if ($expectedState === '' || !hash_equals($expectedState, $returnedState)) {
            throw new Exception('Verifikasi login Google gagal (State mismatch). Silakan coba lagi.');
        }

        $googleUser = fetch_google_user_from_code($_GET['code'], $redirectUri, $GOOGLE_CLIENT_ID, $GOOGLE_CLIENT_SECRET);
        
        // Pastikan $conn ada dari config.php
        if (!isset($conn)) {
            throw new Exception('Koneksi database ($conn) tidak ditemukan. Cek file config.php');
        }

        $user = find_or_create_user(
            $conn,
            $googleUser['email'],
            $googleUser['name'] ?? $googleUser['email'],
            $googleUser['picture'] ?? ''
        );
        
        $rememberMe = !empty($_SESSION['remember_login']);
        unset($_SESSION['remember_login']);
        set_authenticated_session($user, $rememberMe);

        if (empty($user['role_selected'])) {
            header('Location: ' . $baseUrl . '/pilih-role.html');
            exit;
        }

        header('Location: ' . $baseUrl . '/dashboard.html');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['error'])) {
        throw new Exception('Login Google dibatalkan atau tidak dapat dilanjutkan.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        session_start();
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!empty($data['code'])) {
            $googleUser = fetch_google_user_from_code($data['code'], $redirectUri, $GOOGLE_CLIENT_ID, $GOOGLE_CLIENT_SECRET);
        } else {
            json_response(["success" => false, "error" => "Data login tidak lengkap"], 400);
        }

        if (!isset($conn)) {
            throw new Exception('Koneksi database ($conn) tidak ditemukan. Cek file config.php');
        }

        $user = find_or_create_user(
            $conn,
            $googleUser['email'],
            $googleUser['name'] ?? $googleUser['email'],
            $googleUser['picture'] ?? ''
        );
        set_authenticated_session($user, !empty($data['remember_me']));

        json_response([
            "success" => true,
            "onboarding_required" => empty($user['role_selected']),
            "user" => $_SESSION['user']
        ]);
    }

    json_response(["success" => false, "error" => "Invalid request"], 405);
} catch (Exception $e) {
    error_log('login.php error: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . $baseUrl . '/index.html?error=' . urlencode($e->getMessage()));
        exit;
    }
    json_response(["success" => false, "error" => $e->getMessage()], 400);
}
?>