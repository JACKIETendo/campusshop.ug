-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2025 at 11:44 AM
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
(60, 2, 38, NULL, 2),
(62, 8, 30, NULL, 1),
(63, 8, 44, NULL, 1),
(69, 1, 27, NULL, 2),
(70, 10, 35, NULL, 1),
(78, 10, 31, NULL, 1);

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
(22, 2, 39, '2025-11-07 08:55:53'),
(23, 2, 50, '2025-11-07 08:56:01'),
(24, 8, 44, '2025-11-11 08:55:30'),
(25, 1, 54, '2025-11-13 06:35:27'),
(26, 1, 27, '2025-11-13 06:35:40'),
(27, 1, 51, '2025-11-13 07:02:07'),
(28, 1, 44, '2025-11-14 10:36:27');

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
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('new','read','replied') DEFAULT 'new',
  `admin_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `name`, `email`, `message`, `created_at`, `status`, `admin_reply`, `replied_at`) VALUES
(2, 1, 'Tendo', 'ntendo4343@gmail.com', 'yeah', '2025-09-19 14:41:44', 'read', NULL, NULL),
(3, 1, 'Tendo', 'ntendo4343@gmail.com', 'wow', '2025-09-19 14:48:25', 'new', NULL, NULL),
(4, 1, 'Tendo', 'ntendo4343@gmail.com', 'wow', '2025-09-19 14:51:50', 'read', NULL, NULL),
(5, 1, 'Tendo', 'ntendo4343@gmail.com', 'yeah', '2025-09-19 14:54:49', 'read', NULL, NULL),
(6, 1, 'Tendo', 'ntendo4343@gmail.com', 'best work', '2025-11-04 14:37:37', 'replied', 'sure', '2025-11-04 15:55:14');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `payment_method` varchar(50) NOT NULL DEFAULT 'Unknown',
  `tracking_number` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_date`, `total_amount`, `payment_method`, `tracking_number`, `status`) VALUES
