/* 
  Migration: Add first_name and last_name to users
  Date: 2025-09-01 04:39
  Notes:
    - Nullable, since exhibition signups are email-only.
    - utf8mb4_unicode_ci for multilingual input.
    - Columns placed after email for logical grouping.
*/

ALTER TABLE `users`
  ADD COLUMN `first_name` VARCHAR(191) 
    COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `email`,
  ADD COLUMN `last_name` VARCHAR(191) 
    COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `first_name`;
