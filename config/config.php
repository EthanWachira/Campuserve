<?php
/**
 * Campuserve configuration. Defaults match a stock XAMPP install
 * (MySQL user "root" with an empty password). Override with environment
 * variables or edit the values below.
 */
return [
    'app_name' => 'Campuserve',
    'demo_mode' => true,                 // shows one-click demo accounts on the sign-in page; set false in production
    'timezone' => 'Africa/Nairobi',

    'db' => [
        'host'    => getenv('CS_DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('CS_DB_PORT') ?: '3306',
        'name'    => getenv('CS_DB_NAME') ?: 'campuserve',
        'user'    => getenv('CS_DB_USER') ?: 'root',
        'pass'    => getenv('CS_DB_PASS') !== false ? getenv('CS_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],

    'ml' => [
        'url'          => getenv('CS_ML_URL') ?: 'http://127.0.0.1:5000',
        'timeout'      => 3,             // seconds; ticket submission never waits longer than this
        'metrics_file' => __DIR__ . '/../ml/model/metrics.json',
    ],

    // Where emailed links point. Used by the cron script too (no browser, so no HTTP_HOST).
    'app_url' => rtrim(getenv('CS_APP_URL') ?: 'http://localhost/Campuserve', '/'),

    'mail' => [
        // 'log'  = write each email to storage/mail/ (safe for demos; nothing leaves the machine)
        // 'smtp' = really send (Gmail: smtp.gmail.com, 587, tls, an App Password; or Mailtrap for testing)
        'driver'     => getenv('CS_MAIL_DRIVER') ?: 'log',
        'host'       => getenv('CS_SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => (int) (getenv('CS_SMTP_PORT') ?: 587),
        'encryption' => getenv('CS_SMTP_ENC') ?: 'tls',        // tls (STARTTLS), ssl, or none
        'username'   => getenv('CS_SMTP_USER') ?: '',
        'password'   => getenv('CS_SMTP_PASS') ?: '',
        'from_email' => getenv('CS_MAIL_FROM') ?: 'no-reply@campuserve.test',
        'from_name'  => 'Campuserve',
        'timeout'    => 8,
        'max_attempts' => 3,
    ],

    'security' => [
        'max_failed_logins' => 5,
        'lock_minutes'      => 10,
        'min_password_len'  => 8,
        'reset_minutes'     => 60,       // password reset link lifetime
        'reset_per_hour'    => 3,        // reset emails per account per hour
        'demo_classify_per_minute' => 12,   // rate limit for the public landing-page demo
    ],
];
