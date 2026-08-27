-- Add extended member fields
ALTER TABLE members
    ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER email,
    ADD COLUMN gender ENUM('male','female','other') DEFAULT NULL AFTER date_of_birth,
    ADD COLUMN membership_type VARCHAR(50) DEFAULT NULL AFTER gender,
    ADD COLUMN area_of_interest TEXT DEFAULT NULL AFTER membership_type;

-- Dynamic options table for membership types and areas of interest
CREATE TABLE IF NOT EXISTS member_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('membership_type','area_of_interest') NOT NULL,
    value VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cat_val (category, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default membership types
INSERT IGNORE INTO member_options (category, value) VALUES
('membership_type', 'Individual'),
('membership_type', 'Family'),
('membership_type', 'Student'),
('membership_type', 'Senior');

-- Seed default areas of interest
INSERT IGNORE INTO member_options (category, value) VALUES
('area_of_interest', 'Sports & Recreation'),
('area_of_interest', 'Social Events'),
('area_of_interest', 'Volunteer Activities'),
('area_of_interest', 'Educational Programs');
