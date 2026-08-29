-- Gym Management System - Universal Live Production Database Dump
-- Compatible with All MySQL (5.7, 8.0+) and MariaDB Versions

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_in_time` time NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `member_id`, `check_in_date`, `check_in_time`, `check_out_time`, `created_at`) VALUES
('1', '1', '2026-08-11', '06:30:00', NULL, '2026-08-11 16:58:28'),
('2', '2', '2026-08-11', '07:00:00', NULL, '2026-08-11 16:58:28'),
('6', '2', '2026-08-20', '12:14:18', '12:23:37', '2026-08-20 12:14:18'),
('7', '1', '2026-08-20', '12:23:32', '12:23:41', '2026-08-20 12:23:32'),
('8', '3', '2026-08-21', '12:12:41', '12:12:57', '2026-08-21 12:12:41'),
('9', '3', '2026-08-21', '12:18:14', '12:18:15', '2026-08-21 12:18:14'),
('10', '8', '2026-08-21', '12:44:27', '12:44:33', '2026-08-21 12:44:27');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_products`
--

DROP TABLE IF EXISTS `canteen_products`;
CREATE TABLE `canteen_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'piece',
  `purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 5,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_products`
--

INSERT INTO `canteen_products` (`id`, `name`, `category`, `unit`, `purchase_price`, `sale_price`, `stock_qty`, `min_stock`, `status`, `created_at`) VALUES
('1', 'protein', 'Supplements', 'pack', '5000.00', '8000.00', '3', '5', 'active', '2026-08-17 16:21:25'),
('3', 'Whey Protein Shake (Chocolate)', 'Supplements', 'Glass', '320.00', '450.00', '30', '5', 'active', '2026-08-28 14:55:45'),
('4', 'Pre-Workout Blast (Fruit Punch)', 'Energy Drinks', 'Can', '180.00', '280.00', '45', '8', 'active', '2026-08-28 14:55:45'),
('5', 'Protein Bar (Peanut Crunch)', 'Snacks', 'Piece', '150.00', '220.00', '35', '5', 'active', '2026-08-28 14:55:45'),
('6', 'Gatorade Sports Drink (500ml)', 'Beverages', 'Bottle', '160.00', '250.00', '40', '10', 'active', '2026-08-28 14:55:45'),
('7', 'BCAA Recovery Drink (Lime)', 'Supplements', 'Glass', '220.00', '350.00', '25', '5', 'active', '2026-08-28 14:55:45'),
('8', 'Nestle Mineral Water (1.5L)', 'Beverages', 'Bottle', '70.00', '120.00', '60', '15', 'active', '2026-08-28 14:55:45'),
('9', 'Boiled Eggs with Black Pepper', 'Snacks', 'Pair', '60.00', '100.00', '19', '4', 'active', '2026-08-28 14:55:45');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_purchase_items`
--

DROP TABLE IF EXISTS `canteen_purchase_items`;
CREATE TABLE `canteen_purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `purchase_id` (`purchase_id`),
  KEY `fk_cpi_product` (`product_id`),
  CONSTRAINT `canteen_purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `canteen_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cpi_product` FOREIGN KEY (`product_id`) REFERENCES `canteen_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_purchase_items`
--

INSERT INTO `canteen_purchase_items` (`id`, `purchase_id`, `product_id`, `qty`, `unit_price`, `total`) VALUES
('1', '1', '1', '1', '5000.00', '5000.00');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_purchases`
--

DROP TABLE IF EXISTS `canteen_purchases`;
CREATE TABLE `canteen_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `purchase_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_canteen_purchases_supplier` (`supplier_id`),
  CONSTRAINT `fk_canteen_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `canteen_suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_purchases`
--

INSERT INTO `canteen_purchases` (`id`, `supplier_id`, `total_amount`, `paid_amount`, `purchase_date`, `notes`, `created_at`) VALUES
('1', '1', '5000.00', '500.00', '2026-08-17', NULL, '2026-08-17 16:22:24');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_sale_items`
--

DROP TABLE IF EXISTS `canteen_sale_items`;
CREATE TABLE `canteen_sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `fk_csi_product` (`product_id`),
  CONSTRAINT `canteen_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `canteen_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_csi_product` FOREIGN KEY (`product_id`) REFERENCES `canteen_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_sale_items`
--

