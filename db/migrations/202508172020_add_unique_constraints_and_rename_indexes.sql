-- 1) Users: unique email (also serves as an index for lookups)
ALTER TABLE users
  ADD UNIQUE KEY uniq_users_email (email);

-- 2) Drawings: enforce unique slot
ALTER TABLE drawings
  ADD UNIQUE KEY uniq_notebook_section_page (notebook_id, section_id, page);

-- 3) Rename indexes to use uq_ prefix
-- users: uniq_ → uq_
ALTER TABLE users RENAME INDEX uniq_users_email TO uq_users_email;

-- sections: unique_ → uq_
ALTER TABLE sections RENAME INDEX unique_notebook_position TO uq_notebook_position;

-- drawings: uniq_ → uq_
ALTER TABLE drawings RENAME INDEX uniq_notebook_section_page TO uq_notebook_section_page;

-- drawing_neighbors: unique_ → uq_
ALTER TABLE drawing_neighbors RENAME INDEX unique_neighbor_association TO uq_neighbor_association;
