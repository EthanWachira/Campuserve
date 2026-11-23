-- CampuServe: records of the model classifying the database (supervisor step 4).
-- Run once in phpMyAdmin: click your database -> SQL tab -> paste -> Go. Safe to run again; deletes nothing.

CREATE TABLE IF NOT EXISTS ml_runs (
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

CREATE TABLE IF NOT EXISTS ml_run_results (
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

SELECT 'ml_runs and ml_run_results are ready' AS status;
