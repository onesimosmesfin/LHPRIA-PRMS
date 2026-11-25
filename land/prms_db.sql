-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2026 at 06:06 AM
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
-- Database: `prms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('HQ','SUBCITY') DEFAULT 'SUBCITY',
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `type`, `code`, `created_at`) VALUES
(1, 'Headquarter (HQ)', 'HQ', 'HQ', '2026-11-19 15:09:01'),
(2, 'Addis Ketema', 'SUBCITY', 'AK', '2026-11-19 15:09:01'),
(3, 'Akaky Kaliti', 'SUBCITY', 'AKT', '2026-11-19 15:09:01'),
(4, 'Arada', 'SUBCITY', 'ARD', '2026-11-19 15:09:01'),
(5, 'Bole', 'SUBCITY', 'BOL', '2026-11-19 15:09:01'),
(6, 'Gulele', 'SUBCITY', 'GUL', '2026-11-19 15:09:01'),
(7, 'Kirkos', 'SUBCITY', 'KIR', '2026-11-19 15:09:01'),
(8, 'Kolfe Keranio', 'SUBCITY', 'KK', '2026-11-19 15:09:01'),
(9, 'Lemi Kura', 'SUBCITY', 'LMK', '2026-11-19 15:09:01'),
(10, 'Lideta', 'SUBCITY', 'LID', '2026-11-19 15:09:01'),
(11, 'Nefas Silk-Lafto', 'SUBCITY', 'NSL', '2026-11-19 15:09:01'),
(12, 'Yeka', 'SUBCITY', 'YEK', '2026-11-19 15:09:01');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `fiscal_year` varchar(20) NOT NULL,
  `period` enum('Annual','Q1','Q2','Q3','Q4') NOT NULL,
  `location_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rolled_back') DEFAULT 'draft',
  `director_feedback` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plan_items`
--

CREATE TABLE `plan_items` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `baseline` decimal(12,2) DEFAULT 0.00,
  `target_value` decimal(12,2) NOT NULL,
  `unit_of_measure` varchar(50) NOT NULL,
  `planned_revenue` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `period` enum('Q1','Q2','Q3','Q4','Monthly','Annual') NOT NULL,
  `general_performance` text DEFAULT NULL,
  `challenges_faced` text DEFAULT NULL,
  `actions_taken` text DEFAULT NULL,
  `status` enum('submitted_to_subcity','rolled_back_to_reporter','forwarded_to_hq') DEFAULT 'submitted_to_subcity',
  `subcity_director_notes` text DEFAULT NULL,
  `hq_remarks` text DEFAULT NULL,
  `forwarded_to_hq_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_items`
--

CREATE TABLE `report_items` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `plan_item_id` int(11) NOT NULL,
  `achieved_value` decimal(12,2) NOT NULL,
  `earned_revenue` decimal(15,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','planner','subcity_director','reporter','hq_director') NOT NULL,
  `location_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role`, `location_id`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin', 'admin@prms.gov.et', '$2a$12$RrxbjPTwwvE4rQ5Y1yJa7um1yvnS8C5DnZKtmZC..xpSVsPCMivo.', 'admin', 1, 'active', '2026-11-19 15:09:02'),
(2, 'Abay Mulisa', 'Abay', 'abay@gmail.com', '$2y$10$Uc0YiW12Vr41QA6SMNMQbeDNeIDIL8nEpeON43IT8UCDFcpWTcNFW', 'planner', 3, 'active', '2026-11-19 15:33:20'),
(3, 'Geleta Tola', 'geleta', 'geleta@gmail.com', '$2y$10$yKMI2qkF8pHgQmBd4RiGtO9jut5TX4hSbO/gqaG42KBSoPMrqRmAG', 'subcity_director', 3, 'active', '2026-11-19 15:45:28'),
(4, 'Bersha', 'bersha', 'bersha@gmail.com', '$2y$10$I3WMMxyu1rjAcoFgM9kzkOqhHnexLHrMVWF/EYxPBqMfCvRovGlq2', 'reporter', 3, 'active', '2026-11-19 15:49:09'),
(5, 'Bekele Gudisa', 'bekele', 'bekele@gmail.com', '$2y$10$xKe0DnAPiX4Y.x2bEOtJtuBXdtPSYlVAHprjSlmOQfBDW6DFEoV32', 'hq_director', 1, 'active', '2026-11-19 16:39:19'),
(6, 'Abraham Mekonen', 'abrish', 'abrish@gmail.com', '$2y$10$O4lns264ji5YE7VBFa/aeOhcDAa8qrwRql.mc22yGy/pb7Wwp3zfC', 'planner', 3, 'active', '2026-11-20 07:26:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `plan_items`
--
ALTER TABLE `plan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Indexes for table `report_items`
--
ALTER TABLE `report_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `plan_item_id` (`plan_item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `location_id` (`location_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `plan_items`
--
ALTER TABLE `plan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `report_items`
--
ALTER TABLE `report_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `plans`
--
ALTER TABLE `plans`
  ADD CONSTRAINT `plans_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plans_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plans_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `plan_items`
--
ALTER TABLE `plan_items`
  ADD CONSTRAINT `plan_items_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_items`
--
ALTER TABLE `report_items`
  ADD CONSTRAINT `report_items_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_items_ibfk_2` FOREIGN KEY (`plan_item_id`) REFERENCES `plan_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
