-- Migration: Convert Membership Plans to Diet Plans
-- Deletes old membership plans and adds 3 diet plans

SET FOREIGN_KEY_CHECKS = 0;

-- Delete old membership plans
DELETE FROM plans WHERE id IN (1, 2, 3);

-- Reset auto increment
ALTER TABLE plans AUTO_INCREMENT = 1;

-- Insert 3 Diet Plans (IDs will be 1, 2, 3 matching existing subscriptions)
INSERT INTO plans (name, duration_days, price, description, features, is_popular, day_pass_discount, status) VALUES
('Weight Loss Diet Plan', 30, 2500.00, 'Customized diet plan for effective weight loss', 'Personalized Meal Plan\nDaily Calorie Tracking\nLow-Carb & High-Protein Meals\nWeekly Progress Review\nHydration Guidelines\nSnack Alternatives', 1, 0, 'active'),
('Muscle Gain Diet Plan', 30, 3000.00, 'High-protein diet plan for muscle building', 'High-Protein Meal Plan\nPre & Post Workout Nutrition\nSupplement Recommendations\nMacro Tracking\nMeal Prep Guidelines\nBi-Weekly Consultation', 0, 0, 'active'),
('Maintenance Diet Plan', 30, 1800.00, 'Balanced diet plan for maintaining healthy weight', 'Balanced Meal Plan\nPortion Control Guide\nHealthy Recipe Collection\nMonthly Check-In\nLifestyle Tips\nSeasonal Menu Adjustments', 0, 0, 'active');

SET FOREIGN_KEY_CHECKS = 1;
