USE `mysantriqu`;

ALTER TABLE `academic_calendar_events`
  ADD COLUMN `jam_mulai` TIME NULL AFTER `tanggal_mulai`,
  ADD COLUMN `jam_selesai` TIME NULL AFTER `tanggal_selesai`;
