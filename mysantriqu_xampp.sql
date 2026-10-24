CREATE DATABASE IF NOT EXISTS `mysantriqu`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mysantriqu`;

CREATE TABLE IF NOT EXISTS `santri` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `asal` VARCHAR(255) NOT NULL DEFAULT '',
  `angkatan` VARCHAR(50) NOT NULL DEFAULT '',
  `semester_aktif` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_santri_nama` (`nama`),
  KEY `idx_santri_angkatan` (`angkatan`),
  KEY `idx_santri_semester` (`semester_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `photo` TEXT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'mahasantri',
  `santri_id` INT UNSIGNED NULL,
  `role_selected` TINYINT(1) NOT NULL DEFAULT 0,
  `password_hash` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_santri_id` (`santri_id`),
  CONSTRAINT `fk_users_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asatidz` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `mata_pelajaran` VARCHAR(150) NOT NULL DEFAULT '',
  `jabatan` VARCHAR(150) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asatidz_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kegiatan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kegiatan` VARCHAR(255) NOT NULL,
  `kategori` VARCHAR(50) NOT NULL DEFAULT 'wajib',
  `frekuensi` VARCHAR(30) NOT NULL DEFAULT 'harian',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `slide_title` VARCHAR(255) NOT NULL DEFAULT '',
  `slide_description` TEXT NULL,
  `slide_image` TEXT NULL,
  `slide_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kegiatan_active_order` (`is_active`, `slide_order`),
  KEY `idx_kegiatan_nama` (`nama_kegiatan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kegiatan_semester` (
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `semester` TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (`kegiatan_id`, `semester`),
  CONSTRAINT `fk_kegiatan_semester_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kegiatan_asatidz` (
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `asatidz_id` INT UNSIGNED NOT NULL,
  `peran` VARCHAR(30) NOT NULL DEFAULT 'pengampu',
  PRIMARY KEY (`kegiatan_id`, `asatidz_id`),
  KEY `idx_kegiatan_asatidz_asatidz` (`asatidz_id`),
  CONSTRAINT `fk_kegiatan_asatidz_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_kegiatan_asatidz_asatidz`
    FOREIGN KEY (`asatidz_id`) REFERENCES `asatidz` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absensi_sesi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absensi_sesi_kegiatan_tanggal` (`kegiatan_id`, `tanggal`),
  KEY `idx_absensi_sesi_tanggal` (`tanggal`),
  KEY `idx_absensi_sesi_created_by` (`created_by`),
  CONSTRAINT `fk_absensi_sesi_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_sesi_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absensi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `santri_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'hadir',
  `keterangan` TEXT NULL,
  `tindak_lanjut` TEXT NULL,
  `input_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absensi_kegiatan_tanggal_santri` (`kegiatan_id`, `tanggal`, `santri_id`),
  KEY `idx_absensi_santri_tanggal` (`santri_id`, `tanggal`),
  KEY `idx_absensi_input_by` (`input_by`),
  KEY `idx_absensi_tanggal_status` (`tanggal`, `status`),
  CONSTRAINT `fk_absensi_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_user`
    FOREIGN KEY (`input_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `absensi_asatidz` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `asatidz_id` INT UNSIGNED NULL,
  `nama_asatidz` VARCHAR(255) NOT NULL,
  `mata_pelajaran` VARCHAR(150) NOT NULL DEFAULT '',
  `peran` VARCHAR(30) NOT NULL DEFAULT 'pengampu',
  `status` VARCHAR(30) NOT NULL DEFAULT 'hadir',
  `keterangan` TEXT NULL,
  `tindak_lanjut` TEXT NULL,
  `input_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_absensi_asatidz_kegiatan_tanggal_asatidz` (`kegiatan_id`, `tanggal`, `asatidz_id`),
  KEY `idx_absensi_asatidz_tanggal_status` (`tanggal`, `status`),
  KEY `idx_absensi_asatidz_input_by` (`input_by`),
  KEY `idx_absensi_asatidz_nama` (`nama_asatidz`),
  CONSTRAINT `fk_absensi_asatidz_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_asatidz_asatidz`
    FOREIGN KEY (`asatidz_id`) REFERENCES `asatidz` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_absensi_asatidz_user`
    FOREIGN KEY (`input_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laporan_kegiatan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kegiatan_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'selesai',
  `dilaporkan_oleh` INT UNSIGNED NULL,
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_laporan_kegiatan_tanggal` (`kegiatan_id`, `tanggal`),
  KEY `idx_laporan_kegiatan_tanggal` (`tanggal`),
  KEY `idx_laporan_kegiatan_user` (`dilaporkan_oleh`),
  CONSTRAINT `fk_laporan_kegiatan_kegiatan`
    FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_laporan_kegiatan_user`
    FOREIGN KEY (`dilaporkan_oleh`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jenis_pelanggaran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(255) NOT NULL,
  `poin_default` INT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jenis_pelanggaran_nama` (`nama`),
  KEY `idx_jenis_pelanggaran_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pelanggaran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `santri_id` INT UNSIGNED NOT NULL,
  `jenis_pelanggaran_id` INT UNSIGNED NULL,
  `tanggal` DATE NOT NULL,
  `jenis_pelanggaran` VARCHAR(255) NOT NULL,
  `poin` INT UNSIGNED NOT NULL DEFAULT 1,
  `keterangan` TEXT NULL,
  `status_tindak_lanjut` VARCHAR(30) NOT NULL DEFAULT 'belum',
  `catatan_bk` TEXT NULL,
  `input_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pelanggaran_santri_tanggal` (`santri_id`, `tanggal`),
  KEY `idx_pelanggaran_tanggal_jenis` (`tanggal`, `jenis_pelanggaran`),
  KEY `idx_pelanggaran_jenis_id` (`jenis_pelanggaran_id`),
  KEY `idx_pelanggaran_input_by` (`input_by`),
  CONSTRAINT `fk_pelanggaran_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pelanggaran_jenis`
    FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pelanggaran_user`
    FOREIGN KEY (`input_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dashboard_slide_state` (
  `user_id` INT UNSIGNED NOT NULL,
  `active_index` INT UNSIGNED NOT NULL DEFAULT 0,
  `state_date` DATE NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_dashboard_slide_state_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