INSERT INTO `canteen_sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `unit_price`, `subtotal`) VALUES
('5', '5', '1', '1', '8000.00', '8000.00'),
('6', '6', '1', '1', '8000.00', '8000.00'),
('7', '7', '1', '1', '8000.00', '8000.00'),
('8', '8', '9', '1', '100.00', '100.00');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_sales`
--

DROP TABLE IF EXISTS `canteen_sales`;
CREATE TABLE `canteen_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(50) DEFAULT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `received_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','online','easypaisa','jazzcash') DEFAULT 'cash',
  `payment_date` date DEFAULT NULL,
  `sale_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sales_member` (`member_id`),
  CONSTRAINT `fk_sales_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_sales`
--

INSERT INTO `canteen_sales` (`id`, `receipt_no`, `customer_name`, `member_id`, `total_amount`, `discount`, `net_amount`, `final_amount`, `received_amount`, `payment_method`, `payment_date`, `sale_date`, `notes`, `created_at`) VALUES
('5', 'RCP-20260824-5266', 'Amit Kumar', '3', '8000.00', '0.00', '0.00', '8000.00', '500.00', 'cash', '2026-08-24', '2026-08-24', NULL, '2026-08-24 12:46:42'),
('6', 'RCP-20260827-7867', 'Afaq', '10', '8000.00', '0.00', '0.00', '8000.00', '10.00', 'cash', '2026-08-27', '2026-08-27', NULL, '2026-08-27 17:25:20'),
('7', 'RCP-20260828-9065', 'Ali', '8', '8000.00', '0.00', '0.00', '8000.00', '0.00', 'cash', '2026-08-28', '2026-08-28', NULL, '2026-08-28 12:37:39'),
('8', 'RCP-20260828-6069', NULL, NULL, '100.00', '0.00', '0.00', '100.00', '100.00', 'cash', '2026-08-28', '2026-08-28', NULL, '2026-08-28 15:32:06');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_stock_log`
--

DROP TABLE IF EXISTS `canteen_stock_log`;
CREATE TABLE `canteen_stock_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `type` enum('purchase','sale','adjustment_in','adjustment_out','opening') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `canteen_stock_log_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `canteen_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_stock_log`
--

INSERT INTO `canteen_stock_log` (`id`, `product_id`, `type`, `quantity`, `reference_id`, `notes`, `created_at`) VALUES
('7', '1', 'sale', '1', '5', 'Sale RCP-20260824-5266', '2026-08-24 12:46:42'),
('8', '1', 'sale', '1', '6', 'Sale RCP-20260827-7867', '2026-08-27 17:25:20'),
('9', '1', 'sale', '1', '7', 'Sale RCP-20260828-9065', '2026-08-28 12:37:39'),
('10', '3', 'opening', '30', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('11', '4', 'opening', '45', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('12', '5', 'opening', '35', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('13', '6', 'opening', '40', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('14', '7', 'opening', '25', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('15', '8', 'opening', '60', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('16', '9', 'opening', '20', NULL, 'Initial sample stock', '2026-08-28 14:55:45'),
('17', '9', 'sale', '1', '8', 'Sale RCP-20260828-6069', '2026-08-28 15:32:06');

-- --------------------------------------------------------

--
-- Table structure for table `canteen_supplier_payments`
--

DROP TABLE IF EXISTS `canteen_supplier_payments`;
CREATE TABLE `canteen_supplier_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','bank_transfer') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_csp_supplier` (`supplier_id`),
  CONSTRAINT `fk_csp_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `canteen_suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `canteen_suppliers`
--

