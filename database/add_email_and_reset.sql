-- Campuserve phase 1: email queue + password reset tokens.
-- Run once in phpMyAdmin: select the campuserve database -> SQL tab -> paste -> Go. Safe to run again.
CREATE TABLE IF NOT EXISTS password_resets (
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

CREATE TABLE IF NOT EXISTS email_queue (
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

INSERT IGNORE INTO settings (name, setting_value) VALUES ('email_notifications', '1');
SELECT 'email_queue and password_resets are ready' AS status;
