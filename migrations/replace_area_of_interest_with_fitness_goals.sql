-- Migration: Replace Area of Interest with Fitness Goals
-- Date: 2026-08-31

ALTER TABLE member_options 
MODIFY COLUMN category ENUM('membership_type','area_of_interest','fitness_goal') NOT NULL;

-- Remove obsolete non-fitness items
DELETE FROM member_options 
WHERE value IN ('Sports & Recreation', 'Social Events', 'Volunteer Activities', 'Educational Programs');

-- Insert requested default fitness goals
INSERT IGNORE INTO member_options (category, value) VALUES
('fitness_goal', 'Weight Loss'),
('fitness_goal', 'Muscle Gain'),
('fitness_goal', 'Endurance Training'),
('fitness_goal', 'Health Recovery'),
('fitness_goal', 'Improve Cardiac Health'),
('area_of_interest', 'Weight Loss'),
('area_of_interest', 'Muscle Gain'),
('area_of_interest', 'Endurance Training'),
('area_of_interest', 'Health Recovery'),
('area_of_interest', 'Improve Cardiac Health');
