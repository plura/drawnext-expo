-- Migration: create_drawing_neighbors
-- Created at: 2025-07-29 19:32:00

BEGIN;

-- db.drawing_neighbors definition

CREATE TABLE `drawing_neighbors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drawing_id` int(11) NOT NULL COMMENT 'Reference to the parent drawing',
  `neighbor_section_id` int(11) NOT NULL COMMENT 'Section of the neighboring drawing',
  `neighbor_page` int(11) NOT NULL COMMENT 'Page where neighbor exists',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Auto-track association time',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_neighbor_association` (`drawing_id`,`neighbor_section_id`,`neighbor_page`),
  KEY `neighbor_section_id` (`neighbor_section_id`),
  CONSTRAINT `drawing_neighbors_ibfk_1` FOREIGN KEY (`drawing_id`) REFERENCES `drawings` (`drawing_id`) ON DELETE CASCADE,
  CONSTRAINT `drawing_neighbors_ibfk_2` FOREIGN KEY (`neighbor_section_id`) REFERENCES `sections` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks physical proximity of drawings in notebooks';

COMMIT;