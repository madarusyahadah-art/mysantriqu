<?php
declare(strict_types=1);

function require_calendar_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['success' => false, 'error' => 'Silakan masuk untuk membuka kalender akademik.'], 401);
    }

    return $user;
}

function require_calendar_admin(array $user): void
{
    if (strtolower((string) ($user['role'] ?? '')) !== 'admin') {
        json_response(['success' => false, 'error' => 'Hanya admin yang dapat mengelola kalender akademik.'], 403);
    }
}

function calendar_valid_date(mixed $value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function calendar_parse_optional_time(mixed $value): string|false|null
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value)) {
        return false;
    }
    $value = trim($value);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return $value . ':00';
    }

    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $value) ? $value : false;
}

function process_academic_semester_rollovers(mysqli $connection): int
{
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $connection->begin_transaction();
    try {
        $events = $connection->prepare(
            "SELECT e.id
             FROM academic_calendar_events e
             INNER JOIN academic_calendar_imports i ON i.id = e.import_id
             WHERE i.status = 'published'
               AND e.is_semester_end = 1
               AND e.is_cancelled = 0
               AND e.semester_incremented_at IS NULL
               AND e.tanggal_selesai IS NOT NULL
               AND e.tanggal_selesai < ?
               AND DATE(i.published_at) <= e.tanggal_selesai
             ORDER BY e.tanggal_selesai ASC, e.id ASC
             FOR UPDATE"
        );
        $events->bind_param('s', $today);
        $events->execute();
        $eventIds = array_map('intval', array_column($events->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
        $events->close();
        if (!$eventIds) {
            $connection->commit();
            return 0;
        }

        $advance = $connection->prepare('UPDATE santri SET semester_aktif = LEAST(semester_aktif + 1, 6)');
        $mark = $connection->prepare(
            'UPDATE academic_calendar_events SET semester_incremented_at = NOW()
             WHERE id = ? AND semester_incremented_at IS NULL'
        );
        foreach ($eventIds as $eventId) {
            $advance->execute();
            $mark->bind_param('i', $eventId);
            $mark->execute();
        }
        $advance->close();
        $mark->close();
        $connection->commit();

        return count($eventIds);
    } catch (Throwable $error) {
        $connection->rollback();
        error_log('Academic semester rollover failed: ' . $error->getMessage());
        throw $error;
    }
}

function calendar_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function calendar_base64url_decode(string $value): string|false
{
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded === false ? false : $decoded;
}

function calendar_valid_push_endpoint(string $endpoint): bool
{
    $parts = parse_url($endpoint);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['fragment']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
        return false;
    }

    $host = strtolower(rtrim((string) $parts['host'], '.'));
    foreach (['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'] as $allowedHost) {
        if ($host === $allowedHost || str_ends_with($host, '.' . $allowedHost)) {
            return true;
        }
    }

    return false;
}

function academic_calendar_vapid_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = getenv('MYSANTRIQU_PUSH_CONFIG')
        ?: dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'mysantriqu-push.php';
    $fileConfig = is_file($path) ? require $path : [];
    if (!is_array($fileConfig)) {
        throw new RuntimeException('Web Push configuration must return an array.');
    }
    $config = [
        'public_key' => getenv('MYSANTRIQU_VAPID_PUBLIC_KEY') ?: (string) ($fileConfig['public_key'] ?? ''),
        'private_key' => getenv('MYSANTRIQU_VAPID_PRIVATE_KEY') ?: (string) ($fileConfig['private_key'] ?? ''),
        'subject' => getenv('MYSANTRIQU_VAPID_SUBJECT') ?: (string) ($fileConfig['subject'] ?? ''),
    ];

    return $config;
}

function calendar_openssl_key_options(): array
{
    $options = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ];
    $root = dirname(__DIR__, 3);
    $candidates = [
        getenv('OPENSSL_CONF') ?: '',
        $root . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        $root . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            $options['config'] = $candidate;
            break;
        }
    }

    return $options;
}

function calendar_hkdf(string $inputKey, string $salt, string $info, int $length): string
{
    $pseudoRandomKey = hash_hmac('sha256', $inputKey, $salt, true);
    $output = '';
    $block = '';
    for ($index = 1; strlen($output) < $length; $index++) {
        $block = hash_hmac('sha256', $block . $info . chr($index), $pseudoRandomKey, true);
        $output .= $block;
    }

    return substr($output, 0, $length);
}

function calendar_der_signature_to_raw(string $signature): string
{
    $offset = 2;
    if (ord($signature[1]) & 0x80) {
        $offset += 1 + (ord($signature[1]) & 0x7f);
    }
    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('Invalid VAPID signature.');
    }
    $rLength = ord($signature[$offset + 1]);
    $r = substr($signature, $offset + 2, $rLength);
    $offset += 2 + $rLength;
    if (ord($signature[$offset]) !== 0x02) {
        throw new RuntimeException('Invalid VAPID signature.');
    }
    $sLength = ord($signature[$offset + 1]);
    $s = substr($signature, $offset + 2, $sLength);

    return str_pad(substr($r, -32), 32, "\0", STR_PAD_LEFT)
        . str_pad(substr($s, -32), 32, "\0", STR_PAD_LEFT);
}

