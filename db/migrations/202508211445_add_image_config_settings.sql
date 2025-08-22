BEGIN;

-- Display image longest side (px) → 2048
INSERT INTO config (`key`,`value`,`default`,`options`,`value_type`)
VALUES ('images.display_max_edge','2048','2048',NULL,'int')
ON DUPLICATE KEY UPDATE
  `value`=VALUES(`value`),
  `default`=VALUES(`default`),
  `options`=VALUES(`options`),
  `value_type`=VALUES(`value_type`);

-- Thumbnail longest side (px) → keep 400
INSERT INTO config (`key`,`value`,`default`,`options`,`value_type`)
VALUES ('images.thumb_max_edge','400','400',NULL,'int')
ON DUPLICATE KEY UPDATE
  `value`=VALUES(`value`),
  `default`=VALUES(`default`),
  `options`=VALUES(`options`),
  `value_type`=VALUES(`value_type`);

-- WebP quality (0–100) → 82
INSERT INTO config (`key`,`value`,`default`,`options`,`value_type`)
VALUES ('images.webp_quality','82','82',NULL,'int')
ON DUPLICATE KEY UPDATE
  `value`=VALUES(`value`),
  `default`=VALUES(`default`),
  `options`=VALUES(`options`),
  `value_type`=VALUES(`value_type`);

COMMIT;
