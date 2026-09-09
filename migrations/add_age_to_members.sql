-- Migration: Add age to members table
-- Date: 2026-09-07
-- Manually entered age in years (stored alongside date_of_birth)

ALTER TABLE members
    ADD COLUMN age INT DEFAULT NULL AFTER date_of_birth;