(1, 1, '2025-11-14 08:58:15', 3000.00, 'Unknown', NULL, 'Pending'),
(2, 1, '2025-11-14 10:07:38', 20000.00, 'Unknown', NULL, 'Pending'),
(3, 1, '2025-11-14 10:46:21', 20500.00, 'Unknown', NULL, 'Pending'),
(4, 1, '2025-11-14 11:37:01', 15000.00, 'Unknown', NULL, 'Pending'),
(5, 1, '2025-11-14 11:39:36', 3000.00, 'Unknown', NULL, 'Pending'),
(6, 1, '2025-11-14 11:40:47', 8000.00, 'Unknown', NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `price`) VALUES
(1, 1, 31, 'Ball point pens', 1, 3000.00),
(2, 2, 41, 'Mens/Womens long sleeve T-Shirt', 1, 20000.00),
(3, 3, 42, 'Men\\\\\\\'s Golf Polo T-shirt', 1, 20500.00),
(4, 4, 38, 'On-the-Go Notebooks', 1, 15000.00),
(5, 5, 44, 'Retractable ball point pens', 1, 3000.00),
(6, 6, 39, 'Hard Cover Notebooks', 1, 8000.00);

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
  `network_provider` varchar(50) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_deliveries`
--

INSERT INTO `pending_deliveries` (`id`, `user_id`, `username`, `phone`, `payment_method`, `amount`, `status`, `created_at`, `cart_id`, `location`, `stripe_payment_intent_id`, `paypal_order_id`, `network_provider`, `product_id`, `product_name`, `product_image`, `quantity`) VALUES
(27, 1, 'Tendo', '0765777269', 'Pay on Delivery', NULL, 'Completed', '2025-10-14 13:46:04', 46, 'Room 19', NULL, NULL, '', NULL, NULL, NULL, 1),
(35, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-11-04 15:07:43', 52, 'hostel A', NULL, NULL, '', 36, 'calculus 1', 'images/1757425454_organic chemistry.jpeg', 1),
(38, 1, 'Tendo', '0755087665', 'Mobile Money', 25000.00, 'Completed', '2025-11-11 09:05:02', 45, 'hostel A', NULL, NULL, 'Airtel', 40, 'T-shirt 3', 'images/1762423764_t-shirt_3.png', 1),
(39, 1, 'Tendo', '0765777269', 'Mobile Money', 2000.00, 'Completed', '2025-11-11 09:28:57', 65, 'hostel A', NULL, NULL, 'MTN', 43, 'pen 1', 'images/1762424132_scott-gummerson-FGWpR-O9QlQ-unsplash.jpg', 1),
(42, 1, 'Tendo', '0755087665', 'Mobile Money', 7000.00, 'Completed', '2025-11-13 06:36:51', 68, 'hostel A', NULL, NULL, 'Airtel', 54, 'Introduction to Socialogy', 'images/1763012019_Gemini_Generated_Image_lwvr59lwvr59lwvr.png', 1),
(43, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-11-13 06:56:16', 74, 'hostel A', NULL, NULL, '', 58, 'T-shirt', 'images/1763016894_1763013515_Gemini_Generated_Image_meerilmeerilmeer.png', 1),
(44, 1, 'Tendo', '0755087665', 'Mobile Money', 510000.00, 'Completed', '2025-11-13 07:03:34', 75, 'hostel A', NULL, NULL, 'Airtel', 51, 'classic  hand watch', 'images/1763011064_Gemini_Generated_Image_ytfqzgytfqzgytfq__2_.png', 6),
(45, 10, 'David', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-11-13 08:27:49', 79, 'hostel A', NULL, NULL, '', 43, 'metallic fountain pens', 'images/1762424132_scott-gummerson-FGWpR-O9QlQ-unsplash.jpg', 1),
(46, 10, 'David', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-11-13 08:31:13', 80, 'hostel A', NULL, NULL, '', 43, 'metallic fountain pens', 'images/1762424132_scott-gummerson-FGWpR-O9QlQ-unsplash.jpg', 4),
(47, 1, 'Tendo', '0755087665', 'Mobile Money', 25000.00, 'Completed', '2025-11-14 07:52:38', 82, 'hostel A', NULL, NULL, 'Airtel', 64, 'Pass bag', 'images/1763102831_bag.png', 1),
(48, 1, 'Tendo', '0755087665', 'Mobile Money', 3000.00, 'Completed', '2025-11-14 07:58:15', 77, 'hostel A', NULL, NULL, 'MTN', 31, 'Ball point pens', 'images/1756883749_anna-evans-eM51ZBCLtYk-unsplash.jpg', 1),
(49, 1, 'Tendo', '0755087665', 'Pay on Delivery', NULL, 'Completed', '2025-11-14 09:07:39', 72, 'hostel A', NULL, NULL, NULL, 41, 'Mens/Womens long sleeve T-Shirt', 'images/1757425778_faith-yarn-Wr0TpKqf26s-unsplash.jpg', 1),
(50, 1, 'Tendo', '0765777269', 'Mobile Money', 20500.00, 'Completed', '2025-11-14 09:46:21', 71, 'hostel A', NULL, NULL, 'Airtel', 42, 'Men\\\\\\\'s Golf Polo T-shirt', 'images/1763012479_Gemini_Generated_Image_fwvxudfwvxudfwvx__1_.png', 1),
(51, 1, 'Tendo', '0765777269', 'Pay on Delivery', NULL, 'Completed', '2025-11-14 10:37:01', 85, 'hostel A', NULL, NULL, NULL, 38, 'On-the-Go Notebooks', 'images/1757425589_asterisk-kwon-q_gjDWf9ths-unsplash.jpg', 1),
(52, 1, 'Tendo', '0765777269', 'Pay on Delivery', NULL, 'Completed', '2025-11-14 10:39:36', 84, 'Room 19', NULL, NULL, NULL, 44, 'Retractable ball point pens', 'images/1763011120_Gemini_Generated_Image_ytfqzgytfqzgytfq.png', 1),
(53, 1, 'Tendo', '0755087665', 'Mobile Money', 8000.00, 'Completed', '2025-11-14 10:40:47', 87, 'Room 19', NULL, NULL, 'Airtel', 39, 'Hard Cover Notebooks', 'images/1757425682_designecologist-gh1IgGFnhSk-unsplash.jpg', 1);

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
  `category` enum('Bags','Branded Jumpers','Bottles','Pens','Note Books','Wall Clocks','T-Shirts') DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image_path`, `caption`, `category`, `stock`) VALUES
