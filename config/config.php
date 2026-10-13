<?php
/**
 * Campusserve configuration. Defaults match a stock XAMPP install
 * (MySQL user "root" with an empty password). Override with environment
 * variables or edit the values below.
 */
return [
    'app_name' => 'Campusserve',
    'demo_mode' => true,                 // shows one-click demo accounts on the sign-in page; set false in production
    'timezone' => 'Africa/Nairobi',

    'db' => [
        'host'    => getenv('CS_DB_HOST') ?: '127.0.0.1',
        'port'    => getenv('CS_DB_PORT') ?: '3306',
        'name'    => getenv('CS_DB_NAME') ?: 'campusserve',
        'user'    => getenv('CS_DB_USER') ?: 'root',
        'pass'    => getenv('CS_DB_PASS') !== false ? getenv('CS_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],

    'ml' => [
        'url'          => getenv('CS_ML_URL') ?: 'http://127.0.0.1:5000',
        'timeout'      => 3,             // seconds; ticket submission never waits longer than this
        'metrics_file' => __DIR__ . '/../ml/model/metrics.json',
    ],

    'security' => [
        'max_failed_logins' => 5,
        'lock_minutes'      => 10,
        'min_password_len'  => 8,
        'demo_classify_per_minute' => 12,   // rate limit for the public landing-page demo
    ],
];
