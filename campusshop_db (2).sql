-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 14, 2025 at 06:21 PM
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
-- Database: `campusshop_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `session_id`, `quantity`) VALUES
(42, 1, 44, NULL, 1),
(45, 1, 40, NULL, 1),
(50, 1, 37, NULL, 1),
(51, 1, 31, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`id`, `user_id`, `product_id`, `added_at`) VALUES
(2, 1, 36, '2025-09-19 07:20:31'),
(3, 1, 40, '2025-09-19 07:20:32'),
(8, 1, 39, '2025-10-09 08:00:41'),
(9, 1, 43, '2025-10-09 08:00:56'),
(10, 1, 42, '2025-10-09 09:09:44'),
(16, 1, 48, '2025-10-09 10:15:28'),
(17, 1, 32, '2025-10-09 10:15:36'),
(18, 1, 49, '2025-10-09 10:15:40');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `name`, `email`, `message`, `created_at`) VALUES
(1, 1, 'Tendo', 'ntendo4343@gmail.com', 'goood', '2025-09-19 14:41:30'),
(2, 1, 'Tendo', 'ntendo4343@gmail.com', 'yeah', '2025-09-19 14:41:44'),
(3, 1, 'Tendo', 'ntendo4343@gmail.com', 'wow', '2025-09-19 14:48:25'),
(4, 1, 'Tendo', 'ntendo4343@gmail.com', 'wow', '2025-09-19 14:51:50'),
(5, 1, 'Tendo', 'ntendo4343@gmail.com', 'yeah', '2025-09-19 14:54:49');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `created_at`) VALUES
(1, NULL, 'we have new branded jackets', '2025-09-25 10:50:29'),
(2, NULL, 'new sales', '2025-10-08 17:00:39'),
(4, NULL, 'good notification well good', '2025-10-08 19:02:48'),
(6, NULL, 'hello dear customers', '2025-10-09 11:28:15');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_deliveries`
--

