<?php
class AuthController extends Controller
{
    public const PROGRAMMES = ['BBIT', 'BSc Informatics and Computer Science', 'BCom', 'Bachelor of Laws (LLB)', 'Bachelor of Science in Finance and Accounting', 'Other'];

    public function loginAction(): void
    {
        if (Auth::user()) redirect(Auth::homeRoute());

        if ($this->isPost()) {
            $this->requireCsrf();
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($email === '' || $password === '') {
                flash('error', 'Enter your email and password.');
            } else {
                [$ok, $msg] = Auth::attempt($email, $password);
                if ($ok) { clear_old(); redirect(Auth::homeRoute()); }
                flash('error', $msg);
            }
            with_old(['email' => $email]);
            redirect('auth/login');
        }

        $demo = App::config('demo_mode')
            ? Database::all('SELECT role, email, full_name FROM users WHERE id IN (1, 2, 12, 15)
                             ORDER BY FIELD(role,"student","lecturer","staff","admin")') : [];
        $this->render('auth/login', ['title' => 'Sign in', 'demoUsers' => $demo], 'layouts/auth');
        clear_old();
    }

    public function registerAction(): void
    {
        if (Auth::user()) redirect(Auth::homeRoute());

        if ($this->isPost()) {
            $this->requireCsrf();
            $d = [
    'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    'identifier' => trim((string) ($_POST['identifier'] ?? '')),
    'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'date_of_birth' => trim((string) ($_POST['date_of_birth'] ?? '')),
    'programme' => (string) ($_POST['programme'] ?? ''),
    'year_of_study' => (int) ($_POST['year_of_study'] ?? 0),
    'password' => (string) ($_POST['password'] ?? ''),
];
            $confirm = (string) ($_POST['password_confirm'] ?? '');
            $errors = $this->validateRegistration($d, $confirm);
            if (!$errors && User::emailOrIdTaken($d['email'], $d['identifier'])) {
                $errors['email'] = 'An account with this email or admission number already exists.';
            }
            if ($errors) {
                with_old(array_diff_key($d, ['password' => 1]));
                $_SESSION['errors'] = $errors;
                redirect('auth/register');
            }
            User::createStudent($d);
            [$ok] = Auth::attempt($d['email'], $d['password']);
            clear_old();
            flash('success', 'Your account is ready. Welcome to Campuserve.');
            redirect($ok ? Auth::homeRoute() : 'auth/login');
        }

        $errors = $_SESSION['errors'] ?? []; unset($_SESSION['errors']);
        $this->render('auth/register', ['title' => 'Create your account', 'errors' => $errors, 'programmes' => self::PROGRAMMES], 'layouts/auth');
        clear_old();
    }

    /** Step 1: ask for a reset link. The reply is identical whether or not the email exists. */
    public function forgotAction(): void
    {
        if (Auth::user()) redirect(Auth::homeRoute());

        if ($this->isPost()) {
            $this->requireCsrf();
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter the email address you registered with.');
                with_old(['email' => $email]);
                redirect('auth/forgot');
            }
            $this->sendResetLink($email);
            flash('success', 'If that email belongs to an account, a reset link is on its way. It expires in ' . App::config('security')['reset_minutes'] . ' minutes.');
            redirect('auth/login');
        }
        $this->render('auth/forgot', ['title' => 'Forgot password'], 'layouts/auth');
        clear_old();
    }

