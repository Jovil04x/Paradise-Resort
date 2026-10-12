-- Paradise Resort SQL schema + seed
-- Compatible with MySQL 8.x / MariaDB 10.4+

CREATE DATABASE IF NOT EXISTS `paradise_resort` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `paradise_resort`;

-- Tables
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price_per_night` DECIMAL(10,2) NOT NULL,
  `capacity` INT NOT NULL DEFAULT 2,
  `image_path` VARCHAR(255) NOT NULL,
  `amenities` TEXT NULL,
  `bed_type` VARCHAR(100) NULL,
  `size_sqm` INT NULL,
  `view` VARCHAR(100) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `check_in` DATE NOT NULL,
  `check_out` DATE NOT NULL,
  `guests` INT NOT NULL DEFAULT 1,
  `status` ENUM('pending','approved','rejected','cancelled','checked_in','completed','no_show') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_dates` CHECK (`check_out` > `check_in`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful index for availability checks
CREATE INDEX IF NOT EXISTS `idx_bookings_room_dates` ON `bookings` (`room_id`, `check_in`, `check_out`, `status`);

-- Payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(50) NOT NULL DEFAULT 'card',
  `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  `reference` VARCHAR(64) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blocked dates per room
CREATE TABLE IF NOT EXISTS `blocked_dates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `room_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_block_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Coupons
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(40) NOT NULL UNIQUE,
  `type` ENUM('percent','fixed') NOT NULL,
  `value` DECIMAL(10,2) NOT NULL,
  `valid_from` DATE NULL,
  `valid_to` DATE NULL,
  `max_uses` INT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seasonal price rules
CREATE TABLE IF NOT EXISTS `price_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `multiplier` DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  `label` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reviews
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `room_id` INT UNSIGNED NOT NULL,
  `booking_id` INT UNSIGNED NULL,
  `rating` TINYINT NOT NULL,
  `comment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity` VARCHAR(64) NULL,
  `entity_id` INT UNSIGNED NULL,
  `meta` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact messages (contact form)
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_contact_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact replies (admin)
CREATE TABLE IF NOT EXISTS `contact_replies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `message_id` INT UNSIGNED NOT NULL,
  `admin_user_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `reply` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_contact_reply_msg` FOREIGN KEY (`message_id`) REFERENCES `contact_messages`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contact_reply_admin` FOREIGN KEY (`admin_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contact_reply_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed rooms (images must exist under /juliusproject/room/)
INSERT INTO `rooms` (`name`, `description`, `price_per_night`, `capacity`, `image_path`) VALUES
('Ocean View Suite', 'Spacious suite with panoramic ocean views and modern interiors. Booking includes free resort access.', 199.00, 3, 'room/90135f04-673a-4420-827b-b156fb0f6348.jpeg'),
('Garden Villa', 'Private villa surrounded by lush gardens, perfect for a tranquil retreat. Free resort access included.', 249.00, 4, 'room/c7c6ffa3-ee66-4581-8b53-06ce537d659e.jpeg'),
('Deluxe King Room', 'Modern room with king bed, warm tones, and sleek finishes. Complimentary resort access.', 149.00, 2, 'room/4a578ea2-8214-4467-b812-aff3febbebc7.jpeg'),
('Family Suite', 'Comfortable suite ideal for families, featuring extra space and amenities. Free resort access included.', 279.00, 5, 'room/01798dce-c932-41c7-893c-25acc70546e8.jpeg');

-- Optional seed: coupons
INSERT INTO `coupons` (`code`,`type`,`value`,`valid_from`,`valid_to`,`max_uses`) VALUES
('WELCOME10','percent',10,'2025-01-01','2026-12-31',NULL),
('FLAT100','fixed',100.00,'2025-01-01','2026-12-31',500)
ON DUPLICATE KEY UPDATE `code`=VALUES(`code`);

-- Optional seed: seasonal price rules (example peak months)
INSERT INTO `price_rules` (`start_date`,`end_date`,`multiplier`,`label`) VALUES
('2025-12-15','2026-01-05',1.25,'Peak Holiday'),
('2025-04-01','2025-04-10',1.15,'Summer Kickoff')
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`);

-- Note: Admin user is auto-seeded by the app on first run if not present.
-- If you want to seed an admin here manually, run PHP to generate a password_hash for 'admin123'
-- and replace the placeholder HASH below.
-- Example (run once in PHP): echo password_hash('admin123', PASSWORD_DEFAULT);
-- INSERT INTO `users` (`full_name`,`email`,`password_hash`,`role`) VALUES ('Administrator','admin@paradise.local','REPLACE_WITH_PASSWORD_HASH','admin');

