/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.7.2-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: db
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
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='App configuration. For upload_directory: Prefer project-relative paths (./uploads/) over absolute paths. Root paths (/) are forbidden.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config`
--

LOCK TABLES `config` WRITE;
/*!40000 ALTER TABLE `config` DISABLE KEYS */;
INSERT INTO `config` VALUES
('images.aspect_ratio','1,1','1,1',NULL,'csv'),
('images.display_max_edge','2048','2048',NULL,'int'),
('images.thumb_max_edge','400','400',NULL,'int'),
('images.webp_quality','82','82',NULL,'int'),
('notebooks.pages.fallback_count','10','10',NULL,'int'),
('submissions.allow_registration','true','false','true,false','boolean'),
('uploads.allowed_mime_types','image/jpeg,image/png,image/webp','image/jpeg,image/png',NULL,'csv'),
('uploads.directory','./uploads','./uploads',NULL,'string'),
('uploads.max_size_mb','5','2',NULL,'int'),
('users.auth_method','email_only','EMAIL_ONLY','email_only,email_password,magic_link','string');
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
  UNIQUE KEY `uq_neighbor_association` (`drawing_id`,`neighbor_section_id`,`neighbor_page`),
  KEY `neighbor_section_id` (`neighbor_section_id`),
  CONSTRAINT `drawing_neighbors_ibfk_1` FOREIGN KEY (`drawing_id`) REFERENCES `drawings` (`drawing_id`) ON DELETE CASCADE,
  CONSTRAINT `drawing_neighbors_ibfk_2` FOREIGN KEY (`neighbor_section_id`) REFERENCES `sections` (`section_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks physical proximity of drawings in notebooks';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drawing_neighbors`
--

LOCK TABLES `drawing_neighbors` WRITE;
/*!40000 ALTER TABLE `drawing_neighbors` DISABLE KEYS */;
INSERT INTO `drawing_neighbors` VALUES
(1,3,2,6,'2025-08-27 14:50:22'),
(2,3,3,16,'2025-08-27 14:50:22');
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
  `authored_at` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `notebook_id` int(11) NOT NULL COMMENT 'Denormalized for app-side uniqueness check on (notebook, page, section). Populated via application logic.',
  `test` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`drawing_id`),
  UNIQUE KEY `uq_notebook_section_page` (`notebook_id`,`section_id`,`page`),
  KEY `drawings_users_FK` (`user_id`),
  KEY `drawings_sections_FK` (`section_id`),
  KEY `drawings_notebooks_FK` (`notebook_id`),
  CONSTRAINT `drawings_notebooks_FK` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`notebook_id`),
  CONSTRAINT `drawings_sections_FK` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`),
  CONSTRAINT `drawings_users_FK` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drawings`
--

