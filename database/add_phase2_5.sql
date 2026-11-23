-- Campuserve phases 2-5: SLA tracking, appointment reminders, per-user email preference, system settings.
-- Run once in phpMyAdmin (database campuserve -> SQL tab -> paste -> Go). Safe to run again; deletes nothing.
-- (Run add_email_and_reset.sql first if you have not already.)
ALTER TABLE tickets      ADD COLUMN IF NOT EXISTS sla_breached_at DATETIME NULL AFTER resolved_at;
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reminder_24h_at DATETIME NULL;
ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reminder_1h_at  DATETIME NULL;
ALTER TABLE users        ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) NOT NULL DEFAULT 1;

INSERT IGNORE INTO settings (name, setting_value) VALUES
  ('email_notifications', '1'), ('appointment_reminders', '1'), ('jobs_enabled', '1'), ('jobs_last_run', '0'),
  ('sla_high', '8'), ('sla_medium', '24'), ('sla_low', '72');
SELECT 'phase 2-5 columns and settings are ready' AS status;
