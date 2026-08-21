-- Migration: Enhance plans table for membership plans
ALTER TABLE plans
    ADD COLUMN features TEXT DEFAULT NULL AFTER description,
    ADD COLUMN is_popular TINYINT(1) DEFAULT 0 AFTER features,
    ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER is_popular;

-- Update existing plans with features
UPDATE plans SET
    features = 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart',
    is_popular = 0,
    status = 'active'
WHERE name = 'Monthly';

UPDATE plans SET
    features = 'Gym Equipment Access\nLocker Facility\nBasic Diet Chart\n2 Group Classes/Week\nSteam Room Access',
    is_popular = 1,
    status = 'active'
WHERE name = 'Quarterly';

UPDATE plans SET
    features = 'Gym Equipment Access\nLocker Facility\nPersonal Diet Plan\nUnlimited Group Classes\nSteam & Sauna Access\nPersonal Trainer (4 Sessions)\nFree Merchandise',
    is_popular = 0,
    status = 'active'
WHERE name = 'Yearly';
