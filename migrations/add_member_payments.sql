CREATE TABLE IF NOT EXISTS member_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','card','bank_transfer','easypaisa','jazzcash') DEFAULT 'cash',
    payment_for VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    payment_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;