CREATE TABLE `pending_deliveries` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `payment_method` enum('Mobile Money','Pay on Delivery') NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cart_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `paypal_order_id` varchar(255) DEFAULT NULL,
  `network_provider` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_deliveries`
--

INSERT INTO `pending_deliveries` (`id`, `user_id`, `username`, `phone`, `payment_method`, `amount`, `status`, `created_at`, `cart_id`, `location`, `stripe_payment_intent_id`, `paypal_order_id`, `network_provider`) VALUES
(1, 2, 'Ntendo', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-08-28 09:47:49', NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Ntendo', '0755087665', 'Mobile Money', 50000.00, 'Pending', '2025-08-28 09:48:17', NULL, NULL, NULL, NULL, NULL),
(3, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-08-28 09:49:49', NULL, NULL, NULL, NULL, NULL),
(4, 1, 'Tendo', '0755087665', 'Mobile Money', 15000.00, 'Pending', '2025-08-28 09:49:58', NULL, NULL, NULL, NULL, NULL),
(5, 1, 'Tendo', '0755087665', 'Mobile Money', 2000.00, 'Pending', '2025-08-28 15:05:48', 5, NULL, NULL, NULL, NULL),
(6, 1, 'Tendo', '0755087665', 'Mobile Money', 8000.00, 'Pending', '2025-08-28 15:06:30', 8, NULL, NULL, NULL, NULL),
(7, 1, 'Tendo', '0755087665', 'Mobile Money', 10000.00, 'Pending', '2025-08-28 15:07:58', 10, NULL, NULL, NULL, NULL),
(8, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-08-28 15:08:14', 12, NULL, NULL, NULL, NULL),
(9, 1, 'Tendo', '0755087665', 'Mobile Money', 15000.00, 'Pending', '2025-09-01 13:42:58', 1, 'hostel A', NULL, NULL, NULL),
(12, 1, 'Tendo', '0755087665', 'Mobile Money', 6000.00, 'Completed', '2025-09-09 12:16:11', 20, 'hostel A', NULL, NULL, NULL),
(13, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-09-24 13:34:09', 25, 'hostel A', NULL, NULL, NULL),
(14, 1, 'Tendo', '0755087665', 'Mobile Money', 20000.00, 'Completed', '2025-09-24 13:35:10', 35, 'hostel A', NULL, NULL, NULL),
(15, 1, 'Tendo', '0755087665', 'Mobile Money', 40000.00, 'Completed', '2025-09-24 13:36:36', 34, 'Hostal B', NULL, NULL, NULL),
(16, 1, 'Tendo', '0755087665', 'Mobile Money', 21000.00, 'Completed', '2025-09-29 15:25:58', 27, 'Hostal B', NULL, NULL, 'Airtel'),
(17, 1, 'Tendo', '0755087665', 'Mobile Money', 646000.00, 'Pending', '2025-10-05 10:36:44', 26, 'bugema', NULL, NULL, 'Airtel'),
(18, 1, 'Tendo', '0755087665', 'Mobile Money', 646000.00, 'Pending', '2025-10-05 10:36:44', 33, 'bugema', NULL, NULL, 'Airtel'),
(19, 1, 'Tendo', '0755087665', 'Mobile Money', 646000.00, 'Completed', '2025-10-05 10:36:44', 37, 'bugema', NULL, NULL, 'Airtel'),
(20, 1, 'Tendo', '0755087665', 'Mobile Money', 646000.00, 'Pending', '2025-10-05 10:36:45', 39, 'bugema', NULL, NULL, 'Airtel'),
(21, 1, 'Tendo', '0755087665', 'Mobile Money', 646000.00, 'Completed', '2025-10-05 10:36:45', 40, 'bugema', NULL, NULL, 'Airtel'),
(22, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-10-05 10:37:51', 43, 'bugema', NULL, NULL, ''),
(23, 1, 'Tendo', '0755097665', 'Mobile Money', 15000.00, 'Pending', '2025-10-08 16:05:32', 44, 'Room 19', NULL, NULL, 'Airtel'),
(24, 1, 'Tendo', '0755087665', 'Mobile Money', 120000.00, 'Pending', '2025-10-13 12:36:54', 47, 'hostel A', NULL, NULL, 'Airtel'),
(25, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-10-13 12:37:50', 48, 'hostel A', NULL, NULL, ''),
(26, 1, 'Tendo', '0765777269', 'Mobile Money', 15000.00, 'Pending', '2025-10-14 13:44:38', 49, 'Room 19', NULL, NULL, 'MTN'),
(27, 1, 'Tendo', '0765777269', 'Pay on Delivery', NULL, 'Pending', '2025-10-14 13:46:04', 46, 'Room 19', NULL, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `category` enum('Textbooks','Branded Jumpers','Bottles','Pens','Note Books','Wall Clocks','T-Shirts') DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image_path`, `caption`, `category`, `stock`) VALUES
(26, 'Branded Jumper', 20000.00, 'images/1756883515_1millikarat-CYh74tW1Cas-unsplash.jpg', 'good', 'Branded Jumpers', 10),
(27, 'Jumper', 20000.00, 'images/1756883557_1millikarat-8MnWJMQJIaE-unsplash.jpg', 'qwertyuiop', 'Branded Jumpers', 10),
(28, 'Good Jumper', 15000.00, 'images/1756883615_chris-lynch-4fV6prPjV-g-unsplash.jpg', 'wertyuiop', 'Branded Jumpers', 10),
(29, 'Wall Clock', 20000.00, 'images/1756883655_aedrian-salazar-D4lJ7p7S8eY-unsplash.jpg', 'qwertyuiop', 'Wall Clocks', 10),
(30, 'Clock', 15000.00, 'images/1756883701_kyem-ghosh-ANvGuZ8_bA0-unsplash.jpg', 'qwertyuiop', 'Wall Clocks', 10),
(31, 'Blue Pens', 1000.00, 'images/1756883749_anna-evans-eM51ZBCLtYk-unsplash.jpg', 'qwertyuiop', 'Pens', 10),
(32, 'Water Bottle', 1000.00, 'images/1756883841_adnan-mistry-lALZb4UD9Es-unsplash.jpg', 'qwertyuio', 'Bottles', 10),
(33, 'T-shirt 1', 15000.00, 'images/1757425198_campus T-shirt.jpeg', 'gooood', 'T-Shirts', 10),
(34, 'T-shirt 2', 15000.00, 'images/1757425230_florencia-simonini-PDZAMYvduVk-unsplash.jpg', 'goooooooooo', 'T-Shirts', 10),
(35, 'Introduction to Programming', 15000.00, 'images/1757425410_introduction to programming.jpeg', 'good book', 'Textbooks', 10),
(36, 'calculus 1', 16000.00, 'images/1757425454_organic chemistry.jpeg', 'goood book', 'Textbooks', 10),
(37, 'note book 1', 20000.00, 'images/1757425559_notebook A4.jpeg', 'goooooood', 'Note Books', 10),
(38, 'note book 2', 15000.00, 'images/1757425589_asterisk-kwon-q_gjDWf9ths-unsplash.jpg', 'gooooooooooooooooooooo', 'Note Books', 10),
(39, 'note book 3', 2000.00, 'images/1757425682_designecologist-gh1IgGFnhSk-unsplash.jpg', 'dooooooooooooooo', 'Note Books', 10),
(40, 'T-shirt 3', 40000.00, 'images/1757425736_alan-bowman-65QPpnFIssE-unsplash.jpg', 'wertyuiokkkkk', 'T-Shirts', 10),
(41, 'T-shirt 4', 20000.00, 'images/1757425778_faith-yarn-Wr0TpKqf26s-unsplash.jpg', 'wooooooooooooooo', 'T-Shirts', 10),
(42, 'T-shirt 5', 20500.00, 'images/1757425813_haryo-setyadi-acn5ERAeSb4-unsplash.jpg', 'rooooooooooooooooo', 'T-Shirts', 10),
(43, 'pen 1', 2000.00, 'images/1757425846_ballpoint pen.jpeg', 'rooooooooooooo', 'Pens', 10),
(44, 'pen 2', 3000.00, 'images/1757425877_copper-and-wild-1lbmrKTx8gQ-unsplash.jpg', '4000', 'Pens', 10),
(45, 'pen 3', 1000.00, 'images/1757425916_florian-klauer-DUOTbkFqvFw-unsplash.jpg', 'rooooooooooooooooooooo', 'Pens', 10),
(46, 'pen 4', 2000.00, 'images/1757425951_faris-mohammed-PQinRWK1TgU-unsplash.jpg', 'wooooooooooooo', 'Pens', 10),
(47, 'water bottle 1', 8000.00, 'images/1757426012_adnan-mistry-9kOhd1e608M-unsplash.jpg', 'wpppppppppppppppppppppp', 'Bottles', 10),
(48, 'water bottle 2', 20000.00, 'images/1757426065_shrey-gupta-HFGdbcqBt_0-unsplash.jpg', 'eoooooooooooooooooo', 'Bottles', 10),
(49, 'water bottle 3', 30000.00, 'images/1757426154_adnan-mistry-1EtGcA9uWDw-unsplash.jpg', 'eoooooooooo', 'Bottles', 10),
(50, 'wall clock 1', 80000.00, 'images/1757426309_isidro-lam--4sTc8gIh_U-unsplash.jpg', 'rooooooooooooooooooooooooo', 'Wall Clocks', 10),
(51, 'wall clock 2', 85000.00, 'images/1757426343_1757426309_isidro-lam--4sTc8gIh_U-unsplash.jpg', 'wooooooooooooooooooo', 'Wall Clocks', 10),
(52, 'Wall Clock 7', 200000.00, 'images/1758721235_karl-callwood-KQONyIFXNZU-unsplash.jpg', 'goooooooooooooooooooooooooooooooooooooooooooo', 'Wall Clocks', 10),
(53, 'text book 7', 7000.00, 'images/1759934374_isidro-lam--4sTc8gIh_U-unsplash.jpg', 'the best boot', 'Textbooks', 0),
(54, 'Introduction to E-commerce', 234567.00, 'images/1760449943_1757425682_designecologist-gh1IgGFnhSk-unsplash.jpg', '190 pages.\\\\\\\\r\\\\\\\\nIt is good for a student doing E-commerce and E-business', 'Textbooks', 3),
(55, 'Introduction to UI design ', 20000.00, 'images/1760452004_1757425559_notebook_A4.jpeg', '190 pages. The book is good for UI designers.', 'Textbooks', 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student','lecturer') NOT NULL DEFAULT 'student',
  `email` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `email`, `profile_picture`, `reset_token`, `reset_expires`) VALUES
