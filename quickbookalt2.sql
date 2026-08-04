-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2025 at 10:22 AM
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
-- Database: `quickbookalt2`
--

-- --------------------------------------------------------

--
-- Table structure for table `accepted_orders`
--

CREATE TABLE `accepted_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `order_date` datetime NOT NULL,
  `order_details` text DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `notified` tinyint(1) DEFAULT 0,
  `payment_status` enum('pending','verified','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accepted_orders`
--

INSERT INTO `accepted_orders` (`id`, `user_id`, `customer_name`, `total_amount`, `order_date`, `order_details`, `payment_screenshot`, `notified`, `payment_status`) VALUES
(1, 5, 'mnm', 175.00, '2025-11-03 16:19:50', '[{\"menuId\":\"17\",\"name\":\"Bangus Silog\",\"variation\":\"With Garlic Rice and Eggs\",\"price\":175,\"quantity\":1,\"notes\":\"\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/69082911dc3f4_1762142481.png\"}]', NULL, 0, 'pending'),
(2, 6, 'customer', 2100.00, '2025-11-04 00:07:39', '[{\"menuId\":\"17\",\"name\":\"Bangus Silog\",\"variation\":\"With Garlic Rice and Eggs\",\"price\":175,\"quantity\":12,\"notes\":\"Need food\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/69082911dc3f4_1762142481.png\"}]', 'payment_2_1762245561_6909bbb9400ec.png', 0, 'pending'),
(5, 7, 'customer1', 525.00, '2025-11-04 15:17:10', '[{\"menuId\":\"10\",\"name\":\"Hungarian  Silog\",\"variation\":\"With Garlic Rice and Eggs\",\"price\":105,\"quantity\":5,\"notes\":\"\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/69082424cf7da_1762141220.png\"}]', 'payment_5_1762246153_6909be09beecb.png', 0, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `balancesheet`
--

CREATE TABLE `balancesheet` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cashflow`
--

CREATE TABLE `cashflow` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `cash_in` decimal(12,2) DEFAULT 0.00,
  `cash_out` decimal(12,2) DEFAULT 0.00,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `completed_orders`
--

CREATE TABLE `completed_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `order_date` datetime NOT NULL,
  `order_details` text DEFAULT NULL,
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `delivered` tinyint(1) DEFAULT 0,
  `notified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `completed_orders`
--

INSERT INTO `completed_orders` (`id`, `user_id`, `customer_name`, `total_amount`, `order_date`, `order_details`, `payment_screenshot`, `delivered`, `notified`) VALUES
(1, 6, 'customer', 170.00, '2025-11-04 00:25:13', '[{\"menuId\":\"8\",\"name\":\"Longganisa With Egg Fried Rice\",\"variation\":\"Original\",\"price\":170,\"quantity\":1,\"notes\":\"Pakibilisan po sana.Thanks\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/690823409d69c_1762140992.png\"}]', 'payment_3_1762192722_6908ed52af8ca.png', 0, 1),
(2, 6, 'customer', 135.00, '2025-11-04 14:44:51', '[{\"menuId\":\"16\",\"name\":\"Chicken Fillet Silog\",\"variation\":\"With Garlic Rice and Eggs\",\"price\":135,\"quantity\":1,\"notes\":\"\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/690828c440bca_1762142404.png\"}]', 'payment_4_1762244931_6909b9431ff23.png', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `cooking_orders`
--

CREATE TABLE `cooking_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_details` text DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cost_of_goods_sold`
--

CREATE TABLE `cost_of_goods_sold` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `entry_date` datetime NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_date` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_statistics`
--

CREATE TABLE `daily_statistics` (
  `id` int(11) NOT NULL,
  `stat_date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_sales` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_targets`
--

CREATE TABLE `dashboard_targets` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `target_amount` decimal(12,2) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `no` varchar(50) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `deposit_to` enum('cash','inventory') NOT NULL,
  `category` enum('account payable','short-term','long-term','note payable') NOT NULL,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gcash_reconciliation`
--

CREATE TABLE `gcash_reconciliation` (
  `id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sizes` varchar(255) DEFAULT NULL,
  `variations` varchar(255) DEFAULT NULL,
  `prices` varchar(255) DEFAULT NULL,
  `addons` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `sizes`, `variations`, `prices`, `addons`, `image_path`, `is_available`, `created_at`) VALUES
(8, 'Longganisa With Egg Fried Rice', 'Garlic Flavor', 'Original, Ala Carte, With Garlic Rice and Eggs', 'Original, Ala Carte, With Garlic Rice and Eggs', '170, 115, 145', '', 'assets/images/uploads/690823409d69c_1762140992.png', 1, '2025-11-03 03:36:32'),
(9, 'Hotdog Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Riice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Riice', '85, 55, 11000', '', 'assets/images/uploads/6908242fd4eba_1762141231.png', 1, '2025-11-03 03:38:09'),
(10, 'Hungarian  Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '105, 80, 130', '', 'assets/images/uploads/69082424cf7da_1762141220.png', 1, '2025-11-03 03:40:20'),
(11, 'Shanghai Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '105, 80, 110', '', 'assets/images/uploads/690824905063f_1762141328.png', 1, '2025-11-03 03:42:08'),
(12, 'Tapa Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '145, 115, 170', '', 'assets/images/uploads/690825f00b1f0_1762141680.png', 1, '2025-11-03 03:47:43'),
(13, 'Tocino Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '135, 110, 160', '', 'assets/images/uploads/6908267f65cc1_1762141823.png', 1, '2025-11-03 03:50:23'),
(14, 'Chicken Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '135, 110, 160', '', 'assets/images/uploads/6908273ced24a_1762142012.png', 1, '2025-11-03 03:53:32'),
(15, 'Fish Fillet Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '140, 115, 165', '', 'assets/images/uploads/6908287c4af76_1762142332.png', 1, '2025-11-03 03:58:52'),
(16, 'Chicken Fillet Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '135, 110, 160', '', 'assets/images/uploads/690828c440bca_1762142404.png', 1, '2025-11-03 04:00:04'),
(17, 'Bangus Silog', '', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', 'With Garlic Rice and Eggs, Ala Carte, Egg Fried Rice', '175, 145, 195', '', 'assets/images/uploads/69082911dc3f4_1762142481.png', 1, '2025-11-03 04:01:21');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_cashflow`
--

CREATE TABLE `monthly_cashflow` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `cash_in` decimal(12,2) NOT NULL,
  `cash_out` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_cashflow`
--

INSERT INTO `monthly_cashflow` (`id`, `year`, `month`, `cash_in`, `cash_out`) VALUES
(1, 2025, 1, 50000.00, 20000.00),
(2, 2025, 2, 60000.00, 25000.00),
(3, 2025, 3, 55000.00, 22000.00);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_revenue`
--

CREATE TABLE `monthly_revenue` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `revenue` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_revenue`
--

INSERT INTO `monthly_revenue` (`id`, `year`, `month`, `revenue`) VALUES
(1, 2025, 1, 48000.00),
(2, 2025, 2, 59000.00),
(3, 2025, 3, 54000.00);

-- --------------------------------------------------------

--
-- Table structure for table `monthly_sales`
--

CREATE TABLE `monthly_sales` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `total_sales` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_sales`
--

INSERT INTO `monthly_sales` (`id`, `year`, `month`, `total_sales`) VALUES
(1, 2025, 1, 50000.00),
(2, 2025, 2, 60000.00),
(3, 2025, 3, 55000.00);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operating_expenses`
--

CREATE TABLE `operating_expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_details` text DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_history`
--

CREATE TABLE `order_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `order_date` datetime NOT NULL,
  `status` enum('completed','pending','cancelled') DEFAULT 'pending',
  `payment_screenshot` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_history`
--

INSERT INTO `order_history` (`id`, `user_id`, `customer_name`, `total_amount`, `order_date`, `status`, `payment_screenshot`) VALUES
(4, 1, 'Alice', 1200.00, '2025-01-15 12:30:00', 'completed', NULL),
(5, 2, 'Bob', 800.00, '2025-02-10 14:20:00', 'pending', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `size` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `addons` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `payslip_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_orders`
--

CREATE TABLE `pending_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `order_date` datetime NOT NULL,
  `order_details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_orders`
--

INSERT INTO `pending_orders` (`id`, `user_id`, `customer_name`, `total_amount`, `order_date`, `order_details`) VALUES
(2, 5, 'mnm', 170.00, '2025-11-03 14:33:45', '[{\"menuId\":\"8\",\"name\":\"Longganisa With Egg Fried Rice\",\"variation\":\"Original\",\"price\":170,\"quantity\":1,\"notes\":\"\",\"addons\":[],\"image_path\":\"\\/Food_System\\/assets\\/images\\/uploads\\/690823409d69c_1762140992.png\"}]');

-- --------------------------------------------------------

--
-- Table structure for table `pos_daily_sales`
--

CREATE TABLE `pos_daily_sales` (
  `id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `received_orders`
--

CREATE TABLE `received_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_details` text DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_date` datetime NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `pwd` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_code` varchar(10) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `pwd`, `created_at`, `is_verified`, `verification_code`, `role`) VALUES
(1, 'wew', 'rusellegarcia4@gmail.com', NULL, '$2y$12$Pyf9f1kigs4IyxN3eEDvtet/deDSOJAxkxa7.U2x/0Iovn048A7mi', '2025-10-08 05:32:31', 1, '528849', 'admin'),
(2, 'yaco', 'burdagul015@gmail.com', NULL, '$2y$12$yF57Lq5LiNfladW9I5Zry.T./q411rfAEBfsWePryRdZp8cCpZqz.', '2025-10-08 05:35:16', 0, '684361', 'user'),
(3, 'user', 'luietan04@gmail.com', NULL, '$2y$12$9iFJZQwNH6XHfuSOrssxU.z7201ILY0WQFp2.2N7882Bn9zWn0ub2', '2025-10-08 06:54:57', 0, '678081', 'user'),
(4, 'janil', 'agustinruselle880@gmail.com', NULL, '$2y$12$H9ldKGJxWAlMViVrJvoOH.CpZPBecmjQbgD31tcR2echzMybyrkla', '2025-10-19 13:52:26', 1, '200684', 'user'),
(5, 'mnm', 'mangkepweng648@gmail.com', NULL, '$2y$12$M3lrjsBdt/Va89WlcFjfvu3SuCx01Wa5UlCaYLceOoT.fPRhBOwqC', '2025-10-24 00:42:01', 1, '194476', 'user'),
(6, 'customer', 'jerichocondesa@gmail.com', NULL, '$2y$12$MCdHKyySlHR7pi.JFV.8o.GTsOh7YBuWKJJ667dO0C8wump0Mf/La', '2025-11-03 17:36:05', 1, '140068', 'user'),
(7, 'customer1', 'gecho4546@gmail.com', NULL, '$2y$12$U1CCjtcUatgqhgA6O5VW6O6ThL9n2GVNIa.SlmTUCW2qAwLWv9bq.', '2025-11-04 08:42:54', 1, '232393', 'user');

-- --------------------------------------------------------

--
-- Table structure for table `user_carts`
--

CREATE TABLE `user_carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cart_data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_carts`
--

INSERT INTO `user_carts` (`id`, `user_id`, `cart_data`, `created_at`, `updated_at`) VALUES
(32, 5, '[]', '2025-11-03 09:49:28', '2025-11-03 09:49:50'),
(38, 6, '[]', '2025-11-04 08:14:44', '2025-11-04 08:14:51'),
(39, 7, '[]', '2025-11-04 08:47:05', '2025-11-04 08:47:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accepted_orders`
--
ALTER TABLE `accepted_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `balancesheet`
--
ALTER TABLE `balancesheet`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cashflow`
--
ALTER TABLE `cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `completed_orders`
--
ALTER TABLE `completed_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cooking_orders`
--
ALTER TABLE `cooking_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cost_of_goods_sold`
--
ALTER TABLE `cost_of_goods_sold`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daily_statistics`
--
ALTER TABLE `daily_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_stat_date` (`stat_date`);

--
-- Indexes for table `dashboard_targets`
--
ALTER TABLE `dashboard_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year_month` (`year`,`month`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`);

--
-- Indexes for table `gcash_reconciliation`
--
ALTER TABLE `gcash_reconciliation`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monthly_cashflow`
--
ALTER TABLE `monthly_cashflow`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year_month` (`year`,`month`);

--
-- Indexes for table `monthly_revenue`
--
ALTER TABLE `monthly_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year_month` (`year`,`month`);

--
-- Indexes for table `monthly_sales`
--
ALTER TABLE `monthly_sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year_month` (`year`,`month`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `operating_expenses`
--
ALTER TABLE `operating_expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_history`
--
ALTER TABLE `order_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_orders`
--
ALTER TABLE `pending_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `pos_daily_sales`
--
ALTER TABLE `pos_daily_sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `received_orders`
--
ALTER TABLE `received_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_carts`
--
ALTER TABLE `user_carts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_cart` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accepted_orders`
--
ALTER TABLE `accepted_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `balancesheet`
--
ALTER TABLE `balancesheet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cashflow`
--
ALTER TABLE `cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `completed_orders`
--
ALTER TABLE `completed_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cooking_orders`
--
ALTER TABLE `cooking_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cost_of_goods_sold`
--
ALTER TABLE `cost_of_goods_sold`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_statistics`
--
ALTER TABLE `daily_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dashboard_targets`
--
ALTER TABLE `dashboard_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gcash_reconciliation`
--
ALTER TABLE `gcash_reconciliation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `monthly_cashflow`
--
ALTER TABLE `monthly_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `monthly_revenue`
--
ALTER TABLE `monthly_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `monthly_sales`
--
ALTER TABLE `monthly_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operating_expenses`
--
ALTER TABLE `operating_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `order_history`
--
ALTER TABLE `order_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_orders`
--
ALTER TABLE `pending_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pos_daily_sales`
--
ALTER TABLE `pos_daily_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `received_orders`
--
ALTER TABLE `received_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_carts`
--
ALTER TABLE `user_carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accepted_orders`
--
ALTER TABLE `accepted_orders`
  ADD CONSTRAINT `accepted_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `completed_orders`
--
ALTER TABLE `completed_orders`
  ADD CONSTRAINT `completed_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cooking_orders`
--
ALTER TABLE `cooking_orders`
  ADD CONSTRAINT `cooking_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_history`
--
ALTER TABLE `order_history`
  ADD CONSTRAINT `order_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_orders`
--
ALTER TABLE `pending_orders`
  ADD CONSTRAINT `pending_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `received_orders`
--
ALTER TABLE `received_orders`
  ADD CONSTRAINT `received_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_carts`
--
ALTER TABLE `user_carts`
  ADD CONSTRAINT `user_carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
