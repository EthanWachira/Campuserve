<?php
/** Administrator: create and edit accounts, send password links. */
class AccountsController extends Controller
{
    public function editAction(): void
    {
        $me = $this->requireRole('admin');
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $row = $id ? Database::one('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($id && !$row) App::notFound();

        if ($this->isPost()) {
            $this->requireCsrf();
            $d = [
                'role' => (string) ($_POST['role'] ?? ''),
                'identifier' => trim((string) ($_POST['identifier'] ?? '')),
                'full_name' => trim((string) ($_POST['full_name'] ?? '')),
                'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
                'department_id' => (int) ($_POST['department_id'] ?? 0) ?: null,
                'date_of_birth' => trim((string) ($_POST['date_of_birth'] ?? '')) ?: null,
                'programme' => trim((string) ($_POST['programme'] ?? '')) ?: null,
                'year_of_study' => (int) ($_POST['year_of_study'] ?? 0) ?: null,
            ];
            if ($row && (int) $row['id'] === (int) $me['id']) $d['role'] = $row['role'];     // never let an admin lock themselves out
            $err = $this->validate($d, $row);
            if ($err) { with_old($d); flash('error', implode(' ', $err)); redirect('accounts/edit', $id ? ['id' => $id] : []); }
            if (!in_array($d['role'], ['staff', 'lecturer'], true)) $d['department_id'] = null;
            if ($d['role'] !== 'student') { $d['programme'] = null; $d['year_of_study'] = null; }

            if ($row) {
                Database::exec('UPDATE users SET role=?, identifier=?, full_name=?, email=?, date_of_birth=?, department_id=?, programme=?, year_of_study=? WHERE id=?',
                    [$d['role'], $d['identifier'], $d['full_name'], $d['email'], $d['date_of_birth'], $d['department_id'], $d['programme'], $d['year_of_study'], $row['id']]);
                Auth::audit((int) $me['id'], 'user_edited', "user #{$row['id']}");
                flash('success', 'Account updated.');
            } else {
                Database::exec('INSERT INTO users (role, identifier, full_name, email, date_of_birth, password_hash, department_id, programme, year_of_study) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$d['role'], $d['identifier'], $d['full_name'], $d['email'], $d['date_of_birth'], password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), $d['department_id'], $d['programme'], $d['year_of_study']]);
                $newId = Database::insertId();
                Auth::audit((int) $me['id'], 'user_created', "user #$newId ({$d['role']})");
                $this->sendLink($newId, true);
                flash('success', 'Account created. A link to set the password was emailed to ' . $d['email'] . '.');
            }
            clear_old();
            redirect('admin/users');
        }
        $this->render('accounts/edit', ['title' => $row ? 'Edit account' : 'New account', 'u' => $me, 'row' => $row,
            'departments' => Database::all('SELECT id, name FROM departments ORDER BY name'), 'programmes' => AuthController::PROGRAMMES]);
        clear_old();
    }

    /** POST: email a (new) password link to a user. */
    public function sendresetAction(): void
    {
        $me = $this->requireRole('admin');
        if (!$this->isPost()) redirect('admin/users');
        $this->requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if (Database::value('SELECT 1 FROM users WHERE id = ? AND status = "active"', [$id])) {
            $this->sendLink($id, false);
            Auth::audit((int) $me['id'], 'reset_sent_by_admin', "user #$id");
            flash('success', 'A password reset link was emailed to the user.');
        } else flash('error', 'That account is suspended or does not exist.');
        redirect('admin/users');
    }

    private function sendLink(int $userId, bool $welcome): void
    {
        $u = Database::one('SELECT email, full_name FROM users WHERE id = ?', [$userId]);
        $minutes = $welcome ? 72 * 60 : App::config('security')['reset_minutes'];
        $token = bin2hex(random_bytes(32));
        Database::exec('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
        Database::exec('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),?)',
            [$userId, hash('sha256', $token), $minutes, $_SERVER['REMOTE_ADDR'] ?? null]);
        $hrs = $minutes >= 120 ? round($minutes / 60) . ' hours' : $minutes . ' minutes';
        Mailer::queue($userId, $u['email'], $u['full_name'],
            $welcome ? '[Campuserve] Your account is ready' : '[Campuserve] Reset your password',
            $welcome ? 'Welcome to Campuserve' : 'Reset your password',
            ($welcome ? "An administrator created a Campuserve account for you.\nUse the button to choose your password. " : "An administrator sent you a password reset link.\n") . "The link works once and expires in $hrs.",
            '?r=auth/reset&token=' . $token, 'Choose your password');
    }

    private function validate(array $d, ?array $row): array
    {
        $e = [];
        if (!in_array($d['role'], ['student', 'lecturer', 'staff', 'admin'], true)) $e[] = 'Choose a role.';
        if (!preg_match('/^[A-Za-z0-9\-]{3,30}$/', $d['identifier'])) $e[] = 'Admission or staff number must be 3 to 30 letters or digits.';
        if (mb_strlen($d['full_name']) < 3 || mb_strlen($d['full_name']) > 120) $e[] = 'Enter a full name (3 to 120 characters).';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e[] = 'Enter a valid email address.';
        if ($d['role'] === 'staff' && !$d['department_id']) $e[] = 'Support staff need a department.';
        if ($d['date_of_birth']) {
            $x = DateTime::createFromFormat('Y-m-d', $d['date_of_birth']);
            if (!$x || $x->format('Y-m-d') !== $d['date_of_birth'] || $x > new DateTime('today')) $e[] = 'Enter a valid date of birth.';
        }
        if (!$e && Database::value('SELECT 1 FROM users WHERE (email = ? OR identifier = ?) AND id <> ? LIMIT 1', [$d['email'], $d['identifier'], $row['id'] ?? 0])) {
            $e[] = 'Another account already uses this email or number.';
        }
        return $e;
    }
}
