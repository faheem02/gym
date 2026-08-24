-- Allow opening stock entries in stock log
ALTER TABLE canteen_stock_log
    MODIFY COLUMN type ENUM('purchase','sale','adjustment_in','adjustment_out','opening') NOT NULL;
