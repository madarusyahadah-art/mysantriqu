<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config.php';
require_role(['admin', 'supervisor', 'kepala', 'santri']);

$date = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT k.id, k.nama_kegiatan, k.kategori, k.frekuensi, k.slide_title, k.slide_description,
           k.slide_image, k.slide_order, lk.status AS status_laporan,
           lk.dilaporkan_oleh, u.name AS nama_pelapor,
           (SELECT COUNT(*) FROM absensi a
           WHERE a.kegiatan_id = k.id AND a.tanggal = ? AND a.status <> 'hadir') AS santri_tidak_hadir,
           (SELECT COUNT(*) FROM absensi_asatidz aa
            WHERE aa.kegiatan_id = k.id AND aa.tanggal = ? AND aa.status <> 'hadir'
              AND aa.asatidz_id IS NOT NULL) AS asatidz_tidak_hadir
    FROM kegiatan k
    LEFT JOIN laporan_kegiatan lk ON lk.kegiatan_id = k.id AND lk.tanggal = ?
    LEFT JOIN users u ON u.id = lk.dilaporkan_oleh
    WHERE k.is_active = 1
      AND (k.frekuensi = 'harian' OR lk.id IS NOT NULL)
    ORDER BY k.slide_order ASC, k.id ASC
");
$stmt->bind_param("sss", $date, $date, $date);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

json_response(["success" => true, "tanggal" => $date, "kegiatan" => $activities]);
