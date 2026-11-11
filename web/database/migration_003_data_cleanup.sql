-- SmartQ migration 003: data cleanup found during the audit. Safe to run more than once.
-- Portable (MariaDB 10.4+ / MySQL 8). Run after migration 002.

-- 1) Empty birth dates were saved as '' -> 0000-00-00 (non-strict MySQL) or failed (strict MySQL 8).
--    The code now stores NULL; clean old rows.
UPDATE `users` SET `birth_date` = NULL WHERE CAST(`birth_date` AS CHAR) = '0000-00-00';
