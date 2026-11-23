-- CampuServe database schema (MySQL 8 / MariaDB 10.4+, as shipped with XAMPP)
-- Import order: schema.sql, then seed.sql

CREATE DATABASE IF NOT EXISTS campuserve CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campuserve;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ml_feedback_used, ml_models, email_queue, password_resets, settings, ml_run_results, ml_runs, audit_log, notifications, appointments, lecturer_availability,
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
    date_of_birth   DATE NULL,
    age             TINYINT UNSIGNED NULL COMMENT 'derived from date_of_birth by triggers and a daily event; never edit by hand',
    password_hash   VARCHAR(255) NOT NULL,
    department_id   INT UNSIGNED NULL COMMENT 'staff and lecturers only',
    programme       VARCHAR(100) NULL COMMENT 'students only',
    year_of_study   TINYINT UNSIGNED NULL,
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    email_notifications TINYINT(1) NOT NULL DEFAULT 1,
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
    sla_breached_at        DATETIME NULL COMMENT 'set once by the overdue job so staff are alerted only once',
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

-- Supporting files uploaded with a service request. Files are stored outside direct public access.
CREATE TABLE ticket_attachments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id     INT UNSIGNED NOT NULL,
    uploaded_by   INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name   VARCHAR(100) NOT NULL UNIQUE,
    mime_type     VARCHAR(120) NOT NULL,
    file_size     INT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ta_ticket (ticket_id, created_at)
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
    reminder_24h_at DATETIME NULL,
    reminder_1h_at  DATETIME NULL,
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

-- Age is derived from date_of_birth. The triggers recalculate it on every insert/update (so it is refreshed
-- whenever a user signs in); the daily event covers people who turn a year older without signing in.
-- The event only runs when the scheduler is on:  SET GLOBAL event_scheduler = ON;
DROP TRIGGER IF EXISTS users_age_insert;
DROP TRIGGER IF EXISTS users_age_update;
CREATE TRIGGER users_age_insert BEFORE INSERT ON users FOR EACH ROW
    SET NEW.age = IF(NEW.date_of_birth IS NULL, NULL, TIMESTAMPDIFF(YEAR, NEW.date_of_birth, CURDATE()));
CREATE TRIGGER users_age_update BEFORE UPDATE ON users FOR EACH ROW
    SET NEW.age = IF(NEW.date_of_birth IS NULL, NULL, TIMESTAMPDIFF(YEAR, NEW.date_of_birth, CURDATE()));
DROP EVENT IF EXISTS refresh_user_ages;
CREATE EVENT refresh_user_ages ON SCHEDULE EVERY 1 DAY STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 5 MINUTE)
    DO UPDATE users SET age = TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) WHERE date_of_birth IS NOT NULL;

-- Records of the model classifying the database (see add_ml_runs.sql)
CREATE TABLE ml_runs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_by          INT UNSIGNED NULL,
    model_version   VARCHAR(60) NOT NULL,
    records         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'requests classified in this run',
    matched         INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'model category equals the category stored on the request',
    needs_review    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'predictions below the confidence threshold',
    avg_confidence  DECIMAL(5,4) NULL,
    duration_ms     INT UNSIGNED NULL,
    started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_run_user FOREIGN KEY (run_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE ml_run_results (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id                 INT UNSIGNED NOT NULL,
    ticket_id              INT UNSIGNED NOT NULL,
    predicted_category_id  INT UNSIGNED NOT NULL,
    confidence             DECIMAL(5,4) NOT NULL,
    priority               VARCHAR(10) NULL,
    matches_current        TINYINT(1) NOT NULL,
    needs_review           TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_rr_run    FOREIGN KEY (run_id)    REFERENCES ml_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_rr_cat    FOREIGN KEY (predicted_category_id) REFERENCES categories(id),
    INDEX idx_rr_run (run_id)
) ENGINE=InnoDB;

-- Switches the administrator can change (automatic assignment on/off)
CREATE TABLE settings (
    name           VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value  VARCHAR(255) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO settings (name, setting_value) VALUES ('auto_assign', '1');

CREATE TABLE password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64) NOT NULL UNIQUE COMMENT 'sha256 of the emailed token; the token itself is never stored',
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pr_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE email_queue (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    to_email    VARCHAR(150) NOT NULL,
    to_name     VARCHAR(120) NULL,
    subject     VARCHAR(200) NOT NULL,
    body_html   MEDIUMTEXT NOT NULL,
    body_text   MEDIUMTEXT NOT NULL,
    status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at     DATETIME NULL,
    INDEX idx_eq_status (status, created_at),
    INDEX idx_eq_user (user_id)
) ENGINE=InnoDB;

INSERT INTO settings (name, setting_value) VALUES ('email_notifications', '1');
INSERT IGNORE INTO settings (name, setting_value) VALUES ('appointment_reminders','1'),('jobs_enabled','1'),('jobs_last_run','0'),('sla_high','8'),('sla_medium','24'),('sla_low','72');

-- Continuous learning: every retraining run, and which staff-labelled requests each model used.
CREATE TABLE IF NOT EXISTS ml_models (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version                  VARCHAR(80) NOT NULL,
    previous_version         VARCHAR(80) NULL,
    status                   ENUM('deployed','rejected') NOT NULL DEFAULT 'deployed',
    trigger_source           ENUM('manual','auto') NOT NULL DEFAULT 'manual',
    base_rows                INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'rows in the original dataset',
    trained_rows             INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'unique rows this model was trained on',
    prev_trained_rows        INT UNSIGNED NULL,
    feedback_rows            INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'staff-labelled requests available',
    feedback_train           INT UNSIGNED NOT NULL DEFAULT 0,
    feedback_test            INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'held out, never trained on',
    corrected_rows           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'staff changed the category',
    synthetic_accuracy       DECIMAL(5,4) NULL,
    prev_synthetic_accuracy  DECIMAL(5,4) NULL,
    real_accuracy            DECIMAL(5,4) NULL COMMENT 'on held-out staff-labelled requests',
    prev_real_accuracy       DECIMAL(5,4) NULL,
    macro_f1                 DECIMAL(5,4) NULL,
    categories_learned       TEXT NULL COMMENT 'JSON: category => training examples from staff',
    note                     VARCHAR(255) NULL,
    duration_ms              INT UNSIGNED NULL,
    trained_by               INT UNSIGNED NULL,
    trained_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mm_user FOREIGN KEY (trained_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mm_at (trained_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ml_feedback_used (
    ticket_id  INT UNSIGNED NOT NULL PRIMARY KEY,
    model_id   INT UNSIGNED NOT NULL,
    CONSTRAINT fk_fu_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_fu_model  FOREIGN KEY (model_id)  REFERENCES ml_models(id) ON DELETE CASCADE
) ENGINE=InnoDB;
