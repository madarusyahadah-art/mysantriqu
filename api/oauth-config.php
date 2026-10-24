<?php
$localOAuthConfigPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'mysantriqu-oauth.php';
$localCredentialsPath = __DIR__ . DIRECTORY_SEPARATOR . 'oauth-credentials.local.php';

$localOAuthConfig = [];
if (is_file($localCredentialsPath)) {
    $localOAuthConfig = require $localCredentialsPath;
} elseif (is_file($localOAuthConfigPath)) {
    $localOAuthConfig = require $localOAuthConfigPath;
}

if (!is_array($localOAuthConfig)) {
    throw new RuntimeException('Konfigurasi OAuth lokal tidak valid.');
}

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: ($localOAuthConfig['client_id'] ?? '958378077119-gq83onrdjaa2bd0qlqarns74uluan5rj.apps.googleusercontent.com'),
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: ($localOAuthConfig['client_secret'] ?? ''),
];
