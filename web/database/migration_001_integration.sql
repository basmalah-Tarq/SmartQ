-- SmartQ migration 001: integration fixes between the website, the mobile API and the database.
-- Safe to run on an existing smartq database (keeps all data). Run once.

-- 1) Time-slot length per schedule (the website already assumed this column; it did not exist).
ALTER TABLE `schedules`
  ADD COLUMN IF NOT EXISTS `slot_duration` INT NOT NULL DEFAULT 30 AFTER `capacity`;

-- 2) Exact booked time (the app never stored it; the website did).
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `appointment_time` TIME NULL DEFAULT NULL AFTER `queue_number`;

-- 3) 'missed' status is used by the admin panel but was missing from the column definition.
ALTER TABLE `appointments`
  MODIFY `status` ENUM('waiting','confirmed','cancelled','done','missed') DEFAULT 'waiting';

-- 4) Mobile login tokens now expire.
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `token_expires_at` DATETIME NULL DEFAULT NULL AFTER `api_token`;

-- 5) Allow re-booking after a cancellation, while still blocking real double bookings.
--    Old rule: UNIQUE(user_id, schedule_id) even for cancelled bookings -> users could never rebook.
--    New rule: only ACTIVE bookings are unique, enforced by the database (safe under concurrency).
ALTER TABLE `appointments` ADD INDEX IF NOT EXISTS `idx_user_schedule` (`user_id`, `schedule_id`);
ALTER TABLE `appointments` DROP INDEX IF EXISTS `unique_user_schedule`;

ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `active_user_lock` INT
    AS (IF(`status` IN ('cancelled','missed'), NULL, `user_id`)) PERSISTENT,
  ADD COLUMN IF NOT EXISTS `active_slot_lock` TIME
    AS (IF(`status` IN ('cancelled','missed'), NULL, `appointment_time`)) PERSISTENT;

ALTER TABLE `appointments`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_active_user_per_schedule` (`schedule_id`, `active_user_lock`),
  ADD UNIQUE INDEX IF NOT EXISTS `uq_active_slot_per_schedule` (`schedule_id`, `active_slot_lock`);

-- 6) Prevent accidental duplicate schedules for the same place/date/time.
ALTER TABLE `schedules` ADD INDEX IF NOT EXISTS `idx_place_date` (`place_id`, `schedule_date`);
