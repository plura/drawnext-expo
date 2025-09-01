/* 
  Migration: Standardize charset/collation to utf8mb4 / utf8mb4_unicode_ci
  Scope: database + all app tables
  Notes:
    - Compatible with MySQL 5.7/8.0 and MariaDB.
    - Avoids MySQL-8-only collations like utf8mb4_0900_ai_ci for portability.
*/

-- 👉 Set this once depending on environment
-- SET @DB := 'db_remote_name';   -- remote
SET @DB := 'db';                     -- local ddev (example)

-- Session charset/collation
SET NAMES utf8mb4;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET collation_connection = utf8mb4_unicode_ci;

-- 1) Database default
SET @sql = CONCAT('ALTER DATABASE `', @DB, '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Convert each table
SET FOREIGN_KEY_CHECKS = 0;

SET @tables = 'config,users,notebooks,sections,drawings,files,drawing_neighbors';

-- Iterate manually (no cursor, just build queries)
-- You can copy/paste these lines per table:
SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`config` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`notebooks` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`sections` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`drawings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`files` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('ALTER TABLE `', @DB, '`.`drawing_neighbors` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- 3) Verification
SHOW VARIABLES LIKE 'character_set%';
SHOW VARIABLES LIKE 'collation%';

SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = @DB;

SELECT TABLE_NAME, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @DB
ORDER BY TABLE_NAME;

-- Example: column-level check for users
SET @sql = CONCAT('SHOW FULL COLUMNS FROM `', @DB, '`.`users`;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
