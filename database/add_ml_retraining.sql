-- CampuServe: continuous learning (retraining from staff feedback).
-- Run once in phpMyAdmin: click your database -> SQL tab -> paste -> Go. Safe to run again; deletes nothing.

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

INSERT IGNORE INTO settings (name, setting_value) VALUES ('ml_auto_retrain', '0'), ('ml_retrain_threshold', '10');

SELECT 'ml_models and ml_feedback_used are ready' AS status;
