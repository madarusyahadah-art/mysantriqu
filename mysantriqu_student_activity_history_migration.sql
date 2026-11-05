CREATE TABLE IF NOT EXISTS student_activity_imports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_hash CHAR(64) NOT NULL,
  source_filename VARCHAR(255) NOT NULL,
  academic_year_start SMALLINT UNSIGNED NOT NULL,
  expected_events INT UNSIGNED NOT NULL,
  imported_events INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('processing', 'completed') NOT NULL DEFAULT 'processing',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_activity_import_hash (file_hash),
  KEY idx_student_activity_import_year_status (academic_year_start, status),
  CONSTRAINT fk_student_activity_import_user
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_activity_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  import_id BIGINT UNSIGNED NOT NULL,
  santri_id INT UNSIGNED NOT NULL,
  activity_name VARCHAR(255) NOT NULL,
  occurred_on DATE NOT NULL,
  slot_number TINYINT UNSIGNED NOT NULL,
  source_sheet VARCHAR(20) NOT NULL,
  source_row SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_activity_source_cell
    (import_id, source_sheet, source_row, activity_name, slot_number),
  KEY idx_student_activity_student_date (santri_id, occurred_on, activity_name),
  KEY idx_student_activity_import (import_id),
  CONSTRAINT fk_student_activity_history_import
    FOREIGN KEY (import_id) REFERENCES student_activity_imports (id) ON DELETE CASCADE,
  CONSTRAINT fk_student_activity_history_santri
    FOREIGN KEY (santri_id) REFERENCES santri (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
