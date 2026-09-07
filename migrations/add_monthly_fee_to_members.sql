-- Migration: Add monthly_fee to members table
-- Date: 2026-08-31

ALTER TABLE members 
ADD COLUMN monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER registration_fee;
