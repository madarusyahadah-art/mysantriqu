<?php
declare(strict_types=1);

function json_response(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('mysantriqu_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db_connection(): mysqli
{
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $host = getenv('DB_HOST') ?: 'localhost';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'mysantriqu';

    try {
        $connection = new mysqli($host, $user, $password, $database);
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (mysqli_sql_exception $error) {
        error_log('Database connection failed: ' . $error->getMessage());
        throw new RuntimeException('Database connection failed.', 0, $error);
    }
}

function set_authenticated_session(array $user, bool $rememberMe = false): void
{
    start_app_session();
    session_regenerate_id(true);
    unset($user['password_hash']);
    $user['role_selected'] = (int) ($user['role_selected'] ?? 0);
    $_SESSION['user'] = $user;
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['remember_me'] = $rememberMe;

    $cookie = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $rememberMe ? time() + (30 * 24 * 60 * 60) : 0,
        'path' => $cookie['path'] ?: '/',
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_user(): ?array
{
    start_app_session();
    if (empty($_SESSION['authenticated']) || !is_array($_SESSION['user'] ?? null)) {
        return null;
    }
    return $_SESSION['user'];
}

function normalize_phone_number(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $value);
    if (!is_string($digits) || $digits === '') {
        return null;
    }

    if (str_starts_with($value, '+')) {
        $normalized = '+' . $digits;
    } elseif (str_starts_with($digits, '0')) {
        $normalized = '+62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '62')) {
        $normalized = '+' . $digits;
    } else {
        return null;
    }

    return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) ? $normalized : null;
}
