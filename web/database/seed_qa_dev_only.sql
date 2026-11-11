-- =====================================================================
-- QA test accounts - LOCAL DEV DATABASE ONLY (smartq_dev).
-- NOT part of the deploy SQL. Run AFTER seed_demo.sql.
--   qa1@smartq.test        / qa123456   (user)
--   qa.manager@smartq.test / test12345  (manager, owns "Pearl Dental Care")
--   qa.admin@smartq.test   / test12345  (admin)
-- =====================================================================
SET NAMES utf8mb4;

DELETE FROM users WHERE email IN ('qa1@smartq.test', 'qa.manager@smartq.test', 'qa.admin@smartq.test');

INSERT INTO users (full_name, email, password, phone, birth_date, profile_image, role) VALUES
('QA Tester',   'qa1@smartq.test',        '$2y$10$M1ExPQB4cMvX7iXSI/akxePjrsvl2DXS.q7BKG/1sP2NUTfIdkPum', '0599000111', '1999-01-01', NULL, 'user'),
('QA Manager',  'qa.manager@smartq.test', '$2y$10$Mm0pfsUHMPlKe3sp4xIfw.lnx5EWaDlJctD4X4n7.fbhsfzTLQSJS', '0599000102', '1990-01-01', NULL, 'manager'),
('QA Admin',    'qa.admin@smartq.test',   '$2y$10$Mm0pfsUHMPlKe3sp4xIfw.lnx5EWaDlJctD4X4n7.fbhsfzTLQSJS', '0599000103', '1988-01-01', NULL, 'admin');

-- Give the QA manager one demo place.
UPDATE places
SET manager_id = (SELECT id FROM users WHERE email = 'qa.manager@smartq.test')
WHERE place_name = 'Pearl Dental Care';
