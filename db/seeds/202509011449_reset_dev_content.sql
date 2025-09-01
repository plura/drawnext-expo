-- reset_dev_content.sql
-- Purpose: wipe content + users, reseed notebooks/sections, and add 3 admins.
-- This WILL delete ALL users and recreate only the three below.

-- USE drawnext;
SET NAMES utf8mb4;

START TRANSACTION;

-- 1) Delete in FK-safe order (children → parents)
DELETE FROM drawing_neighbors;
DELETE FROM files;
DELETE FROM drawings;
DELETE FROM sections;
DELETE FROM notebooks;
DELETE FROM users;

-- 2) Reset AUTO_INCREMENT
ALTER TABLE drawing_neighbors AUTO_INCREMENT = 1;
ALTER TABLE files            AUTO_INCREMENT = 1;
ALTER TABLE drawings         AUTO_INCREMENT = 1;
ALTER TABLE sections         AUTO_INCREMENT = 1;
ALTER TABLE notebooks        AUTO_INCREMENT = 1;
ALTER TABLE users            AUTO_INCREMENT = 1;

-- 3) Reseed notebooks (IDs 1..4)
INSERT INTO notebooks
  (`title`,        `subtitle`,                                                       `description`, `color_bg`, `color_text`, `pages`)
VALUES
  ('Notebook I',   'In the future, nature will take over the world',                 NULL,          'c49ee3',  'ffffff',     50),
  ('Notebook II',  'In the future, thanks to AI, no one will need to work anymore',  NULL,          'fabf4a',  'ffffff',     50),
  ('Notebook III', 'In the future, our bodies will be free from biological limits',  NULL,          '7dab94',  'ffffff',     50),
  ('Notebook IV',  'In the future, the population will continue to grow at the same rate as today',
                                                                                      NULL,          '8aa8d4',  'ffffff',     50);

-- 4) Reseed sections (Space/Land/Ocean for each notebook)
INSERT INTO sections (`notebook_id`, `position`, `label`) VALUES
(1, 1, 'Space'), (1, 2, 'Land'), (1, 3, 'Ocean'),
(2, 1, 'Space'), (2, 2, 'Land'), (2, 3, 'Ocean'),
(3, 1, 'Space'), (3, 2, 'Land'), (3, 3, 'Ocean'),
(4, 1, 'Space'), (4, 2, 'Land'), (4, 3, 'Ocean');

-- 5) Seed ONLY the three admin users (with names)
INSERT INTO users (email, first_name, last_name, password_hash, test, is_admin)
VALUES
  ('tiago.simoes@plura.pt',  'Tiago', 'Simões',   NULL, 0, 1),
  ('joanabrigido@toyno.com', 'Joana', 'Brígido',  NULL, 0, 1),
  ('aldotornaghi@toyno.com', 'Aldo',  'Tornaghi', NULL, 0, 1);

COMMIT;

-- (Optional quick checks)
-- SELECT user_id, email, first_name, last_name, is_admin FROM users ORDER BY user_id;
-- SELECT notebook_id, title FROM notebooks ORDER BY notebook_id;
-- SELECT section_id, notebook_id, position, label FROM sections ORDER BY notebook_id, position;
