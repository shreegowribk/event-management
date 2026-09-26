-- ==========================================================
-- Database Schema for Evenza - College Event Management System
-- Database Name: evenza_db
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `evenza_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `evenza_db`;

-- ----------------------------------------------------------
-- 1. Students Table
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `usn` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(15) NOT NULL,
  `course` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Admins Table
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Events Table
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `event_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `event_date` DATE NOT NULL,
  `event_time` TIME NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `max_seats` INT NOT NULL DEFAULT 50,
  `status` ENUM('upcoming', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins`(`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Registrations Table
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `registrations` (
  `registration_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  `checked_in` TINYINT(1) NOT NULL DEFAULT 0,
  `checked_in_at` DATETIME DEFAULT NULL,
  `checked_in_by` INT DEFAULT NULL,
  CONSTRAINT `fk_reg_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reg_checked_in_by` FOREIGN KEY (`checked_in_by`) REFERENCES `admins`(`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `unique_student_event` UNIQUE (`student_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Sample Seed Data for Evenza Events
-- ----------------------------------------------------------
INSERT INTO `events` (`event_name`, `category`, `event_date`, `event_time`, `venue`, `description`, `max_seats`) VALUES
('TechBlitz: Hackathon 2026', 'Technical', '2026-10-15', '09:30:00', 'Campus Computer Lab 3', 'A 12-hour intensive coding hackathon where students build cutting-edge solutions for smart campus management. Teams of 1-3 members can compete.', 45),
('Nrityotsav: Inter-College Cultural Fest', 'Cultural', '2026-10-22', '14:00:00', 'Campus Main Auditorium', 'Grand cultural evening featuring classical and western group dance competitions, solo singing, and drama performances celebrating Karnataka heritage.', 120),
('Chanakya: Management Conclave', 'Management', '2026-11-05', '10:00:00', 'Seminar Hall 1', 'Premier business quiz, crisis management simulation, and product launch pitching competition designed for aspiring business leaders and innovators.', 60),
('Full-Stack Web Development Workshop', 'Workshop', '2026-10-28', '11:00:00', 'Digital Learning Center', 'Hands-on masterclass covering modern web development, APIs, database optimization, and deployment best practices guided by industry architects.', 35),
('Spardha: Annual Badminton Tournament', 'Sports', '2026-11-12', '08:00:00', 'Campus Sports Complex Court A', 'Annual intra-college badminton tournament for Men and Women singles and doubles. Trophies and certificates for winners and runners-up.', 32),
('CyberGuard: Ethical Hacking Bootcamp', 'Technical', '2026-11-20', '10:30:00', 'MCA Seminar Hall', 'Comprehensive workshop on network security, penetration testing fundamentals, and ethical hacking protocols with real-world defensive labs.', 50);
