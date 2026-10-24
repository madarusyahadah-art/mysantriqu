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

// Hitung rincian alpa/sakit/izin per kegiatan
$counts = [];
$countStmt = $conn->prepare("
    SELECT kegiatan_id, status, COUNT(*) AS total FROM absensi
    WHERE tanggal = ? GROUP BY kegiatan_id, status
");
$countStmt->bind_param("s", $date);
$countStmt->execute();
$res = $countStmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['kegiatan_id']]['santri'][$row['status']] = (int)$row['total'];
}
$countStmt->close();

$countStmt2 = $conn->prepare("
    SELECT kegiatan_id, status, COUNT(*) AS total FROM absensi_asatidz
    WHERE tanggal = ? AND asatidz_id IS NOT NULL GROUP BY kegiatan_id, status
");
$countStmt2->bind_param("s", $date);
$countStmt2->execute();
$res2 = $countStmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $counts[$row['kegiatan_id']]['asatidz'][$row['status']] = (int)$row['total'];
}
$countStmt2->close();

foreach ($activities as &$activity) {
    $id = $activity['id'];
    $activity['santri_alpha'] = $counts[$id]['santri']['alpha'] ?? 0;
    $activity['santri_sakit'] = $counts[$id]['santri']['sakit'] ?? 0;
    $activity['santri_izin'] = $counts[$id]['santri']['izin'] ?? 0;
    $activity['asatidz_alpha'] = $counts[$id]['asatidz']['alpha'] ?? 0;
    $activity['asatidz_sakit'] = $counts[$id]['asatidz']['sakit'] ?? 0;
    $activity['asatidz_izin'] = $counts[$id]['asatidz']['izin'] ?? 0;
}
unset($activity);

json_response(["success" => true, "tanggal" => $date, "kegiatan" => $activities]);
