<?php
/**
 * Retry queued emails. Run from the command line (or Windows Task Scheduler, every 5 minutes):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\Campuserve\cron\send_mail.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/core/App.php';
App::boot();
[$sent, $failed] = Mailer::flush(100);
echo date('c') . " mail: sent=$sent failed=$failed\n";
