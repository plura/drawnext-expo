-- DrawNext Migration: Add admin flag to users
-- ------------------------------------------------------------
-- Purpose: Allow role-gated admin UI/actions
-- Affects: users
-- Notes:
-- 	- New column defaults to 0 (non-admin)
-- 	- Run once in each environment
-- 	- Safe to run on existing data

ALTER TABLE `users`
	ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `test`;

-- Optional: seed initial admins (replace emails)
-- UPDATE `users` SET `is_admin` = 1 WHERE `email` IN (
-- 	'you@example.com',
-- 	'teammate1@example.com',
-- 	'teammate2@example.com'
-- );

-- Rollback (manual):
-- ALTER TABLE `users` DROP COLUMN `is_admin`;
