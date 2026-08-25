-- =====================================================================
-- Gym Management System — COMPLETE DATABASE SCHEMA (fresh install)
-- Base schema + all migrations merged. Nothing else needs to be run.
--
-- HOW TO IMPORT (live / cPanel):
--   1) Create a new database in cPanel -> MySQL Databases
--      (e.g. atrmarke_Gym_portal) and attach a user with ALL PRIVILEGES.
--   2) Open phpMyAdmin, SELECT that database (left sidebar).
--   3) Import tab -> choose this file -> Go.
--
-- This file intentionally has NO "USE" statement, so it always imports
-- into whatever database you selected. Compatible with MySQL 5.7+ /
-- MariaDB 10.2+.
--
-- Default login after import: admin / admin123
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Drop existing tables (safe for re-import)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS canteen_stock_log;
DROP TABLE IF EXISTS canteen_supplier_payments;
DROP TABLE IF EXISTS canteen_sale_items;
DROP TABLE IF EXISTS canteen_purchase_items;
DROP TABLE IF EXISTS canteen_sales;
DROP TABLE IF EXISTS canteen_purchases;
DROP TABLE IF EXISTS canteen_suppliers;
DROP TABLE IF EXISTS canteen_products;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS member_payments;
DROP TABLE IF EXISTS staff_salaries;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS day_passes;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS trainers;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- Core tables
-- ---------------------------------------------------------------------

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT DEFAULT NULL,
    features TEXT DEFAULT NULL,
    is_popular TINYINT(1) DEFAULT 0,
    day_pass_discount INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trainers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    specialty VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    join_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    trainer_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_members_trainer FOREIGN KEY (trainer_id)
        REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscriptions_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id)
        REFERENCES plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- includes migration: add_checkout_time.sql (check_out_time)
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_in_time TIME NOT NULL,
    check_out_time TIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    role ENUM('receptionist','trainer','helper','cleaner','manager','accountant','other') NOT NULL DEFAULT 'receptionist',
    salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    address TEXT DEFAULT NULL,
    emergency_contact VARCHAR(100) DEFAULT NULL,
    emergency_phone VARCHAR(20) DEFAULT NULL,
    join_date DATE NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- includes migration: add_member_payments.sql
CREATE TABLE member_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','bank_transfer','easypaisa','jazzcash') DEFAULT 'cash',
    payment_for VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_payments_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- includes migrations: create_staff_salaries.sql + add_salary_payment_type.sql
CREATE TABLE staff_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('salary','advance') NOT NULL DEFAULT 'salary',
    salary_month CHAR(7) NOT NULL COMMENT 'YYYY-MM month the salary is for',
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','easypaisa','jazzcash') NOT NULL DEFAULT 'cash',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_salaries_staff FOREIGN KEY (staff_id)
        REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE day_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    member_id INT DEFAULT NULL,
    pass_type ENUM('gym','kids_play','both') NOT NULL,
    pass_date DATE NOT NULL,
    check_in_time TIME NOT NULL,
    check_out_time TIME DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_day_passes_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Expenses
-- ---------------------------------------------------------------------

CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','easypaisa','jazzcash') DEFAULT 'cash',
    description TEXT DEFAULT NULL,
    receipt_no VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_category FOREIGN KEY (category_id)
        REFERENCES expense_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Canteen subsystem
-- ---------------------------------------------------------------------

CREATE TABLE canteen_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT NULL,
    unit VARCHAR(20) DEFAULT 'piece',
    purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    stock_qty INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 5,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canteen_suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canteen_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    purchase_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_canteen_purchases_supplier FOREIGN KEY (supplier_id)
        REFERENCES canteen_suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canteen_purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_cpi_purchase FOREIGN KEY (purchase_id)
        REFERENCES canteen_purchases(id) ON DELETE CASCADE,
    CONSTRAINT fk_cpi_product FOREIGN KEY (product_id)
        REFERENCES canteen_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- includes migration: add_canteen_sales_member.sql (member_id + FK)
CREATE TABLE canteen_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) DEFAULT NULL,
    customer_name VARCHAR(200) DEFAULT NULL,
    member_id INT NULL DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    received_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','online','easypaisa','jazzcash') DEFAULT 'cash',
    payment_date DATE DEFAULT NULL,
    sale_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sales_member FOREIGN KEY (member_id)
        REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canteen_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_csi_sale FOREIGN KEY (sale_id)
        REFERENCES canteen_sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_csi_product FOREIGN KEY (product_id)
        REFERENCES canteen_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canteen_supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','bank_transfer') DEFAULT 'cash',
    notes TEXT DEFAULT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_csp_supplier FOREIGN KEY (supplier_id)
        REFERENCES canteen_suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- includes migration: add_stock_log_opening_type.sql ('opening' type)
