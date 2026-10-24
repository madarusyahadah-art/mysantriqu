<?php
if (!function_exists("get_oauth_base_url")) {
    function get_oauth_base_url() {
        $appUrl = getenv("APP_URL");
        if ($appUrl) {
            return rtrim($appUrl, "/");
        }

        $rawHost = $_SERVER["HTTP_X_FORWARDED_HOST"] ?? ($_SERVER["HTTP_HOST"] ?? "localhost");
        if (strpos($rawHost, ",") !== false) {
            $parts = explode(",", $rawHost);
            $rawHost = trim($parts[0]);
        }

        $hostParts = explode(":", $rawHost);
        $hostName = $hostParts[0];
        $isIp = filter_var($hostName, FILTER_VALIDATE_IP) !== false;
        $isLocalhost = in_array($hostName, ["localhost", "127.0.0.1"], true);

        if ($isIp && !$isLocalhost) {
            $host = getenv("APP_DOMAIN") ?: "mads.guru.darsya.com";
        } else {
            $host = $rawHost;
        }

        $scriptName = $_SERVER["SCRIPT_NAME"] ?? "/api/login.php";
        $apiPos = strpos($scriptName, "/api/");
        $basePath = $apiPos === false ? "" : substr($scriptName, 0, $apiPos);

        $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
            || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https")
            || (($_SERVER["HTTP_X_FORWARDED_SSL"] ?? "") === "on");

        $hostMain = explode(":", $host)[0];
        $protocol = ($https || !in_array($hostMain, ["localhost", "127.0.0.1"], true)) ? "https" : "http";

        return $protocol . "://" . $host . $basePath;
    }
}

$localConfigFile = __DIR__ . "/oauth-credentials.local.php";
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];

$baseUrl = get_oauth_base_url();
$redirectUri = $baseUrl . "/api/login.php";

return [
    "client_id"     => getenv("GOOGLE_CLIENT_ID") ?: ($localConfig["client_id"] ?? "958378077119-gq83onrdjaa2bd0qlqarns74uluan5rj.apps.googleusercontent.com"),
    "client_secret" => getenv("GOOGLE_CLIENT_SECRET") ?: ($localConfig["client_secret"] ?? ""),
    "base_url"      => $baseUrl,
    "redirect_uri"  => $redirectUri,
];
