<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
$currentUser = require_role(['admin', 'supervisor']);
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    json_response(["success" => false, "error" => "Data JSON tidak valid"], 400);
}

$items = $data['pelanggaran'] ?? null;
if (!is_array($items) || !$items) {
    json_response(["success" => false, "error" => "Data pelanggaran wajib diisi"], 400);
}
$stmt = $conn->prepare("
    INSERT INTO pelanggaran
        (santri_id, jenis_pelanggaran_id, tanggal, jenis_pelanggaran, poin, keterangan, input_by)
    SELECT ?, jp.id, ?, jp.nama, ?, ?, ?
    FROM jenis_pelanggaran jp
    WHERE jp.id = ? AND jp.is_active = 1
");
$total = 0;
$conn->begin_transaction();
try {
    foreach ($items as $item) {
        $santriId = filter_var($item['santri_id'] ?? null, FILTER_VALIDATE_INT);
        $typeId = filter_var($item['jenis_pelanggaran_id'] ?? null, FILTER_VALIDATE_INT);
        $date = is_string($item['tanggal'] ?? null) ? $item['tanggal'] : '';
        $points = filter_var($item['poin'] ?? null, FILTER_VALIDATE_INT);
        $note = is_string($item['keterangan'] ?? null) ? trim($item['keterangan']) : '';
        if (!$santriId || !$typeId || !$points || $points < 1 || $points > 100000 ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new InvalidArgumentException("Data pelanggaran tidak valid");
        }
        $stmt->bind_param("isisii", $santriId, $date, $points, $note, $currentUser['id'], $typeId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new InvalidArgumentException("Jenis pelanggaran tidak ditemukan atau sudah nonaktif");
        }
        $total++;
    }
    $conn->commit();
} catch (InvalidArgumentException $error) {
    $conn->rollback();
    $stmt->close();
    json_response(["success" => false, "error" => $error->getMessage()], 400);
} catch (Throwable $error) {
    $conn->rollback();
    $stmt->close();
    error_log('save-pelanggaran.php error: ' . $error->getMessage());
    json_response(["success" => false, "error" => "Pelanggaran gagal disimpan"], 500);
}
$stmt->close();
json_response(["success" => true, "total" => $total]);