CREATE TABLE canteen_stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('purchase','sale','adjustment_in','adjustment_out','opening') NOT NULL,
    quantity INT NOT NULL,
    reference_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_csl_product FOREIGN KEY (product_id)
        REFERENCES canteen_products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed data
-- ---------------------------------------------------------------------

INSERT INTO users (username, password) VALUES ('admin', 'admin123');

INSERT INTO trainers (name, phone, email, specialty) VALUES
('John Doe', '9876543200', 'john@example.com', 'Strength Training'),
('Maria Khan', '9876543201', 'maria@example.com', 'Yoga & Pilates');

INSERT INTO staff (name, phone, email, role, salary, join_date, status) VALUES
('Ahmed Khan', '0321-1111111', 'ahmed@gym.pk', 'receptionist', 35000.00, '2025-06-01', 'active'),
('Sara Malik', '0333-2222222', 'sara@gym.pk', 'manager', 55000.00, '2025-01-15', 'active'),
('Usman Ali', '0300-3333333', 'usman@gym.pk', 'cleaner', 20000.00, '2026-03-01', 'active');

INSERT INTO plans (name, duration_days, price, description, features, is_popular, day_pass_discount, status) VALUES
('Monthly', 30, 1500.00, 'Basic gym access for one month', 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart', 0, 10, 'active'),
('Quarterly', 90, 4000.00, '3 months access with discount', 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart\n2 Group Classes/Week\nSteam Room Access', 1, 30, 'active'),
('Yearly', 365, 14000.00, 'Full year unlimited access', 'Gym Equipment Access\nLocker Facility\nPersonal Diet Plan\nUnlimited Group Classes\nSteam & Sauna Access\nPersonal Trainer (4 Sessions)\nFree Merchandise', 0, 50, 'active');

INSERT INTO members (name, phone, email, join_date, status, trainer_id) VALUES
('Rahul Sharma', '9876543210', 'rahul@example.com', '2026-01-10', 'active', 1),
('Priya Patel', '9876543211', 'priya@example.com', '2026-02-15', 'active', 2),
('Amit Kumar', '9876543212', 'amit@example.com', '2026-03-20', 'active', 1),
('Sneha Reddy', '9876543213', 'sneha@example.com', '2026-04-05', 'inactive', NULL);

INSERT INTO subscriptions (member_id, plan_id, start_date, end_date, status) VALUES
(1, 1, '2026-07-01', '2026-07-31', 'active'),
(2, 2, '2026-06-01', '2026-08-30', 'active'),
(3, 3, '2026-01-01', '2026-12-31', 'active'),
(4, 1, '2026-03-20', '2026-04-19', 'expired');

INSERT INTO attendance (member_id, check_in_date, check_in_time) VALUES
(1, CURDATE(), '06:30:00'),
(2, CURDATE(), '07:00:00'),
(3, CURDATE(), '08:15:00');

INSERT INTO canteen_products (name, category, unit, purchase_price, sale_price, stock_qty, min_stock) VALUES
('Protein Shake (Chocolate)', 'Beverages', 'packet', 250.00, 350.00, 50, 10),
('Banana', 'Fresh', 'piece', 15.00, 25.00, 100, 20),
('Boiled Eggs (2 pcs)', 'Fresh', 'plate', 30.00, 50.00, 40, 10),
('Water Bottle', 'Beverages', 'bottle', 15.00, 20.00, 100, 20),
('Protein Bar', 'Supplements', 'piece', 120.00, 180.00, 30, 5),
('Creatine Monohydrate', 'Supplements', 'jar', 800.00, 1200.00, 15, 3),
('Electrolyte Drink', 'Beverages', 'bottle', 40.00, 60.00, 60, 15),
('Chicken Breast (Grilled)', 'Fresh', 'plate', 180.00, 280.00, 25, 5);

INSERT INTO canteen_suppliers (name, phone, email, address) VALUES
('Protein House', '0321-1234567', 'info@proteinhouse.pk', 'DHA Phase 5, Karachi'),
('Fresh Valley Dairy', '0333-7654321', 'sales@freshvalley.pk', 'Gulshan-e-Iqbal, Karachi');

INSERT INTO expense_categories (name, description) VALUES
('Rent', 'Building rent / lease payments'),
('Utilities', 'Electricity, Gas, Water, Internet'),
('Salaries', 'Staff salaries and wages'),
('Maintenance', 'Equipment repair and maintenance'),
('Cleaning', 'Cleaning supplies and services'),
('Marketing', 'Advertising and promotional expenses'),
('Miscellaneous', 'Other uncategorized expenses');
