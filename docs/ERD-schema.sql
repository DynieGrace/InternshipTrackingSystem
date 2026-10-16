-- =====================================================================
--  INTERNSHIP TRACKING SYSTEM - ERD schema (for MySQL Workbench)
--  Paste this in MySQL Workbench, run it, then use:
--      Database -> Reverse Engineer...
--  to generate the ERD automatically.
--  Creates a dedicated database so it does not touch your existing data.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS internship_tracking_system_erd
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE internship_tracking_system_erd;

-- ---------------------------------------------------------------------
-- USERS: login accounts (student / coordinator / supervisor)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(80)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','coordinator','supervisor') NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- STUDENTS: student intern profile
-- ---------------------------------------------------------------------
CREATE TABLE students (
  student_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NOT NULL UNIQUE,
  student_number     VARCHAR(50)  NOT NULL UNIQUE,
  full_name          VARCHAR(120) NOT NULL,
  course_year_section VARCHAR(80) NOT NULL,
  contact_number     VARCHAR(40),
  CONSTRAINT fk_students_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- COORDINATORS: internship coordinator profile
-- ---------------------------------------------------------------------
CREATE TABLE coordinators (
  coordinator_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL UNIQUE,
  name           VARCHAR(120) NOT NULL,
  department     VARCHAR(120),
  CONSTRAINT fk_coordinators_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- COMPANIES: partner organisations
-- ---------------------------------------------------------------------
CREATE TABLE companies (
  company_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name   VARCHAR(180) NOT NULL UNIQUE,
  address        VARCHAR(255),
  contact_person VARCHAR(120),
  contact_email  VARCHAR(190)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SUPERVISORS: company / internship supervisor
-- ---------------------------------------------------------------------
CREATE TABLE supervisors (
  supervisor_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL UNIQUE,
  company_id     INT UNSIGNED NOT NULL,
  full_name      VARCHAR(120) NOT NULL,
  email          VARCHAR(190),
  contact_number VARCHAR(40),
  CONSTRAINT fk_supervisors_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_supervisors_company
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- INTERNSHIPS (placements): student assigned to company + supervisor
-- ---------------------------------------------------------------------
CREATE TABLE internships (
  internship_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id    INT UNSIGNED NOT NULL,
  company_id    INT UNSIGNED NOT NULL,
  supervisor_id INT UNSIGNED NULL,
  start_date    DATE,
  end_date      DATE,
  status        ENUM('active','completed','inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_internships_student
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  CONSTRAINT fk_internships_company
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
  CONSTRAINT fk_internships_supervisor
    FOREIGN KEY (supervisor_id) REFERENCES supervisors(supervisor_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REQUIREMENTS: documents a student must submit
-- ---------------------------------------------------------------------
CREATE TABLE requirements (
  requirement_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requirement_name VARCHAR(180) NOT NULL,
  description      VARCHAR(500),
  deadline         DATE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REQUIREMENT_SUBMISSIONS: uploaded files + review status
-- ---------------------------------------------------------------------
CREATE TABLE requirement_submissions (
  submission_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requirement_id INT UNSIGNED NOT NULL,
  student_id     INT UNSIGNED NOT NULL,
  document_path  VARCHAR(255),
  submitted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status         ENUM('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
  reviewed_by    INT UNSIGNED NULL,
  CONSTRAINT fk_submissions_requirement
    FOREIGN KEY (requirement_id) REFERENCES requirements(requirement_id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_student
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  CONSTRAINT fk_submissions_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ATTENDANCE: time in/out and rendered hours
-- ---------------------------------------------------------------------
CREATE TABLE attendance (
  attendance_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  internship_id      INT UNSIGNED NOT NULL,
  attendance_date    DATE,
  time_in            TIME,
  time_out           TIME,
  hours_rendered     DECIMAL(5,2),
  qr_scan_reference  VARCHAR(255),
  CONSTRAINT fk_attendance_internship
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- EVALUATIONS: supervisor feedback
-- ---------------------------------------------------------------------
CREATE TABLE evaluations (
  evaluation_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  internship_id  INT UNSIGNED NOT NULL,
  supervisor_id  INT UNSIGNED NOT NULL,
  rating         TINYINT UNSIGNED,
  feedback       VARCHAR(500),
  evaluation_date DATE,
  CONSTRAINT fk_evaluations_internship
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE,
  CONSTRAINT fk_evaluations_supervisor
    FOREIGN KEY (supervisor_id) REFERENCES supervisors(supervisor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTIFICATIONS: in-app notices
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  message         VARCHAR(500),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_status     TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
