-- === DRAWINGS ===
-- 1) Add columns as NULLable so we can backfill safely
ALTER TABLE drawings
  ADD COLUMN authored_at DATETIME NULL AFTER created_at,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
                               AFTER authored_at;

-- 2) Backfill authored_at from created_at for all existing rows
UPDATE drawings
SET authored_at = created_at
WHERE authored_at IS NULL;

-- 3) Make authored_at NOT NULL now that it’s populated
ALTER TABLE drawings
  MODIFY COLUMN authored_at DATETIME NOT NULL;

-- (Optional) If you want updated_at set initially too:
UPDATE drawings
SET updated_at = created_at
WHERE updated_at IS NULL;


-- === NOTEBOOKS ===
ALTER TABLE notebooks
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
                               AFTER created_at;

-- Backfill updated_at to match created_at for existing rows
UPDATE notebooks
SET updated_at = created_at
WHERE updated_at IS NULL;


-- === SECTIONS ===
ALTER TABLE sections
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP
                               AFTER created_at;

-- Backfill updated_at to match created_at for existing rows
UPDATE sections
SET updated_at = created_at
WHERE updated_at IS NULL;
