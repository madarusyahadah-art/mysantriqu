<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/academic-calendar-helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = calendar_openssl_key_options();
$key = openssl_pkey_new($options);
$exportOptions = isset($options['config']) ? ['config' => $options['config']] : [];
if ($key === false || !openssl_pkey_export($key, $privatePem, null, $exportOptions)) {
    fwrite(STDERR, "Unable to generate a Web Push VAPID key pair.\n");
    exit(1);
}
$details = openssl_pkey_get_details($key);
if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'])) {
    fwrite(STDERR, "Unable to read the generated VAPID public key.\n");
    exit(1);
}
$publicKey = rtrim(strtr(base64_encode("\x04" . $details['ec']['x'] . $details['ec']['y']), '+/', '-_'), '=');

echo "<?php\nreturn ";
var_export([
    'public_key' => $publicKey,
    'private_key' => $privatePem,
    'subject' => 'mailto:admin@example.org',
]);
echo ";\n";
