-- =====================================================================
-- SmartQ demo dataset
-- Run on an EMPTY SmartQ schema (it wipes users/places/schedules/appointments).
-- All dates are relative to the day the script runs (CURDATE()), so the
-- demo never goes stale. Works on MariaDB 10.4+ and MySQL 8.
--
-- Demo credentials
--   admin@smartq.com              Admin@123     (admin)
--   khaled|mona|yousef|rania|tamer|huda|samer @smartq.com   Manager@123 (managers)
--   user@smartq.com, sara@, ahmad@, lina@, omar@ smartq.com  User@123    (customers)
-- Place photos live in web/uploads/places/demo_*.jpg (see CREDITS.md there).
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DELETE FROM appointments;
DELETE FROM schedules;
DELETE FROM places;
DELETE FROM users;

ALTER TABLE appointments AUTO_INCREMENT = 1;
ALTER TABLE schedules AUTO_INCREMENT = 1;
ALTER TABLE places AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------
INSERT INTO users (id, full_name, email, password, phone, birth_date, profile_image, role, created_at, updated_at) VALUES
-- admin (Admin@123)
(1,  'SmartQ Admin',      'admin@smartq.com',  '$2y$10$Fhs.D8N0mV8RzTslC.bnpuTVVYwbaMw4HiiIBTfPil8GqKHENC0qK', '0599000000', '1988-01-15', NULL, 'admin',   NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY),
-- managers (Manager@123)
(2,  'Khaled Al-Masri',   'khaled@smartq.com', '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123401', '1979-03-22', NULL, 'manager', NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 80 DAY),
(3,  'Mona Haddad',       'mona@smartq.com',   '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123402', '1985-07-09', NULL, 'manager', NOW() - INTERVAL 78 DAY, NOW() - INTERVAL 78 DAY),
(4,  'Yousef Abu Shaban', 'yousef@smartq.com', '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123403', '1982-11-30', NULL, 'manager', NOW() - INTERVAL 75 DAY, NOW() - INTERVAL 75 DAY),
(5,  'Rania Khalil',      'rania@smartq.com',  '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123404', '1990-05-14', NULL, 'manager', NOW() - INTERVAL 72 DAY, NOW() - INTERVAL 72 DAY),
(6,  'Tamer Al-Najjar',   'tamer@smartq.com',  '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123405', '1987-02-03', NULL, 'manager', NOW() - INTERVAL 70 DAY, NOW() - INTERVAL 70 DAY),
(7,  'Huda Shaheen',      'huda@smartq.com',   '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123406', '1984-09-18', NULL, 'manager', NOW() - INTERVAL 68 DAY, NOW() - INTERVAL 68 DAY),
(8,  'Samer Odeh',        'samer@smartq.com',  '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599123407', '1981-12-07', NULL, 'manager', NOW() - INTERVAL 66 DAY, NOW() - INTERVAL 66 DAY),
-- customers (User@123)
(9,  'Mohammed Saleh',    'user@smartq.com',   '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0599222222', '1998-03-03', NULL, 'user',    NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY),
(10, 'Sara Abu Ali',      'sara@smartq.com',   '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0597345611', '2000-08-21', NULL, 'user',    NOW() - INTERVAL 55 DAY, NOW() - INTERVAL 55 DAY),
(11, 'Ahmad Hamdan',      'ahmad@smartq.com',  '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0592876543', '1995-01-12', NULL, 'user',    NOW() - INTERVAL 50 DAY, NOW() - INTERVAL 50 DAY),
(12, 'Lina Darwish',      'lina@smartq.com',   '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0569112233', '1993-06-27', NULL, 'user',    NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY),
(13, 'Omar Barakat',      'omar@smartq.com',   '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0598554477', '1991-10-05', NULL, 'user',    NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 40 DAY),
-- extra demo accounts (customers: User@123, manager: Manager@123)
(14, 'Noor Haddad',       'noor@smartq.com',       '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0599000014', '1999-04-04', NULL, 'user',    '2026-04-28 15:13:27', '2026-04-28 15:13:27'),
(15, 'Rami Odeh',         'rami@smartq.com',       '$2y$10$FZiVN9cxSmX7ts7YGORgl.jVsRTSjCu3UpdUJNn1QN/VJxWbhjrli', '0599000015', '1986-05-12', NULL, 'manager', '2026-05-02 07:50:24', '2026-05-02 07:50:24'),
(16, 'Dana Salama',       'dana@smartq.com',       '$2y$10$/jdMr80H8CgEWyUHq.Ke5udrFxHB2R.VcFT2XceixxZDEPJ2UIYPa', '0599000016', NULL,         NULL, 'user',    '2026-08-29 10:39:23', '2026-08-29 10:39:23');

