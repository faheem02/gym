-- Distinguish regular salary payments from advance payments
ALTER TABLE staff_salaries
    ADD COLUMN payment_type ENUM('salary','advance') NOT NULL DEFAULT 'salary' AFTER amount;
