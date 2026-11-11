-- SmartQ migration 002: passwordless email login (one-time codes), Google sign-in, booking emails & reminders.
-- Portable (MariaDB 10.4+ / MySQL 8). Run once after migration 001.

CREATE TABLE IF NOT EXISTS `email_otps` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(120) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `purpose` VARCHAR(20) NOT NULL DEFAULT 'login',
  `attempts` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_email_created` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `app_meta` (
  `meta_key` VARCHAR(64) NOT NULL,
  `meta_value` VARCHAR(255) NULL,
  PRIMARY KEY (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `users` ADD COLUMN `auth_provider` VARCHAR(20) NOT NULL DEFAULT 'password';
ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(64) NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `users` ADD UNIQUE INDEX `uq_google_id` (`google_id`);

ALTER TABLE `appointments` ADD COLUMN `confirmation_sent_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `appointments` ADD COLUMN `reminder_sent_at` DATETIME NULL DEFAULT NULL;
