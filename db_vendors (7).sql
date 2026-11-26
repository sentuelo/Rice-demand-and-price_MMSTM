-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 06:33 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_vendors`
--

-- --------------------------------------------------------

--
-- Table structure for table `tb_admin_reports`
--

CREATE TABLE `tb_admin_reports` (
  `report_id` int(11) NOT NULL,
  `variety` varchar(255) NOT NULL,
  `price_kilo` varchar(255) NOT NULL,
  `price_sacks` varchar(255) NOT NULL,
  `demand_level` enum('High Demand','Medium Demand','Low Demand') NOT NULL,
  `report_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_admin_reports`
--

INSERT INTO `tb_admin_reports` (`report_id`, `variety`, `price_kilo`, `price_sacks`, `demand_level`, `report_date`) VALUES
(64, 'donirado', '45', '2000', 'High Demand', '2026-11-24'),
(65, 'sinandoming', '45', '1500', 'Medium Demand', '2026-11-24'),
(66, 'jasmine', '50', '3000', 'Low Demand', '2026-11-24');

-- --------------------------------------------------------

--
-- Table structure for table `tb_rice`
--

CREATE TABLE `tb_rice` (
  `rice_id` int(11) NOT NULL,
  `vendors_id` int(11) NOT NULL,
  `variety` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_kilo` varchar(255) NOT NULL,
  `price_sacks` varchar(255) NOT NULL,
  `date_added` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_rice`
--

INSERT INTO `tb_rice` (`rice_id`, `vendors_id`, `variety`, `quantity`, `price_kilo`, `price_sacks`, `date_added`) VALUES
(30, 9, 'sinandoming', 10, '45', '1500', '2026-11-25 01:23:55'),
(31, 9, 'donirado', 20, '45', '2000', '2026-11-25 01:24:08'),
(32, 9, 'jasmine', 30, '50', '3000', '2026-11-25 01:24:27');

-- --------------------------------------------------------

--
-- Table structure for table `tb_stall`
--

CREATE TABLE `tb_stall` (
  `stall_id` int(11) NOT NULL,
  `stall_name` varchar(255) NOT NULL,
  `stall_number` int(11) NOT NULL,
  `vendors_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_stall`
--

INSERT INTO `tb_stall` (`stall_id`, `stall_name`, `stall_number`, `vendors_id`) VALUES
(8, 'Barato na, Puti pa ako gani', 2, 9);

-- --------------------------------------------------------

--
-- Table structure for table `tb_user`
--

CREATE TABLE `tb_user` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_user`
--

INSERT INTO `tb_user` (`user_id`, `username`, `password`, `role`) VALUES
(1, 'admin', 'admin123', 'admin'),
(9, 'eric', 'eric123', 'vendor');

-- --------------------------------------------------------

--
-- Table structure for table `tb_vendors`
--

CREATE TABLE `tb_vendors` (
  `vendors_id` int(11) NOT NULL,
  `f_name` varchar(255) NOT NULL,
  `l_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_vendors`
--

INSERT INTO `tb_vendors` (`vendors_id`, `f_name`, `l_name`, `email`, `contact`) VALUES
(9, 'Eric', 'Sentuelo', 'sentueloe@gmail.com', '09157435078');

-- --------------------------------------------------------

--
-- Table structure for table `tb_weekly`
--

CREATE TABLE `tb_weekly` (
  `stall_id` int(11) DEFAULT NULL,
  `weekly_id` int(11) NOT NULL,
  `vendors_id` int(11) NOT NULL,
  `variety` varchar(255) NOT NULL,
  `beginning_stock` varchar(255) NOT NULL,
  `remaining_stock` varchar(255) NOT NULL,
  `quantity_sold` varchar(255) NOT NULL,
  `week_start` varchar(255) NOT NULL,
  `week_end` varchar(255) NOT NULL,
  `demand_level` enum('High Demand','Medium Demand','Low Demand') NOT NULL DEFAULT 'Medium Demand'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tb_weekly`
--

INSERT INTO `tb_weekly` (`stall_id`, `weekly_id`, `vendors_id`, `variety`, `beginning_stock`, `remaining_stock`, `quantity_sold`, `week_start`, `week_end`, `demand_level`) VALUES
(NULL, 42, 9, 'jasmine', '30', '20', '10', '2026-11-25', '2026-11-25', 'Low Demand'),
(NULL, 43, 9, 'donirado', '20', '5', '15', '2026-11-25', '2026-11-25', 'High Demand'),
(NULL, 44, 9, 'sinandoming', '10', '5', '5', '2026-11-25', '2026-11-25', 'Medium Demand');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tb_admin_reports`
--
ALTER TABLE `tb_admin_reports`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `tb_rice`
--
ALTER TABLE `tb_rice`
  ADD PRIMARY KEY (`rice_id`);

--
-- Indexes for table `tb_stall`
--
ALTER TABLE `tb_stall`
  ADD PRIMARY KEY (`stall_id`),
  ADD KEY `fk_stall_vendor` (`vendors_id`);

--
-- Indexes for table `tb_user`
--
ALTER TABLE `tb_user`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `tb_vendors`
--
ALTER TABLE `tb_vendors`
  ADD PRIMARY KEY (`vendors_id`);

--
-- Indexes for table `tb_weekly`
--
ALTER TABLE `tb_weekly`
  ADD PRIMARY KEY (`weekly_id`),
  ADD KEY `fk_weekly_vendor` (`vendors_id`),
  ADD KEY `tb_stall` (`stall_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tb_admin_reports`
--
ALTER TABLE `tb_admin_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `tb_rice`
--
ALTER TABLE `tb_rice`
  MODIFY `rice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `tb_stall`
--
ALTER TABLE `tb_stall`
  MODIFY `stall_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tb_user`
--
ALTER TABLE `tb_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tb_vendors`
--
ALTER TABLE `tb_vendors`
  MODIFY `vendors_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tb_weekly`
--
ALTER TABLE `tb_weekly`
  MODIFY `weekly_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tb_stall`
--
ALTER TABLE `tb_stall`
  ADD CONSTRAINT `fk_stall_vendor` FOREIGN KEY (`vendors_id`) REFERENCES `tb_vendors` (`vendors_id`) ON DELETE SET NULL;

--
-- Constraints for table `tb_weekly`
--
ALTER TABLE `tb_weekly`
  ADD CONSTRAINT `fk_weekly_vendor` FOREIGN KEY (`vendors_id`) REFERENCES `tb_vendors` (`vendors_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tb_stall` FOREIGN KEY (`stall_id`) REFERENCES `tb_stall` (`stall_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
