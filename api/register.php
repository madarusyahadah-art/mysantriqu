<?php
require_once __DIR__ . '/config.php';

json_response([
    "success" => false,
    "error" => "Pembuatan akun melalui email saja sudah tidak tersedia. Verifikasi akun Google terlebih dahulu.",
], 410);
?>
