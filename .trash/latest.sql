-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: db
-- ------------------------------------------------------
-- Server version	10.11.11-MariaDB-ubu2204-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `config`
--

DROP TABLE IF EXISTS `config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `config` (
  `key` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL,
  `default` varchar(255) DEFAULT NULL COMMENT 'Optional fallback value',
  `options` varchar(255) DEFAULT NULL COMMENT 'CSV of allowed values',
  `value_type` enum('boolean','csv','int','number','string') NOT NULL DEFAULT 'string' COMMENT 'Field type for value validation',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='App configuration. For upload_directory: Prefer project-relative paths (./uploads/) over absolute paths. Root paths (/) are forbidden.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES
('allowed_mime_types','image/jpeg,image/png,image/webp','image/jpeg,image/png',NULL,'csv'),
('allow_submission_registry','true','false','true,false','boolean'),
('auth_method','email_only','EMAIL_ONLY','EMAIL_ONLY,EMAIL_PASSWORD,MAGIC_LINK','string'),
('max_upload_size','5','2',NULL,'int'),
('upload_directory','./uploads','./uploads',NULL,'string');
/*!40000 ALTER TABLE `config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drawing_neighbors`
--

DROP TABLE IF EXISTS `drawing_neighbors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drawing_neighbors`
--

LOCK TABLES `drawing_neighbors` WRITE;
/*!40000 ALTER TABLE `drawing_neighbors` DISABLE KEYS */;
/*!40000 ALTER TABLE `drawing_neighbors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drawings`
--

DROP TABLE IF EXISTS `drawings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `drawings` (
  `drawing_id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `notebook_id` int(11) NOT NULL COMMENT 'Denormalized for app-side uniqueness check on (notebook, page, section). Populated via application logic.',
  `test` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`drawing_id`),
  KEY `drawings_users_FK` (`user_id`),
  KEY `drawings_sections_FK` (`section_id`),
  KEY `drawings_notebooks_FK` (`notebook_id`),
  CONSTRAINT `drawings_notebooks_FK` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`notebook_id`),
  CONSTRAINT `drawings_sections_FK` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`),
  CONSTRAINT `drawings_users_FK` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drawings`
--

LOCK TABLES `drawings` WRITE;
/*!40000 ALTER TABLE `drawings` DISABLE KEYS */;
INSERT INTO `drawings` VALUES
(9,'2025-07-29 00:13:18',2,1,1,1,0),
(10,'2025-07-29 21:01:41',2,1,2,1,0);
/*!40000 ALTER TABLE `drawings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `file_id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `drawing_id` int(11) NOT NULL COMMENT 'Currently 1:1 with drawings, but may allow multiple files per drawing in future',
  `stored_filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `filesize` int(11) DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `width` smallint(5) unsigned DEFAULT NULL,
  `height` smallint(5) unsigned DEFAULT NULL,
  `test` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag for test environment files',
  PRIMARY KEY (`file_id`),
  UNIQUE KEY `drawing_id` (`drawing_id`),
  CONSTRAINT `files_drawings_FK` FOREIGN KEY (`drawing_id`) REFERENCES `drawings` (`drawing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
INSERT INTO `files` VALUES
(1,'2025-07-29 00:13:18',9,'f6e4dc6e5cbcdc198667aec1074d3922.jpg','drawing.jpg',51159,'image/jpeg',377,512,1),
(2,'2025-07-29 21:01:41',10,'b908db4132c4a3ce717d758487ab89ed.jpg','drawing.jpg',51159,'image/jpeg',377,512,1);
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notebooks`
--

DROP TABLE IF EXISTS `notebooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notebooks` (
  `notebook_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pages` int(11) DEFAULT NULL,
  PRIMARY KEY (`notebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notebooks`
--

LOCK TABLES `notebooks` WRITE;
/*!40000 ALTER TABLE `notebooks` DISABLE KEYS */;
INSERT INTO `notebooks` VALUES
(1,'Notebook I','2025-07-24 17:19:23',NULL),
(2,'Notebook II','2025-07-24 17:19:23',NULL),
(3,'Notebook III','2025-07-24 17:19:23',NULL),
(4,'Notebook IV','2025-07-24 17:19:23',NULL),
(5,'Notebook V','2025-07-24 17:19:23',NULL);
/*!40000 ALTER TABLE `notebooks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL AUTO_INCREMENT,
  `notebook_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `unique_notebook_position` (`notebook_id`,`position`),
  CONSTRAINT `sections_notebooks_FK` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`notebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES
(1,1,1,'A','2025-07-24 17:19:58'),
(2,1,2,'B','2025-07-24 17:19:58'),
(3,1,3,'C','2025-07-24 17:19:58'),
(4,2,1,'A','2025-07-24 17:19:58'),
(5,2,2,'B','2025-07-24 17:19:58'),
(6,2,3,'C','2025-07-24 17:19:58'),
(7,3,1,'A','2025-07-24 17:19:58'),
(8,3,2,'B','2025-07-24 17:19:58'),
(9,3,3,'C','2025-07-24 17:19:58'),
(10,4,1,'A','2025-07-24 17:19:58'),
(11,4,2,'B','2025-07-24 17:19:58'),
(12,4,3,'C','2025-07-24 17:19:58'),
(13,5,1,'A','2025-07-24 17:19:58'),
(14,5,2,'B','2025-07-24 17:19:58'),
(15,5,3,'C','2025-07-24 17:19:58');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `test` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'trpsimoes@gmail.com',NULL,'2025-07-28 20:28:35',NULL,0),
(2,'test@example.com',NULL,'2025-07-28 22:13:16',NULL,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-04 21:33:29
