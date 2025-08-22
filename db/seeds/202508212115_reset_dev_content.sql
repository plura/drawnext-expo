-- reset_dev_content.sql
-- Purpose: wipe content tables and reseed notebooks/sections for dev/testing.
-- Leaves `config` and `users` intact.

-- --- Safety: disable checks while truncating
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;

-- Wipe content tables
TRUNCATE TABLE drawing_neighbors;
TRUNCATE TABLE files;
TRUNCATE TABLE drawings;
TRUNCATE TABLE sections;
TRUNCATE TABLE notebooks;

-- Restore checks
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

-- ------------------------------------------------------------------
-- SEED: Notebooks (IDs will auto-increment 1..4)
-- NOTE: description left NULL intentionally; adjust `pages` if needed.
-- ------------------------------------------------------------------
INSERT INTO notebooks
  (`title`,       `subtitle`,                                                      `description`, `color_bg`, `color_text`, `pages`)
VALUES
  ('Notebook I',  'In the future, nature will take over the world',                NULL,          'c49ee3',  'ffffff',     50),
  ('Notebook II', 'In the future, thanks to AI, no one will need to work anymore', NULL,          'fabf4a',  'ffffff',     50),
  ('Notebook III','In the future, our bodies will be free from biological limits', NULL,          '7dab94',  'ffffff',     50),
  ('Notebook IV', 'In the future, the population will continue to grow at the same rate as today',
                                                                                   NULL,          '8aa8d4',  'ffffff',     50);

-- ------------------------------------------------------------------
-- SEED: Sections for each notebook (1..4): Space / Land / Ocean
-- Labels: position 1=Space, 2=Land, 3=Ocean
-- ------------------------------------------------------------------

-- Notebook 1
INSERT INTO sections (`notebook_id`, `position`, `label`) VALUES
(1, 1, 'Space'), (1, 2, 'Land'), (1, 3, 'Ocean');

-- Notebook 2
INSERT INTO sections (`notebook_id`, `position`, `label`) VALUES
(2, 1, 'Space'), (2, 2, 'Land'), (2, 3, 'Ocean');

-- Notebook 3
INSERT INTO sections (`notebook_id`, `position`, `label`) VALUES
(3, 1, 'Space'), (3, 2, 'Land'), (3, 3, 'Ocean');

-- Notebook 4
INSERT INTO sections (`notebook_id`, `position`, `label`) VALUES
(4, 1, 'Space'), (4, 2, 'Land'), (4, 3, 'Ocean');
