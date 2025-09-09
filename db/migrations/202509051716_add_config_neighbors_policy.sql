-- 202509051716_add_config_neighbors_policy.sql
-- Purpose: introduce a config flag to control how neighbors are handled on submission.
-- Key: submissions.neighbors.policy
-- Values:
--   - 'strict'     → current behavior (any invalid neighbor blocks submission)
--   - 'permissive' → proceed; drop invalid neighbors
--   - 'ignore'     → (optional) proceed; skip all neighbors
-- Default remains 'strict'. We DO NOT overwrite an existing runtime value.

INSERT INTO config (`key`, `value`, `default`, `options`, `value_type`)
VALUES (
  'submissions.neighbors.policy',
  'strict',                        -- current value on first install
  'strict',                        -- default/fallback
  'strict,permissive,ignore',      -- remove ",ignore" if you don't want that mode
  'string'
)
ON DUPLICATE KEY UPDATE
  `default`    = VALUES(`default`),     -- keep metadata in sync
  `options`    = VALUES(`options`),
  `value_type` = VALUES(`value_type`);  -- but do NOT overwrite the current 'value'

-- Verify (optional):
-- SELECT `key`,`value`,`default`,`options`,`value_type`
-- FROM config WHERE `key`='submissions.neighbors.policy';