LOCK TABLES `drawings` WRITE;
/*!40000 ALTER TABLE `drawings` DISABLE KEYS */;
INSERT INTO `drawings` VALUES
(1,'2025-08-27 00:28:02','2025-08-27 00:28:02','2025-08-31 00:44:55',3,7,5,3,0),
(2,'2025-08-27 14:49:31','2025-08-27 14:49:31','2025-08-31 00:44:55',1,11,10,4,0),
(3,'2025-08-27 14:50:22','2025-08-27 14:50:22','2025-08-31 00:44:55',4,1,4,1,0),
(4,'2025-08-27 21:50:29','2025-08-27 21:50:29','2025-08-31 01:59:13',1,9,14,3,0),
(5,'2025-08-27 21:52:09','2025-08-27 21:52:09','2025-08-31 14:13:15',1,6,15,2,0),
(6,'2025-08-30 22:59:15','2025-08-30 22:59:15','2025-08-31 00:44:55',1,5,13,2,0),
(7,'2025-08-31 23:59:01','2025-08-31 23:59:01','2025-08-31 23:59:01',5,10,43,4,0),
(8,'2025-09-01 15:56:13','2025-09-01 15:56:13','2025-09-01 15:56:13',1,6,44,2,0);
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
INSERT INTO `files` VALUES
(1,'2025-08-27 00:28:04',1,'9e81173f172e56f7b1a359b4b56e488c__display.webp','b8802daf0d866e89f3bc2de25df8cf31.jpg',942522,'image/webp',2000,1331,0),
(2,'2025-08-27 14:49:31',2,'300b10c210fd46eeda09bd512421ee60__display.webp','WhatsApp Image 2024-09-05 at 16.01.15.jpeg',20870,'image/webp',1106,740,0),
(3,'2025-08-27 14:50:22',3,'092e46ce15f977d1e035fc765cfc293c__display.webp','comunica-favicon (1).png',8238,'image/webp',512,512,0),
(4,'2025-08-27 21:50:29',4,'96317e00aef6c98d73c73153692f8465__display.webp','Jewelery_Box_Mockup_1.jpg',45032,'image/webp',1600,1200,0),
(5,'2025-08-27 21:52:10',5,'a90b292c79524ad6e90cfc2c85569747__display.webp','Business_Card_Mockup_1.jpg',139118,'image/webp',1600,1200,0),
(6,'2025-08-30 22:59:16',6,'7da1d730edce43aea65d04c30b4af76a__display.webp','47ed8f349e2c1f3a1501474f0f4fb39155cd900b.jpg',81938,'image/webp',1366,2048,0),
(7,'2025-08-31 23:59:01',7,'50f7464907815c1339edb07d5badb9d4__display.webp','logo_hires (1).png',10472,'image/webp',1600,1600,0),
(8,'2025-09-01 15:56:13',8,'00b5d5d310785f62aebffb1d4994db67__display.webp','WhatsApp Image 2025-07-04 at 15.39.45.jpeg',274338,'image/webp',2048,1152,0);
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
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `color_bg` varchar(7) DEFAULT NULL,
  `color_text` varchar(7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pages` int(11) DEFAULT NULL,
  PRIMARY KEY (`notebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notebooks`
--

LOCK TABLES `notebooks` WRITE;
/*!40000 ALTER TABLE `notebooks` DISABLE KEYS */;
INSERT INTO `notebooks` VALUES
(1,'Notebook I','In the future, nature will take over the world',NULL,'c49ee3','ffffff','2025-08-21 22:36:11','2025-08-31 00:44:55',50),
(2,'Notebook II','In the future, thanks to AI, no one will need to work anymore',NULL,'fabf4a','ffffff','2025-08-21 22:36:11','2025-08-31 00:44:55',50),
(3,'Notebook III','In the future, our bodies will be free from biological limits',NULL,'7dab94','ffffff','2025-08-21 22:36:11','2025-08-31 00:44:55',50),
(4,'Notebook IV','In the future, the population will continue to grow at the same rate as today',NULL,'8aa8d4','ffffff','2025-08-21 22:36:11','2025-08-31 00:44:55',50);
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `uq_notebook_position` (`notebook_id`,`position`),
  CONSTRAINT `sections_notebooks_FK` FOREIGN KEY (`notebook_id`) REFERENCES `notebooks` (`notebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES
(1,1,1,'Space','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(2,1,2,'Land','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(3,1,3,'Ocean','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(4,2,1,'Space','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(5,2,2,'Land','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(6,2,3,'Ocean','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(7,3,1,'Space','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(8,3,2,'Land','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(9,3,3,'Ocean','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(10,4,1,'Space','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(11,4,2,'Land','2025-08-21 22:36:11','2025-08-31 00:44:55'),
(12,4,3,'Ocean','2025-08-21 22:36:11','2025-08-31 00:44:55');
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
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `test` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'trpsimoes@gmail.com',NULL,NULL,NULL,'2025-07-28 20:28:35','2025-08-28 23:19:07',0,1),
(2,'test@example.com',NULL,NULL,NULL,'2025-07-28 22:13:16','2025-07-28 22:13:16',0,0),
(3,'miguel.grima@amgi.pt',NULL,NULL,NULL,'2025-08-27 00:28:02','2025-08-27 00:28:02',0,0),
(4,'carlos@sapo.pt',NULL,NULL,NULL,'2025-08-27 14:50:22','2025-08-27 14:50:22',0,0),
(5,'tiago.simoes@plura.pt',NULL,NULL,NULL,'2025-08-31 23:59:01',NULL,0,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-09-04  0:37:21
