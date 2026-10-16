-- =====================================================================
--  INTERNSHIP TRACKING SYSTEM
--  MySQL / MariaDB schema and sample data
--  Import this file through phpMyAdmin or the MySQL command line:
--      mysql -u root < database.sql
--  WARNING: this script DROPS and recreates the application tables.
--           Import only into a development database.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS internship_tracking_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE internship_tracking_system;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS evaluation_scores;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS evaluation_criteria;
DROP TABLE IF EXISTS document_submissions;
DROP TABLE IF EXISTS requirements;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS internship_placements;
DROP TABLE IF EXISTS supervisors;
DROP TABLE IF EXISTS student_profiles;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users: one row per login account. Role decides the dashboard.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(80)  NOT NULL UNIQUE,
  full_name     VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  avatar        VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','supervisor','coordinator') NOT NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- password_resets: short-lived tokens for the forgot-password flow.
-- Only the SHA-256 hash of the token is stored.
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- student_profiles: student-specific information.
-- ---------------------------------------------------------------------
CREATE TABLE student_profiles (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        BIGINT UNSIGNED NOT NULL UNIQUE,
  student_number VARCHAR(50)  NOT NULL UNIQUE,
  course         VARCHAR(120) NOT NULL DEFAULT 'BS Information Technology',
  year_level     VARCHAR(30)  NOT NULL DEFAULT '4th Year',
  phone          VARCHAR(40)  NOT NULL DEFAULT '',
  address        VARCHAR(255) NOT NULL DEFAULT '',
  attendance_token CHAR(64) NULL UNIQUE,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_student_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- companies: partner organisations offering internship placements.
-- ---------------------------------------------------------------------
CREATE TABLE companies (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(180) NOT NULL,
  address       VARCHAR(255) NOT NULL DEFAULT '',
  contact_person VARCHAR(120) NOT NULL DEFAULT '',
  contact_email VARCHAR(190) NOT NULL DEFAULT '',
  contact_phone VARCHAR(40)  NOT NULL DEFAULT '',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_companies_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- supervisors: company-side users who review their interns.
-- ---------------------------------------------------------------------
CREATE TABLE supervisors (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL UNIQUE,
  company_id BIGINT UNSIGNED NULL,
  job_title  VARCHAR(120) NOT NULL DEFAULT '',
  CONSTRAINT fk_supervisors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_supervisors_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- internship_placements: assigns a student to a company and supervisor.
-- A student may have at most one active placement at a time.
-- ---------------------------------------------------------------------
CREATE TABLE internship_placements (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id     BIGINT UNSIGNED NOT NULL,
  company_id     BIGINT UNSIGNED NOT NULL,
  supervisor_id  BIGINT UNSIGNED NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NULL,
  required_hours DECIMAL(8,2) NOT NULL DEFAULT 486.00,
  status         ENUM('active','completed','inactive') NOT NULL DEFAULT 'active',
  archived       TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_placements_student_status (student_id, status),
  CONSTRAINT fk_placements_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_placements_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_placements_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors(id) ON DELETE SET NULL,
  CONSTRAINT chk_placement_required_hours CHECK (required_hours > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- requirements: documents a student must submit, each with a deadline.
-- ---------------------------------------------------------------------
CREATE TABLE requirements (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title              VARCHAR(180) NOT NULL,
  description        TEXT NULL,
  due_date           DATE NULL,
  allowed_extensions VARCHAR(120) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  max_file_bytes     INT UNSIGNED NOT NULL DEFAULT 5242880,
  active             TINYINT(1) NOT NULL DEFAULT 1,
  archived           TINYINT(1) NOT NULL DEFAULT 0,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_requirements_active (active, archived, due_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- document_submissions: uploaded files and their review status.
-- ---------------------------------------------------------------------
CREATE TABLE document_submissions (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requirement_id   BIGINT UNSIGNED NOT NULL,
  student_id       BIGINT UNSIGNED NOT NULL,
  stored_name      VARCHAR(255) NOT NULL,
  original_name    VARCHAR(255) NOT NULL,
  mime_type        VARCHAR(100) NOT NULL,
  file_size        INT UNSIGNED NOT NULL,
  status           ENUM('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  reviewer_id      BIGINT UNSIGNED NULL,
  reviewer_remarks VARCHAR(1000) NULL,
  submitted_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at      DATETIME NULL,
  CONSTRAINT fk_submissions_requirement FOREIGN KEY (requirement_id) REFERENCES requirements(id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_submissions_review (status, submitted_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attendance_records: time-in / time-out sessions and rendered hours.
-- Students never write here directly; the coordinator or the assigned
-- supervisor records attendance by scanning the student's QR code.
-- ---------------------------------------------------------------------
CREATE TABLE attendance_records (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  placement_id        BIGINT UNSIGNED NOT NULL,
  student_id          BIGINT UNSIGNED NOT NULL,
  time_in             DATETIME NOT NULL,
  time_out            DATETIME NULL,
  rendered_hours      DECIMAL(8,2) NOT NULL DEFAULT 0,
  status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  verified_by         BIGINT UNSIGNED NULL,
  verified_at         DATETIME NULL,
  correction_reason   VARCHAR(500) NULL,
  corrected_by        BIGINT UNSIGNED NULL,
  corrected_at        DATETIME NULL,
  flagged_reason      VARCHAR(255) NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attendance_student_date (student_id, time_in),
  INDEX idx_attendance_status (status),
  CONSTRAINT fk_attendance_placement FOREIGN KEY (placement_id) REFERENCES internship_placements(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_attendance_corrector FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_attendance_hours CHECK (rendered_hours >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- evaluation_criteria: configurable scoring categories and weights.
-- ---------------------------------------------------------------------
CREATE TABLE evaluation_criteria (
  id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(120) NOT NULL UNIQUE,
  weight DECIMAL(6,2) NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_criteria_weight CHECK (weight >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- evaluations / evaluation_scores: supervisor feedback on a placement.
-- ---------------------------------------------------------------------
CREATE TABLE evaluations (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  placement_id  BIGINT UNSIGNED NOT NULL,
  student_id    BIGINT UNSIGNED NOT NULL,
  supervisor_id BIGINT UNSIGNED NOT NULL,
  comments      TEXT NULL,
  released      TINYINT(1) NOT NULL DEFAULT 0,
  evaluated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evaluations_placement FOREIGN KEY (placement_id) REFERENCES internship_placements(id) ON DELETE CASCADE,
  CONSTRAINT fk_evaluations_student FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_evaluations_supervisor FOREIGN KEY (supervisor_id) REFERENCES supervisors(id) ON DELETE CASCADE,
  INDEX idx_evaluations_student (student_id, released)
) ENGINE=InnoDB;

CREATE TABLE evaluation_scores (
  evaluation_id BIGINT UNSIGNED NOT NULL,
  criteria_id   BIGINT UNSIGNED NOT NULL,
  rating        TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (evaluation_id, criteria_id),
  CONSTRAINT fk_scores_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
  CONSTRAINT fk_scores_criteria FOREIGN KEY (criteria_id) REFERENCES evaluation_criteria(id) ON DELETE CASCADE,
  CONSTRAINT chk_score_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- notifications: per-user in-app notices.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NOT NULL,
  title      VARCHAR(180) NOT NULL,
  body       VARCHAR(1000) NOT NULL DEFAULT '',
  read_at    DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user_read (user_id, read_at, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- announcements: coordinator broadcasts to a role audience.
-- ---------------------------------------------------------------------
CREATE TABLE announcements (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(180) NOT NULL,
  body       TEXT NOT NULL,
  audience   ENUM('all','student','supervisor','coordinator') NOT NULL DEFAULT 'all',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_announcements_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- audit_logs: security-relevant actions, including attendance corrections.
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    BIGINT UNSIGNED NULL,
  action     VARCHAR(120) NOT NULL,
  details    VARCHAR(1000) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- =====================================================================
--  SAMPLE DATA
--  All sample accounts use the password: Intern@123
-- =====================================================================

INSERT INTO users (id, username, full_name, email, password_hash, role) VALUES
(1, 'coordinator', 'Cindy Valencia',        'coordinator@example.test', '$2y$10$fBsWWJ2C7gb0kW9ZBrxd1eoOZUwPYNi/XG1wPcZNwKFWfOIUuUqhu', 'coordinator'),
(2, 'student01',   'Juan Dela Cruz',        'student01@example.test',   '$2y$10$fBsWWJ2C7gb0kW9ZBrxd1eoOZUwPYNi/XG1wPcZNwKFWfOIUuUqhu', 'student'),
(3, 'student02',   'Maria Santos',          'student02@example.test',   '$2y$10$fBsWWJ2C7gb0kW9ZBrxd1eoOZUwPYNi/XG1wPcZNwKFWfOIUuUqhu', 'student'),
(4, 'supervisor',  'Ana Reyes',             'supervisor@example.test',  '$2y$10$fBsWWJ2C7gb0kW9ZBrxd1eoOZUwPYNi/XG1wPcZNwKFWfOIUuUqhu', 'supervisor'),
(5, 'student03',   'Pedro Penduko',         'student03@example.test',   '$2y$10$fBsWWJ2C7gb0kW9ZBrxd1eoOZUwPYNi/XG1wPcZNwKFWfOIUuUqhu', 'student');

INSERT INTO student_profiles (id, user_id, student_number, course, year_level, phone, address) VALUES
(1, 2, 'STU-2026-001', 'BS Information Technology', '4th Year', '0917-000-0001', 'Cebu City'),
(2, 3, 'STU-2026-002', 'BS Information Technology', '4th Year', '0917-000-0002', 'Mandaue City'),
(3, 5, 'STU-2026-003', 'BS Information Technology', '4th Year', '0917-000-0003', 'Lapu-Lapu City');

INSERT INTO companies (id, name, address, contact_person, contact_email, contact_phone) VALUES
(1, 'Northstar Solutions', 'Cebu IT Park, Cebu City', 'Rafael Lim', 'hr@northstar.example.test', '032-000-0001'),
(2, 'Brightwave Digital',  'Banilad, Cebu City',      'Grace Tan',  'careers@brightwave.example.test', '032-000-0002');

INSERT INTO supervisors (id, user_id, company_id, job_title) VALUES
(1, 4, 1, 'Internship Supervisor');

INSERT INTO internship_placements
  (id, student_id, company_id, supervisor_id, start_date, end_date, required_hours, status) VALUES
(1, 1, 1, 1, DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 60 DAY), 486.00, 'active'),
(2, 2, 2, 1, DATE_SUB(CURRENT_DATE, INTERVAL 20 DAY), DATE_ADD(CURRENT_DATE, INTERVAL 70 DAY), 486.00, 'active');

INSERT INTO requirements (id, title, description, due_date, allowed_extensions) VALUES
(1, 'Signed internship agreement', 'Upload the signed memorandum of agreement between the school and the company.', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), 'pdf,jpg,jpeg,png'),
(2, 'Weekly journal', 'Submit the latest weekly internship journal entry.', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 'pdf,jpg,jpeg,png'),
(3, 'Final evaluation form', 'Company-completed final evaluation form.', DATE_ADD(CURRENT_DATE, INTERVAL 75 DAY), 'pdf,jpg,jpeg,png'),
(4, 'Updated resume / CV', 'Upload your most recent resume.', DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 'pdf');

INSERT INTO evaluation_criteria (id, name, weight) VALUES
(1, 'Punctuality', 1),
(2, 'Communication', 1),
(3, 'Teamwork', 1),
(4, 'Professionalism', 1),
(5, 'Task performance', 1);

INSERT INTO attendance_records
  (id, placement_id, student_id, time_in, time_out, rendered_hours, status, verified_by, verified_at) VALUES
(1, 1, 1, DATE_SUB(NOW(), INTERVAL 5 DAY) + INTERVAL 8 HOUR, DATE_SUB(NOW(), INTERVAL 5 DAY) + INTERVAL 16 HOUR, 8.00, 'approved', 1, NOW()),
(2, 1, 1, DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 9 HOUR, DATE_SUB(NOW(), INTERVAL 4 DAY) + INTERVAL 17 HOUR, 8.00, 'approved', 1, NOW()),
(3, 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 8 HOUR, DATE_SUB(NOW(), INTERVAL 3 DAY) + INTERVAL 15 HOUR, 7.00, 'pending', NULL, NULL),
(4, 2, 2, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 8 HOUR, DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 16 HOUR, 8.00, 'approved', 1, NOW());

INSERT INTO document_submissions
  (requirement_id, student_id, stored_name, original_name, mime_type, file_size, status) VALUES
(2, 1, 'sample-journal-placeholder.txt', 'weekly-journal-01.pdf', 'application/pdf', 1024, 'submitted');

INSERT INTO announcements (title, body, audience, created_by) VALUES
('Welcome to the Internship Tracking System',
'Log your attendance daily and keep your requirements updated. Contact the internship coordinator for any concerns.',
'all', 1);

INSERT INTO notifications (user_id, title, body) VALUES
(2, 'Placement assigned', 'You have been assigned to Northstar Solutions under Ana Reyes.'),
(3, 'Placement assigned', 'You have been assigned to Brightwave Digital under Ana Reyes.');
