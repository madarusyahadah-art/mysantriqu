<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$kegiatanId = filter_input(INPUT_GET, 'kegiatan_id', FILTER_VALIDATE_INT);

if ($id) {
    $stmt = $conn->prepare("SELECT id, nama, mata_pelajaran, jabatan FROM asatidz WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $asatidz = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$asatidz) {
        json_response(["success" => false, "error" => "Asatidz tidak ditemukan"], 404);
    }

    $stmt = $conn->prepare("
        SELECT kegiatan_id, peran
        FROM kegiatan_asatidz
        WHERE asatidz_id = ?
        ORDER BY kegiatan_id
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $asatidz['penugasan'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    json_response(["success" => true, "asatidz" => $asatidz]);
}

if ($kegiatanId) {
    $stmt = $conn->prepare("
        SELECT a.id, a.nama, a.mata_pelajaran, a.jabatan, ka.peran
        FROM asatidz a
        JOIN kegiatan_asatidz ka
            ON ka.asatidz_id = a.id
        WHERE ka.kegiatan_id = ?
        ORDER BY a.nama ASC
    ");
    $stmt->bind_param("i", $kegiatanId);
} else {
    $stmt = $conn->prepare("
        SELECT a.id, a.nama, a.mata_pelajaran, a.jabatan, NULL AS peran,
               (SELECT COUNT(*) FROM kegiatan_asatidz ka WHERE ka.asatidz_id = a.id) AS jumlah_penugasan,
               (SELECT GROUP_CONCAT(
                    CONCAT(k.nama_kegiatan, ' — ', CASE ka.peran
                        WHEN 'masul' THEN 'Mas''ul'
                        WHEN 'musyrif' THEN 'Musyrif'
                        ELSE 'Pengampu'
                    END)
                    ORDER BY k.nama_kegiatan SEPARATOR ', '
                )
                FROM kegiatan_asatidz ka
                JOIN kegiatan k ON k.id = ka.kegiatan_id
                WHERE ka.asatidz_id = a.id) AS penugasan_label
        FROM asatidz a
        ORDER BY nama ASC
    ");
}

$stmt->execute();
$asatidz = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
json_response(["success" => true, "asatidz" => $asatidz]);
