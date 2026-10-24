<?php
require_once __DIR__ . '/config.php';
$user = require_role(['admin', 'supervisor', 'kepala', 'santri']);

$userId = (int) $user['id'];
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("
        INSERT INTO dashboard_slide_state (user_id, active_index, state_date)
        VALUES (?, 0, ?)
        ON DUPLICATE KEY UPDATE
          active_index = IF(state_date < VALUES(state_date), 0, active_index),
          state_date = VALUES(state_date)
    ");
    $stmt->bind_param("is", $userId, $today);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(["success" => false, "error" => "Posisi slideshow gagal dimuat"], 500);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT active_index, state_date FROM dashboard_slide_state WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $state = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    json_response([
        "success" => true,
        "active_index" => (int) $state['active_index'],
        "state_date" => $state['state_date'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $index = filter_var(is_array($data) ? ($data['active_index'] ?? null) : null, FILTER_VALIDATE_INT);
    $stateDate = is_array($data) ? ($data['state_date'] ?? '') : '';
    if ($index === false || $index < 0 || $stateDate !== $today) {
        json_response(["success" => false, "error" => "Posisi slide tidak valid atau sudah berganti hari"], 409);
    }

    $stmt = $conn->prepare("
        INSERT INTO dashboard_slide_state (user_id, active_index, state_date)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
          active_index = IF(state_date = VALUES(state_date), VALUES(active_index), 0),
          state_date = VALUES(state_date)
    ");
    $stmt->bind_param("iis", $userId, $index, $today);
    if (!$stmt->execute()) {
        $stmt->close();
        json_response(["success" => false, "error" => "Posisi slideshow gagal disimpan"], 500);
    }
    $stmt->close();
    json_response(["success" => true, "active_index" => $index, "state_date" => $today]);
}

json_response(["success" => false, "error" => "Metode tidak didukung"], 405);
?>
