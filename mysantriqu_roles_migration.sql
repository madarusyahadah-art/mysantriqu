USE `mysantriqu`;

UPDATE `users`
SET `role` = CASE `role`
  WHEN 'santri' THEN 'mahasantri'
  WHEN 'supervisor' THEN 'musyrif'
  WHEN 'kepala' THEN 'kajur'
  ELSE `role`
END
WHERE `role` IN ('santri', 'supervisor', 'kepala');

ALTER TABLE `users`
  MODIFY `role` VARCHAR(30) NOT NULL DEFAULT 'mahasantri',
  MODIFY `role_selected` TINYINT(1) NOT NULL DEFAULT 1;
