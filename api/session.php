<?php
require_once __DIR__ . '/config.php';

$user = require_login();

json_response([
    "success" => true,
    "authenticated" => true,
    "user" => $user,
]);
?>
