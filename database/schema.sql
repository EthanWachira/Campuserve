-- CampuServe database schema (MySQL 8 / MariaDB 10.4+, as shipped with XAMPP)
-- Import order: schema.sql, then seed.sql

CREATE DATABASE IF NOT EXISTS campuserve CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campuserve;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log, notifications, appointments, lecturer_availability,
                     ml_predictions, ticket_updates, tickets, categories, users, departments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    code        VARCHAR(10)  NOT NULL UNIQUE,
    email       VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('student','lecturer','staff','admin') NOT NULL,
    identifier      VARCHAR(30)  NOT NULL UNIQUE COMMENT 'admission no. for students, staff no. otherwise',
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    department_id   INT UNSIGNED NULL COMMENT 'staff and lecturers only',
    programme       VARCHAR(100) NULL COMMENT 'students only',
    year_of_study   TINYINT UNSIGNED NULL,
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE categories (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL UNIQUE COMMENT 'must match the labels the ML model predicts',
    department_id  INT UNSIGNED NOT NULL COMMENT 'default routing target',
    description    VARCHAR(255) NULL,
    CONSTRAINT fk_cat_dept FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB;

CREATE TABLE tickets (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no           VARCHAR(20)  NOT NULL UNIQUE COMMENT 'e.g. CS-2026-000042',
    student_id             INT UNSIGNED NOT NULL,
    subject                VARCHAR(150) NOT NULL,
    description            TEXT NOT NULL,
    category_id            INT UNSIGNED NOT NULL,
    department_id          INT UNSIGNED NOT NULL,
    priority               ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    status                 ENUM('open','assigned','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    assigned_to            INT UNSIGNED NULL,
    classification_source  ENUM('ml','rules','manual') NOT NULL DEFAULT 'ml',
    needs_review           TINYINT(1) NOT NULL DEFAULT 0,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at            DATETIME NULL,
    CONSTRAINT fk_t_student  FOREIGN KEY (student_id)    REFERENCES users(id),
    CONSTRAINT fk_t_category FOREIGN KEY (category_id)   REFERENCES categories(id),
    CONSTRAINT fk_t_dept     FOREIGN KEY (department_id) REFERENCES departments(id),
    CONSTRAINT fk_t_assignee FOREIGN KEY (assigned_to)   REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_t_status (status),
    INDEX idx_t_student (student_id, created_at),
    INDEX idx_t_dept_status (department_id, status),
    INDEX idx_t_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE ticket_updates (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    message     TEXT NOT NULL,
    old_status  VARCHAR(20) NULL,
    new_status  VARCHAR(20) NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'internal notes are hidden from the student',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tu_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_tu_user   FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tu_ticket (ticket_id, created_at)
) ENGINE=InnoDB;

-- One row per automatic classification. Staff corrections fill final_category_id,
-- which gives the labelled feedback needed to retrain and to report model accuracy.
CREATE TABLE ml_predictions (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id             INT UNSIGNED NOT NULL,
    predicted_category_id INT UNSIGNED NOT NULL,
    confidence            DECIMAL(5,4) NOT NULL,
    model_version         VARCHAR(60) NOT NULL,
    final_category_id     INT UNSIGNED NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mp_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_pred   FOREIGN KEY (predicted_category_id) REFERENCES categories(id),
    CONSTRAINT fk_mp_final  FOREIGN KEY (final_category_id) REFERENCES categories(id),
    INDEX idx_mp_ticket (ticket_id)
) ENGINE=InnoDB;

-- Tables below back the appointment module (built in the next phase; seeded so dashboards have data).
CREATE TABLE lecturer_availability (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lecturer_id  INT UNSIGNED NOT NULL,
    day_of_week  TINYINT UNSIGNED NOT NULL COMMENT '1 = Monday ... 7 = Sunday',
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    CONSTRAINT fk_la_lect FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_la_lect (lecturer_id, day_of_week)
) ENGINE=InnoDB;

CREATE TABLE appointments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id   INT UNSIGNED NOT NULL,
    lecturer_id  INT UNSIGNED NOT NULL,
    starts_at    DATETIME NOT NULL,
    ends_at      DATETIME NOT NULL,
    purpose      VARCHAR(255) NOT NULL,
    status       ENUM('pending','confirmed','declined','cancelled','completed') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ap_student FOREIGN KEY (student_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_lect    FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ap_lect (lecturer_id, starts_at),
    INDEX idx_ap_student (student_id, starts_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(150) NOT NULL,
    body        VARCHAR(500) NULL,
    link        VARCHAR(255) NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_n_user (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(50) NOT NULL,
    detail      VARCHAR(255) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_action (action, created_at)
) ENGINE=InnoDB;
