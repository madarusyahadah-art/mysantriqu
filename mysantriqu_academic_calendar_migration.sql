USE `mysantriqu`;

ALTER TABLE `academic_calendar_imports`
  DROP INDEX `uq_academic_calendar_year`,
  ADD COLUMN `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published' AFTER `imported_by`,
  ADD COLUMN `published_at` DATETIME NULL AFTER `created_at`,
  ADD KEY `idx_academic_calendar_status_year` (`status`, `tahun_ajaran`, `id`);

UPDATE `academic_calendar_imports`
SET `published_at` = `created_at`
WHERE `status` = 'published' AND `published_at` IS NULL;

ALTER TABLE `academic_calendar_events`
  ADD COLUMN `keterangan` TEXT NULL AFTER `nama_kegiatan`,
  ADD COLUMN `kategori` VARCHAR(50) NOT NULL DEFAULT 'Lainnya' AFTER `keterangan`,
  ADD COLUMN `is_cancelled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_semester_end`;

CREATE TABLE IF NOT EXISTS `academic_calendar_changes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_id` INT UNSIGNED NOT NULL,
  `event_id` INT UNSIGNED NULL,
  `change_type` VARCHAR(30) NOT NULL,
  `old_value` JSON NULL,
  `new_value` JSON NULL,
  `reason` VARCHAR(1000) NOT NULL,
  `changed_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_calendar_changes_import_created` (`import_id`, `created_at`),
  KEY `idx_calendar_changes_event` (`event_id`),
  CONSTRAINT `fk_calendar_changes_import`
    FOREIGN KEY (`import_id`) REFERENCES `academic_calendar_imports` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_changes_event`
    FOREIGN KEY (`event_id`) REFERENCES `academic_calendar_events` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_changes_user`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `academic_calendar_push_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `endpoint_hash` CHAR(64) NOT NULL,
  `endpoint` TEXT NOT NULL,
  `p256dh` VARCHAR(255) NOT NULL,
  `auth` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calendar_push_endpoint_hash` (`endpoint_hash`),
  KEY `idx_calendar_push_user` (`user_id`),
  CONSTRAINT `fk_calendar_push_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;