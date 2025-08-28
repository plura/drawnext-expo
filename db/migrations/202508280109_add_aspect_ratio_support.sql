-- 202508280109_add_aspect_ratio_support.sql
-- Purpose:
-- 	- Add a global default aspect ratio to the config table (images.aspect_ratio).
-- Notes:
-- 	- Stored as CSV "w,h" so your Config parser (value_type='csv') returns an array.
-- 	- Default set to 1:1 (square). Adjust as needed later.

-- Global default: images.aspect_ratio as CSV "w,h"
INSERT INTO `config` (`key`, `value`, `default`, `options`, `value_type`)
VALUES ('images.aspect_ratio', '1,1', '1,1', NULL, 'csv')
ON DUPLICATE KEY UPDATE
	`value`=VALUES(`value`),
	`default`=VALUES(`default`),
	`value_type`=VALUES(`value_type`);


-- (Optional) Sanity check:
-- SELECT * FROM `config` WHERE `key`='images.aspect_ratio';
