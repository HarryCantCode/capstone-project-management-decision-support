-- MySQL dump 10.13  Distrib 8.0.32, for Linux (x86_64)
--
-- Host: localhost    Database: dex_pms
-- ------------------------------------------------------
-- Server version	8.0.32

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,'user','created','App\\Models\\User','created',1,NULL,NULL,'{\"attributes\": {\"name\": \"Harry Reyes\", \"email\": \"admin@dex-pms.local\", \"two_factor_enabled\": false}}',NULL,'2026-08-20 23:23:31','2026-08-20 23:23:31'),(2,'user','created','App\\Models\\User','created',2,NULL,NULL,'{\"attributes\": {\"name\": \"Maria Santos\", \"email\": \"manager@dex-pms.local\", \"two_factor_enabled\": false}}',NULL,'2026-08-20 23:23:31','2026-08-20 23:23:31'),(3,'user','created','App\\Models\\User','created',3,NULL,NULL,'{\"attributes\": {\"name\": \"Jose Cruz\", \"email\": \"inventory@dex-pms.local\", \"two_factor_enabled\": false}}',NULL,'2026-08-20 23:23:31','2026-08-20 23:23:31'),(4,'user','created','App\\Models\\User','created',4,'App\\Models\\User',1,'{\"attributes\": {\"name\": \"Charles Andrew Balanon\", \"email\": \"charles.arthur.herrero@dex-pms.local\", \"job_title\": \"admin\", \"employee_number\": \"12345\", \"two_factor_enabled\": false}}',NULL,'2026-08-21 17:58:27','2026-08-21 17:58:27'),(5,'user','updated','App\\Models\\User','updated',1,'App\\Models\\User',4,'{\"old\": {\"name\": \"Harry Reyes\", \"job_title\": null}, \"attributes\": {\"name\": \"Harry Aga\", \"job_title\": \"Developer\"}}',NULL,'2026-08-21 18:00:36','2026-08-21 18:00:36'),(6,'user','updated','App\\Models\\User','updated',4,'App\\Models\\User',4,'{\"old\": {\"employee_number\": \"12345\"}, \"attributes\": {\"employee_number\": \"EMP-005\"}}',NULL,'2026-08-21 18:01:31','2026-08-21 18:01:31'),(7,'project','created','App\\Models\\Project','created',1,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"SM Elevator\", \"status\": \"pending\", \"client_name\": \"SM CORP\"}}',NULL,'2026-08-25 17:28:07','2026-08-25 17:28:07'),(8,'project','deleted','App\\Models\\Project','deleted',1,'App\\Models\\User',4,'{\"old\": {\"name\": \"SM Elevator\", \"status\": \"pending\", \"client_name\": \"SM CORP\"}}',NULL,'2026-08-25 21:53:19','2026-08-25 21:53:19'),(9,'project','created','App\\Models\\Project','created',2,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"STI Munoz ELEVATOR\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-25 22:15:37','2026-08-25 22:15:37'),(10,'project','created','App\\Models\\Project','created',3,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"STI Munoz ELEVATOR\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-25 22:20:40','2026-08-25 22:20:40'),(11,'project','deleted','App\\Models\\Project','deleted',2,'App\\Models\\User',4,'{\"old\": {\"name\": \"STI Munoz ELEVATOR\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-25 22:21:26','2026-08-25 22:21:26'),(12,'project','deleted','App\\Models\\Project','deleted',3,'App\\Models\\User',4,'{\"old\": {\"name\": \"STI Munoz ELEVATOR\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-25 22:24:09','2026-08-25 22:24:09'),(13,'project','created','App\\Models\\Project','created',4,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"STI Munnoz Elavator\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-26 10:29:10','2026-08-26 10:29:10'),(14,'user','created','App\\Models\\User','created',5,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"Arthur Charles Andrew\", \"email\": \"dummy.user@dex-pms.local\", \"job_title\": \"dummy\", \"employee_number\": \"EMP-000000\", \"two_factor_enabled\": false}}',NULL,'2026-08-26 11:25:31','2026-08-26 11:25:31'),(15,'user','created','App\\Models\\User','created',6,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"jose rizal\", \"email\": \"jose.rizal@dex-pms.local\", \"job_title\": \"manager\", \"employee_number\": \"emp-12345\", \"two_factor_enabled\": false}}',NULL,'2026-08-26 12:37:03','2026-08-26 12:37:03'),(16,'user','updated','App\\Models\\User','updated',6,'App\\Models\\User',4,'{\"old\": {\"job_title\": \"manager\"}, \"attributes\": {\"job_title\": \"ceo\"}}',NULL,'2026-08-26 12:41:31','2026-08-26 12:41:31'),(17,'user','created','App\\Models\\User','created',7,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"Arthur Balanon\", \"email\": \"arthur.balanon@dex-pms.local\", \"job_title\": \"staff\", \"employee_number\": \"EMP-22222\", \"two_factor_enabled\": false}}',NULL,'2026-08-26 12:49:55','2026-08-26 12:49:55'),(18,'project','created','App\\Models\\Project','created',5,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"20th Floor SMDC condominium\", \"status\": \"pending\", \"client_name\": \"SMDC\"}}',NULL,'2026-08-27 22:53:05','2026-08-27 22:53:05'),(19,'project','created','App\\Models\\Project','created',6,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"20th Floor SMDC condominium\", \"status\": \"pending\", \"client_name\": \"SMDC\"}}',NULL,'2026-08-27 22:58:28','2026-08-27 22:58:28'),(20,'project','deleted','App\\Models\\Project','deleted',5,'App\\Models\\User',4,'{\"old\": {\"name\": \"20th Floor SMDC condominium\", \"status\": \"pending\", \"client_name\": \"SMDC\"}}',NULL,'2026-08-27 22:59:11','2026-08-27 22:59:11'),(21,'project','deleted','App\\Models\\Project','deleted',4,'App\\Models\\User',4,'{\"old\": {\"name\": \"STI Munnoz Elavator\", \"status\": \"pending\", \"client_name\": \"Christian Corbito\"}}',NULL,'2026-08-27 22:59:28','2026-08-27 22:59:28'),(22,'user','created','App\\Models\\User','created',8,NULL,NULL,'{\"attributes\": {\"name\": \"John Robert Smith\", \"email\": \"john.smith.verify@dex-pms.local\", \"birthdate\": \"2000-05-14T16:00:00.000000Z\", \"job_title\": \"Design Specialist\", \"last_name\": \"Smith\", \"first_name\": \"John\", \"middle_name\": \"Robert\", \"employee_number\": \"EMP-2026001\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:09:03','2026-08-29 22:09:03'),(23,'user','created','App\\Models\\User','created',9,NULL,NULL,'{\"attributes\": {\"name\": \"TestSync User\", \"email\": \"test.sync.user@dex-pms.local\", \"birthdate\": \"1998-04-19T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"User\", \"first_name\": \"TestSync\", \"middle_name\": null, \"account_number\": \"1000008\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:20:54','2026-08-29 22:20:54'),(24,'user','deleted','App\\Models\\User','deleted',9,'App\\Models\\User',1,'{\"old\": {\"name\": \"TestSync User\", \"email\": \"test.sync.user@dex-pms.local\", \"birthdate\": \"1998-04-19T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"User\", \"first_name\": \"TestSync\", \"middle_name\": null, \"account_number\": \"1000008\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": null}}',NULL,'2026-08-29 22:20:55','2026-08-29 22:20:55'),(25,'user','created','App\\Models\\User','created',10,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"Angelika Arrogante\", \"email\": \"angelika.fheb@dex-pms.local\", \"birthdate\": \"2005-02-26T16:00:00.000000Z\", \"job_title\": \"Manager\", \"last_name\": \"Arrogante\", \"first_name\": \"Angelika\", \"middle_name\": null, \"account_number\": \"1000008\", \"employee_number\": \"EMP-2026005\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:24:40','2026-08-29 22:24:40'),(26,'user','created','App\\Models\\User','created',11,NULL,NULL,'{\"attributes\": {\"name\": \"Archive Tester\", \"email\": \"archive.test@dex-pms.local\", \"birthdate\": \"1992-06-09T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"Tester\", \"first_name\": \"Archive\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:26','2026-08-29 22:33:26'),(27,'user','deleted','App\\Models\\User','deleted',11,'App\\Models\\User',11,'{\"old\": {\"name\": \"Archive Tester\", \"email\": \"archive.test@dex-pms.local\", \"birthdate\": \"1992-06-09T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"Tester\", \"first_name\": \"Archive\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:27','2026-08-29 22:33:27'),(28,'user','restored','App\\Models\\User','restored',11,NULL,NULL,'{\"attributes\": {\"name\": \"Archive Tester\", \"email\": \"archive.test@dex-pms.local\", \"birthdate\": \"1992-06-09T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"Tester\", \"first_name\": \"Archive\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:27','2026-08-29 22:33:27'),(29,'user','deleted','App\\Models\\User','deleted',11,'App\\Models\\User',11,'{\"old\": {\"name\": \"Archive Tester\", \"email\": \"archive.test@dex-pms.local\", \"birthdate\": \"1992-06-09T16:00:00.000000Z\", \"job_title\": \"Engineer\", \"last_name\": \"Tester\", \"first_name\": \"Archive\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": null}}',NULL,'2026-08-29 22:33:27','2026-08-29 22:33:27'),(30,'user','created','App\\Models\\User','created',12,'App\\Models\\User',11,'{\"attributes\": {\"name\": \"Manager SelfService\", \"email\": \"manager.selfservice@dex-pms.local\", \"birthdate\": \"1988-03-24T16:00:00.000000Z\", \"job_title\": \"Manager\", \"last_name\": \"SelfService\", \"first_name\": \"Manager\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:27','2026-08-29 22:33:27'),(31,'user','updated','App\\Models\\User','updated',12,'App\\Models\\User',12,'{\"old\": {\"email\": \"manager.selfservice@dex-pms.local\", \"two_factor_enabled\": null}, \"attributes\": {\"email\": \"manager.updated@dex-pms.local\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:28','2026-08-29 22:33:28'),(32,'user','deleted','App\\Models\\User','deleted',12,'App\\Models\\User',12,'{\"old\": {\"name\": \"Manager SelfService\", \"email\": \"manager.updated@dex-pms.local\", \"birthdate\": \"1988-03-24T16:00:00.000000Z\", \"job_title\": \"Manager\", \"last_name\": \"SelfService\", \"first_name\": \"Manager\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026006\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:33:29','2026-08-29 22:33:29'),(33,'user','deleted','App\\Models\\User','deleted',6,'App\\Models\\User',4,'{\"old\": {\"name\": \"jose rizal\", \"email\": \"jose.rizal@dex-pms.local\", \"birthdate\": null, \"job_title\": \"ceo\", \"last_name\": \"rizal\", \"first_name\": \"jose\", \"middle_name\": null, \"account_number\": \"1000006\", \"employee_number\": \"emp-12345\", \"two_factor_enabled\": false}}',NULL,'2026-08-29 22:35:01','2026-08-29 22:35:01'),(34,'user','deleted','App\\Models\\User','deleted',7,'App\\Models\\User',4,'{\"old\": {\"name\": \"Arthur Balanon\", \"email\": \"arthur.balanon@dex-pms.local\", \"birthdate\": null, \"job_title\": \"staff\", \"last_name\": \"Balanon\", \"first_name\": \"Arthur\", \"middle_name\": null, \"account_number\": \"1000007\", \"employee_number\": \"EMP-22222\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 19:18:43','2026-08-30 19:18:43'),(35,'user','deleted','App\\Models\\User','deleted',5,'App\\Models\\User',4,'{\"old\": {\"name\": \"Arthur Charles Andrew\", \"email\": \"dummy.user@dex-pms.local\", \"birthdate\": null, \"job_title\": \"dummy\", \"last_name\": \"Charles Andrew\", \"first_name\": \"Arthur\", \"middle_name\": null, \"account_number\": \"1000005\", \"employee_number\": \"EMP-000000\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 19:18:59','2026-08-30 19:18:59'),(36,'user','deleted','App\\Models\\User','deleted',3,'App\\Models\\User',4,'{\"old\": {\"name\": \"Jose Cruz\", \"email\": \"inventory@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Cruz\", \"first_name\": \"Jose\", \"middle_name\": null, \"account_number\": \"1000003\", \"employee_number\": \"EMP-003\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 19:19:18','2026-08-30 19:19:18'),(37,'user','deleted','App\\Models\\User','deleted',2,'App\\Models\\User',4,'{\"old\": {\"name\": \"Maria Santos\", \"email\": \"manager@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Santos\", \"first_name\": \"Maria\", \"middle_name\": null, \"account_number\": \"1000002\", \"employee_number\": \"EMP-002\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 19:19:29','2026-08-30 19:19:29'),(38,'project','updated','App\\Models\\Project','updated',6,'App\\Models\\User',4,'{\"old\": {\"contract_price\": \"0.00\", \"target_completion_date\": null}, \"attributes\": {\"contract_price\": \"20000000.00\", \"target_completion_date\": \"2026-10-24T16:00:00.000000Z\"}}',NULL,'2026-08-30 20:17:04','2026-08-30 20:17:04'),(39,'project','updated','App\\Models\\Project','updated',6,'App\\Models\\User',4,'{\"old\": {\"actual_spend\": \"0.00\"}, \"attributes\": {\"actual_spend\": \"2000000.00\"}}',NULL,'2026-08-30 20:18:19','2026-08-30 20:18:19'),(40,'project','created','App\\Models\\Project','created',7,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"Makati Towers\", \"status\": \"pending\", \"client_name\": \"AGA Corporation\", \"actual_spend\": \"0.00\", \"contract_price\": \"34999999.99\", \"target_completion_date\": \"2026-10-24T16:00:00.000000Z\"}}',NULL,'2026-08-30 20:34:21','2026-08-30 20:34:21'),(41,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',4,'{\"old\": {\"actual_spend\": \"0.00\"}, \"attributes\": {\"actual_spend\": \"2000000.00\"}}',NULL,'2026-08-30 20:36:04','2026-08-30 20:36:04'),(42,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',4,'{\"old\": {\"actual_spend\": \"2000000.00\"}, \"attributes\": {\"actual_spend\": \"4000000.00\"}}',NULL,'2026-08-30 20:36:08','2026-08-30 20:36:08'),(43,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',4,'{\"old\": {\"actual_spend\": \"4000000.00\"}, \"attributes\": {\"actual_spend\": \"2000000.00\"}}',NULL,'2026-08-30 20:36:22','2026-08-30 20:36:22'),(44,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',4,'{\"old\": {\"actual_spend\": \"2000000.00\"}, \"attributes\": {\"actual_spend\": \"3500000.00\"}}',NULL,'2026-08-30 20:38:54','2026-08-30 20:38:54'),(45,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',4,'{\"old\": {\"status\": \"pending\"}, \"attributes\": {\"status\": \"ongoing\"}}',NULL,'2026-08-30 20:42:40','2026-08-30 20:42:40'),(46,'project','updated','App\\Models\\Project','updated',6,'App\\Models\\User',4,'{\"old\": {\"status\": \"pending\"}, \"attributes\": {\"status\": \"completed\"}}',NULL,'2026-08-30 21:02:52','2026-08-30 21:02:52'),(47,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',10,'{\"old\": {\"actual_spend\": \"3500000.00\"}, \"attributes\": {\"actual_spend\": \"4500000.00\"}}',NULL,'2026-08-30 21:04:58','2026-08-30 21:04:58'),(48,'project','updated','App\\Models\\Project','updated',7,'App\\Models\\User',10,'{\"old\": {\"actual_spend\": \"4500000.00\"}, \"attributes\": {\"actual_spend\": \"14500000.00\"}}',NULL,'2026-08-30 21:27:24','2026-08-30 21:27:24'),(49,'project','created','App\\Models\\Project','created',8,'App\\Models\\User',10,'{\"attributes\": {\"name\": \"Pax Silica Elevators\", \"status\": \"pending\", \"client_name\": \"Elite group\", \"actual_spend\": \"0.00\", \"contract_price\": \"15000000.00\", \"target_completion_date\": \"2027-01-31T16:00:00.000000Z\"}}',NULL,'2026-08-30 23:02:06','2026-08-30 23:02:06'),(50,'project','updated','App\\Models\\Project','updated',8,'App\\Models\\User',10,'{\"old\": {\"status\": \"pending\"}, \"attributes\": {\"status\": \"ongoing\"}}',NULL,'2026-08-30 23:02:49','2026-08-30 23:02:49'),(51,'project','updated','App\\Models\\Project','updated',8,'App\\Models\\User',10,'{\"old\": {\"actual_spend\": \"0.00\"}, \"attributes\": {\"actual_spend\": \"1000000.00\"}}',NULL,'2026-08-30 23:45:24','2026-08-30 23:45:24'),(52,'user','deleted','App\\Models\\User','deleted',1,'App\\Models\\User',4,'{\"old\": {\"name\": \"Harry Aga\", \"email\": \"admin@dex-pms.local\", \"birthdate\": null, \"job_title\": \"Developer\", \"last_name\": \"Aga\", \"first_name\": \"Harry\", \"middle_name\": null, \"account_number\": \"1000001\", \"employee_number\": \"EMP-001\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 23:53:00','2026-08-30 23:53:00'),(53,'user','created','App\\Models\\User','created',13,'App\\Models\\User',4,'{\"attributes\": {\"name\": \"Harry Aga\", \"email\": \"harry.aga@dex-pms.local\", \"birthdate\": \"2004-01-24T16:00:00.000000Z\", \"job_title\": \"Developer\", \"last_name\": \"Aga\", \"first_name\": \"Harry\", \"middle_name\": null, \"account_number\": \"1000009\", \"employee_number\": \"EMP-2026007\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 23:55:10','2026-08-30 23:55:10'),(54,'user','deleted','App\\Models\\User','deleted',4,'App\\Models\\User',13,'{\"old\": {\"name\": \"Charles Andrew Balanon\", \"email\": \"charles.arthur.herrero@dex-pms.local\", \"birthdate\": null, \"job_title\": \"admin\", \"last_name\": \"Andrew Balanon\", \"first_name\": \"Charles\", \"middle_name\": null, \"account_number\": \"1000004\", \"employee_number\": \"EMP-005\", \"two_factor_enabled\": false}}',NULL,'2026-08-30 23:56:45','2026-08-30 23:56:45'),(55,'project','created','App\\Models\\Project','created',9,'App\\Models\\User',13,'{\"attributes\": {\"name\": \"Quezon City: All Purpose Building\", \"status\": \"pending\", \"client_name\": \"Quezon City Goverment\", \"actual_spend\": \"0.00\", \"contract_price\": \"4500000.00\", \"target_completion_date\": \"2027-01-24T16:00:00.000000Z\"}}',NULL,'2026-08-31 17:40:15','2026-08-31 17:40:15'),(56,'project','updated','App\\Models\\Project','updated',9,'App\\Models\\User',13,'{\"old\": {\"name\": \"Quezon City: All Purpose Building\"}, \"attributes\": {\"name\": \"Quezon City: Multi-Purpose Building\"}}',NULL,'2026-08-31 17:41:24','2026-08-31 17:41:24'),(57,'user','deleted','App\\Models\\User','deleted',10,NULL,NULL,'{\"old\": {\"name\": \"Angelika Arrogante\", \"email\": \"angelika.fheb@dex-pms.local\", \"birthdate\": \"2005-02-26T16:00:00.000000Z\", \"job_title\": \"Manager\", \"last_name\": \"Arrogante\", \"first_name\": \"Angelika\", \"middle_name\": null, \"account_number\": \"1000008\", \"employee_number\": \"EMP-2026005\", \"two_factor_enabled\": false}}',NULL,'2026-09-01 12:47:53','2026-09-01 12:47:53'),(58,'user','restored','App\\Models\\User','restored',10,'App\\Models\\User',13,'{\"attributes\": {\"name\": \"Angelika Arrogante\", \"email\": \"angelika.fheb@dex-pms.local\", \"birthdate\": \"2005-02-26T16:00:00.000000Z\", \"job_title\": \"Manager\", \"last_name\": \"Arrogante\", \"first_name\": \"Angelika\", \"middle_name\": null, \"account_number\": \"1000008\", \"employee_number\": \"EMP-2026005\", \"two_factor_enabled\": false}}',NULL,'2026-09-01 12:49:02','2026-09-01 12:49:02'),(59,'project','updated','App\\Models\\Project','updated',9,'App\\Models\\User',10,'{\"old\": {\"actual_spend\": \"0.00\"}, \"attributes\": {\"actual_spend\": \"100000.00\"}}',NULL,'2026-09-01 13:10:35','2026-09-01 13:10:35'),(60,'user','restored','App\\Models\\User','restored',1,NULL,NULL,'{\"attributes\": {\"name\": \"Harry Aga\", \"email\": \"admin@dex-pms.local\", \"birthdate\": null, \"job_title\": \"Developer\", \"last_name\": \"Aga\", \"first_name\": \"Harry\", \"middle_name\": null, \"account_number\": \"1000001\", \"employee_number\": \"EMP-001\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:37:08','2026-09-15 15:37:08'),(61,'user','restored','App\\Models\\User','restored',2,NULL,NULL,'{\"attributes\": {\"name\": \"Maria Santos\", \"email\": \"manager@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Santos\", \"first_name\": \"Maria\", \"middle_name\": null, \"account_number\": \"1000002\", \"employee_number\": \"EMP-002\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:37:08','2026-09-15 15:37:08'),(62,'user','restored','App\\Models\\User','restored',3,NULL,NULL,'{\"attributes\": {\"name\": \"Jose Cruz\", \"email\": \"inventory@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Cruz\", \"first_name\": \"Jose\", \"middle_name\": null, \"account_number\": \"1000003\", \"employee_number\": \"EMP-003\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:37:08','2026-09-15 15:37:08'),(63,'user','deleted','App\\Models\\User','deleted',1,'App\\Models\\User',13,'{\"old\": {\"name\": \"Harry Reyes\", \"email\": \"admin@dex-pms.local\", \"birthdate\": null, \"job_title\": \"Developer\", \"last_name\": \"Aga\", \"first_name\": \"Harry\", \"middle_name\": null, \"account_number\": \"1000001\", \"employee_number\": \"EMP-001\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:44:08','2026-09-15 15:44:08'),(64,'user','deleted','App\\Models\\User','deleted',2,'App\\Models\\User',13,'{\"old\": {\"name\": \"Maria Santos\", \"email\": \"manager@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Santos\", \"first_name\": \"Maria\", \"middle_name\": null, \"account_number\": \"1000002\", \"employee_number\": \"EMP-002\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:44:36','2026-09-15 15:44:36'),(65,'user','deleted','App\\Models\\User','deleted',3,'App\\Models\\User',13,'{\"old\": {\"name\": \"Jose Cruz\", \"email\": \"inventory@dex-pms.local\", \"birthdate\": null, \"job_title\": null, \"last_name\": \"Cruz\", \"first_name\": \"Jose\", \"middle_name\": null, \"account_number\": \"1000003\", \"employee_number\": \"EMP-003\", \"two_factor_enabled\": false}}',NULL,'2026-09-15 15:44:49','2026-09-15 15:44:49');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `decision_support_requests`
--

DROP TABLE IF EXISTS `decision_support_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `decision_support_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `input_summary` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','processing','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `output` json DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `decision_support_requests_project_id_foreign` (`project_id`),
  KEY `decision_support_requests_created_by_foreign` (`created_by`),
  CONSTRAINT `decision_support_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `decision_support_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `decision_support_requests`
--

LOCK TABLES `decision_support_requests` WRITE;
/*!40000 ALTER TABLE `decision_support_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `decision_support_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_08_20_000001_create_projects_table',1),(5,'2026_08_20_000002_create_project_status_history_table',1),(6,'2026_08_20_000003_create_resources_table',1),(7,'2026_08_20_000004_create_resource_allocations_table',1),(8,'2026_08_20_215254_create_permission_tables',1),(9,'2026_08_20_215305_create_activity_log_table',1),(10,'2026_08_20_215306_add_event_column_to_activity_log_table',1),(11,'2026_08_20_215307_add_batch_uuid_column_to_activity_log_table',1),(12,'2026_08_21_000000_add_employee_details_to_users_table',2),(13,'2026_08_21_101000_create_decision_support_requests_table',3),(14,'2026_08_27_000001_create_personnel_table',4),(15,'2026_08_29_000001_add_name_parts_and_birthdate_to_users_table',5),(16,'2026_08_29_000002_add_account_number_to_users_and_rename_staff_role',6),(17,'2026_08_29_000003_add_soft_deletes_to_users_table',7),(18,'2026_08_30_000001_add_timeline_costing_fields_to_projects_table',8),(19,'2026_08_30_000002_create_project_costs_and_add_delayed_status_to_projects',9),(20,'2026_08_30_000003_create_project_personnel_history_table',10),(21,'2026_09_01_000001_add_failed_login_attempts_to_users_table',11),(22,'2026_09_01_000002_add_locked_until_to_users_table',12);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(2,'App\\Models\\User',2),(3,'App\\Models\\User',3),(1,'App\\Models\\User',4),(3,'App\\Models\\User',5),(2,'App\\Models\\User',6),(3,'App\\Models\\User',7),(2,'App\\Models\\User',8),(2,'App\\Models\\User',10),(1,'App\\Models\\User',13);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personnel`
--

DROP TABLE IF EXISTS `personnel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personnel` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expertise` enum('Site Engineer','Foreman','Safety Officer','Worker') COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `date_assigned` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personnel_employee_id_unique` (`employee_id`),
  KEY `personnel_project_id_foreign` (`project_id`),
  KEY `personnel_created_by_foreign` (`created_by`),
  KEY `personnel_updated_by_foreign` (`updated_by`),
  CONSTRAINT `personnel_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `personnel_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `personnel_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personnel`
--

LOCK TABLES `personnel` WRITE;
/*!40000 ALTER TABLE `personnel` DISABLE KEYS */;
INSERT INTO `personnel` VALUES (1,'EMP-2026001','Angelika Fheb',NULL,'Arrogante','2005-02-27','Philippines','Site Engineer',7,'2026-09-01',4,10,'2026-08-27 22:36:12','2026-09-01 23:12:46',NULL),(2,'EMP-2026002','Charles',NULL,'Narido','2002-12-25','Philippines','Foreman',8,'2026-08-30',4,10,'2026-08-27 22:41:50','2026-08-30 23:39:30',NULL),(3,'EMP-2026003','Jhon Andrew',NULL,'Herrero','2004-08-11','Philippines','Safety Officer',7,'2026-09-01',4,10,'2026-08-27 22:43:38','2026-09-01 23:12:46',NULL),(4,'EMP-2026004','Arthur',NULL,'Balanon','2004-03-10','Philippines','Safety Officer',8,'2026-08-30',4,10,'2026-08-27 22:51:20','2026-08-30 23:44:11',NULL),(6,'EMP-2026006','Coco',NULL,'Martin','1990-02-05','Philippines','Foreman',8,'2026-08-30',10,10,'2026-08-30 22:01:49','2026-08-30 23:37:26',NULL),(7,'EMP-2026008','Dandren Juzztine',NULL,'Fernandez','2003-06-18','Philippines','Worker',9,'2026-09-04',13,13,'2026-08-31 14:51:32','2026-09-04 11:49:30',NULL),(8,'EMP-2026009','Jomari',NULL,'Caylao','2004-03-08','Philippines','Worker',9,'2026-09-04',13,13,'2026-08-31 14:54:45','2026-09-04 11:49:17',NULL),(9,'EMP-2026010','Mike',NULL,'Abordo','2002-04-12','Philipppines','Worker',7,'2026-09-04',13,13,'2026-08-31 15:11:58','2026-09-04 11:48:54',NULL),(10,'EMP-2026011','Nathaniel Carlo',NULL,'Morva','2003-12-28','Philippines','Site Engineer',9,'2026-09-01',13,10,'2026-08-31 15:26:46','2026-09-01 13:09:39',NULL),(11,'EMP-2026012','Jerson',NULL,'Dionisio','2004-01-15','Philippines','Worker',7,'2026-09-01',13,10,'2026-08-31 15:28:40','2026-09-01 23:12:24',NULL),(12,'EMP-2026013','Jhon Nerwin',NULL,'Aga','2007-02-22','Philippines','Worker',9,'2026-09-01',13,10,'2026-08-31 15:30:34','2026-09-01 13:09:40',NULL);
/*!40000 ALTER TABLE `personnel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_costs`
--

DROP TABLE IF EXISTS `project_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'materials',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `incurred_date` date DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_costs_created_by_foreign` (`created_by`),
  KEY `project_costs_updated_by_foreign` (`updated_by`),
  KEY `project_costs_project_id_created_at_index` (`project_id`,`created_at`),
  CONSTRAINT `project_costs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_costs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_costs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_costs`
--

LOCK TABLES `project_costs` WRITE;
/*!40000 ALTER TABLE `project_costs` DISABLE KEYS */;
INSERT INTO `project_costs` VALUES (1,6,'Excavation','other',2000000.00,'2026-08-30',4,4,'2026-08-30 20:18:19','2026-08-30 20:18:19',NULL),(2,7,'Floor Excavation','equipment',2000000.00,'2026-08-30',4,4,'2026-08-30 20:36:04','2026-08-30 20:36:22','2026-08-30 20:36:22'),(3,7,'Floor Excavation','equipment',2000000.00,'2026-08-30',4,4,'2026-08-30 20:36:08','2026-08-30 20:36:08',NULL),(4,7,'Scaffolding','materials',1500000.00,'2026-08-30',4,4,'2026-08-30 20:38:53','2026-08-30 20:38:53',NULL),(5,7,'Labor','labor',1000000.00,'2026-08-30',10,10,'2026-08-30 21:04:57','2026-08-30 21:04:57',NULL),(6,7,'Construction of railings','labor',10000000.00,'2026-08-30',10,10,'2026-08-30 21:27:24','2026-08-30 21:27:24',NULL),(7,8,'Scaffolding construction','other',1000000.00,'2026-08-30',10,10,'2026-08-30 23:45:23','2026-08-30 23:45:23',NULL),(8,9,'Excavation','other',100000.00,'2026-09-01',10,10,'2026-09-01 13:10:35','2026-09-01 13:10:35',NULL);
/*!40000 ALTER TABLE `project_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_personnel_history`
--

DROP TABLE IF EXISTS `project_personnel_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_personnel_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `personnel_id` bigint unsigned NOT NULL,
  `assigned_at` date NOT NULL,
  `released_at` date DEFAULT NULL,
  `release_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_by` bigint unsigned DEFAULT NULL,
  `released_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_personnel_history_assigned_by_foreign` (`assigned_by`),
  KEY `project_personnel_history_released_by_foreign` (`released_by`),
  KEY `project_personnel_history_project_id_released_at_index` (`project_id`,`released_at`),
  KEY `project_personnel_history_personnel_id_released_at_index` (`personnel_id`,`released_at`),
  CONSTRAINT `project_personnel_history_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_personnel_history_personnel_id_foreign` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_personnel_history_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_personnel_history_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_personnel_history`
--

LOCK TABLES `project_personnel_history` WRITE;
/*!40000 ALTER TABLE `project_personnel_history` DISABLE KEYS */;
INSERT INTO `project_personnel_history` VALUES (1,6,1,'2026-08-27','2026-08-30','unassigned',4,10,'2026-08-30 23:06:34','2026-08-30 23:31:45'),(2,6,2,'2026-08-27','2026-08-30','unassigned',4,10,'2026-08-30 23:06:34','2026-08-30 23:31:45'),(3,6,3,'2026-08-27','2026-08-30','unassigned',4,10,'2026-08-30 23:06:34','2026-08-30 23:31:45'),(4,6,4,'2026-08-30','2026-08-30','unassigned',4,10,'2026-08-30 23:06:34','2026-08-30 23:28:24'),(5,7,6,'2026-08-30','2026-08-30','unassigned',10,10,'2026-08-30 23:06:34','2026-08-30 23:11:20'),(6,8,1,'2026-08-30','2026-08-30','unassigned',10,10,'2026-08-30 23:37:26','2026-08-30 23:46:24'),(7,8,6,'2026-08-30',NULL,NULL,10,NULL,'2026-08-30 23:37:26','2026-08-30 23:37:26'),(8,8,2,'2026-08-30',NULL,NULL,10,NULL,'2026-08-30 23:39:30','2026-08-30 23:39:30'),(9,8,4,'2026-08-30',NULL,NULL,10,NULL,'2026-08-30 23:44:11','2026-08-30 23:44:11'),(10,9,10,'2026-09-01',NULL,NULL,10,NULL,'2026-09-01 13:09:40','2026-09-01 13:09:40'),(11,9,12,'2026-09-01',NULL,NULL,10,NULL,'2026-09-01 13:09:40','2026-09-01 13:09:40'),(12,7,11,'2026-09-01',NULL,NULL,10,NULL,'2026-09-01 23:12:24','2026-09-01 23:12:24'),(13,7,1,'2026-09-01',NULL,NULL,10,NULL,'2026-09-01 23:12:46','2026-09-01 23:12:46'),(14,7,3,'2026-09-01',NULL,NULL,10,NULL,'2026-09-01 23:12:46','2026-09-01 23:12:46'),(15,7,9,'2026-09-04',NULL,NULL,13,NULL,'2026-09-04 11:48:54','2026-09-04 11:48:54'),(16,9,8,'2026-09-04',NULL,NULL,13,NULL,'2026-09-04 11:49:17','2026-09-04 11:49:17'),(17,9,7,'2026-09-04',NULL,NULL,13,NULL,'2026-09-04 11:49:30','2026-09-04 11:49:30');
/*!40000 ALTER TABLE `project_personnel_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_status_history`
--

DROP TABLE IF EXISTS `project_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `from_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `changed_by` bigint unsigned NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_status_history_changed_by_foreign` (`changed_by`),
  KEY `project_status_history_project_id_changed_at_index` (`project_id`,`changed_at`),
  CONSTRAINT `project_status_history_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `project_status_history_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_status_history`
--

LOCK TABLES `project_status_history` WRITE;
/*!40000 ALTER TABLE `project_status_history` DISABLE KEYS */;
INSERT INTO `project_status_history` VALUES (1,1,NULL,'pending',NULL,4,'2026-08-25 17:28:06'),(2,2,NULL,'pending',NULL,4,'2026-08-25 22:15:37'),(3,3,NULL,'pending',NULL,4,'2026-08-25 22:20:40'),(4,4,NULL,'pending',NULL,4,'2026-08-26 10:29:10'),(5,5,NULL,'pending',NULL,4,'2026-08-27 22:53:04'),(6,6,NULL,'pending',NULL,4,'2026-08-27 22:58:27'),(7,6,'pending','pending','Contract price set to ₱20,000,000.00 · Target completion date set to Oct 25, 2026 · Start date set to Aug 30, 2026',4,'2026-08-30 20:17:04'),(8,6,'pending','pending','Actual spend updated to ₱2,000,000.00',4,'2026-08-30 20:18:19'),(9,7,NULL,'pending','Project created with initial status Pending and contract value ₱34,999,999.99',4,'2026-08-30 20:34:21'),(10,7,'pending','pending','Actual spend updated to ₱2,000,000.00',4,'2026-08-30 20:36:04'),(11,7,'pending','pending','Actual spend updated to ₱4,000,000.00',4,'2026-08-30 20:36:08'),(12,7,'pending','pending','Actual spend updated to ₱2,000,000.00',4,'2026-08-30 20:36:22'),(13,7,'pending','pending','Actual spend updated to ₱3,500,000.00',4,'2026-08-30 20:38:54'),(14,7,'pending','ongoing','Status updated to Ongoing',4,'2026-08-30 20:42:40'),(15,6,'pending','completed','Status updated to Completed',4,'2026-08-30 21:02:52'),(16,7,'ongoing','ongoing','Actual spend updated to ₱4,500,000.00',10,'2026-08-30 21:04:57'),(17,7,'ongoing','ongoing','Actual spend updated to ₱14,500,000.00',10,'2026-08-30 21:27:24'),(18,8,NULL,'pending','Project created with initial status Pending and contract value ₱15,000,000.00',10,'2026-08-30 23:02:06'),(19,8,'pending','ongoing','Status updated to Ongoing',10,'2026-08-30 23:02:49'),(20,8,'ongoing','ongoing','Assigned manpower: EMP-2026004 (Arthur Balanon - Safety Officer)',10,'2026-08-30 23:44:11'),(21,8,'ongoing','ongoing','Actual spend updated from ₱0.00 to ₱1,000,000.00',10,'2026-08-30 23:45:23'),(22,8,'ongoing','ongoing','Logged expenditure of ₱1,000,000.00 for Other (Scaffolding construction)',10,'2026-08-30 23:45:24'),(23,8,'ongoing','ongoing','Unassigned manpower: EMP-2026001 (Angelika Fheb Arrogante - Site Engineer)',10,'2026-08-30 23:46:24'),(24,9,NULL,'pending','Project created with initial status Pending and contract value ₱4,500,000.00',13,'2026-08-31 17:40:15'),(25,9,'pending','pending','Project name changed from \'Quezon City: All Purpose Building\' to \'Quezon City: Multi-Purpose Building\'',13,'2026-08-31 17:41:24'),(26,9,'pending','pending','Assigned 2 manpower: EMP-2026011 (Nathaniel Carlo Morva - Site Engineer), EMP-2026013 (Jhon Nerwin Aga - Worker)',10,'2026-09-01 13:09:40'),(27,9,'pending','pending','Actual spend updated from ₱0.00 to ₱100,000.00',10,'2026-09-01 13:10:35'),(28,9,'pending','pending','Logged expenditure of ₱100,000.00 for Other (Excavation)',10,'2026-09-01 13:10:35'),(29,7,'ongoing','ongoing','Assigned 1 manpower (Batch): EMP-2026012 (Jerson Dionisio - Worker)',10,'2026-09-01 23:12:24'),(30,7,'ongoing','ongoing','Assigned 2 manpower (Batch): EMP-2026001 (Angelika Fheb Arrogante - Site Engineer), EMP-2026003 (Jhon Andrew Herrero - Safety Officer)',10,'2026-09-01 23:12:46'),(31,7,'ongoing','ongoing','Assigned manpower: EMP-2026010 (Mike Abordo - Worker)',13,'2026-09-04 11:48:54'),(32,9,'pending','pending','Assigned manpower: EMP-2026009 (Jomari Caylao - Worker)',13,'2026-09-04 11:49:17'),(33,9,'pending','pending','Assigned manpower: EMP-2026008 (Dandren Juzztine Fernandez - Worker)',13,'2026-09-04 11:49:30');
/*!40000 ALTER TABLE `project_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `target_completion_date` date DEFAULT NULL,
  `contract_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `actual_spend` decimal(12,2) NOT NULL DEFAULT '0.00',
  `project_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_project_code_unique` (`project_code`),
  KEY `projects_created_by_foreign` (`created_by`),
  KEY `projects_updated_by_foreign` (`updated_by`),
  KEY `projects_status_created_at_index` (`status`,`created_at`),
  KEY `projects_status_index` (`status`),
  CONSTRAINT `projects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `projects_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,'SM Elevator','description here','SM CORP',NULL,NULL,0.00,0.00,'DEX-2026-001','pending','2026-08-25 21:53:19',4,4,'2026-08-25 17:28:06','2026-08-25 21:53:19'),(2,'STI Munoz ELEVATOR','10th Floor elevator System','Christian Corbito',NULL,NULL,0.00,0.00,'DEX-2026-002','pending','2026-08-25 22:21:26',4,4,'2026-08-25 22:15:37','2026-08-25 22:21:26'),(3,'STI Munoz ELEVATOR','10th Floor elevator System','Christian Corbito',NULL,NULL,0.00,0.00,'DEX-2026-003','pending','2026-08-25 22:24:09',4,4,'2026-08-25 22:20:40','2026-08-25 22:24:09'),(4,'STI Munnoz Elavator','ELEVATOR 10th Floor','Christian Corbito',NULL,NULL,0.00,0.00,'DEX-2026-004','pending','2026-08-27 22:59:28',4,4,'2026-08-26 10:29:10','2026-08-27 22:59:28'),(5,'20th Floor SMDC condominium','500ft tall','SMDC',NULL,NULL,0.00,0.00,'DEX-2026-005','pending','2026-08-27 22:59:11',4,4,'2026-08-27 22:53:04','2026-08-27 22:59:11'),(6,'20th Floor SMDC condominium','500ft tall','SMDC','2026-08-30','2026-10-25',20000000.00,2000000.00,'DEX-2026-006','completed',NULL,4,4,'2026-08-27 22:58:27','2026-08-30 21:02:52'),(7,'Makati Towers','25th Floor building','AGA Corporation','2026-08-30','2026-10-25',34999999.99,14500000.00,'DEX-2026-007','ongoing',NULL,4,10,'2026-08-30 20:34:21','2026-08-30 21:27:24'),(8,'Pax Silica Elevators','Multi-level building for pax silica','Elite group','2026-12-01','2027-02-01',15000000.00,1000000.00,'DEX-2026-008','ongoing',NULL,10,10,'2026-08-30 23:02:06','2026-08-30 23:45:23'),(9,'Quezon City: Multi-Purpose Building','Two (2) units passenger elevator','Quezon City Goverment','2026-09-25','2027-01-25',4500000.00,100000.00,'DEX-2026-009','pending',NULL,13,10,'2026-08-31 17:40:15','2026-09-01 13:10:35');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resource_allocations`
--

DROP TABLE IF EXISTS `resource_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `resource_id` bigint unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_allocations_resource_id_foreign` (`resource_id`),
  KEY `resource_allocations_created_by_foreign` (`created_by`),
  KEY `resource_allocations_updated_by_foreign` (`updated_by`),
  KEY `resource_allocations_project_id_resource_id_index` (`project_id`,`resource_id`),
  CONSTRAINT `resource_allocations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `resource_allocations_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resource_allocations_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`),
  CONSTRAINT `resource_allocations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resource_allocations`
--

LOCK TABLES `resource_allocations` WRITE;
/*!40000 ALTER TABLE `resource_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `resource_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resources`
--

DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('material','tool','equipment') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_available` int unsigned NOT NULL DEFAULT '0',
  `condition` enum('good','needs_maintenance','out_of_service') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `updated_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resources_resource_code_unique` (`resource_code`),
  KEY `resources_created_by_foreign` (`created_by`),
  KEY `resources_updated_by_foreign` (`updated_by`),
  KEY `resources_type_quantity_available_index` (`type`,`quantity_available`),
  KEY `resources_type_index` (`type`),
  KEY `resources_condition_index` (`condition`),
  CONSTRAINT `resources_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `resources_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resources`
--

LOCK TABLES `resources` WRITE;
/*!40000 ALTER TABLE `resources` DISABLE KEYS */;
INSERT INTO `resources` VALUES (1,'Elevator Guide Rail','MAT-2026-001','material',48,'good',NULL,2,2,'2026-08-20 23:23:32','2026-08-20 23:23:32'),(2,'Safety Harness','TOOL-2026-001','tool',12,'needs_maintenance',NULL,2,5,'2026-08-20 23:23:32','2026-08-26 11:56:28'),(3,'Chain Block Hoist','EQP-2026-001','equipment',3,'good',NULL,2,5,'2026-08-20 23:23:32','2026-08-26 11:54:16');
/*!40000 ALTER TABLE `resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','web','2026-08-20 23:23:30','2026-08-20 23:23:30'),(2,'Manager','web','2026-08-20 23:23:30','2026-08-20 23:23:30'),(3,'Staff','web','2026-08-20 23:23:30','2026-08-20 23:23:30');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `employee_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_login_attempts` int unsigned NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_employee_number_unique` (`employee_number`),
  UNIQUE KEY `users_account_number_unique` (`account_number`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_updated_by_foreign` (`updated_by`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Harry Reyes','Harry',NULL,'Aga',NULL,'EMP-001','1000001','Developer','admin@dex-pms.local',NULL,'$2y$12$AUFeoEcnxDjplNIaWCxFFuTTMW4w8e6CCgrL44v5p0EMGoJH07Wz.',0,NULL,'XoGMRW0WU5QrYH0wL7RvaGO3UL3XlA8FYj7Zp6TOvjbqFoMdTupUJXmqPwU8',NULL,0,1,1,'2026-08-20 23:23:31','2026-09-15 15:44:08','2026-09-15 15:44:08'),(2,'Maria Santos','Maria',NULL,'Santos',NULL,'EMP-002','1000002',NULL,'manager@dex-pms.local',NULL,'$2y$12$5c9aoRSShIR4GSMsbw5Vieu5788DAQBC3DUSNYtYY9gO5X5hYjTN6',0,NULL,NULL,NULL,0,2,2,'2026-08-20 23:23:31','2026-09-15 15:44:36','2026-09-15 15:44:36'),(3,'Jose Cruz','Jose',NULL,'Cruz',NULL,'EMP-003','1000003',NULL,'inventory@dex-pms.local',NULL,'$2y$12$n/GrChhPKBfSPzd9sjDcvu97zCNq7gFO0Z6rDpswPr3fo76SBIP46',0,NULL,NULL,NULL,0,3,3,'2026-08-20 23:23:31','2026-09-15 15:44:48','2026-09-15 15:44:48'),(4,'Charles Andrew Balanon','Charles',NULL,'Andrew Balanon',NULL,'EMP-005','1000004','admin','charles.arthur.herrero@dex-pms.local',NULL,'$2y$12$bKgTnTETumZT/qglf2UL2uYP0lq4MbZUaYChx/wCqWpUT2lrKmqGy',0,NULL,'yXi5vpyKMz3CaUSJjMYJVickfJAktZIKvSBneNDwE6WRjUxYXcXGljKgmCau',NULL,0,NULL,NULL,'2026-08-21 17:58:27','2026-08-30 23:56:45','2026-08-30 23:56:45'),(5,'Arthur Charles Andrew','Arthur',NULL,'Charles Andrew',NULL,'EMP-000000','1000005','dummy','dummy.user@dex-pms.local',NULL,'$2y$12$iOtGUj90KjEkoraa./aTCeo9GDt/TXW8FR6Z7j33elM8r9svGiOYS',0,NULL,NULL,NULL,0,NULL,NULL,'2026-08-26 11:25:31','2026-08-30 19:18:59','2026-08-30 19:18:59'),(6,'jose rizal','jose',NULL,'rizal',NULL,'emp-12345','1000006','ceo','jose.rizal@dex-pms.local',NULL,'$2y$12$14sRfK/0T3RrC7rJ0n2H9uyNisdTMKyj/hRW/JtSnslCZigiO./PS',0,NULL,NULL,NULL,0,NULL,NULL,'2026-08-26 12:37:02','2026-08-29 22:35:01','2026-08-29 22:35:01'),(7,'Arthur Balanon','Arthur',NULL,'Balanon',NULL,'EMP-22222','1000007','staff','arthur.balanon@dex-pms.local',NULL,'$2y$12$4/l4X1tcqF8Khme2J.C2dezHFKHWvaD9Amf1sSaKf1jUI7uSFS6lq',0,NULL,NULL,NULL,0,NULL,NULL,'2026-08-26 12:49:55','2026-08-30 19:18:43','2026-08-30 19:18:43'),(10,'Angelika Arrogante','Angelika',NULL,'Arrogante','2005-02-27','EMP-2026005','1000008','Manager','angelika.fheb@dex-pms.local',NULL,'$2y$12$zL6/Vpigv7bsyvlcnVCxleRdO5zVwpcM63GCcPhJibyzMneixDMzS',0,NULL,NULL,NULL,0,4,4,'2026-08-29 22:24:40','2026-09-01 22:53:12',NULL),(13,'Harry Aga','Harry',NULL,'Aga','2004-01-25','EMP-2026007','1000009','Developer','harry.aga@dex-pms.local',NULL,'$2y$12$xgbOscMGdnwrF3s6oInsW.Ya.OxVRAm3rW9JU5Og93b8EWFSWxoWK',0,NULL,'ntcr8Q1W0Qhf3KG6AkMyQ4a5v4tHdtfoh7aa1uWJE1rPXf39D7eD5hcGOI2C',NULL,0,4,4,'2026-08-30 23:55:09','2026-08-30 23:55:09',NULL);
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

-- Dump completed on 2026-09-15  7:45:01