-- ---------------------------------------------------------------------
-- Places (place_image = bare file name in web/uploads/places/)
-- ---------------------------------------------------------------------
INSERT INTO places (id, manager_id, place_name, category, city, address, description, place_image, active, created_at, updated_at) VALUES
(1,  2, 'Al Noor Family Clinic',              'Clinic',            'Gaza',          'Omar Al-Mukhtar St, Al-Rimal',          'Family medicine clinic for general check-ups, chronic disease follow-up and pediatric consultations. Book a time slot and skip the crowded waiting room.', 'demo_al_noor_family_clinic.jpg',   1, NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 80 DAY),
(2,  3, 'Bright Smile Dental Center',         'Dental Clinic',     'Khan Younis',   'Jamal Abdel Nasser St, near the Municipality', 'Modern dental center for check-ups, cleaning, fillings and orthodontic consultations, with fully sterilized equipment and a friendly team.', 'demo_bright_smile_dental.jpg',     1, NOW() - INTERVAL 78 DAY, NOW() - INTERVAL 78 DAY),
(3,  3, 'Pearl Dental Care',                  'Dental Clinic',     'Ramallah',      'Al-Irsal St, Al-Masyoun',               'Cosmetic and general dentistry including whitening, crowns and implant consultations in a calm, modern clinic.', 'demo_pearl_dental_care.jpg',       1, NOW() - INTERVAL 77 DAY, NOW() - INTERVAL 77 DAY),
(4,  2, 'Al Rahma General Hospital',          'Hospital',          'Deir al-Balah', 'Salah Al-Din Rd, Deir al-Balah',        'Outpatient department covering internal medicine, pediatrics and gynecology. Reserve your outpatient turn in advance.', 'demo_al_rahma_hospital.jpg',       1, NOW() - INTERVAL 79 DAY, NOW() - INTERVAL 79 DAY),
(5,  4, 'Palestine Trust Bank – Gaza Branch', 'Bank',              'Gaza',          'Al-Wehda St, Al-Rimal',                 'Personal banking services: account opening, transfers, cards and loan inquiries. Book a counter appointment instead of standing in line.', 'demo_palestine_trust_bank.jpg',    1, NOW() - INTERVAL 75 DAY, NOW() - INTERVAL 75 DAY),
(6,  5, 'Lumière Beauty Lounge',              'Beauty Center',     'Gaza',          'Al-Nasr St, Al-Nasr',                   'Skin care, facials, hair styling and bridal makeup in a relaxing, women-only lounge.', 'demo_lumiere_beauty_lounge.jpg',   1, NOW() - INTERVAL 72 DAY, NOW() - INTERVAL 72 DAY),
(7,  5, 'Classic Cuts Barbershop',            'Barbershop',        'Nuseirat',      'Main Market St, Nuseirat',              'Haircuts, beard trims and hot-towel shaves by experienced barbers. Reserve your chair and avoid the wait.', 'demo_classic_cuts_barbershop.jpg', 1, NOW() - INTERVAL 71 DAY, NOW() - INTERVAL 71 DAY),
(8,  6, 'Iron Peak Gym',                      'Gym',               'Ramallah',      'Al-Tireh St, Al-Tireh',                 'Fully equipped fitness center with cardio, strength and personal training areas. Book a 60-minute training session.', 'demo_iron_peak_gym.jpg',           1, NOW() - INTERVAL 70 DAY, NOW() - INTERVAL 70 DAY),
(9,  8, 'Civil Registry Services Office',     'Government Office', 'Rafah',         'Taha Hussein St, Rafah City Center',    'Issuing and renewing ID cards, birth certificates and family records. Book a service turn to reduce waiting time at the counters.', 'demo_civil_registry_office.jpg',   1, NOW() - INTERVAL 66 DAY, NOW() - INTERVAL 66 DAY),
(10, 7, 'Green Cross Pharmacy',               'Pharmacy',          'Nablus',        'Rafidia St, Rafidia',                   'Community pharmacy offering prescription preparation, medication counseling and blood pressure checks.', 'demo_green_cross_pharmacy.jpg',    1, NOW() - INTERVAL 68 DAY, NOW() - INTERVAL 68 DAY),
(11, 7, 'Al Hayat Pharmacy',                  'Pharmacy',          'Deir al-Balah', 'Al-Bahr St, Deir al-Balah',             'Neighborhood pharmacy for prescriptions, chronic medication refills and private pharmacist consultations.', 'demo_al_hayat_pharmacy.jpg',       1, NOW() - INTERVAL 67 DAY, NOW() - INTERVAL 67 DAY),
(12, 7, 'CareLab Diagnostics',                'Laboratory',        'Hebron',        'Ein Sarah St',                          'Medical laboratory for blood tests, hormone panels and routine check-up packages with fast, accurate results.', 'demo_carelab_diagnostics.jpg',     1, NOW() - INTERVAL 67 DAY, NOW() - INTERVAL 67 DAY),
(13, 8, 'BioCheck Medical Laboratory',        'Laboratory',        'Khan Younis',   'Al-Tahrir St, Khan Younis',             'Clinical laboratory offering CBC, blood sugar, lipid profile and vitamin tests. Book a sampling time and get most results the same day.', 'demo_biocheck_laboratory.jpg',     1, NOW() - INTERVAL 65 DAY, NOW() - INTERVAL 65 DAY),
(14, 2, 'Vision Plus Eye Clinic',             'Clinic',            'Nablus',        'Sufian St, Downtown',                   'Eye examinations, vision tests and glasses prescriptions by certified ophthalmologists.', 'demo_vision_plus_eye_clinic.jpg',  1, NOW() - INTERVAL 76 DAY, NOW() - INTERVAL 76 DAY);

