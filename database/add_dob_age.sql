-- CampuServe: date of birth + automatically maintained age
-- Run once in phpMyAdmin: click your database -> SQL tab -> paste -> Go.
-- Safe to run again. Does not delete anything.

-- 1. Columns. Date of birth is the real data; age is derived from it and kept up to date.
ALTER TABLE users ADD COLUMN IF NOT EXISTS date_of_birth DATE NULL AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS age TINYINT UNSIGNED NULL AFTER date_of_birth;

-- 2. Triggers: age is recalculated every time a row is inserted or updated
--    (a student registering, a user signing in, an admin editing a record, ...).
DROP TRIGGER IF EXISTS users_age_insert;
DROP TRIGGER IF EXISTS users_age_update;
CREATE TRIGGER users_age_insert BEFORE INSERT ON users FOR EACH ROW
    SET NEW.age = IF(NEW.date_of_birth IS NULL, NULL, TIMESTAMPDIFF(YEAR, NEW.date_of_birth, CURDATE()));
CREATE TRIGGER users_age_update BEFORE UPDATE ON users FOR EACH ROW
    SET NEW.age = IF(NEW.date_of_birth IS NULL, NULL, TIMESTAMPDIFF(YEAR, NEW.date_of_birth, CURDATE()));

-- 3. Daily refresh, so someone who turns a year older gets the new age even if they never sign in.
--    (Needs the MySQL event scheduler: see the note at the bottom of this file.)
DROP EVENT IF EXISTS refresh_user_ages;
CREATE EVENT refresh_user_ages ON SCHEDULE EVERY 1 DAY STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 5 MINUTE)
    DO UPDATE users SET age = TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) WHERE date_of_birth IS NOT NULL;

-- 4. Dates of birth for the 30 demo accounts (only where none is set yet).
--    Students: about 18-24. Lecturers and staff: about 31-53. Administrator: about 40.
UPDATE users SET date_of_birth = DATE_SUB(CURDATE(), INTERVAL (6700 + MOD(id * 173, 2000)) DAY)
 WHERE id <= 30 AND role = 'student' AND date_of_birth IS NULL;
UPDATE users SET date_of_birth = DATE_SUB(CURDATE(), INTERVAL (11500 + MOD(id * 331, 8000)) DAY)
 WHERE id <= 30 AND role IN ('staff', 'lecturer') AND date_of_birth IS NULL;
UPDATE users SET date_of_birth = DATE_SUB(CURDATE(), INTERVAL 14800 DAY)
 WHERE id <= 30 AND role = 'admin' AND date_of_birth IS NULL;

-- 5. Fill in age for every row that has a date of birth (also covers accounts you registered yourself).
UPDATE users SET age = TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) WHERE date_of_birth IS NOT NULL;

-- 6. Check it.
SELECT id, role, full_name, date_of_birth, age FROM users ORDER BY id;

-- NOTE: to make the daily event run, turn the scheduler on:
--   SET GLOBAL event_scheduler = ON;
-- This lasts until MySQL restarts. To keep it on permanently, open C:\xampp\mysql\bin\my.ini,
-- add the line   event_scheduler=ON   under the [mysqld] heading, save, and restart MySQL.
-- Even with the scheduler off, age is still refreshed whenever the account row is updated, for
-- example at every sign-in, because the triggers above run on every update.
