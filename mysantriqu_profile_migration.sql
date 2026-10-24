USE `mysantriqu`;

ALTER TABLE `santri`
  ADD COLUMN `no_ktm` VARCHAR(50) NULL AFTER `nama`;
