-- Rename modified_at -> updated_at while preserving values
ALTER TABLE users
  CHANGE COLUMN modified_at updated_at TIMESTAMP NULL DEFAULT NULL
                                  ON UPDATE CURRENT_TIMESTAMP;

-- If you also want to ensure existing rows have an initial updated_at:
UPDATE users
SET updated_at = created_at
WHERE updated_at IS NULL;
