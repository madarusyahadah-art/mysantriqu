USE `mysantriqu`;

CREATE TABLE IF NOT EXISTS `student_journal_entries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `santri_id` INT UNSIGNED NOT NULL,
  `entry_date` DATE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_journal_date` (`santri_id`, `entry_date`),
  CONSTRAINT `fk_student_journal_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_thesis_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `santri_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'menunggu',
  `admin_note` TEXT NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_thesis_santri_date` (`santri_id`, `submitted_at`),
  KEY `idx_student_thesis_status` (`status`),
  CONSTRAINT `fk_student_thesis_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_thesis_reviewer`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_khs_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `santri_id` INT UNSIGNED NOT NULL,
  `semester` TINYINT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_khs_semester` (`santri_id`, `semester`),
  CONSTRAINT `fk_student_khs_santri`
    FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_khs_uploader`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
