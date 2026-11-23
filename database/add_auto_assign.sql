-- CampuServe: settings table for automatic assignment of requests.
-- Run once in phpMyAdmin: click your database -> SQL tab -> paste -> Go. Safe to run again; deletes nothing.
CREATE TABLE IF NOT EXISTS settings (
    name           VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value  VARCHAR(255) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (name, setting_value) VALUES ('auto_assign', '1');

SELECT name, setting_value FROM settings;
