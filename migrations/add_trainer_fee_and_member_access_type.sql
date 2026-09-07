-- Migration: Add fee to trainers, and access_type, registration_fee, trainer_fee, kids_fee, discount, guardian_name to members

ALTER TABLE trainers
    ADD COLUMN fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER specialty;

ALTER TABLE members
    ADD COLUMN guardian_name VARCHAR(100) DEFAULT NULL AFTER name,
    ADD COLUMN access_type ENUM('gym','kids_play','both') NOT NULL DEFAULT 'gym' AFTER status,
    ADD COLUMN registration_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER access_type,
    ADD COLUMN trainer_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER registration_fee,
    ADD COLUMN kids_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER trainer_fee,
    ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER kids_fee;
