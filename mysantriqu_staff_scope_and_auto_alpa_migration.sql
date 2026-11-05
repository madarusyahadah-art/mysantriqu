CREATE TABLE IF NOT EXISTS staff_santri_assignments (
  user_id INT UNSIGNED NOT NULL,
  santri_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, santri_id),
  KEY idx_staff_santri_assignment_student (santri_id),
  CONSTRAINT fk_staff_santri_assignment_user
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_staff_santri_assignment_student
    FOREIGN KEY (santri_id) REFERENCES santri (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pelanggaran
  ADD COLUMN absensi_id INT UNSIGNED NULL AFTER santri_id,
  ADD UNIQUE KEY uq_pelanggaran_absensi (absensi_id),
  ADD CONSTRAINT fk_pelanggaran_absensi
    FOREIGN KEY (absensi_id) REFERENCES absensi (id) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO pelanggaran
  (santri_id, absensi_id, jenis_pelanggaran_id, tanggal, jenis_pelanggaran, poin,
   keterangan, status_tindak_lanjut, catatan_bk, input_by)
SELECT a.santri_id, a.id, NULL, a.tanggal, 'Ketidakhadiran (Alpa)', 0,
       CONCAT('Tidak hadir pada kegiatan: ', k.nama_kegiatan),
       'belum', '', a.input_by
FROM absensi a
INNER JOIN kegiatan k ON k.id = a.kegiatan_id
WHERE LOWER(TRIM(a.status)) = 'alpa';