DROP TABLE IF EXISTS `canteen_suppliers`;
CREATE TABLE `canteen_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `canteen_suppliers`
--

INSERT INTO `canteen_suppliers` (`id`, `name`, `phone`, `email`, `address`, `balance`, `status`, `created_at`) VALUES
('1', 'Ali', '03214521452', 'ali@gmail.com', 'Lahore', '4500.00', 'active', '2026-08-17 14:27:15'),
('2', 'Ali', '03214521452', 'ali@gmail.com', 'Lahore', '0.00', 'active', '2026-08-17 14:34:04');

-- --------------------------------------------------------

--
-- Table structure for table `day_passes`
--

DROP TABLE IF EXISTS `day_passes`;
CREATE TABLE `day_passes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visitor_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `pass_type` enum('gym','kids_play','both') NOT NULL,
  `pass_date` date NOT NULL,
  `check_in_time` time NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `day_passes_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `day_passes`
--

INSERT INTO `day_passes` (`id`, `visitor_name`, `phone`, `member_id`, `pass_type`, `pass_date`, `check_in_time`, `check_out_time`, `amount`, `notes`, `created_at`) VALUES
('1', 'Faheem', '03021452145', NULL, 'gym', '2026-08-17', '13:04:35', '15:50:55', '2000.00', 'abc', '2026-08-17 13:04:35'),
('2', 'Faheem', '03080060633', '10', 'both', '2026-08-25', '16:45:11', NULL, '250.00', NULL, '2026-08-25 16:45:11'),
('3', 'Faheem', '03214569654', '10', 'both', '2026-08-28', '11:30:18', NULL, '2500.00', 'abc', '2026-08-28 11:30:18'),
('4', 'Ahmad', '032221457987', '8', 'kids_play', '2026-08-28', '11:39:20', '11:42:10', '1000.00', NULL, '2026-08-28 11:39:20'),
('5', 'Zunair', '0321564564', '3', 'both', '2026-08-28', '11:43:07', NULL, '2500.00', 'ava', '2026-08-28 11:43:07');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`, `status`, `created_at`) VALUES
('1', 'Rent', 'Building rent / lease payments', 'active', '2026-08-17 15:58:36'),
('2', 'Utilities', 'Electricity, Gas, Water, Internet', 'active', '2026-08-17 15:58:36'),
('3', 'Salaries', 'Staff salaries and wages', 'active', '2026-08-17 15:58:36'),
('4', 'Maintenance', 'Equipment repair and maintenance', 'active', '2026-08-17 15:58:36'),
('5', 'Cleaning', 'Cleaning supplies and services', 'active', '2026-08-17 15:58:36'),
('6', 'Marketing', 'Advertising and promotional expenses', 'active', '2026-08-17 15:58:36'),
('7', 'Miscellaneous', 'Other uncategorized expenses', 'active', '2026-08-17 15:58:36');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','easypaisa','jazzcash') DEFAULT 'cash',
  `description` text DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category_id`, `amount`, `expense_date`, `payment_method`, `description`, `receipt_no`, `created_at`) VALUES
('1', '1', '5000.00', '2026-08-24', 'cash', NULL, NULL, '2026-08-24 15:10:45');

-- --------------------------------------------------------

--
-- Table structure for table `member_options`
--

DROP TABLE IF EXISTS `member_options`;
CREATE TABLE `member_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` enum('membership_type','area_of_interest') NOT NULL,
  `value` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_val` (`category`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_options`
--

INSERT INTO `member_options` (`id`, `category`, `value`, `created_at`) VALUES
('1', 'membership_type', 'Individual', '2026-08-25 12:42:59'),
('2', 'membership_type', 'Family', '2026-08-25 12:42:59'),
('3', 'membership_type', 'Student', '2026-08-25 12:42:59'),
('4', 'membership_type', 'Senior', '2026-08-25 12:42:59'),
('5', 'area_of_interest', 'Sports & Recreation', '2026-08-25 12:42:59'),
('6', 'area_of_interest', 'Social Events', '2026-08-25 12:42:59'),
('7', 'area_of_interest', 'Volunteer Activities', '2026-08-25 12:42:59'),
('8', 'area_of_interest', 'Educational Programs', '2026-08-25 12:42:59');

-- --------------------------------------------------------

--
-- Table structure for table `member_payments`
--

DROP TABLE IF EXISTS `member_payments`;
CREATE TABLE `member_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','bank_transfer','easypaisa','jazzcash') DEFAULT 'cash',
  `payment_for` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `member_payments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_payments`
--

INSERT INTO `member_payments` (`id`, `member_id`, `amount`, `payment_method`, `payment_for`, `notes`, `payment_date`, `created_at`) VALUES
('5', '3', '500.00', 'cash', 'Membership Fee', NULL, '2026-08-21', '2026-08-21 11:59:20'),
('6', '10', '500.00', 'cash', 'Other', NULL, '2026-08-28', '2026-08-28 12:49:52'),
('7', '10', '5.00', 'cash', 'Other', NULL, '2026-08-28', '2026-08-28 12:50:50');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `membership_type` varchar(50) DEFAULT NULL,
  `area_of_interest` text DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `trainer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_members_trainer` (`trainer_id`),
  CONSTRAINT `fk_members_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `phone`, `email`, `date_of_birth`, `gender`, `membership_type`, `area_of_interest`, `join_date`, `status`, `trainer_id`, `created_at`) VALUES
('1', 'Rahul Sharma', '9876543210', 'rahul@example.com', NULL, NULL, NULL, NULL, '2026-01-10', 'active', '1', '2026-08-11 16:58:28'),
('2', 'Priya Patel', '9876543211', 'priya@example.com', NULL, NULL, NULL, NULL, '2026-02-15', 'active', '2', '2026-08-11 16:58:28'),
('3', 'Amit Kumar', '9876543212', 'amit@example.com', NULL, NULL, NULL, NULL, '2026-03-20', 'active', '1', '2026-08-21 11:41:45'),
('4', 'Sneha Reddy', '9876543213', 'sneha@example.com', NULL, NULL, NULL, NULL, '2026-04-05', 'inactive', NULL, '2026-08-21 11:41:45'),
('8', 'Ali', '03211123144', 'ali@gmail.com', NULL, NULL, NULL, NULL, '2026-08-21', 'active', '3', '2026-08-21 12:23:57'),
('10', 'Afaq', '03214569876', 'afaq@gmaill.com', '2014-06-25', 'male', 'Student', 'Social Events, Sports & Recreation', '2026-08-25', 'active', '1', '2026-08-25 12:56:34');

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL,
  `is_popular` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `day_pass_discount` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `duration_days`, `price`, `description`, `features`, `is_popular`, `status`, `day_pass_discount`, `created_at`) VALUES
('1', 'Monthly', '30', '1500.00', 'Basic gym access for one month', 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart', '0', 'active', '10', '2026-08-11 16:58:28'),
('2', 'Quarterly', '90', '4000.00', '3 months access with discount', 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart\n2 Group Classes/Week\nSteam Room Access', '1', 'active', '30', '2026-08-11 16:58:28'),
('3', 'Yearly', '365', '14000.00', 'Full year unlimited access', 'Gym Equipment Access\nLocker Facility\nPersonal Diet Plan\nUnlimited Group Classes\nSteam & Sauna Access\nPersonal Trainer (4 Sessions)\nFree Merchandise', '0', 'active', '50', '2026-08-11 16:58:28'),
('4', 'Shake Plan', '30', '2500.00', 'Banana Shake', 'Dates\r\nMilk\r\nSugar', '0', 'active', '0', '2026-08-21 12:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('receptionist','trainer','helper','cleaner','manager','accountant','other') NOT NULL DEFAULT 'receptionist',
  `salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `join_date` date NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `name`, `phone`, `email`, `role`, `salary`, `address`, `emergency_contact`, `emergency_phone`, `join_date`, `status`, `notes`, `created_at`) VALUES
