-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: finance_tracker
-- ------------------------------------------------------
-- Server version	8.0.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_category` varchar(100) NOT NULL,
  `sub_category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
INSERT INTO `expense_categories` VALUES (1,'ADMINISTRATION',NULL),(2,'DISCIPLESHIP',NULL),(3,'EVANGELISM',NULL),(4,'FELLOWSHIP',NULL),(5,'MANAGEMENT',NULL),(6,'MINISTRY OF MINISTRIES',NULL),(7,'WORSHIP',NULL);
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_entries`
--

DROP TABLE IF EXISTS `expense_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sunday_id` int NOT NULL,
  `category_id` int NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sunday_id` (`sunday_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `expense_entries_ibfk_1` FOREIGN KEY (`sunday_id`) REFERENCES `sundays` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_entries_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_entries`
--

LOCK TABLES `expense_entries` WRITE;
/*!40000 ALTER TABLE `expense_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income_categories`
--

DROP TABLE IF EXISTS `income_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `main_category` varchar(100) NOT NULL,
  `sub_category` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income_categories`
--

LOCK TABLES `income_categories` WRITE;
/*!40000 ALTER TABLE `income_categories` DISABLE KEYS */;
INSERT INTO `income_categories` VALUES (1,'TITHES',NULL),(2,'DONATIONS',NULL);
/*!40000 ALTER TABLE `income_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `income_entries`
--

DROP TABLE IF EXISTS `income_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `income_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sunday_id` int NOT NULL,
  `category_id` int NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sunday_id` (`sunday_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `income_entries_ibfk_1` FOREIGN KEY (`sunday_id`) REFERENCES `sundays` (`id`) ON DELETE CASCADE,
  CONSTRAINT `income_entries_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `income_entries`
--

LOCK TABLES `income_entries` WRITE;
/*!40000 ALTER TABLE `income_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `income_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sundays`
--

DROP TABLE IF EXISTS `sundays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sundays` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sunday_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sunday_date` (`sunday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sundays`
--

LOCK TABLES `sundays` WRITE;
/*!40000 ALTER TABLE `sundays` DISABLE KEYS */;
/*!40000 ALTER TABLE `sundays` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-10 16:05:35
