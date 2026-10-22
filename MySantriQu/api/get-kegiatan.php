<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor', 'kepala', 'santri']);

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection not established"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT k.id, k.nama_kegiatan, k.kategori, k.frekuensi, k.is_active,
           k.slide_title, k.slide_description, k.slide_image, k.slide_order,
           (SELECT GROUP_CONCAT(semester ORDER BY semester)
            FROM kegiatan_semester WHERE kegiatan_id = k.id) AS semester_eligible
    FROM kegiatan k
    ORDER BY slide_order ASC, id ASC
");
$stmt->execute();
$result = $stmt->get_result();

$kegiatan = [];
while ($row = $result->fetch_assoc()) {
    $kegiatan[] = $row;
}

echo json_encode(["success" => true, "kegiatan" => $kegiatan]);

$stmt->close();
$conn->close();
?>