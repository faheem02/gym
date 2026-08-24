-- Staff salary payments tracking
CREATE TABLE IF NOT EXISTS staff_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    salary_month CHAR(7) NOT NULL COMMENT 'YYYY-MM month the salary is for',
    payment_date DATE NOT NULL,
    payment_method ENUM('cash','card','bank_transfer','easypaisa','jazzcash') NOT NULL DEFAULT 'cash',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT staff_salaries_ibfk_1 FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
