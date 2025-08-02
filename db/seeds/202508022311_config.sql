INSERT INTO config (`key`, `value`, `default`, `options`, `value_type`) VALUES
	('allowed_mime_types', 'image/jpeg,image/png,image/webp', 'image/jpeg,image/png,image/webp', NULL, 'csv'),
	('allow_submission_registry', 'false', 'false', 'true,false', 'boolean'),
	('auth_method', 'EMAIL_ONLY', 'EMAIL_ONLY', 'EMAIL_ONLY,EMAIL_PASSWORD,MAGIC_LINK', 'string'),
	('max_upload_size', '2', '2', NULL, 'int'),
	('upload_directory', './uploads', './uploads', NULL, 'string');
