-- Run in phpMyAdmin: select your database, open the SQL tab, paste, Go.
-- Turns st1001@..., 110201@... into firstname.lastname@campuserve.test (titles Dr./Prof. dropped).
-- Only the 30 demo accounts (ids 2-30) change. Accounts you registered yourself are not touched.
UPDATE users
SET email = CONCAT(
    LOWER(REPLACE(REPLACE(REPLACE(full_name, 'Dr. ', ''), 'Prof. ', ''), ' ', '.')),
    '@campuserve.test')
WHERE id BETWEEN 2 AND 30;

SELECT id, role, full_name, email FROM users WHERE id <= 30 ORDER BY id;
