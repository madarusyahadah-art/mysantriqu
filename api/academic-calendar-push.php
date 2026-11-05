<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/academic-calendar-helpers.php';

$user = require_calendar_user();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    $connection = db_connection();
} catch (RuntimeException $error) {
    error_log('Academic calendar push database unavailable: ' . $error->getMessage());
    json_response(['success' => false, 'error' => 'Database belum tersedia. Pastikan MySQL aktif.'], 503);
}

if ($method === 'GET') {
    try {
        $vapid = academic_calendar_vapid_config();
    } catch (Throwable $error) {
        error_log('Academic calendar VAPID config unavailable: ' . $error->getMessage());
        json_response(['success' => false, 'error' => 'Konfigurasi notifikasi push tidak dapat dibaca.'], 503);
    }
    $publicKey = $vapid['public_key'];
    $userId = (int) $user['id'];
    $endpoint = trim((string) ($_GET['endpoint'] ?? ''));
    if ($endpoint !== '') {
        if (strlen($endpoint) > 2048 || !calendar_valid_push_endpoint($endpoint)) {
            json_response(['success' => false, 'error' => 'Alamat layanan push tidak valid.'], 400);
        }
    }
    if ($endpoint === '') {
        $statement = $connection->prepare(
            'SELECT id FROM academic_calendar_push_subscriptions WHERE user_id = ? LIMIT 1'
        );
        $statement->bind_param('i', $userId);
    } else {
        $endpointHash = hash('sha256', $endpoint);
        $statement = $connection->prepare(
            'SELECT id FROM academic_calendar_push_subscriptions WHERE user_id = ? AND endpoint_hash = ? LIMIT 1'
        );
        $statement->bind_param('is', $userId, $endpointHash);
    }
    $statement->execute();
    $subscribed = $statement->get_result()->num_rows > 0;
    $statement->close();
    json_response([
        'success' => true,
        'configured' => $publicKey !== '' && $vapid['private_key'] !== '' && $vapid['subject'] !== '',
        'public_key' => $publicKey,
        'subscribed' => $subscribed,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    json_response(['success' => false, 'error' => 'Metode tidak didukung.'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$action = is_array($data) ? (string) ($data['action'] ?? '') : '';
$subscription = is_array($data) ? ($data['subscription'] ?? null) : null;
$endpoint = is_array($subscription) ? trim((string) ($subscription['endpoint'] ?? '')) : '';
if (strlen($endpoint) > 2048 || !calendar_valid_push_endpoint($endpoint)) {
    json_response(['success' => false, 'error' => 'Alamat layanan push tidak valid.'], 400);
}
$endpointHash = hash('sha256', $endpoint);
$userId = (int) $user['id'];

if ($action === 'subscribe') {
    $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
    $p256dh = trim((string) ($keys['p256dh'] ?? ''));
    $auth = trim((string) ($keys['auth'] ?? ''));
    $decodedPublicKey = calendar_base64url_decode($p256dh);
    $decodedAuth = calendar_base64url_decode($auth);
    if ($decodedPublicKey === false || strlen($decodedPublicKey) !== 65 || $decodedPublicKey[0] !== "\x04"
        || $decodedAuth === false || strlen($decodedAuth) < 16 || strlen($p256dh) > 255 || strlen($auth) > 255) {
        json_response(['success' => false, 'error' => 'Kunci langganan push tidak valid.'], 400);
    }

    $statement = $connection->prepare(
        'INSERT INTO academic_calendar_push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), endpoint = VALUES(endpoint),
                                 p256dh = VALUES(p256dh), auth = VALUES(auth)'
    );
    $statement->bind_param('issss', $userId, $endpointHash, $endpoint, $p256dh, $auth);
    $statement->execute();
    $statement->close();
    json_response(['success' => true, 'subscribed' => true]);
}

if ($action === 'unsubscribe') {
    $statement = $connection->prepare(
        'DELETE FROM academic_calendar_push_subscriptions WHERE user_id = ? AND endpoint_hash = ?'
    );
    $statement->bind_param('is', $userId, $endpointHash);
    $statement->execute();
    $statement->close();
    json_response(['success' => true, 'subscribed' => false]);
}

json_response(['success' => false, 'error' => 'Aksi notifikasi tidak dikenal.'], 400);