(1, 'Tendo', '$2y$10$.J8ZXCZ5.GXiG9fWrBjC8u0eBSKBeXIrHWLZ5/4/umDfPXO8Oy7Xy', 'student', 'ntendo4343@gmail.com', 'uploads/68d4f25852ff7.jpg', '18ae5dc6acbe6fcd7c64c4c3e232b8f86052ea2d9af2e4edbca7edabd0e79ebc63508c4d207a5a09330b56e4013a0cd79716', '2025-10-14 18:27:29'),
(2, 'Ntendo', '$2y$10$GbXPRbSAW3aWl08OJNAPDOtXr.IgJHMkc1bdPgCXMqgrSLtGi9iGG', 'admin', NULL, NULL, NULL, NULL),
(3, 'jackline', '$2y$10$ae06eA/cGPqBYjHbZGMbIeHdZFd2gjkT8F2iBeuNq8Koo96l5DE7C', 'student', NULL, NULL, NULL, NULL),
(4, 'Tendo Jackline', '$2y$10$gXvRCoAko64VPlmqBGAfZOyuVUWxtdVLD9/XSTLplcG7qMP3oBKua', 'lecturer', 'ntendo2018@gmail.com', NULL, NULL, NULL),
(5, 'Paul', '$2y$10$3IaJOA9d7v2/iFGqpZyO6.nLmtzxmaaiBoKDpeFLfEzyW5fCgKWza', 'admin', 'tendojackline79@gmail.com', NULL, NULL, NULL),
(6, 'morret123', '$2y$10$z2T80pvv1bLam.CV8mnwF.JYSMMnLEzt7Rk9sCluuO0DePR9rzr9i', 'lecturer', 'joann@gmail.com', NULL, NULL, NULL),
(7, 'agaba', '$2y$10$O8YD7dXLiFYg8Xvgd9zd7.U1TtEqav9kfwMRVDH5HkgOIKYZlXLB6', 'student', 'davidk@gmail.com', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_user_product` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  ADD CONSTRAINT `pending_deliveries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
