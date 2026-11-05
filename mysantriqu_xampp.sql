CREATE DATABASE IF NOT EXISTS mysantriqu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mysantriqu;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NULL,
  phone_number VARCHAR(20) NULL,
  phone_verified_at DATETIME NULL,
  photo TEXT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'mahasantri',
  santri_id INT UNSIGNED NULL,
  role_selected TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone_number (phone_number),
  KEY idx_users_santri_id (santri_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;