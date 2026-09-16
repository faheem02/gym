-- Add weight tracking fields
-- members.weight : current/start weight of the member (kg)
-- member_payments.weight : weight recorded at the time of each payment (kg),
--                          used for progress tracking (loss/gain)

ALTER TABLE members
    ADD COLUMN weight DECIMAL(6,2) DEFAULT NULL AFTER age;

ALTER TABLE member_payments
    ADD COLUMN weight DECIMAL(6,2) DEFAULT NULL AFTER amount;

ALTER TABLE member_payments
    ADD COLUMN age INT DEFAULT NULL AFTER weight;
