ALTER TABLE users
  ADD COLUMN phone_number VARCHAR(20) NULL AFTER email,
  ADD COLUMN phone_verified_at DATETIME NULL AFTER phone_number,
  ADD UNIQUE KEY uq_users_phone_number (phone_number);
