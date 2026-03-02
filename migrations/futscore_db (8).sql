
-- LLM AGENTS! IF YOU NEED THE FULL DB SCHEMA FOR THIS PROJECT
-- THIS FILE IS WHERE YOU CAN FIND IT. AS IT IS A FULL DUMP OF THE DATABASE.
-- DON'T MODIFY THIS FILE UNLESS THERE'S A MIGRATION.
-- IF YOU'RE UNSURE WHETHER TO MODIFY THIS FILE OR NOT, ASK THE USER FIRST!

-- MariaDB dump 10.19  Distrib 10.11.15-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: futscore_db
-- ------------------------------------------------------
-- Server version	10.11.15-MariaDB

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
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('superadmin','admin','operator','pelatih') DEFAULT 'admin',
  `team_id` int(11) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_admin_team` (`team_id`),
  KEY `fk_admin_event` (`event_id`),
  CONSTRAINT `fk_admin_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_admin_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES
(1,'admin','admin@futscore.com','$2y$10$dzjlNxiMyOK83KloXa.W/eaAoCda5d.WuiMDfR0VCb6yMoivKIkkq','Administrator Utama','superadmin',NULL,NULL,1,'2026-03-02 11:36:03','2026-01-27 11:49:53','2026-03-02 04:36:03'),
(2,'pelatih','pelatih@futscore.com','$2y$10$g7pBfc0hydIITTcNCk098u1gsdFSUIa.F5Xbm24wx7CAZtK8i8N2O','Coach FutScore','pelatih',5,NULL,1,'2026-03-02 11:35:35','2026-01-29 12:39:47','2026-03-02 04:35:35'),
(3,'pelatih_oxygen','oxygen_coach@example.com','$2y$10$q2KWRIdOpLeyMep7Z/pLU.25cU7jXoY7V.uy8ZJcd4r1AB7kIrTcq','Coach Oxygen','pelatih',32,NULL,1,'2026-03-02 11:18:02','2026-01-31 05:02:08','2026-03-02 04:18:02'),
(4,'pelatih_mantang','obemahoo@gmail.com','$2y$12$XLbpLDPAbvO3M8H1gmzmyO7yjsfXgEIkyWFsBppIFLydihqVKkLve','pelatihmantang','pelatih',28,NULL,1,NULL,'2026-03-01 05:59:16','2026-03-01 05:59:16'),
(5,'pelatih_vitanza','obemahoos@gmail.com','$2y$12$/oT1Z0oZT4RR2rOJCHV4meFWwa4pPduf9xLR7Q33l2Ki2.P7KJKIm','pelatihvitanza','pelatih',43,NULL,1,NULL,'2026-03-01 06:02:55','2026-03-01 06:02:55');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `berita`
--

DROP TABLE IF EXISTS `berita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `berita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `konten` longtext NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `penulis` varchar(100) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `tag` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_views` (`views`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `berita`
--

LOCK TABLES `berita` WRITE;
/*!40000 ALTER TABLE `berita` DISABLE KEYS */;
INSERT INTO `berita` VALUES
(1,'Apostel Johannus','apostel-johannus','<p>tes</p>',NULL,'Johan','published','Gospelus',18,'2026-01-31 11:06:51','2026-02-12 14:48:08');
/*!40000 ALTER TABLE `berita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `challenges`
--