('1', 'Ahmed Khan', '0321-1111111', 'ahmed@gym.pk', 'receptionist', '35000.00', NULL, NULL, NULL, '2025-06-01', 'active', NULL, '2026-08-17 16:35:31'),
('2', 'Sara Malik', '0333-2222222', 'sara@gym.pk', 'manager', '55000.00', NULL, NULL, NULL, '2025-01-15', 'active', NULL, '2026-08-17 16:35:31'),
('3', 'Usman Ali', '0300-3333333', 'usman@gym.pk', 'cleaner', '20000.00', NULL, NULL, NULL, '2026-03-01', 'active', NULL, '2026-08-17 16:35:31');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','absent','late','half_day','leave') NOT NULL DEFAULT 'present',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_salaries`
--

DROP TABLE IF EXISTS `staff_salaries`;
CREATE TABLE `staff_salaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('salary','advance') NOT NULL DEFAULT 'salary',
  `salary_month` char(7) NOT NULL COMMENT 'YYYY-MM month the salary is for',
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','card','bank_transfer','easypaisa','jazzcash') NOT NULL DEFAULT 'cash',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_salaries_ibfk_1` (`staff_id`),
  CONSTRAINT `staff_salaries_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_salaries`
--

INSERT INTO `staff_salaries` (`id`, `staff_id`, `amount`, `payment_type`, `salary_month`, `payment_date`, `payment_method`, `notes`, `created_at`) VALUES
('1', '1', '35000.00', 'salary', '2026-08', '2026-08-21', 'cash', NULL, '2026-08-21 12:58:05'),
('2', '3', '20000.00', 'salary', '2026-08', '2026-08-22', 'cash', NULL, '2026-08-22 11:47:32'),
('4', '1', '35000.00', 'salary', '2025-06', '2025-06-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('5', '1', '35000.00', 'salary', '2025-07', '2025-07-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('6', '1', '35000.00', 'salary', '2025-08', '2025-08-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('7', '1', '35000.00', 'salary', '2025-09', '2025-09-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('8', '1', '35000.00', 'salary', '2025-10', '2025-10-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('9', '1', '35000.00', 'salary', '2025-11', '2025-11-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('10', '1', '35000.00', 'salary', '2025-12', '2025-12-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('11', '1', '35000.00', 'salary', '2026-01', '2026-01-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('12', '1', '35000.00', 'salary', '2026-02', '2026-02-28', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('13', '1', '35000.00', 'salary', '2026-03', '2026-03-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('14', '1', '35000.00', 'salary', '2026-04', '2026-04-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('15', '1', '35000.00', 'salary', '2026-05', '2026-05-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('16', '1', '35000.00', 'salary', '2026-06', '2026-06-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('17', '1', '35000.00', 'salary', '2026-07', '2026-07-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:32:18'),
('18', '2', '55000.00', 'salary', '2025-01', '2025-01-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('19', '2', '55000.00', 'salary', '2025-02', '2025-02-28', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('20', '2', '55000.00', 'salary', '2025-03', '2025-03-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('21', '2', '55000.00', 'salary', '2025-04', '2025-04-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('22', '2', '55000.00', 'salary', '2025-05', '2025-05-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('23', '2', '55000.00', 'salary', '2025-06', '2025-06-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('24', '2', '55000.00', 'salary', '2025-07', '2025-07-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('25', '2', '55000.00', 'salary', '2025-08', '2025-08-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('26', '2', '55000.00', 'salary', '2025-09', '2025-09-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('27', '2', '55000.00', 'salary', '2025-10', '2025-10-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('28', '2', '55000.00', 'salary', '2025-11', '2025-11-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('29', '2', '55000.00', 'salary', '2025-12', '2025-12-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('30', '2', '55000.00', 'salary', '2026-01', '2026-01-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('31', '2', '55000.00', 'salary', '2026-02', '2026-02-28', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('32', '2', '55000.00', 'salary', '2026-03', '2026-03-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('33', '2', '55000.00', 'salary', '2026-04', '2026-04-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('34', '2', '55000.00', 'salary', '2026-05', '2026-05-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('35', '2', '55000.00', 'salary', '2026-06', '2026-06-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('36', '2', '55000.00', 'salary', '2026-07', '2026-07-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('37', '3', '20000.00', 'salary', '2026-03', '2026-03-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('38', '3', '20000.00', 'salary', '2026-04', '2026-04-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('39', '3', '20000.00', 'salary', '2026-05', '2026-05-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('40', '3', '20000.00', 'salary', '2026-06', '2026-06-30', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01'),
('41', '3', '20000.00', 'salary', '2026-07', '2026-07-31', 'cash', 'Old salary (backfill)', '2026-08-22 12:34:01');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `member_id`, `plan_id`, `start_date`, `end_date`, `status`, `created_at`) VALUES
('1', '1', '1', '2026-07-01', '2026-07-31', 'expired', '2026-08-11 16:58:28'),
('2', '2', '2', '2026-06-01', '2026-08-30', 'active', '2026-08-11 16:58:28'),
('3', '3', '3', '2026-01-01', '2026-12-31', 'active', '2026-08-21 11:41:45'),
('4', '4', '1', '2026-03-20', '2026-04-19', 'expired', '2026-08-21 11:41:45'),
('6', '8', '1', '2026-08-21', '2026-09-20', 'active', '2026-08-21 12:23:57'),
('7', '4', '4', '2026-08-21', '2026-09-20', 'active', '2026-08-21 12:28:07'),
('8', '10', '1', '2026-08-25', '2026-09-24', 'active', '2026-08-25 12:56:34');

-- --------------------------------------------------------

--
-- Table structure for table `trainers`
--

DROP TABLE IF EXISTS `trainers`;
CREATE TABLE `trainers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trainers`
--

INSERT INTO `trainers` (`id`, `name`, `phone`, `email`, `specialty`, `created_at`) VALUES
('1', 'John Doe', '9876543200', 'john@example.com', 'Strength Training', '2026-08-11 16:58:28'),
('2', 'Maria Khan', '9876543201', 'maria@example.com', 'Yoga & Pilates', '2026-08-11 16:58:28'),
('3', 'Yousaf', '03214521452', 'yousaf@gmail.com', 'Training', '2026-08-21 12:22:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
('1', 'admin', 'admin123', '2026-08-11 16:58:28');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
