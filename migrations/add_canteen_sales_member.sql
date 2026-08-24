-- Link canteen sales to gym members
ALTER TABLE canteen_sales
    ADD COLUMN member_id INT NULL DEFAULT NULL AFTER customer_name,
    ADD CONSTRAINT fk_sales_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL;
