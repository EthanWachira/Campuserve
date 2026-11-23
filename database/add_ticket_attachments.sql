-- Run once on an existing CampuServe database to enable request attachments.
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL UNIQUE,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ta_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ta_ticket (ticket_id, created_at)
) ENGINE=InnoDB;
