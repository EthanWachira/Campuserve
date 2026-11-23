<?php
/**
 * Background jobs: overdue alerts, appointment reminders, email retries.
 * They run (a) from cron/run_jobs.php if you schedule it, and (b) automatically at most every 10 minutes
 * after a signed-in user opens the dashboard, so a plain XAMPP install works without Task Scheduler.
 */
class Jobs
{
    public static function maybeRun(): void
    {
        if (Settings::get('jobs_enabled', '1') !== '1') return;
        $now = time();
        try {
            Database::exec("INSERT IGNORE INTO settings (name, setting_value) VALUES ('jobs_last_run', '0')");
            $claimed = Database::exec("UPDATE settings SET setting_value = ? WHERE name = 'jobs_last_run' AND CAST(setting_value AS UNSIGNED) < ?", [(string) $now, $now - 600]);
        } catch (Throwable $e) { return; }
        if (!$claimed) return;
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            else { @ob_end_flush(); @flush(); }
            ignore_user_abort(true);
            try { self::run(); } catch (Throwable $e) { error_log('Jobs failed: ' . $e->getMessage()); }
        });
    }

    /** @return array{overdue:int,reminders:int,sent:int,failed:int} */
    public static function run(): array
    {
        $out = ['overdue' => 0, 'reminders' => 0, 'sent' => 0, 'failed' => 0];
        try { $out['overdue'] = self::overdueAlerts(); } catch (Throwable $e) { error_log('overdueAlerts: ' . $e->getMessage()); }
        if (Settings::get('appointment_reminders', '1') === '1') {
            try { $out['reminders'] = self::appointmentReminders(); } catch (Throwable $e) { error_log('appointmentReminders: ' . $e->getMessage()); }
        }
        try { [$out['sent'], $out['failed']] = Mailer::flush(50); } catch (Throwable $e) { error_log('mail flush: ' . $e->getMessage()); }
        try { Settings::set('jobs_last_run', (string) time()); } catch (Throwable $e) {}
        try { MLTrainer::autoRetrainIfDue(); } catch (Throwable $e) { error_log('auto retrain: ' . $e->getMessage()); }
        return $out;
    }

    /** Alert once per request that has passed its service-level target. */
    private static function overdueAlerts(): int
    {
        $rows = Database::all('SELECT t.id, t.reference_no, t.subject, t.priority, t.assigned_to, t.department_id FROM tickets t
                                WHERE t.sla_breached_at IS NULL AND ' . Sla::overdueCond() . ' ORDER BY t.created_at LIMIT 50');
        $admins = Database::all('SELECT id FROM users WHERE role = "admin" AND status = "active"');
        foreach ($rows as $t) {
            if (!Database::exec('UPDATE tickets SET sla_breached_at = NOW() WHERE id = ? AND sla_breached_at IS NULL', [$t['id']])) continue;
            Ticket::addUpdate((int) $t['id'], null, 'Service target of ' . Sla::hours($t['priority']) . ' h passed without resolution.', null, null, true);
            $link = '?r=tickets/show&id=' . $t['id'];
            $title = 'Overdue: ' . $t['reference_no'];
            $body = $t['subject'] . ' (' . $t['priority'] . ' priority) is past its ' . Sla::hours($t['priority']) . ' hour target.';
            $to = [];
            if ($t['assigned_to']) $to[] = (int) $t['assigned_to'];
            else foreach (User::staffIn((int) $t['department_id']) as $s) $to[] = (int) $s['id'];
            foreach ($admins as $a) $to[] = (int) $a['id'];
            foreach (array_unique($to) as $uid) Notification::send($uid, $title, mb_substr($body, 0, 480), $link);
        }
        return count($rows);
    }

    /** One reminder about a day ahead and one about an hour ahead, for confirmed appointments. */
    private static function appointmentReminders(): int
    {
        $rows = Database::all(
            'SELECT a.id, a.student_id, a.lecturer_id, a.starts_at, a.purpose, a.reminder_24h_at, a.reminder_1h_at, s.full_name AS sn, l.full_name AS ln
               FROM appointments a JOIN users s ON s.id = a.student_id JOIN users l ON l.id = a.lecturer_id
              WHERE a.status = "confirmed" AND a.starts_at > NOW() AND a.starts_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                AND (a.reminder_24h_at IS NULL OR (a.reminder_1h_at IS NULL AND a.starts_at <= DATE_ADD(NOW(), INTERVAL 1 HOUR))) LIMIT 100');
        $n = 0;
        foreach ($rows as $a) {
            $soon = strtotime($a['starts_at']) - time() <= 3600;
            if ($soon && $a['reminder_1h_at'] === null) {
                Database::exec('UPDATE appointments SET reminder_1h_at = NOW(), reminder_24h_at = COALESCE(reminder_24h_at, NOW()) WHERE id = ?', [$a['id']]);
                $when = 'starts at ' . fmt_time($a['starts_at']) . ' (within the hour)';
            } elseif ($a['reminder_24h_at'] === null) {
                Database::exec('UPDATE appointments SET reminder_24h_at = NOW() WHERE id = ?', [$a['id']]);
                $when = 'is on ' . fmt_day($a['starts_at']) . ' at ' . fmt_time($a['starts_at']);
            } else continue;
            Notification::send((int) $a['student_id'], 'Reminder: appointment with ' . mb_substr($a['ln'], 0, 80), 'Your appointment ' . $when . '. Purpose: ' . mb_substr($a['purpose'], 0, 200), '?r=appointments/index');
            Notification::send((int) $a['lecturer_id'], 'Reminder: appointment with ' . mb_substr($a['sn'], 0, 80), 'Your appointment ' . $when . '. Purpose: ' . mb_substr($a['purpose'], 0, 200), '?r=appointments/index');
            $n++;
        }
        return $n;
    }
}
