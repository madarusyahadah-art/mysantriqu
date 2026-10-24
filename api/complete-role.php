<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_login();

json_response([
    "success" => false,
    "error" => "Role hanya dapat ditetapkan oleh admin melalui pengelolaan pengguna.",
], 403);
?>
