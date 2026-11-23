<?php
/**
 * Overdue alerts + appointment reminders + email retries. Optional: the app also runs these by itself
 * (at most every 10 minutes) whenever someone opens the dashboard. To run them on a schedule:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Campuserve\cron\run_jobs.php      (Task Scheduler, every 10 min)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/core/App.php';
App::boot();
$r = Jobs::run();
echo date('c') . " jobs: overdue={$r['overdue']} reminders={$r['reminders']} mail_sent={$r['sent']} mail_failed={$r['failed']}\n";
