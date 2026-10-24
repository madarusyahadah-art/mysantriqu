USE `mysantriqu`;

CREATE TABLE IF NOT EXISTS `academic_calendar_imports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `judul` VARCHAR(255) NOT NULL,
  `tahun_ajaran` VARCHAR(9) NOT NULL,
  `nama_berkas` VARCHAR(255) NOT NULL,
  `imported_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_academic_calendar_year` (`tahun_ajaran`),
  CONSTRAINT `fk_academic_calendar_import_user`
    FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_calendar_events` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_id` INT UNSIGNED NOT NULL,
  `tanggal_mulai` DATE NULL,
  `tanggal_selesai` DATE NULL,
  `nama_kegiatan` VARCHAR(1000) NOT NULL,
  `is_semester_end` TINYINT(1) NOT NULL DEFAULT 0,
  `semester_incremented_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_academic_calendar_event_dates` (`tanggal_mulai`, `tanggal_selesai`),
  KEY `idx_academic_calendar_rollover` (`is_semester_end`, `semester_incremented_at`, `tanggal_selesai`),
  CONSTRAINT `fk_academic_calendar_event_import`
    FOREIGN KEY (`import_id`) REFERENCES `academic_calendar_imports` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