-- ---------------------------------------------------------------------
-- Schedules: every place, every day from 7 days ago to 13 days ahead
-- (past days feed the dashboards/charts, the next 14 days are bookable).
-- capacity = number of slots between start_time and end_time.
-- ---------------------------------------------------------------------
INSERT INTO schedules (place_id, schedule_date, start_time, end_time, capacity, slot_duration, status, created_at, updated_at)
SELECT h.place_id,
       DATE_ADD(CURDATE(), INTERVAL d.n DAY),
       h.start_time, h.end_time, h.capacity, h.slot_duration, 'open',
       TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:00:00'),
       TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '09:00:00')
FROM (
          SELECT 1 AS place_id, '09:00:00' AS start_time, '14:00:00' AS end_time, 20 AS slot_duration, 15 AS capacity
UNION ALL SELECT 1,  '16:00:00', '20:00:00', 20, 12
UNION ALL SELECT 2,  '10:00:00', '14:00:00', 30, 8
UNION ALL SELECT 2,  '16:00:00', '20:00:00', 30, 8
UNION ALL SELECT 3,  '09:00:00', '15:00:00', 30, 12
UNION ALL SELECT 4,  '08:00:00', '14:00:00', 15, 24
UNION ALL SELECT 5,  '08:00:00', '14:00:00', 15, 24
UNION ALL SELECT 6,  '10:00:00', '19:00:00', 60, 9
UNION ALL SELECT 7,  '10:00:00', '21:00:00', 30, 22
UNION ALL SELECT 8,  '06:00:00', '22:00:00', 60, 16
UNION ALL SELECT 9,  '08:00:00', '14:00:00', 20, 18
UNION ALL SELECT 10, '09:00:00', '21:00:00', 30, 24
UNION ALL SELECT 11, '08:00:00', '16:00:00', 20, 24
UNION ALL SELECT 12, '07:30:00', '13:30:00', 15, 24
UNION ALL SELECT 13, '08:00:00', '14:00:00', 20, 18
UNION ALL SELECT 14, '09:00:00', '14:00:00', 30, 10
UNION ALL SELECT 14, '16:00:00', '19:00:00', 30, 6
) h
CROSS JOIN (
          SELECT -7 AS n UNION ALL SELECT -6 UNION ALL SELECT -5 UNION ALL SELECT -4
UNION ALL SELECT -3 UNION ALL SELECT -2 UNION ALL SELECT -1 UNION ALL SELECT 0
UNION ALL SELECT 1  UNION ALL SELECT 2  UNION ALL SELECT 3  UNION ALL SELECT 4
UNION ALL SELECT 5  UNION ALL SELECT 6  UNION ALL SELECT 7  UNION ALL SELECT 8
UNION ALL SELECT 9  UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
UNION ALL SELECT 13
) d
ORDER BY d.n, h.place_id, h.start_time;

