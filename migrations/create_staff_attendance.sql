CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_time` TIME NOT NULL,
  `check_out_time` TIME DEFAULT NULL,
  `status` ENUM('present', 'absent', 'late', 'half_day', 'leave') NOT NULL DEFAULT 'present',
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