function calendar_webpush_vapid_token(string $audience): array
{
    $config = academic_calendar_vapid_config();
    $publicKey = $config['public_key'];
    $privateKey = $config['private_key'];
    $subject = $config['subject'];
    if ($publicKey === '' || $privateKey === '' || $subject === '') {
        throw new RuntimeException('Web Push VAPID settings are incomplete.');
    }

    $header = calendar_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
    $claims = calendar_base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 60 * 60,
        'sub' => $subject,
    ], JSON_THROW_ON_ERROR));
    $message = $header . '.' . $claims;
    $key = openssl_pkey_get_private($privateKey);
    if ($key === false || !openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign Web Push authorization.');
    }

    return [$message . '.' . calendar_base64url_encode(calendar_der_signature_to_raw($signature)), $publicKey];
}

function calendar_webpush_encrypt(array $subscription, string $payload): string
{
    $clientPublicKey = calendar_base64url_decode((string) $subscription['p256dh']);
    $authSecret = calendar_base64url_decode((string) $subscription['auth']);
    if ($clientPublicKey === false || strlen($clientPublicKey) !== 65 || $clientPublicKey[0] !== "\x04"
        || $authSecret === false || strlen($authSecret) < 16) {
        throw new RuntimeException('Invalid Web Push subscription keys.');
    }

    $publicDer = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $clientPublicKey;
    $clientKey = openssl_pkey_get_public(
        "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($publicDer), 64, "\n")
        . "-----END PUBLIC KEY-----\n"
    );
    $serverKey = openssl_pkey_new(calendar_openssl_key_options());
    $serverDetails = $serverKey === false ? false : openssl_pkey_get_details($serverKey);
    if ($clientKey === false || $serverKey === false || !is_array($serverDetails)) {
        throw new RuntimeException('Unable to prepare Web Push encryption keys.');
    }

    $serverPublicKey = "\x04" . $serverDetails['ec']['x'] . $serverDetails['ec']['y'];
    $sharedSecret = openssl_pkey_derive($clientKey, $serverKey, 32);
    if ($sharedSecret === false) {
        throw new RuntimeException('Unable to derive Web Push encryption secret.');
    }

    $inputKey = calendar_hkdf(
        $sharedSecret,
        $authSecret,
        "WebPush: info\0" . $clientPublicKey . $serverPublicKey,
        32
    );
    $salt = random_bytes(16);
    $contentEncryptionKey = calendar_hkdf($inputKey, $salt, "Content-Encoding: aes128gcm\0", 16);
    $nonce = calendar_hkdf($inputKey, $salt, "Content-Encoding: nonce\0", 12);
    $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $contentEncryptionKey, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt Web Push payload.');
    }

    return $salt . pack('N', 4096) . chr(strlen($serverPublicKey)) . $serverPublicKey . $ciphertext . $tag;
}

function send_academic_calendar_push(mysqli $connection, string $title, string $body): array
{
    if (!function_exists('curl_init')) {
        error_log('Web Push skipped: PHP cURL is unavailable.');
        return ['configured' => false, 'sent' => 0, 'failed' => 0, 'subscriptions' => 0];
    }
    try {
        $vapid = academic_calendar_vapid_config();
        $configured = $vapid['public_key'] !== '' && $vapid['private_key'] !== '' && $vapid['subject'] !== '';
        $subscriptions = $connection->query(
            'SELECT id, endpoint, p256dh, auth FROM academic_calendar_push_subscriptions'
        )->fetch_all(MYSQLI_ASSOC);
        if (!$configured) {
            error_log('Web Push skipped: VAPID settings are incomplete.');
            return ['configured' => false, 'sent' => 0, 'failed' => 0, 'subscriptions' => count($subscriptions)];
        }
        $sent = 0;
        $failed = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $endpoint = (string) $subscription['endpoint'];
                $parts = parse_url($endpoint);
                if (!calendar_valid_push_endpoint($endpoint) || !is_array($parts)) {
                    throw new RuntimeException('Invalid push service endpoint.');
                }
                $audience = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
                [$token, $publicKey] = calendar_webpush_vapid_token($audience);
                $payload = json_encode([
                    'title' => $title,
                    'body' => $body,
                    'url' => 'kalender-akademik.html',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $encryptedPayload = calendar_webpush_encrypt($subscription, $payload);
                $request = curl_init($endpoint);
                curl_setopt_array($request, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $encryptedPayload,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: vapid t=' . $token . ', k=' . $publicKey,
                        'Content-Type: application/octet-stream',
                        'Content-Encoding: aes128gcm',
                        'TTL: 86400',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                ]);
                curl_exec($request);
                $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
                $error = curl_error($request);
                curl_close($request);
                if ($status === 404 || $status === 410) {
                    $delete = $connection->prepare('DELETE FROM academic_calendar_push_subscriptions WHERE id = ?');
                    $delete->bind_param('i', $subscription['id']);
                    $delete->execute();
                    $delete->close();
                    $failed++;
                } elseif ($status < 200 || $status >= 300) {
                    error_log('Web Push delivery failed with HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
                    $failed++;
                } else {
                    $sent++;
                }
            } catch (Throwable $error) {
                error_log('Web Push delivery failed: ' . $error->getMessage());
                $failed++;
            }
        }
        return [
            'configured' => true,
            'sent' => $sent,
            'failed' => $failed,
            'subscriptions' => count($subscriptions),
        ];
    } catch (Throwable $error) {
        error_log('Web Push subscriptions could not be loaded: ' . $error->getMessage());
        return ['configured' => false, 'sent' => 0, 'failed' => 1, 'subscriptions' => 0];
    }
}