-- ---------------------------------------------------------------------
-- Appointments
-- Each row: place, day offset, schedule session start, slot time, customer,
-- status, queue number (1..n per schedule), note. Customer name/phone/email
-- are copied from the user row. Every slot time is a real slot of its schedule.
-- ---------------------------------------------------------------------
INSERT INTO appointments (user_id, schedule_id, customer_name, customer_phone, customer_email, customer_age, queue_number, notes, status, appointment_time, created_at, updated_at)
SELECT u.id, s.id, u.full_name, u.phone, u.email,
       TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()),
       b.q, b.notes, b.st, b.atime,
       LEAST(NOW(), TIMESTAMP(DATE_SUB(s.schedule_date, INTERVAL 2 DAY), '12:30:00')),
       LEAST(NOW(), TIMESTAMP(DATE_SUB(s.schedule_date, INTERVAL 2 DAY), '12:30:00'))
FROM (
-- ===== today =====
          SELECT 1 AS pid, 0 AS dn, '09:00:00' AS sst, '09:40:00' AS atime, 10 AS uid, 'confirmed' AS st, 1 AS q, 'Follow-up for blood pressure' AS notes
UNION ALL SELECT 1,  0, '09:00:00', '10:20:00', 11, 'waiting',   2, NULL
UNION ALL SELECT 1,  0, '09:00:00', '11:00:00',  9, 'waiting',   3, 'General check-up'
UNION ALL SELECT 5,  0, '08:00:00', '09:15:00', 12, 'confirmed', 1, 'Open a savings account'
UNION ALL SELECT 5,  0, '08:00:00', '10:30:00', 13, 'waiting',   2, 'Card replacement'
UNION ALL SELECT 7,  0, '10:00:00', '17:00:00', 13, 'confirmed', 1, 'Haircut and beard trim'
UNION ALL SELECT 7,  0, '10:00:00', '18:30:00', 11, 'waiting',   2, NULL
UNION ALL SELECT 12, 0, '07:30:00', '08:00:00', 10, 'confirmed', 1, 'Fasting blood test'
UNION ALL SELECT 12, 0, '07:30:00', '08:15:00', 12, 'cancelled', 2, NULL
UNION ALL SELECT 12, 0, '07:30:00', '08:45:00',  9, 'waiting',   3, 'Vitamin D test'
-- ===== tomorrow =====
UNION ALL SELECT 2,  1, '10:00:00', '10:30:00',  9, 'confirmed', 1, 'Teeth cleaning'
UNION ALL SELECT 2,  1, '10:00:00', '11:30:00', 12, 'waiting',   2, NULL
UNION ALL SELECT 6,  1, '10:00:00', '12:00:00', 10, 'confirmed', 1, 'Facial treatment'
UNION ALL SELECT 6,  1, '10:00:00', '15:00:00', 12, 'waiting',   2, 'Hair styling'
UNION ALL SELECT 9,  1, '08:00:00', '08:40:00', 11, 'waiting',   1, 'ID card renewal'
UNION ALL SELECT 9,  1, '08:00:00', '09:20:00', 13, 'waiting',   2, 'Birth certificate'
UNION ALL SELECT 9,  1, '08:00:00', '10:00:00', 10, 'cancelled', 3, NULL
UNION ALL SELECT 8,  1, '06:00:00', '18:00:00', 13, 'confirmed', 1, 'Personal training session'
-- ===== in 2-3 days =====
UNION ALL SELECT 14, 2, '16:00:00', '16:30:00', 11, 'waiting',   1, 'Vision test'
UNION ALL SELECT 14, 2, '16:00:00', '17:00:00',  9, 'waiting',   2, NULL
UNION ALL SELECT 4,  2, '08:00:00', '08:30:00', 12, 'confirmed', 1, 'Internal medicine clinic'
UNION ALL SELECT 4,  2, '08:00:00', '09:45:00', 13, 'waiting',   2, 'Pediatrics - son, 6 years'
UNION ALL SELECT 10, 3, '09:00:00', '11:00:00', 10, 'waiting',   1, 'Medication counseling'
UNION ALL SELECT 13, 3, '08:00:00', '08:20:00',  9, 'waiting',   1, 'Lipid profile'
UNION ALL SELECT 3,  3, '09:00:00', '13:30:00', 11, 'waiting',   1, 'Whitening consultation'
-- ===== past week (done / missed) =====
UNION ALL SELECT 1, -1, '09:00:00', '09:00:00', 11, 'done',      1, NULL
UNION ALL SELECT 1, -1, '09:00:00', '09:20:00', 10, 'done',      2, NULL
UNION ALL SELECT 1, -1, '09:00:00', '10:00:00', 13, 'missed',    3, NULL
UNION ALL SELECT 5, -1, '08:00:00', '08:30:00',  9, 'done',      1, 'Money transfer'
UNION ALL SELECT 5, -1, '08:00:00', '09:00:00', 12, 'done',      2, NULL
UNION ALL SELECT 7, -2, '10:00:00', '11:00:00', 11, 'done',      1, NULL
UNION ALL SELECT 7, -2, '10:00:00', '12:30:00', 13, 'done',      2, NULL
UNION ALL SELECT 12,-2, '07:30:00', '07:45:00', 10, 'done',      1, NULL
UNION ALL SELECT 12,-2, '07:30:00', '08:00:00',  9, 'missed',    2, NULL
UNION ALL SELECT 2, -3, '10:00:00', '10:00:00', 12, 'done',      1, NULL
UNION ALL SELECT 2, -3, '10:00:00', '11:00:00',  9, 'done',      2, NULL
UNION ALL SELECT 6, -3, '10:00:00', '13:00:00', 10, 'done',      1, NULL
UNION ALL SELECT 9, -4, '08:00:00', '08:00:00', 13, 'done',      1, NULL
UNION ALL SELECT 9, -4, '08:00:00', '08:20:00', 11, 'done',      2, NULL
UNION ALL SELECT 9, -4, '08:00:00', '09:00:00', 12, 'cancelled', 3, NULL
UNION ALL SELECT 4, -5, '08:00:00', '08:15:00',  9, 'done',      1, NULL
UNION ALL SELECT 4, -5, '08:00:00', '09:00:00', 10, 'missed',    2, NULL
UNION ALL SELECT 8, -5, '06:00:00', '07:00:00', 11, 'done',      1, NULL
UNION ALL SELECT 14,-6, '09:00:00', '10:00:00', 12, 'done',      1, NULL
UNION ALL SELECT 10,-6, '09:00:00', '10:00:00', 13, 'done',      1, NULL
UNION ALL SELECT 1, -7, '16:00:00', '16:20:00',  9, 'done',      1, NULL
UNION ALL SELECT 1, -7, '16:00:00', '17:00:00', 10, 'done',      2, NULL
UNION ALL SELECT 13,-7, '08:00:00', '08:40:00', 12, 'done',      1, NULL
UNION ALL SELECT 11,-7, '08:00:00', '09:00:00', 11, 'done',      1, NULL
) b
JOIN schedules s ON s.place_id = b.pid
                AND s.schedule_date = DATE_ADD(CURDATE(), INTERVAL b.dn DAY)
                AND s.start_time = b.sst
JOIN users u ON u.id = b.uid
ORDER BY s.schedule_date, s.id, b.q;

SET FOREIGN_KEY_CHECKS=1;