(26, 'Hooded jumper', 20000.00, 'images/1763013200_Gemini_Generated_Image_x1u0pcx1u0pcx1u0.png', 'Cozy Bugema University jumper made from soft, high-quality fabric. Show your campus pride in comfort and style perfect for any season.', 'Branded Jumpers', 5),
(27, 'Sweater vest', 20000.00, 'images/1763011623_Gemini_Generated_Image_kut6jxkut6jxkut6__1_.png', 'Cozy Bugema University jumper made from soft, high-quality fabric. Show your campus pride in comfort and style perfect for any season.', 'Branded Jumpers', 10),
(28, 'Crew-neck pullover', 15000.00, 'images/1763014976_Gemini_Generated_Image_92j83s92j83s92j8.png', 'Cozy Bugema University jumper made from soft, high-quality fabric. Show your campus pride in comfort and style perfect for any season.', 'Branded Jumpers', 9),
(29, 'Wall Clock', 50000.00, 'images/1763012955_Gemini_Generated_Image_mxwuc3mxwuc3mxwu.png', 'Stylish decorative wall clock with a silent quartz mechanism. Combines functionality with elegance for any wall space.', 'Wall Clocks', 10),
(30, 'Clock', 15000.00, 'images/1763011000_Gemini_Generated_Image_ytfqzgytfqzgytfq__1_.png', 'Stylish decorative wall clock with a silent quartz mechanism. Combines functionality with elegance for any wall space.', 'Wall Clocks', 10),
(31, 'Ball point pens', 3000.00, 'images/1756883749_anna-evans-eM51ZBCLtYk-unsplash.jpg', 'Smooth-writing Bugema University pen with a sleek design and comfortable grip. Perfect for students, staff, and everyday office use.', 'Pens', 9),
(32, 'Metallic Water Bottle', 10000.00, 'images/1762423731_bottle_1.png', 'Eco-friendly Bugema University water bottle designed to keep your drinks hot or cold. Stylish, reusable, and perfect for daily hydration.', 'Bottles', 10),
(33, 'Womens VPolo T-Shirt', 15000.00, 'images/1763012594_Gemini_Generated_Image_kut6jxkut6jxkut6.png', 'Classic Bugema University T-shirt with a bold logo print. Lightweight, breathable, and ideal for students, alumni, and supporters.', 'T-Shirts', 10),
(34, 'Mens Polo T-shirt', 15000.00, 'images/1763013515_Gemini_Generated_Image_meerilmeerilmeer.png', 'Classic Bugema University T-shirt with a bold logo print. Lightweight, breathable, and ideal for students, alumni, and supporters.', 'T-Shirts', 10),
(35, 'Introduction to Programming', 15000.00, 'images/1757425410_introduction to programming.jpeg', 'Official Bugema University textbook with updated content and clear explanations. Ideal for academic study and course reference.', '', 10),
(36, 'calculus 1', 16000.00, 'images/1757425454_organic chemistry.jpeg', '190 pages. The book is good for UI designers. Official Bugema University textbook with updated content and clear explanations. Ideal for academic study and course reference.', '', 10),
(37, 'Side Spiral Notebooks', 20000.00, 'images/1763011824_Gemini_Generated_Image_wdmzmewdmzmewdmz.png', 'Durable Bugema University notebook with ruled pages and a sturdy cover — perfect for class notes, meetings, or personal journaling. (150 pages)', 'Note Books', 10),
(38, 'On-the-Go Notebooks', 15000.00, 'images/1757425589_asterisk-kwon-q_gjDWf9ths-unsplash.jpg', 'Durable Bugema University notebook with ruled pages and a sturdy cover — perfect for class notes, meetings, or personal journaling. (150 pages)', 'Note Books', 8),
(39, 'Hard Cover Notebooks', 8000.00, 'images/1757425682_designecologist-gh1IgGFnhSk-unsplash.jpg', 'Durable Bugema University notebook with ruled pages and a sturdy cover — perfect for class notes, meetings, or personal journaling. (150 pages)', 'Note Books', 8),
(40, 'Mens/Womens V-neck T-shirt', 25000.00, 'images/1762423764_t-shirt_3.png', 'Classic Bugema University T-shirt with a bold logo print. Lightweight, breathable, and ideal for students, alumni, and supporters.', 'T-Shirts', 10),
(41, 'Mens/Womens long sleeve T-Shirt', 20000.00, 'images/1757425778_faith-yarn-Wr0TpKqf26s-unsplash.jpg', 'Classic Bugema University T-shirt with a bold logo print. Lightweight, breathable, and ideal for students, alumni, and supporters.', 'T-Shirts', 9),
(42, 'Men\\\\\\\'s Golf Polo T-shirt', 20500.00, 'images/1763012479_Gemini_Generated_Image_fwvxudfwvxudfwvx__1_.png', 'Classic Bugema University T-shirt with a bold logo print. Lightweight, breathable, and ideal for students, alumni, and supporters.', 'T-Shirts', 8),
(43, 'metallic fountain pens', 2000.00, 'images/1762424132_scott-gummerson-FGWpR-O9QlQ-unsplash.jpg', 'Smooth-writing Bugema University pen with a sleek design and comfortable grip. Perfect for students, staff, and everyday office use.', 'Pens', 4),
(44, 'Retractable ball point pens', 3000.00, 'images/1763011120_Gemini_Generated_Image_ytfqzgytfqzgytfq.png', 'Smooth-writing Bugema University pen with a sleek design and comfortable grip. Perfect for students, staff, and everyday office use.', 'Pens', 8),
(45, 'Retractable metallic ball pens', 1000.00, 'images/1763013343_Gemini_Generated_Image_psvbpupsvbpupsvb.png', 'Smooth-writing Bugema University pen with a sleek design and comfortable grip. Perfect for students, staff, and everyday office use.', 'Pens', 10),
(46, 'Retractable wooden ball point pens', 2000.00, 'images/1763013044_Gemini_Generated_Image_y0htdqy0htdqy0ht.png', 'Smooth-writing Bugema University pen with a sleek design and comfortable grip. Perfect for students, staff, and everyday office use.', 'Pens', 10),
(47, 'Plastic water bottle	', 8500.00, 'images/1763012507_Gemini_Generated_Image_4rimi74rimi74rim.png', 'Eco-friendly Bugema University water bottle designed to keep your drinks hot or cold. Stylish, reusable, and perfect for daily hydration.', 'Bottles', 5),
(48, 'Metallic water bottle', 20000.00, 'images/1763012560_Gemini_Generated_Image_uu2ql9uu2ql9uu2q.png', 'Eco-friendly Bugema University water bottle designed to keep your drinks hot or cold. Stylish, reusable, and perfect for daily hydration.', 'Bottles', 10),
(49, 'Plastic water bottle', 9500.00, 'images/1763013010_Gemini_Generated_Image_7e2kx47e2kx47e2k.png', 'Eco-friendly Bugema University water bottle designed to keep your drinks hot or cold. Stylish, reusable, and perfect for daily hydration.', 'Bottles', 10),
(50, 'Stylish decorative wall clock', 50000.00, 'images/1763011476_1763011000_Gemini_Generated_Image_ytfqzgytfqzgytfq__1_.png', 'Stylish decorative wall clock with a silent quartz mechanism. Combines functionality with elegance for any wall space.', 'Wall Clocks', 10),
(51, 'classic  hand watch', 85000.00, 'images/1763011064_Gemini_Generated_Image_ytfqzgytfqzgytfq__2_.png', 'Classic wooden wall clock with smooth sweeping hands and vintage charm. Adds warmth and style to any living or office space.', 'Wall Clocks', 4),
(52, 'wooden wall clock', 50000.00, 'images/1763012985_Gemini_Generated_Image_kiuzigkiuzigkiuz.png', 'Classic wooden wall clock with smooth sweeping hands and vintage charm. Adds warmth and style to any living or office space.', 'Wall Clocks', 13),
(53, 'Greek History', 50000.00, 'images/1762970616_1757425454_organic_chemistry.jpeg', 'High quality paper note book (150 papers)', '', 5),
(54, 'Introduction to Socialogy', 50000.00, 'images/1763012019_Gemini_Generated_Image_lwvr59lwvr59lwvr.png', '190 pages. It is good for a student doing E-commerce and E-business. Official Bugema University textbook with updated content and clear explanations. Ideal for academic study and course reference.', '', 2),
(55, 'Introduction to UI design ', 60000.00, 'images/1763011906_Gemini_Generated_Image_1trj4j1trj4j1trj.png', '190 pages. The book is good for UI designers. Official Bugema University textbook with updated content and clear explanations. Ideal for academic study and course reference.', '', 4),
(56, 'Introduction to Basic Entrepreneurship ', 11000.00, 'images/1762968513_1757425454_organic_chemistry.jpeg', 'High quality paper note book (150 papers). Official Bugema University textbook with updated content and clear explanations. Ideal for academic study and course reference.', '', 6),
(58, 'T-shirt', 20000.00, 'images/1763016894_1763013515_Gemini_Generated_Image_meerilmeerilmeer.png', 'wear your pride', 'T-Shirts', 3),
(62, 'Tote bags', 20000.00, 'images/1763102686_bag2.png', 'Tote bags that can carry alot', 'Bags', 5),
(63, 'Hand bag', 30000.00, 'images/1763102772_bag5.png', 'Ladies Hand Bags', 'Bags', 10),
(64, 'Pass bag', 25000.00, 'images/1763102831_bag.png', 'small hand bag', 'Bags', 12),
(65, 'Waist bag', 35000.00, 'images/1763102912_bag4.png', 'waist bag', 'Bags', 11),
(66, 'Laptop bag', 45000.00, 'images/1763103360_bag3.png', 'Laptop bags', 'Bags', 16);

