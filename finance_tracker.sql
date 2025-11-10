-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 10, 2025 at 11:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `finance_tracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `main_category` varchar(100) NOT NULL,
  `sub_category` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `main_category`, `sub_category`) VALUES
(20, 'MANAGEMENT', NULL),
(21, 'ADMINISTRATION', NULL),
(23, 'DISCIPLESHIP', NULL),
(24, 'WORSHIP', NULL),
(25, 'MINISTRY OF MINISTRIES', NULL),
(26, 'FELLOWSHIP', NULL),
(27, 'EVANGELISM', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expense_entries`
--

CREATE TABLE `expense_entries` (
  `id` int(11) NOT NULL,
  `sunday_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `income_entries`
--

CREATE TABLE `income_entries` (
  `id` int(11) NOT NULL,
  `sunday_id` int(11) NOT NULL,
  `income_type` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sundays`
--

CREATE TABLE `sundays` (
  `id` int(11) NOT NULL,
  `sunday_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sundays`
--

INSERT INTO `sundays` (`id`, `sunday_date`) VALUES
(11, '2025-01-05'),
(1, '2025-11-02'),
(7, '2025-11-09'),
(16, '2025-11-16'),
(9, '2025-12-28'),
(12, '2026-01-04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_entries`
--
ALTER TABLE `expense_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sunday_id` (`sunday_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Indexes for table `income_entries`
--
ALTER TABLE `income_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sunday_id` (`sunday_id`);

--
-- Indexes for table `sundays`
--
ALTER TABLE `sundays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sunday_date` (`sunday_date`),
  ADD KEY `idx_sunday_date` (`sunday_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `expense_entries`
--
ALTER TABLE `expense_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `income_entries`
--
ALTER TABLE `income_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `sundays`
--
ALTER TABLE `sundays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expense_entries`
--
ALTER TABLE `expense_entries`
  ADD CONSTRAINT `expense_entries_ibfk_1` FOREIGN KEY (`sunday_id`) REFERENCES `sundays` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expense_entries_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`);

--
-- Constraints for table `income_entries`
--
ALTER TABLE `income_entries`
  ADD CONSTRAINT `income_entries_ibfk_1` FOREIGN KEY (`sunday_id`) REFERENCES `sundays` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
