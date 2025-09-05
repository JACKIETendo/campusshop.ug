-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 28, 2025 at 12:09 PM
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
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`) VALUES
(1, 1, 8),
(4, 1, 8),
(5, 1, 14),
(7, 2, 10);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_deliveries`
--

INSERT INTO `pending_deliveries` (`id`, `user_id`, `username`, `phone`, `payment_method`, `amount`, `status`, `created_at`) VALUES
(1, 2, 'Ntendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-08-28 09:47:49'),
(2, 2, 'Ntendo', '0755087665', 'Mobile Money', 50000.00, 'Pending', '2025-08-28 09:48:17'),
(3, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Pending', '2025-08-28 09:49:49'),
(4, 1, 'Tendo', '0755087665', 'Mobile Money', 15000.00, 'Pending', '2025-08-28 09:49:58');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `price`, `image_path`) VALUES
(1, 'Introduction to Programming', 'Textbooks', 45000.00, 'images/introduction to programming.jpeg'),
(2, 'Calculus I', 'Textbooks', 50000.00, 'images/calculus 1.jpeg'),
(3, 'Organic Chemistry', 'Textbooks', 48000.00, 'images/organic chemistry.jpeg'),
(4, 'Notebook A4', 'Stationery', 5000.00, 'images/notebook A4.jpeg'),
(5, 'Ballpoint Pen', 'Stationery', 2000.00, 'images/ballpoint pen.jpeg'),
(6, 'Highlighter Set', 'Stationery', 8000.00, 'images/highlighter set.jpeg'),
(7, 'University Hoodie', 'Clothes', 30000.00, 'images/universityhoodie.jpeg'),
(8, 'Campus T-Shirt', 'Clothes', 15000.00, 'images/campus T-shirt.jpeg'),
(10, 'Scientific Calculator', 'Electronics', 25000.00, 'images/scientific calculator.jpeg'),
(11, 'USB Drive 16GB', 'Electronics', 20000.00, 'images/USB Drive 16GB.jpeg'),
(12, 'Earphones', 'Electronics', 15000.00, 'images/earphones.jpeg'),
(13, 'Campus Cafe Sandwich', 'Food & Snacks', 8000.00, 'images/campus cafe sandwich.jpeg'),
(14, 'Bottled Water', 'Food & Snacks', 2000.00, 'images/bottled water.jpeg'),
(15, 'Energy Bar', 'Food & Snacks', 3000.00, 'images/energy bar.jpeg'),
(16, 'Bedside Lamp', 'Dorm Essentials', 15000.00, 'images/bedside lamp.jpeg'),
(17, 'Storage Box', 'Dorm Essentials', 12000.00, 'images/storages box.jpeg'),
(19, 'cap', 'Clothes', 7000.00, 'images/1756369537_bugema cap.jpeg'),
(20, 'Round Dress 1', 'Clothes', 10000.00, 'images/1756369642_dress.jpeg'),
(21, 'Plush Blanket', 'Dorm Essentials', 48000.00, 'images/1756372012_blanket.jpeg'),
(22, 'Landary Basket', 'Dorm Essentials', 10000.00, 'images/1756372063_laundary basket.jpeg'),
(23, 'Mattress', 'Dorm Essentials', 70000.00, 'images/1756372086_mattress.jpeg'),
(24, 'Another Mattress', 'Dorm Essentials', 70000.00, 'images/1756372161_1756372086_mattress.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student','lecturer') NOT NULL DEFAULT 'student'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'Tendo', '$2y$10$.J8ZXCZ5.GXiG9fWrBjC8u0eBSKBeXIrHWLZ5/4/umDfPXO8Oy7Xy', 'student'),
(2, 'Ntendo', '$2y$10$GbXPRbSAW3aWl08OJNAPDOtXr.IgJHMkc1bdPgCXMqgrSLtGi9iGG', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- Constraints for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  ADD CONSTRAINT `pending_deliveries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
