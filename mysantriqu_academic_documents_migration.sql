USE mysantriqu;

CREATE TABLE IF NOT EXISTS academic_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_type VARCHAR(20) NOT NULL,
  student_id INT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  author_name VARCHAR(255) NOT NULL DEFAULT '',
  author_status VARCHAR(20) NOT NULL DEFAULT '',
  academic_period VARCHAR(50) NOT NULL DEFAULT '',
  publication_year SMALLINT UNSIGNED NULL,
  description TEXT NULL,
  original_file_name VARCHAR(255) NOT NULL,
  storage_name CHAR(68) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_academic_documents_storage_name (storage_name),
  UNIQUE KEY uq_academic_documents_khs_period (document_type, student_id, academic_period),
  KEY idx_academic_documents_type_created (document_type, created_at),
  KEY idx_academic_documents_student_type (student_id, document_type),
  CONSTRAINT fk_academic_documents_student
    FOREIGN KEY (student_id) REFERENCES santri (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_academic_documents_uploader
    FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE academic_documents
  MODIFY storage_name CHAR(68) NOT NULL;

ALTER TABLE academic_documents
  ADD COLUMN IF NOT EXISTS author_status VARCHAR(20) NOT NULL DEFAULT '' AFTER author_name;
