/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: sispresensi_db
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-0+deb13u1 from Debian

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
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `check_in_lat` decimal(10,8) DEFAULT NULL,
  `check_in_lng` decimal(11,8) DEFAULT NULL,
  `check_in_distance` decimal(8,2) DEFAULT NULL,
  `check_in_photo` text DEFAULT NULL,
  `check_in_status` enum('on_time','late','out_of_radius') DEFAULT 'on_time',
  `check_out_lat` decimal(10,8) DEFAULT NULL,
  `check_out_lng` decimal(11,8) DEFAULT NULL,
  `check_out_distance` decimal(8,2) DEFAULT NULL,
  `check_out_photo` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_emp_date` (`employee_id`,`date`),
  KEY `idx_date` (`date`),
  CONSTRAINT `attendances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
INSERT INTO `attendances` VALUES
(1,2,'2026-09-11','08:03:49','18:25:00',-6.22415700,106.80960000,71.00,'https://images.unsplash.com/photo-1500000000000?w=200&auto=format&fit=crop&q=80','on_time',-6.22416800,106.80967500,10.50,NULL,'Presensi pagi | Pulang kantor aman',NULL,NULL,'2026-09-17 11:06:19'),
(6,2,'2026-09-14','08:11:56','17:17:53',-6.22414700,106.80963100,37.00,'https://images.unsplash.com/photo-1500000000000?w=200&auto=format&fit=crop&q=80','on_time',-6.22416800,106.80967500,25.00,NULL,'Kehadiran kantor normal',NULL,NULL,'2026-09-17 11:06:19'),
(11,2,'2026-09-15','08:15:23','17:17:24',-6.22413200,106.80968700,53.00,'https://images.unsplash.com/photo-1500000000000?w=200&auto=format&fit=crop&q=80','on_time',-6.22416800,106.80967500,25.00,NULL,'Kehadiran kantor normal',NULL,NULL,'2026-09-17 11:06:19'),
(16,2,'2026-09-16','07:47:13','17:26:45',-6.22424300,106.80971500,56.00,'https://images.unsplash.com/photo-1500000000000?w=200&auto=format&fit=crop&q=80','on_time',-6.22416800,106.80967500,25.00,NULL,'Kehadiran kantor normal',NULL,NULL,'2026-09-17 11:06:19'),
(21,2,'2026-09-17','07:56:29','17:25:37',-6.22411200,106.80974600,28.00,'https://images.unsplash.com/photo-1500000000000?w=200&auto=format&fit=crop&q=80','on_time',-6.22416800,106.80967500,25.00,NULL,'Kehadiran kantor normal',NULL,NULL,'2026-09-17 11:06:19'),
(28,2,'2026-09-18','09:29:29',NULL,0.47713781,101.43140157,21.40,'assets/uploads/selfie_check_in_2_20260918_092929.jpg','late',NULL,NULL,NULL,NULL,'','202.67.45.36','Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36','2026-09-18 02:29:29');
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nip` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `department` varchar(100) NOT NULL DEFAULT 'Teknologi Informasi',
  `position` varchar(100) NOT NULL DEFAULT 'Software Engineer',
  `phone` varchar(30) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nip` (`nip`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES
(2,'EMP-2026-001','Dimas Bagus Saputra','dimas.bagus@perusahaan.com','$2y$12$mvK.TXkdcgMft1xRkw0lne5xOgNC7rXPEXxcNCEQ2dEBHd6mh8ENy','employee','Software Engineering','Senior Fullstack Developer','082198765432','https://sispresensi.hntrwebtech.com/assets/uploads/profile_2_1789876734_397.jpeg',1,'2026-09-17 11:06:19'),
(8,'ADM-001','danki buyuang','admin@perusahaan.com','$2y$12$ykXmCXT4euB49r8s5m2R1.s2DmflyaFqPrbYxz0TYXOyRDf/j3d3K','admin','Management & IT','Head of Technology & Ops','081234567890','https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',1,'2026-09-20 03:45:38');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `office_name` varchar(150) NOT NULL DEFAULT 'Kantor Pusat PT Digital Solusindo',
  `office_address` text DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL DEFAULT -6.22416800,
  `longitude` decimal(11,8) NOT NULL DEFAULT 106.80967500,
  `radius_meters` int(11) NOT NULL DEFAULT 100,
  `work_start_time` time NOT NULL DEFAULT '08:00:00',
  `work_end_time` time NOT NULL DEFAULT '17:00:00',
  `late_tolerance_minutes` int(11) NOT NULL DEFAULT 15,
  `enforce_radius` tinyint(1) NOT NULL DEFAULT 1,
  `require_selfie` tinyint(1) NOT NULL DEFAULT 1,
  `allow_simulation` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(1,'Head Office PT Danki Buyuang','Pacific Century Place Lt. 28, SCBD Kav. 52-53, Jl. Jend. Sudirman, Senayan, Jakarta Selatan',0.47698781,101.43128157,60,'08:00:00','17:00:00',15,1,1,1,'2026-09-20 04:32:49');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-20 11:38:03