    /** Step 2: the emailed link lands here (?r=auth/reset&token=...). */
    public function resetAction(): void
    {
        if (Auth::user()) redirect(Auth::homeRoute());
        $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        $row = preg_match('/^[a-f0-9]{64}$/', $token) ? $this->findReset($token) : null;
        if (!$row) {
            flash('error', 'That reset link is invalid or has expired. Request a new one.');
            redirect('auth/forgot');
        }

        if ($this->isPost()) {
            $this->requireCsrf();
            $pw = (string) ($_POST['password'] ?? '');
            $min = App::config('security')['min_password_len'];
            $err = null;
            if (strlen($pw) < $min || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) $err = "Use at least $min characters with letters and numbers.";
            elseif ($pw !== (string) ($_POST['password_confirm'] ?? '')) $err = 'The two passwords do not match.';
            if ($err) { flash('error', $err); redirect('auth/reset', ['token' => $token]); }

            Database::begin();
            try {
                Database::exec('UPDATE users SET password_hash = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
                Database::exec('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
                Database::commit();
            } catch (Throwable $e) { Database::rollback(); throw $e; }
            Auth::audit((int) $row['user_id'], 'password_reset', null);
            $u = Database::one('SELECT email, full_name FROM users WHERE id = ?', [$row['user_id']]);
            if ($u) Mailer::queue((int) $row['user_id'], $u['email'], $u['full_name'], '[Campuserve] Your password was changed', 'Your password was changed',
                "Your Campuserve password was just changed using a reset link.\nIf this was not you, contact the system administrator immediately.");
            flash('success', 'Password updated. Sign in with your new password.');
            redirect('auth/login');
        }
        $this->render('auth/reset', ['title' => 'Choose a new password', 'token' => $token], 'layouts/auth');
    }

    private function findReset(string $token): ?array
    {
        return Database::one('SELECT pr.* FROM password_resets pr JOIN users u ON u.id = pr.user_id
                               WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.status = "active"', [hash('sha256', $token)]);
    }

    private function sendResetLink(string $email): void
    {
        $cfg = App::config('security');
        $u = Database::one('SELECT id, email, full_name, status FROM users WHERE email = ?', [$email]);
        if (!$u || $u['status'] !== 'active') { Auth::audit(null, 'reset_requested', 'unknown or suspended email'); return; }
        $recent = (int) Database::value('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$u['id']]);
        if ($recent >= $cfg['reset_per_hour']) { Auth::audit((int) $u['id'], 'reset_throttled', null); return; }

        $token = bin2hex(random_bytes(32));
        Database::exec('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$u['id']]);   // older links die
        Database::exec('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),?)',
            [$u['id'], hash('sha256', $token), $cfg['reset_minutes'], $_SERVER['REMOTE_ADDR'] ?? null]);
        Auth::audit((int) $u['id'], 'reset_requested', null);
        Mailer::queue((int) $u['id'], $u['email'], $u['full_name'], '[Campuserve] Reset your password', 'Reset your password',
            "We received a request to reset the password for your Campuserve account.\nThis link works once and expires in {$cfg['reset_minutes']} minutes.\nIf you did not ask for this, ignore this email; your password stays the same.",
            '?r=auth/reset&token=' . $token, 'Choose a new password');
    }

    public function logoutAction(): void
    {
        if ($this->isPost() && csrf_valid($_POST['_csrf'] ?? '')) {
            Auth::logout();
            flash('success', 'You have signed out.');
        }
        redirect('auth/login');
    }

    private function validateRegistration(array $d, string $confirm): array
    {
        $min = App::config('security')['min_password_len'];
        $e = [];
        if (mb_strlen($d['full_name']) < 3 || mb_strlen($d['full_name']) > 120) $e['full_name'] = 'Enter your full name (3 to 120 characters).';
        if (!preg_match('/^[A-Za-z0-9\-]{4,20}$/', $d['identifier'])) $e['identifier'] = 'Admission number must be 4 to 20 letters or digits.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email address.';
        if (!in_array($d['programme'], self::PROGRAMMES, true)) $e['programme'] = 'Choose your programme.';
        if ($d['year_of_study'] < 1 || $d['year_of_study'] > 6) $e['year_of_study'] = 'Choose your year of study.';
        if (strlen($d['password']) < $min || !preg_match('/[A-Za-z]/', $d['password']) || !preg_match('/\d/', $d['password'])) {
            $e['password'] = "Use at least $min characters with letters and numbers.";
        } elseif ($d['password'] !== $confirm) {
            $e['password_confirm'] = 'The two passwords do not match.';
        }
           $dob = DateTime::createFromFormat('Y-m-d', $d['date_of_birth']);
   $today = new DateTime('today');
   if (!$dob || $dob->format('Y-m-d') !== $d['date_of_birth'] || $dob > $today || $dob->diff($today)->y > 100) {
       $e['date_of_birth'] = 'Enter a valid date of birth.';
   }
        return $e;
    }
}
