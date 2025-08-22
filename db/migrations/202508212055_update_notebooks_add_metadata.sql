-- 202508212055_update_notebooks_add_metadata.sql
-- Migration: Update `notebooks` table
-- Changes:
--   - Rename column `name` → `title`
--   - Add `subtitle` (short secondary title)
--   - Add `description` (longer text)
--   - Add `color_bg` (hex background color, e.g. #FFFFFF)
--   - Add `color_text` (hex text color, e.g. #000000)

ALTER TABLE notebooks
  CHANGE COLUMN `name` `title` VARCHAR(255) NULL,
  ADD COLUMN `subtitle`    VARCHAR(255) NULL AFTER `title`,
  ADD COLUMN `description` TEXT         NULL AFTER `subtitle`,
  ADD COLUMN `color_bg`    VARCHAR(7)   NULL AFTER `description`,
  ADD COLUMN `color_text`  VARCHAR(7)   NULL AFTER `color_bg`;