DROP TABLE IF EXISTS `challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_code` varchar(50) NOT NULL,
  `challenger_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `challenge_date` datetime NOT NULL,
  `expiry_date` datetime NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `sport_type` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','accepted','rejected','expired','completed') DEFAULT 'open',
  `challenger_score` int(11) DEFAULT NULL,
  `opponent_score` int(11) DEFAULT NULL,
  `winner_team_id` int(11) DEFAULT NULL,
  `match_status` varchar(50) DEFAULT NULL,
  `match_duration` varchar(20) DEFAULT NULL,
  `match_official` varchar(100) DEFAULT NULL,
  `challenger_uniform_choices` varchar(255) DEFAULT NULL,
  `opponent_uniform_choices` varchar(255) DEFAULT NULL,
  `match_notes` text DEFAULT NULL,
  `result_entered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_code` (`challenge_code`),
  KEY `idx_challenge_code` (`challenge_code`),
  KEY `idx_challenger_id` (`challenger_id`),
  KEY `idx_opponent_id` (`opponent_id`),
  KEY `idx_venue_id` (`venue_id`),
  KEY `idx_status` (`status`),
  KEY `idx_challenge_date` (`challenge_date`),
  KEY `idx_sport_type` (`sport_type`),
  KEY `winner_team_id` (`winner_team_id`),
  KEY `idx_challenges_event_id` (`event_id`),
  CONSTRAINT `challenges_ibfk_1` FOREIGN KEY (`challenger_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenges_ibfk_2` FOREIGN KEY (`opponent_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `challenges_ibfk_3` FOREIGN KEY (`winner_team_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `challenges_ibfk_4` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_challenges_event_id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `challenges`
--

LOCK TABLES `challenges` WRITE;
/*!40000 ALTER TABLE `challenges` DISABLE KEYS */;
INSERT INTO `challenges` VALUES
(2,'CH202601281914E6',12,14,1,'2026-01-29 18:00:00','2026-01-28 18:00:00',NULL,'Futsal','Nope','accepted',5,3,12,'completed','90','Savety',NULL,NULL,'Nope','2026-01-28 17:56:50','2026-01-28 03:55:29','2026-01-28 03:56:50'),
(5,'CH20260130188FA4',13,22,4,'2026-01-30 20:36:00','2026-01-29 20:36:00',NULL,'Silat','','completed',0,0,NULL,'completed','90','',NULL,NULL,'','2026-01-30 20:36:44','2026-01-30 13:33:37','2026-01-30 13:36:44'),
(6,'CH20260130ABF021',13,26,1,'2026-01-30 23:03:00','2026-01-29 23:03:00',NULL,'Badminton','','completed',2,1,13,'completed','90','',NULL,NULL,'','2026-01-30 20:38:59','2026-01-30 13:37:30','2026-01-30 13:38:59'),
(7,'CH20260130801D34',17,23,1,'2026-01-31 22:08:00','2026-01-30 22:08:00',NULL,'Renang','','completed',1,0,17,'completed','90','s',NULL,NULL,'s','2026-02-02 20:23:10','2026-01-30 15:04:08','2026-02-02 13:23:10'),
(9,'CH20260202F9387E',5,22,1,'2026-02-03 18:00:00','2026-02-02 18:00:00',NULL,'Taekwondo','ss','completed',2,0,5,'completed','90','',NULL,NULL,'','2026-02-02 20:23:52','2026-02-02 13:23:43','2026-02-02 13:23:52'),
(10,'CH20260202AC8903',32,5,3,'2026-02-04 18:00:00','2026-02-03 18:00:00',NULL,'Judo','p','completed',2,0,32,'completed','90','walid',NULL,NULL,'s','2026-02-03 11:22:08','2026-02-02 13:32:58','2026-02-03 04:22:08'),
(12,'CH2026020679AECA',18,17,2,'2026-02-06 18:19:00','2026-02-05 18:19:00',NULL,'Futsal','','accepted',NULL,NULL,NULL,'scheduled',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-06 10:36:55','2026-02-16 04:08:51'),
(13,'CH202602069CA133',21,14,3,'2026-02-06 19:25:00','2026-02-05 19:25:00',NULL,'Futsal','','accepted',NULL,NULL,NULL,'scheduled',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-06 10:40:41','2026-02-16 04:08:51'),
(14,'CH20260206F4B308',26,19,2,'2026-02-06 20:13:00','2026-02-05 20:13:00',NULL,'Futsal','','expired',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-06 10:41:03','2026-02-16 04:08:51'),
(15,'CH202602062B3086',33,32,4,'2026-02-06 21:10:00','2026-02-05 21:10:00',NULL,'Futsal','','expired',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-06 10:41:38','2026-02-16 04:08:51'),
(16,'CH20260211E7FBBD',32,25,2,'2026-02-11 18:00:00','2026-02-10 18:00:00',NULL,'LIGA AAFI BATAM U-16 PUTRA 2026','','completed',1,0,32,'completed','90','',NULL,NULL,'','2026-02-11 10:01:26','2026-02-11 02:59:26','2026-02-11 03:01:26'),
(17,'CH20260212258D15',24,27,2,'2026-02-13 18:00:00','2026-02-12 18:00:00',NULL,'LIGA AAFI BATAM U-13 PUTRA 2026','p','accepted',NULL,NULL,NULL,'scheduled',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-12 15:02:10','2026-02-16 04:08:51'),
(19,'CH20260216C69E63',32,25,2,'2026-02-17 18:00:00','2026-02-16 18:00:00',NULL,'LIGA AAFI BATAM U-16 PUTRA 2026','','completed',1,0,32,'completed','90','',NULL,NULL,'','2026-02-16 12:13:21','2026-02-16 04:36:28','2026-02-16 05:13:21'),
(20,'CH2026021614FEEC',32,25,2,'2026-02-17 18:00:00','2026-02-16 18:00:00',NULL,'LIGA AAFI BATAM U-16 PUTRA 2026','','accepted',NULL,NULL,NULL,'scheduled',NULL,NULL,NULL,NULL,NULL,NULL,'2026-02-16 05:58:25','2026-02-16 05:58:30'),
(21,'CH202603019BDE7A',28,25,3,'2026-03-02 18:00:00','2026-03-01 18:00:00',1,'LIGA AAFI BATAM U-16 PUTRA 2026','','accepted',NULL,NULL,NULL,'scheduled',NULL,'Walid1',NULL,NULL,NULL,NULL,'2026-03-01 05:52:41','2026-03-02 04:36:29'),
(22,'CH20260301B6EBE3',43,32,2,'2026-03-01 18:00:00','2026-02-28 18:00:00',1,'LIGA AAFI BATAM U-16 PUTRA 2026','','completed',0,1,32,'completed','90','Ostritch',NULL,NULL,'','2026-03-02 11:19:11','2026-03-01 06:03:55','2026-03-02 04:19:11');
/*!40000 ALTER TABLE `challenges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detail_keluarga_siswa`
--

DROP TABLE IF EXISTS `detail_keluarga_siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_keluarga_siswa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_siswa` bigint(20) unsigned NOT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `pekerjaan_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `pekerjaan_ibu` varchar(100) DEFAULT NULL,
  `status_ekonomi` varchar(50) DEFAULT NULL,
  `jumlah_saudara` int(11) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_keluarga_siswa` (`id_siswa`),
  CONSTRAINT `fk_detail_keluarga_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detail_keluarga_siswa`
--

LOCK TABLES `detail_keluarga_siswa` WRITE;
/*!40000 ALTER TABLE `detail_keluarga_siswa` DISABLE KEYS */;
INSERT INTO `detail_keluarga_siswa` VALUES
(1,1,'s','s','s','s','Mampu',2,'s'),
(2,2,'s','s','s','s','Mampu',2,'s');
/*!40000 ALTER TABLE `detail_keluarga_siswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_brackets`
--

DROP TABLE IF EXISTS `event_brackets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_brackets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `sport_type` varchar(120) NOT NULL DEFAULT '',
  `sf1_team1_id` int(11) DEFAULT NULL,
  `sf1_team2_id` int(11) DEFAULT NULL,
  `sf1_challenge_id` int(11) DEFAULT NULL,
  `sf1_score1` int(11) DEFAULT NULL,
  `sf1_score2` int(11) DEFAULT NULL,
  `sf2_team1_id` int(11) DEFAULT NULL,
  `sf2_team2_id` int(11) DEFAULT NULL,
  `sf2_challenge_id` int(11) DEFAULT NULL,
  `sf2_score1` int(11) DEFAULT NULL,
  `sf2_score2` int(11) DEFAULT NULL,
  `final_challenge_id` int(11) DEFAULT NULL,
  `third_challenge_id` int(11) DEFAULT NULL,
  `final_score1` int(11) DEFAULT NULL,
  `final_score2` int(11) DEFAULT NULL,
  `third_score1` int(11) DEFAULT NULL,
  `third_score2` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_sport` (`event_id`,`sport_type`),
  KEY `idx_event_sport` (`event_id`,`sport_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_brackets`
--

LOCK TABLES `event_brackets` WRITE;
/*!40000 ALTER TABLE `event_brackets` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_brackets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_taxonomy`
--

DROP TABLE IF EXISTS `event_taxonomy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_taxonomy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_group_slug` varchar(140) NOT NULL,
  `event_group_name` varchar(140) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `legacy_event_name` varchar(100) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_taxonomy_legacy_event` (`legacy_event_name`),
  KEY `idx_event_taxonomy_group` (`event_group_slug`,`sort_order`,`category_name`),
  KEY `idx_event_taxonomy_group_name` (`event_group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_taxonomy`
--

LOCK TABLES `event_taxonomy` WRITE;
/*!40000 ALTER TABLE `event_taxonomy` DISABLE KEYS */;
INSERT INTO `event_taxonomy` VALUES
(1,'liga-aafi-batam-2026','LIGA AAFI BATAM 2026','U13','LIGA AAFI BATAM U-13 PUTRA 2026',13,'2026-02-16 11:35:48','2026-02-16 11:35:48'),
(2,'liga-aafi-batam-2026','LIGA AAFI BATAM 2026','U16','LIGA AAFI BATAM U-16 PUTRA 2026',16,'2026-02-16 11:35:48','2026-02-16 11:35:48'),
(3,'liga-aafi-batam-2026','LIGA AAFI BATAM 2026','U16 PUTRI','LIGA AAFI BATAM U-16 PUTRI 2026',17,'2026-02-16 11:35:48','2026-02-16 11:35:48');
/*!40000 ALTER TABLE `event_taxonomy` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_team_values`
--

DROP TABLE IF EXISTS `event_team_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_team_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `sport_type` varchar(120) NOT NULL DEFAULT '',
  `mn` int(11) NOT NULL DEFAULT 0,
  `m` int(11) NOT NULL DEFAULT 0,
  `mp` int(11) NOT NULL DEFAULT 0,
  `s` int(11) NOT NULL DEFAULT 0,
  `kp` int(11) NOT NULL DEFAULT 0,
  `k` int(11) NOT NULL DEFAULT 0,
  `gm` int(11) NOT NULL DEFAULT 0,
  `gk` int(11) NOT NULL DEFAULT 0,
  `sg` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `kls` int(11) NOT NULL DEFAULT 0,
  `red_cards` int(11) NOT NULL DEFAULT 0,
  `yellow_cards` int(11) NOT NULL DEFAULT 0,
  `green_cards` int(11) NOT NULL DEFAULT 0,
  `match_history` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_team_category` (`event_id`,`team_id`,`sport_type`),
  KEY `idx_event_points` (`event_id`,`points`,`sg`),
  KEY `idx_event_kls` (`event_id`,`kls`),
  KEY `fk_event_team_values_team` (`team_id`),
  CONSTRAINT `fk_event_team_values_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_event_team_values_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_team_values`
--

LOCK TABLES `event_team_values` WRITE;
/*!40000 ALTER TABLE `event_team_values` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_team_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `registration_status` enum('open','closed') DEFAULT 'open',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `contact` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES
(1,'LIGA AAFI BATAM','liga-aafi-batam-u-13-putra',NULL,NULL,'2026-02-22','2026-02-24','Westeros','open',1,'1231241231231241','League','2026-02-22 05:16:23');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goals`
--

DROP TABLE IF EXISTS `goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `minute` int(11) DEFAULT NULL,
  `half` tinyint(1) NOT NULL DEFAULT 1,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `player_id` (`player_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `goals_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`),
  CONSTRAINT `goals_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `goals_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goals`
--

LOCK TABLES `goals` WRITE;
/*!40000 ALTER TABLE `goals` DISABLE KEYS */;
INSERT INTO `goals` VALUES
(2,19,43,32,25,1,NULL),
(3,22,43,32,69,1,NULL);
/*!40000 ALTER TABLE `goals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hasil_asesmen`
--

DROP TABLE IF EXISTS `hasil_asesmen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_asesmen` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_siswa` bigint(20) unsigned NOT NULL,
  `kategori` enum('gaya_belajar','minat_karir','kepribadian','kesehatan_mental') NOT NULL,
  `ringkasan_hasil` text DEFAULT NULL,
  `skor` varchar(255) DEFAULT NULL,
  `terakhir_diperbarui` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hasil_asesmen_siswa` (`id_siswa`),
  CONSTRAINT `fk_hasil_asesmen_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hasil_asesmen`
--

LOCK TABLES `hasil_asesmen` WRITE;
/*!40000 ALTER TABLE `hasil_asesmen` DISABLE KEYS */;
INSERT INTO `hasil_asesmen` VALUES
(1,1,'kepribadian','{\"q1_status_ortu\":\"Lengkap\",\"q2_status_ortu\":\"Ramai\",\"q3_status_ortu\":\"Ya\",\"q4_status_ortu\":\"Jalan Kaki\"}','-','2026-01-06 11:17:25'),
(2,1,'gaya_belajar','{\"q1_gaya_belajar\":\"Auditori\",\"q2_gaya_belajar\":\"Visual\",\"q3_gaya_belajar\":\"Visual\",\"q4_gaya_belajar\":\"Kinestetik\"}','Visual Dominan','2026-01-06 11:17:25'),
(3,1,'kesehatan_mental','{\"q1_nyaman_teman\":\"Ya\",\"q2_cemas\":\"Tidak\",\"q3_cerita\":\"Ya\",\"q4_tekanan_akademik\":\"Ya\",\"q5_bullying\":\"Tidak\"}','Stabil','2026-01-06 11:17:38'),
(4,1,'minat_karir','{\"rencana_lulus\":\"Sekolah Kedinasan\",\"mapel_favorit\":[\"Matematika\",\"Olahraga\",\"Bahasa Indonesia\"],\"minat_pekerjaan\":\"Sosial & Hukum\"}','Sekolah Kedinasan','2026-01-06 11:17:38'),
(5,2,'kepribadian','{\"q1_status_ortu\":\"Lengkap\",\"q2_status_ortu\":\"Ramai\",\"q3_status_ortu\":\"Tidak\",\"q4_status_ortu\":\"Jalan Kaki\"}','-','2026-01-06 11:18:21'),
(6,2,'gaya_belajar','{\"q1_gaya_belajar\":\"Visual\",\"q2_gaya_belajar\":\"Visual\",\"q3_gaya_belajar\":\"Visual\",\"q4_gaya_belajar\":\"Visual\"}','Visual Dominan','2026-01-06 11:18:21'),
(7,2,'kesehatan_mental','{\"q1_nyaman_teman\":\"Ya\",\"q2_cemas\":\"Ya\",\"q3_cerita\":\"Ya\",\"q4_tekanan_akademik\":\"Ya\",\"q5_bullying\":\"Ya\"}','PERLU PERHATIAN KHUSUS (Bullying)','2026-01-06 11:18:30'),
(8,2,'minat_karir','{\"rencana_lulus\":\"Kerja/Wirausaha\",\"mapel_favorit\":[\"Olahraga\",\"KK\",\"Agama\"],\"minat_pekerjaan\":\"Seni & Kreatif\"}','Kerja/Wirausaha','2026-01-06 11:18:30');
/*!40000 ALTER TABLE `hasil_asesmen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `konselor`
--

DROP TABLE IF EXISTS `konselor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `konselor` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_pengguna` bigint(20) unsigned NOT NULL,
  `nip` varchar(30) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `spesialisasi` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nip` (`nip`),
  KEY `fk_konselor_user` (`id_pengguna`),
  CONSTRAINT `fk_konselor_user` FOREIGN KEY (`id_pengguna`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `konselor`
--

LOCK TABLES `konselor` WRITE;
/*!40000 ALTER TABLE `konselor` DISABLE KEYS */;
INSERT INTO `konselor` VALUES
(1,2,'6001','counselortest','Konselor Umum');
/*!40000 ALTER TABLE `konselor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `konsultasi`
--

DROP TABLE IF EXISTS `konsultasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `konsultasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_siswa` bigint(20) unsigned NOT NULL,
  `id_konselor` bigint(20) unsigned NOT NULL,
  `kategori_topik` varchar(50) NOT NULL,
  `deskripsi_keluhan` text NOT NULL,
  `tanggal_konsultasi` datetime NOT NULL,
  `status` enum('menunggu','disetujui','ditolak','dijadwalkan_ulang','selesai') DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_konsultasi_siswa` (`id_siswa`),
  KEY `fk_konsultasi_konselor` (`id_konselor`),
  CONSTRAINT `fk_konsultasi_konselor` FOREIGN KEY (`id_konselor`) REFERENCES `konselor` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_konsultasi_siswa` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `konsultasi`
--

LOCK TABLES `konsultasi` WRITE;
/*!40000 ALTER TABLE `konsultasi` DISABLE KEYS */;
INSERT INTO `konsultasi` VALUES
(1,2,1,'Pribadi','tes','2026-01-09 16:44:00','disetujui','2026-01-06 11:43:31'),
(2,1,1,'Sosial','p','2026-01-14 20:49:00','disetujui','2026-01-06 11:45:40');
/*!40000 ALTER TABLE `konsultasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan_konsultasi`
--

DROP TABLE IF EXISTS `laporan_konsultasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_konsultasi` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_konsultasi` bigint(20) unsigned NOT NULL,
  `inti_masalah` text NOT NULL,
  `solusi_diberikan` text NOT NULL,
  `perlu_tindak_lanjut` tinyint(1) DEFAULT 0,
  `catatan_rahasia` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_laporan_konsultasi` (`id_konsultasi`),
  CONSTRAINT `fk_laporan_konsultasi` FOREIGN KEY (`id_konsultasi`) REFERENCES `konsultasi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan_konsultasi`
--

LOCK TABLES `laporan_konsultasi` WRITE;
/*!40000 ALTER TABLE `laporan_konsultasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `laporan_konsultasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lineups`
--

DROP TABLE IF EXISTS `lineups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lineups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) DEFAULT NULL,
  `player_id` int(11) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `is_starting` tinyint(1) DEFAULT 1,
  `position` varchar(50) DEFAULT NULL,
  `half` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `match_id` (`match_id`),
  KEY `player_id` (`player_id`),
  KEY `team_id` (`team_id`),
  CONSTRAINT `lineups_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`),
  CONSTRAINT `lineups_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `lineups_ibfk_3` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lineups`
--

LOCK TABLES `lineups` WRITE;
/*!40000 ALTER TABLE `lineups` DISABLE KEYS */;
INSERT INTO `lineups` VALUES
(2,19,43,32,0,'DF',1),
(4,22,43,32,1,'DF',1);
/*!40000 ALTER TABLE `lineups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `match_staff_assignments`
--

DROP TABLE IF EXISTS `match_staff_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_staff_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `half` tinyint(1) NOT NULL DEFAULT 1,
  `role` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_match_staff_team_half` (`match_id`,`staff_id`,`team_id`,`half`),
  KEY `idx_msa_staff` (`staff_id`),
  KEY `idx_msa_match` (`match_id`),
  KEY `idx_msa_team` (`team_id`),
  CONSTRAINT `fk_msa_match` FOREIGN KEY (`match_id`) REFERENCES `challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msa_staff` FOREIGN KEY (`staff_id`) REFERENCES `team_staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msa_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `match_staff_assignments`
--

LOCK TABLES `match_staff_assignments` WRITE;
/*!40000 ALTER TABLE `match_staff_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `match_staff_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `match_stats`
--

DROP TABLE IF EXISTS `match_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `team1_possession` int(11) DEFAULT 0,
  `team2_possession` int(11) DEFAULT 0,
  `team1_shots_on_target` int(11) DEFAULT 0,
  `team2_shots_on_target` int(11) DEFAULT 0,
  `team1_fouls` int(11) DEFAULT 0,
  `team2_fouls` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_match_id` (`match_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `match_stats`
--

LOCK TABLES `match_stats` WRITE;
/*!40000 ALTER TABLE `match_stats` DISABLE KEYS */;
INSERT INTO `match_stats` VALUES
(1,10,0,0,0,0,0,0,'2026-02-03 04:22:08','2026-02-03 04:22:08'),
(2,16,0,0,0,0,0,0,'2026-02-11 03:01:26','2026-02-11 03:01:26'),
(3,19,0,0,0,0,0,0,'2026-02-16 05:13:21','2026-02-16 05:13:21'),
(4,22,0,0,0,0,0,0,'2026-03-02 04:19:11','2026-03-02 04:19:11');
/*!40000 ALTER TABLE `match_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `matches`
--

DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) DEFAULT NULL,
  `team1_id` int(11) DEFAULT NULL,
  `team2_id` int(11) DEFAULT NULL,
  `score1` int(11) DEFAULT NULL,
  `score2` int(11) DEFAULT NULL,
  `match_date` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `team1_id` (`team1_id`),
  KEY `team2_id` (`team2_id`),
  CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `matches`
--

LOCK TABLES `matches` WRITE;
/*!40000 ALTER TABLE `matches` DISABLE KEYS */;
/*!40000 ALTER TABLE `matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perangkat`
--

DROP TABLE IF EXISTS `perangkat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perangkat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `no_ktp` varchar(50) NOT NULL,
  `birth_place` varchar(100) DEFAULT NULL,
  `age` date NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Indonesia',
  `photo` varchar(255) DEFAULT NULL,
  `ktp_photo` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perangkat_no_ktp` (`no_ktp`),
  KEY `idx_perangkat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perangkat`
--

LOCK TABLES `perangkat` WRITE;
/*!40000 ALTER TABLE `perangkat` DISABLE KEYS */;
/*!40000 ALTER TABLE `perangkat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perangkat_licenses`
--

DROP TABLE IF EXISTS `perangkat_licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `perangkat_licenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `perangkat_id` int(10) unsigned NOT NULL,
  `license_name` varchar(255) NOT NULL,
  `license_file` varchar(255) NOT NULL,
  `issuing_authority` varchar(255) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_perangkat_license_perangkat` (`perangkat_id`),
  CONSTRAINT `fk_perangkat_license_perangkat` FOREIGN KEY (`perangkat_id`) REFERENCES `perangkat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perangkat_licenses`
--

LOCK TABLES `perangkat_licenses` WRITE;
/*!40000 ALTER TABLE `perangkat_licenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `perangkat_licenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `player_event_cards`
--

DROP TABLE IF EXISTS `player_event_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `player_event_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `sport_type` varchar(120) NOT NULL DEFAULT '',
  `yellow_cards` int(11) NOT NULL DEFAULT 0,
  `red_cards` int(11) NOT NULL DEFAULT 0,
  `green_cards` int(11) NOT NULL DEFAULT 0,
  `suspension_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_player_category` (`event_id`,`player_id`,`sport_type`),
  KEY `idx_event_suspend` (`event_id`,`suspension_until`),
  KEY `fk_player_event_cards_player` (`player_id`),
  KEY `fk_player_event_cards_team` (`team_id`),
  CONSTRAINT `fk_player_event_cards_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_player_event_cards_player` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_player_event_cards_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `player_event_cards`
--

LOCK TABLES `player_event_cards` WRITE;
/*!40000 ALTER TABLE `player_event_cards` DISABLE KEYS */;
/*!40000 ALTER TABLE `player_event_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `players`
--

DROP TABLE IF EXISTS `players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `players` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL,
  `jersey_number` int(11) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('L','P') DEFAULT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `sport_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `birth_place` varchar(100) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `dominant_foot` enum('kiri','kanan','kedua') DEFAULT NULL,
  `position_detail` varchar(100) DEFAULT NULL,
  `dribbling` int(11) DEFAULT 5,
  `technique` int(11) DEFAULT 5,
  `speed` int(11) DEFAULT 5,
  `juggling` int(11) DEFAULT 5,
  `shooting` int(11) DEFAULT 5,
  `setplay_position` int(11) DEFAULT 5,
  `passing` int(11) DEFAULT 5,
  `control` int(11) DEFAULT 5,
  `ktp_image` varchar(255) DEFAULT NULL,
  `kk_image` varchar(255) DEFAULT NULL,
  `birth_cert_image` varchar(255) DEFAULT NULL,
  `diploma_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `nik` (`nik`),
  KEY `team_id` (`team_id`),
  KEY `idx_player_name` (`name`),
  KEY `idx_player_team` (`team_id`),
  KEY `idx_player_nik` (`nik`),
  KEY `idx_players_sport_type` (`sport_type`),
  CONSTRAINT `players_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `players`
--

LOCK TABLES `players` WRITE;
/*!40000 ALTER TABLE `players` DISABLE KEYS */;
INSERT INTO `players` VALUES
(1,'Savety','savety-1769577923','player_1769577923_69799dc32f1ce.JPG',5,99,'FW','2008-08-27','P','9628173163516','2171106708080002','Sepak Bola','2026-01-28 05:25:23','Batam',160,45,'savetyani0002@gmail.com','087898954988','Indonesia','Batam Centre','Batam','Bangka Belitung ','7677','Indonesia','','FW',10,10,10,10,10,10,10,10,'ktp_1769577923_69799dc32f41f.JPG','kk_1769577923_69799dc32f53b.JPG','akte_1769577923_69799dc32f701.JPG',NULL,'active','2026-02-18 06:05:34'),
(8,'Budi Santoso','budi-santoso',NULL,5,7,NULL,'2006-03-20','L',NULL,NULL,NULL,'2026-01-28 10:06:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-01-28 10:06:36'),
(9,'Citra Lestari','citra-lestari',NULL,5,11,NULL,'2007-08-10','P',NULL,NULL,NULL,'2026-01-28 10:06:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-01-28 10:06:36'),
(10,'Dedi Kurniawan','dedi-kurniawan',NULL,5,1,NULL,'2004-12-01','L',NULL,NULL,NULL,'2026-01-28 10:06:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-01-28 10:06:36'),
(11,'Evan Dimas','evan-dimas','player_697f55e83fd53_1769952744.png',5,6,'GK','1995-03-13','L','123131231','1111111111111111','Sepak Bola','2026-01-28 10:06:42','West Sumatra',164,54,'willemmarove@gmail.com','+62 132123 1231','Belanda','p','ppp','Kepri','6666666','Indonesia','kiri','',0,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-18 06:05:34'),
(28,'Budi Santoso','budi-santoso-oxygen',NULL,32,2,'DF','1996-08-23',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,178,70,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(29,'Candra Wijaya','candra-wijaya-oxygen',NULL,17,3,'DF','1994-02-15',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,180,72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-06 12:56:39'),
(30,'Dedi Kusnandar','dedi-kusnandar-oxygen',NULL,32,4,'DF','1997-11-30',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,175,68,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(31,'Eko Prasetyo','eko-prasetyo-oxygen',NULL,32,5,'DF','1993-07-19',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,183,78,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(32,'Fajar Alfian','fajar-alfian-oxygen',NULL,32,6,'MF','1998-03-25',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,170,65,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(33,'Guntur Ariyanto','guntur-ariyanto-oxygen',NULL,32,8,'MF','1999-09-10',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,172,66,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(35,'Irfan Bachdim','irfan-bachdim-oxygen',NULL,32,11,'MF','1995-12-12',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,174,67,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(36,'Joko Anwar','joko-anwar-oxygen',NULL,32,7,'FW','1996-06-08',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,179,73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(37,'Kurniawan Dwi','kurniawan-dwi-oxygen',NULL,32,9,'FW','1994-04-20',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,181,76,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(38,'Lukman Sardi','lukman-sardi-oxygen',NULL,32,20,'GK','1993-10-15',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,185,80,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(39,'Muhammad Rian','muhammad-rian-oxygen',NULL,32,13,'DF','1997-02-28',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,177,71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(40,'Naufal Samudra','naufal-samudra-oxygen',NULL,32,16,'MF','1998-08-17',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,173,68,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(41,'Oscar Lawalata','oscar-lawalata-oxygen',NULL,32,17,'FW','1995-07-07',NULL,NULL,NULL,NULL,'2026-02-03 04:21:17',NULL,178,72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,5,5,5,5,5,5,5,NULL,NULL,NULL,NULL,'active','2026-02-03 04:21:17'),
(43,'Kanye East','kanye-east-ed0150',NULL,32,96,'DF','2008-02-01','L','12321312','1231231231241243','LIGA AAFI BATAM U-16 PUTRA 2026','2026-02-09 15:01:49','Batam',613,23,'kanyeraq@gmail.com','234234234','Indonesia','asdwd','Batam','Kepri','6666666','Indonesia','kanan','DF',8,5,5,5,5,5,5,5,NULL,'kk_1770734963_698b457370653.png',NULL,NULL,'active','2026-02-10 14:49:23'),
(44,'Kanye South','kanye-south-1771469472','',32,23,'DF','2008-02-19','L','0081234567','3201011902080001','LIGA AAFI BATAM U-13 PUTRA 2026','2026-02-19 02:51:12','Chiraq',174,56,'kanyekirk@gmail.com','1231241241232','Indonesia','sdwsdw','Batam','Kepri','6666666','Indonesia','kanan','GK',7,7,5,5,6,5,5,5,'','kk_1771469472_69967aa0a7eec.png','','','active','2026-02-19 03:12:01');
/*!40000 ALTER TABLE `players` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `siswa`
--

DROP TABLE IF EXISTS `siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `siswa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_pengguna` bigint(20) unsigned NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tingkat_kelas` int(11) NOT NULL,
  `jurusan` varchar(50) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  KEY `fk_siswa_user` (`id_pengguna`),
  CONSTRAINT `fk_siswa_user` FOREIGN KEY (`id_pengguna`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `siswa`
--

LOCK TABLES `siswa` WRITE;
/*!40000 ALTER TABLE `siswa` DISABLE KEYS */;
INSERT INTO `siswa` VALUES
(1,3,'1001','siswa 1',11,'RPL','L'),
(2,4,'1002','siswa 2',10,'TKL','L');
/*!40000 ALTER TABLE `siswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_certificates`
--

DROP TABLE IF EXISTS `staff_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `certificate_name` varchar(200) NOT NULL,
  `certificate_file` varchar(255) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `issuing_authority` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_id` (`staff_id`),
  CONSTRAINT `staff_certificates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `team_staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_certificates`
--

LOCK TABLES `staff_certificates` WRITE;
/*!40000 ALTER TABLE `staff_certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_events`
--

DROP TABLE IF EXISTS `team_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_events` (
  `team_id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_id`,`event_name`),
  KEY `idx_team_events_event_name` (`event_name`),
  CONSTRAINT `fk_team_events_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_events`
--

LOCK TABLES `team_events` WRITE;
/*!40000 ALTER TABLE `team_events` DISABLE KEYS */;
INSERT INTO `team_events` VALUES
(5,'LIGA AAFI BATAM U-13 PUTRA 2026','2026-02-10 14:32:31'),
(5,'Sepak Bola','2026-02-10 14:23:14'),
(7,'Futsal','2026-02-10 14:23:14'),
(8,'Futsal','2026-02-10 14:23:14'),
(9,'Futsal','2026-02-10 14:23:14'),
(10,'Futsal','2026-02-10 14:23:14'),
(12,'Badminton','2026-02-10 14:23:14'),
(13,'Panahan','2026-02-10 14:23:14'),
(14,'Futsal','2026-02-10 14:23:14'),
(15,'Futsal','2026-02-10 14:23:14'),
(16,'Sepak Bola','2026-02-10 14:23:14'),
(17,'Futsal','2026-02-10 14:23:14'),
(18,'Futsal','2026-02-10 14:23:14'),
(19,'Futsal','2026-02-10 14:23:14'),
(20,'Futsal','2026-02-10 14:23:14'),
(21,'Futsal','2026-02-10 14:23:14'),
(22,'Sepak Bola','2026-02-10 14:23:14'),
(23,'Sepak Bola','2026-02-10 14:23:14'),
(24,'Sepak Bola','2026-02-10 14:23:14'),
(25,'LIGA AAFI BATAM U-16 PUTRA 2026','2026-02-16 04:35:23'),
(26,'Futsal','2026-02-10 14:23:14'),
(27,'Sepak Bola','2026-02-10 14:23:14'),
(28,'LIGA AAFI BATAM U-16 PUTRA 2026','2026-03-01 05:52:17'),
(28,'Sepak Bola','2026-03-01 05:52:17'),
(29,'Sepak Bola','2026-02-10 14:23:14'),
(30,'Futsal','2026-02-10 14:23:14'),
(31,'Futsal','2026-02-10 14:23:14'),
(32,'LIGA AAFI BATAM U-16 PUTRA 2026','2026-02-16 04:35:08'),
(33,'Sepak Bola','2026-02-10 14:23:14'),
(34,'Sepak Bola','2026-02-10 14:23:14'),
(35,'Sepak Bola','2026-02-10 14:23:14'),
(36,'Futsal','2026-02-10 14:23:14'),
(37,'Sepak Bola','2026-02-10 14:23:14'),
(38,'Sepak Bola','2026-02-10 14:23:14'),
(39,'Futsal','2026-02-10 14:23:14'),
(40,'Futsal','2026-02-10 14:23:14'),
(41,'Futsal','2026-02-10 14:23:14'),
(42,'Sepak Bola','2026-02-10 14:23:14'),
(43,'LIGA AAFI BATAM U-16 PUTRA 2026','2026-03-01 05:53:17'),
(43,'Sepak Bola','2026-03-01 05:53:17'),
(44,'Futsal','2026-02-10 14:23:14'),
(45,'LIGA AAFI BATAM U-13 PUTRA 2026','2026-02-22 04:15:30');
/*!40000 ALTER TABLE `team_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_staff`
--

DROP TABLE IF EXISTS `team_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `birth_place` varchar(100) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Indonesia',
  `is_active` tinyint(1) DEFAULT 1,
  `team_id` int(11) DEFAULT NULL,
  `position` enum('manager','headcoach','coach','goalkeeper_coach','medic','official') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `idx_team_id` (`team_id`),
  KEY `idx_position` (`position`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `team_staff_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_staff`
--

LOCK TABLES `team_staff` WRITE;
/*!40000 ALTER TABLE `team_staff` DISABLE KEYS */;
INSERT INTO `team_staff` VALUES
(1,'Test Staff',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Indonesia',1,12,'coach',NULL,NULL,'2026-01-28 15:32:59','2026-01-28 15:32:59');
/*!40000 ALTER TABLE `team_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `manager` varchar(100) DEFAULT NULL,
  `coach` varchar(100) DEFAULT NULL,
  `basecamp` varchar(255) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `established_year` date DEFAULT NULL,
  `uniform_color` varchar(100) DEFAULT NULL,
  `sport_type` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teams_sport_type` (`sport_type`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES
(5,'theenty','ksks',NULL,'team_1769529051_6978dedb4b085.jpeg',NULL,'ks','batam',NULL,'2026-01-27 15:50:51','2019-01-01','juja','Sepak Bola',1,'2026-02-09 14:23:05'),
(7,'ss','sss',NULL,'team_1769529136_6978df306df61.jpeg',NULL,'eeee','batam',NULL,'2026-01-27 15:52:16','2001-01-01','merah','Futsal',1,'2026-02-09 14:23:05'),
(8,'ss','sss',NULL,'team_1769529156_6978df44184d0.jpeg',NULL,'eeee','batam',NULL,'2026-01-27 15:52:36','2001-01-01','merah','Futsal',1,'2026-02-09 14:23:05'),
(9,'ss','sss',NULL,'team_1769529160_6978df48eabb7.jpeg',NULL,'eeee','batam',NULL,'2026-01-27 15:52:40','2001-01-01','merah','Futsal',1,'2026-02-09 14:23:05'),
(10,'Test Team','TT',NULL,NULL,NULL,'Test Coach',NULL,NULL,'2026-01-27 16:20:04','2020-01-01',NULL,'Futsal',1,'2026-02-09 14:23:05'),
(12,'TEAM MULTAJAB','BUFC',NULL,'team_1769583751_6979b4877da31.png',NULL,'Waldi Multajab','SKAJU',NULL,'2026-01-28 07:02:31','2025-01-01','Birput','Badminton',1,'2026-02-09 14:23:05'),
(13,'House of Marove','mrve',NULL,'team_1769772462_697c95ae65b26.png',NULL,'Willem Marove','West Sumatra',NULL,'2026-01-28 10:27:20','1901-01-01','Kuning Harimau Sumatra','Panahan',1,'2026-02-09 14:23:05'),
(14,'BJFA','Bjfa',NULL,'team_1769587183_6979c1ef8127d.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 00:59:43','2026-01-01','Biru','Futsal',1,'2026-02-09 14:23:05'),
(15,'BR','Br',NULL,'team_1769587231_6979c21fddc17.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:00:31','2026-01-01','Hitam','Futsal',1,'2026-02-09 14:23:05'),
(16,'BSC','Bsc',NULL,'team_1769587276_6979c24c8abcb.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:01:16','2026-01-01','Putih','Sepak Bola',1,'2026-02-09 14:23:05'),
(17,'DULIM','Dulim',NULL,'team_1769587332_6979c284ace20.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:02:12','2026-01-01','Ungu','Futsal',1,'2026-02-09 14:23:05'),
(18,'DUPUL','Dupul',NULL,'team_1769587405_6979c2cdaa38a.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:03:25','2026-01-01','Putih','Futsal',1,'2026-02-09 14:23:05'),
(19,'FAFAGE','Fafage',NULL,'team_1769587466_6979c30a73084.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:04:26','2026-01-01','Biru','Futsal',1,'2026-02-09 14:23:05'),
(20,'GESYA','Gesya',NULL,'team_1769587509_6979c33567521.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:05:09','2026-01-01','Biru','Futsal',1,'2026-02-09 14:23:05'),
(21,'GRFA','Grfa',NULL,'team_1769587560_6979c368de1df.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:06:00','2026-01-01','Kuning','Futsal',1,'2026-02-09 14:23:05'),
(22,'GSP','Gsp',NULL,'team_1769587601_6979c391edf8a.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:06:41','2026-01-01','Emas','Sepak Bola',1,'2026-02-09 14:23:05'),
(23,'IMPERAL','Imperal',NULL,'team_1769587651_6979c3c348f76.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:07:31','2026-01-01','Hitam','Sepak Bola',1,'2026-02-09 14:23:05'),
(24,'KFA','Kfa',NULL,'team_1769587702_6979c3f653703.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:08:22','2026-01-01','Merah','Sepak Bola',1,'2026-02-09 14:23:05'),
(25,'KINGS','Kings',NULL,'team_1769587746_6979c42268874.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:09:06','2026-01-01','Pink','LIGA AAFI BATAM U-16 PUTRA 2026',1,'2026-02-16 04:35:23'),
(26,'KMC','Kmc',NULL,'team_1769587786_6979c44a3bbee.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:09:46','2026-01-01','Kuning','Futsal',1,'2026-02-09 14:23:05'),
(27,'LSFA','Lsfa',NULL,'team_1769587830_6979c476ca496.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:10:30','2026-01-01','Merah','Sepak Bola',1,'2026-02-09 14:23:05'),
(28,'MANTANG','Mantang',NULL,'team_1769587878_6979c4a65c8eb.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:11:18','2026-01-01','Hijau','LIGA AAFI BATAM U-16 PUTRA 2026',1,'2026-03-01 05:52:17'),
(29,'MEKASETA','Mekaseta',NULL,'team_1769587923_6979c4d337fc2.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:12:03','2026-01-01','Oren','Sepak Bola',1,'2026-02-09 14:23:05'),
(30,'NAMOR','Namor',NULL,'team_1769587960_6979c4f8240f3.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:12:40','2026-01-01','Putih','Futsal',1,'2026-02-09 14:23:05'),
(31,'NFA','Nfa',NULL,'team_1769587996_6979c51c30c42.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:13:16','2026-01-01','Hitam','Futsal',1,'2026-02-09 14:23:05'),
(32,'OXYGEN','Oxygen',NULL,'team_1769588031_6979c53fbd4bc.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:13:51','2026-01-01','Hijau','LIGA AAFI BATAM U-16 PUTRA 2026',1,'2026-02-16 04:35:08'),
(33,'PATRIOT','Patriot',NULL,'team_1769588091_6979c57bbbde6.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:14:51','2026-01-01','Putih','Sepak Bola',1,'2026-02-09 14:23:05'),
(34,'PROGRESS PLUS','Progress Plus',NULL,'team_1769588144_6979c5b0e0b5b.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:15:44','2026-01-01','Hitam','Sepak Bola',1,'2026-02-09 14:23:05'),
(35,'SMP 1 TPI','Smp 1 Tpi',NULL,'team_1769588184_6979c5d8b3d1b.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:16:24','2026-01-01','Oren','Sepak Bola',1,'2026-02-09 14:23:05'),
(36,'TANGO','Tango',NULL,'team_1769588219_6979c5fb4c63f.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:16:59','2026-01-01','Kuning','Futsal',1,'2026-02-09 14:23:05'),
(37,'TIGER','Tiger',NULL,'team_1769588276_6979c634250f2.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:17:56','2026-01-01','Emas','Sepak Bola',1,'2026-02-09 14:23:05'),
(38,'TIMASA','Timasa',NULL,'team_1769588316_6979c65cc9cfc.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:18:36','2026-01-01','Hitam','Sepak Bola',1,'2026-02-09 14:23:05'),
(39,'TIPUL','Tipul',NULL,'team_1769588350_6979c67e286f0.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:19:10','2026-01-01','Biru','Futsal',1,'2026-02-09 14:23:05'),
(40,'TMK','Tmk',NULL,'team_1769588386_6979c6a20b646.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:19:46','2026-01-01','Hitam','Futsal',1,'2026-02-09 14:23:05'),
(41,'TOPAS','Topas',NULL,'team_1769588425_6979c6c9cfc0d.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:20:25','2026-01-01','Biru','Futsal',1,'2026-02-09 14:23:05'),
(42,'TUNAS HARAPAN','Tunas Harapan',NULL,'team_1769588474_6979c6fa000a0.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:21:14','2026-01-01','Putih','Sepak Bola',1,'2026-02-09 14:23:05'),
(43,'VITANZA','Vitanza',NULL,'team_1769588513_6979c7216e1f1.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:21:53','2026-01-01','Abu-Abu','LIGA AAFI BATAM U-16 PUTRA 2026',1,'2026-03-01 05:53:17'),
(44,'YILDIZ','Yildiz',NULL,'team_1769588557_6979c74de0478.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:22:37','2026-01-01','Kuning','Futsal',1,'2026-02-09 14:23:05'),
(45,'ZAAP','Zaap',NULL,'team_1769588595_6979c77309121.jpeg',NULL,'Savety','FootSquare',NULL,'2026-01-28 01:23:15','2026-01-01','Hitam-Putih','LIGA AAFI BATAM U-13 PUTRA 2026',1,'2026-02-22 04:15:30');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transfers`
--

DROP TABLE IF EXISTS `transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `player_id` int(11) DEFAULT NULL,
  `from_team_id` int(11) DEFAULT NULL,
  `to_team_id` int(11) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `from_team_id` (`from_team_id`),
  KEY `to_team_id` (`to_team_id`),
  CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
  CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`from_team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`to_team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transfers`
--

LOCK TABLES `transfers` WRITE;
/*!40000 ALTER TABLE `transfers` DISABLE KEYS */;
INSERT INTO `transfers` VALUES
(1,29,32,17,'2026-02-06');
/*!40000 ALTER TABLE `transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `kata_sandi` varchar(255) NOT NULL,
  `peran` enum('admin','siswa','konselor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES
(1,'rickymarove@gmail.com','$2a$12$Njj20/FcS0J7J66e.Nq/tuBaF56T.3EL/L2NtuMHvzjrHH3MA5.7e','admin','2026-01-06 11:14:41'),
(2,'counselortest@gmail.com','$2y$10$bNFfH.CowmdmnFwDeB1iYeEFXtSKJOD0KaRRslMXwNLcFMDIIGoDW','konselor','2026-01-06 11:15:15'),
(3,'siswa@gmail.com','$2y$10$9DAukUv6xb0.nHAHFUMC6eknAs5uT7yQMtfy7u5mKPJGsjfCu3/ai','siswa','2026-01-06 11:15:38'),
(4,'siswa2@gmail.com','$2y$10$w1YC0Z6SSCCn/Y5JX/70CuvclpvfzJxW1XfZs/W9BktTqXI/iJuz.','siswa','2026-01-06 11:17:58');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venues`
--

DROP TABLE IF EXISTS `venues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `venues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `facilities` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venues`
--

LOCK TABLES `venues` WRITE;
/*!40000 ALTER TABLE `venues` DISABLE KEYS */;
INSERT INTO `venues` VALUES
(1,'FootSquare','Jl. Example No. 123',500,NULL,1,'2026-01-28 03:49:45','2026-01-28 03:49:45'),
(2,'GOR Buluh Indah','Jl. Buluh Indah',1000,NULL,1,'2026-01-28 03:49:45','2026-01-28 03:49:45'),
(3,'Lapangan Merdeka','Jl. Merdeka No. 67',800,NULL,1,'2026-01-28 03:49:45','2026-01-28 03:49:45'),
(4,'Stadion Utama','Jl. Stadion No. 1',5000,NULL,1,'2026-01-28 03:49:45','2026-01-28 03:49:45');
/*!40000 ALTER TABLE `venues` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
