<?php
/** Administrator: system switches, SLA targets, email outbox, audit log. */
class SettingsController extends Controller
{
    private const SWITCHES = ['auto_assign', 'email_notifications', 'appointment_reminders', 'jobs_enabled'];
    private const TARGETS = ['sla_high', 'sla_medium', 'sla_low'];

    public function indexAction(): void
    {
        $u = $this->requireRole('admin');
        if ($this->isPost()) {
            $this->requireCsrf();
            foreach (self::SWITCHES as $k) Settings::set($k, !empty($_POST[$k]) ? '1' : '0');
            foreach (self::TARGETS as $k) Settings::set($k, (string) min(720, max(1, (int) ($_POST[$k] ?? 0))));
            Sla::resetCache();
            Auth::audit((int) $u['id'], 'settings_changed', null);
            flash('success', 'Settings saved.');
            redirect('settings/index');
        }
        $last = (int) Settings::get('jobs_last_run', '0');
        $this->render('settings/index', [
            'title' => 'Settings', 'u' => $u,
            's' => [
                'auto_assign' => Settings::get('auto_assign', '0'), 'email_notifications' => Settings::get('email_notifications', '1'),
                'appointment_reminders' => Settings::get('appointment_reminders', '1'), 'jobs_enabled' => Settings::get('jobs_enabled', '1'),
                'sla_high' => Settings::get('sla_high', '8'), 'sla_medium' => Settings::get('sla_medium', '24'), 'sla_low' => Settings::get('sla_low', '72'),
            ],
            'jobsLast' => $last > 0 ? date('Y-m-d H:i:s', $last) : null,
            'mailDriver' => App::config('mail')['driver'], 'mailStats' => Mailer::stats(),
        ]);
    }

    public function runjobsAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('settings/index');
        $this->requireCsrf();
        $r = Jobs::run();
        Auth::audit((int) $u['id'], 'jobs_run_manually', null);
        flash('success', "Jobs ran: {$r['overdue']} overdue alert(s), {$r['reminders']} appointment reminder(s), {$r['sent']} email(s) sent, {$r['failed']} failed.");
        redirect('settings/index');
    }

    public function outboxAction(): void
    {
        $u = $this->requireRole('admin');
        $status = in_array($_GET['status'] ?? '', ['pending', 'sent', 'failed'], true) ? $_GET['status'] : '';
        $rows = Database::all('SELECT id, to_email, subject, status, attempts, last_error, created_at, sent_at FROM email_queue'
            . ($status ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT 100', $status ? [$status] : []);
        $this->render('settings/outbox', ['title' => 'Email outbox', 'u' => $u, 'rows' => $rows, 'status' => $status, 'stats' => Mailer::stats(), 'driver' => App::config('mail')['driver']]);
    }

    public function retryAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('settings/outbox');
        $this->requireCsrf();
        $n = Database::exec('UPDATE email_queue SET status = "pending", attempts = 0 WHERE status = "failed"');
        [$sent, $failed] = Mailer::flush(100);
        Auth::audit((int) $u['id'], 'mail_retry', "$n requeued");
        flash($failed ? 'error' : 'success', "$n failed email(s) requeued: $sent sent, $failed failed again. See the error column for the reason.");
        redirect('settings/outbox');
    }

    public function testAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('settings/outbox');
        $this->requireCsrf();
        $ok = Mailer::queue((int) $u['id'], $u['email'], $u['full_name'], '[Campuserve] Test email', 'Test email', 'If you can read this, Campuserve can send email.', '?r=dashboard/index', 'Open Campuserve');
        [$sent, $failed] = $ok ? Mailer::flush(20) : [0, 1];
        flash($failed ? 'error' : 'success', $failed ? 'The test email failed. Check the error in the table below.' : (App::config('mail')['driver'] === 'smtp' ? 'Test email sent to ' . $u['email'] . '.' : 'Test email written to storage/mail/ (mail driver is "log").'));
        redirect('settings/outbox');
    }

    public function auditAction(): void
    {
        $u = $this->requireRole('admin');
        $action = (string) ($_GET['action'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        $where = []; $p = [];
        if ($action !== '') { $where[] = 'a.action = ?'; $p[] = $action; }
        if ($q !== '') { $where[] = '(a.detail LIKE ? OR u.full_name LIKE ? OR a.ip_address LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        $rows = Database::all('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.id DESC LIMIT 200', $p);
        $actions = array_column(Database::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
        $this->render('settings/audit', ['title' => 'Audit log', 'u' => $u, 'rows' => $rows, 'actions' => $actions, 'action' => $action, 'q' => $q]);
    }
}