-- --------------------------------------------------------

--
-- Table structure for table `product_movements`
--

CREATE TABLE `product_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('Sale','Gift','Return','Damaged','Promotion','Adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `issued_by` varchar(100) NOT NULL,
  `received_by` varchar(100) DEFAULT '',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_movements`
--

INSERT INTO `product_movements` (`id`, `product_id`, `movement_type`, `quantity`, `issued_by`, `received_by`, `remarks`, `created_at`) VALUES
(1, 66, 'Damaged', 0, 'Ntendo', 'marketing', 'said laptop bags', '2025-11-14 10:10:12'),
(2, 65, 'Damaged', 0, 'Ntendo', 'marketing', 'damaged', '2025-11-14 10:11:24'),
(3, 62, 'Sale', 0, 'Ntendo', 'marketing', 'gifts', '2025-11-14 10:12:13'),
(4, 62, 'Gift', 1, 'Ntendo', 'marketing', 'gifts', '2025-11-14 10:28:14'),
(5, 63, 'Sale', 3, 'Ntendo', 'marketing', 'sold', '2025-11-14 10:28:54'),
(6, 58, 'Promotion', 2, 'Ntendo', 'marketing', 'promotions', '2025-11-14 10:29:39'),
(7, 47, 'Gift', 5, 'Ntendo', 'marketing', 'Water bottles', '2025-11-14 10:30:33'),
(8, 62, 'Damaged', 3, 'Ntendo', 'marketing', 'damage', '2025-11-14 10:31:52'),
(9, 42, 'Sale', 1, 'Ntendo', 'Tendo', 'Online order completed', '2025-11-14 10:32:16'),
(10, 52, 'Return', 3, 'Ntendo', 'marketing', 'returns', '2025-11-14 10:32:45'),
(11, 38, 'Sale', 1, 'Ntendo', 'Tendo', 'Online order completed', '2025-11-14 10:38:36'),
(12, 44, 'Sale', 1, 'Ntendo', 'Tendo', 'Online order completed', '2025-11-14 10:39:59'),
(13, 39, 'Sale', 1, 'Ntendo', 'Tendo', 'Online order completed', '2025-11-14 10:41:17');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'Tendo', '$2y$10$.J8ZXCZ5.GXiG9fWrBjC8u0eBSKBeXIrHWLZ5/4/umDfPXO8Oy7Xy', 'student', 'ntendo4343@gmail.com', 'Uploads/690dbc72395d9.jpeg', '18ae5dc6acbe6fcd7c64c4c3e232b8f86052ea2d9af2e4edbca7edabd0e79ebc63508c4d207a5a09330b56e4013a0cd79716', '2025-10-14 18:27:29'),
(2, 'Ntendo', '$2y$10$GbXPRbSAW3aWl08OJNAPDOtXr.IgJHMkc1bdPgCXMqgrSLtGi9iGG', 'admin', NULL, 'Uploads/690db2366a05f.jpeg', NULL, NULL),
(3, 'jackline', '$2y$10$ae06eA/cGPqBYjHbZGMbIeHdZFd2gjkT8F2iBeuNq8Koo96l5DE7C', 'student', NULL, NULL, NULL, NULL),
(4, 'Tendo Jackline', '$2y$10$gXvRCoAko64VPlmqBGAfZOyuVUWxtdVLD9/XSTLplcG7qMP3oBKua', 'lecturer', 'ntendo2018@gmail.com', NULL, NULL, NULL),
(5, 'Paul', '$2y$10$3IaJOA9d7v2/iFGqpZyO6.nLmtzxmaaiBoKDpeFLfEzyW5fCgKWza', 'admin', 'tendojackline79@gmail.com', NULL, NULL, NULL),
(6, 'morret123', '$2y$10$z2T80pvv1bLam.CV8mnwF.JYSMMnLEzt7Rk9sCluuO0DePR9rzr9i', 'lecturer', 'joann@gmail.com', NULL, NULL, NULL),
(7, 'agaba', '$2y$10$O8YD7dXLiFYg8Xvgd9zd7.U1TtEqav9kfwMRVDH5HkgOIKYZlXLB6', 'student', 'davidk@gmail.com', NULL, NULL, NULL),
(8, 'Rhonitah', '$2y$10$m3VsYV.tNGgczLroRpr.Ce19tSR2kYB2CyjGk/yOAUwWbVFM5vtRu', 'student', 'krhonitah@gmail.com', NULL, NULL, NULL),
(9, 'jowanah', '$2y$10$REX3QsbgdIA9gtKx3xtAQ.9GTnbTFH0XBISP.EGoT75SPrAFUS/fq', 'admin', 'jon@gmail.com', NULL, NULL, NULL),
(10, 'David', '$2y$10$u0.jJI3bopLbx2jJCDXYH.nAiKQLWM.Fk.9S3l47tFCfkU371wW7m', 'lecturer', 'davidk01@gmail.com', NULL, NULL, NULL);

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
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

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
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_movements`
--
ALTER TABLE `product_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `product_movements`
--
ALTER TABLE `product_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_deliveries`
--
ALTER TABLE `pending_deliveries`
  ADD CONSTRAINT `pending_deliveries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pending_deliveries_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_movements`
--
ALTER TABLE `product_movements`
  ADD CONSTRAINT `product_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
