<?php
$db_host = getenv('DB_HOST') ?: 'srv-captain--mariadb';
$db_user = getenv('DB_USER') ?: 'mysantriqu_user';
$db_pass = getenv('DB_PASS') ?: 'ee42a59a2eca1d51f51f9e952458f9be';
$db_name = getenv('DB_NAME') ?: 'mysantriqu';

// Create database connection
$conn = null;
$connectionError = '';

try {
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $error) {
    $connectionError = $error->getMessage();
}

if ($conn instanceof mysqli && $conn->connect_error) {
    $connectionError = $conn->connect_error;
}

if ($conn === null || $connectionError !== '') {
    error_log('Database connection failed: ' . $connectionError);

    if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'login.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/login.php';
        $apiPos = strpos($scriptName, '/api/');
        $basePath = $apiPos === false ? '' : substr($scriptName, 0, $apiPos);
        http_response_code(302);
        header('Location: ' . $basePath . '/index.html?error=' . urlencode('Login gagal karena database lokal tidak tersedia. Pastikan MySQL XAMPP aktif dan nama database sudah benar.'));
        exit;
    }

    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Database tidak tersedia. Pastikan MySQL XAMPP aktif dan konfigurasi database sudah benar.',
    ]);
    exit;
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Google OAuth Configuration
$oauthConfig = require __DIR__ . '/oauth-config.php';
$GOOGLE_CLIENT_ID = $oauthConfig['client_id'];
$GOOGLE_CLIENT_SECRET = $oauthConfig['client_secret'];

// Helper function untuk JSON response
if (!function_exists('json_response')) {
    function json_response($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Helper function untuk set session authentication
if (!function_exists('set_authenticated_session')) {
    function set_authenticated_session($user, $rememberMe = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user'] = $user;
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        
        if ($rememberMe) {
            ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
            session_set_cookie_params([
                'lifetime' => 60 * 60 * 24 * 30,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

// Helper function untuk check authentication
if (!function_exists('is_authenticated')) {
    function is_authenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
}

// Helper function untuk get current user
if (!function_exists('get_current_user_data')) {
    function get_current_user_data() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user'] ?? null;
    }
}

// Helper function untuk logout
if (!function_exists('logout_user')) {
    function logout_user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
}

if (!function_exists('normalize_user_role')) {
    function normalize_user_role($role) {
        $legacyRoles = [
            'santri' => 'mahasantri',
            'supervisor' => 'musyrif',
            'kepala' => 'kajur',
        ];

        return $legacyRoles[$role] ?? $role;
    }
}

if (!function_exists('role_access_group')) {
    function role_access_group($role) {
        $role = normalize_user_role($role);

        if (in_array($role, ['musyrif', "mas'ul"], true)) {
            return 'supervisor';
        }

        return $role;
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!is_authenticated()) {
            json_response(["success" => false, "error" => "Silakan login kembali"], 401);
        }

        $user = get_current_user_data();
        if (!is_array($user) || empty($user['id'])) {
            json_response(["success" => false, "error" => "Sesi login tidak valid"], 401);
        }
        $user['role'] = normalize_user_role($user['role'] ?? '');
        $_SESSION['user'] = $user;

        return $user;
    }
}

if (!function_exists('require_role')) {
    function require_role($allowedRoles) {
        $user = require_login();
        if (empty($user['role_selected'])) {
            json_response(["success" => false, "error" => "Silakan tentukan role akun terlebih dahulu"], 403);
        }
        $allowedGroups = array_map('role_access_group', $allowedRoles);

        if (!in_array(role_access_group($user['role']), $allowedGroups, true)) {
            json_response(["success" => false, "error" => "Anda tidak memiliki akses"], 403);
        }

        return $user;
    }
}
?>