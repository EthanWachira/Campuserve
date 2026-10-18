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
            flash('success', 'Your account is ready. Welcome to Campusserve.');
            redirect($ok ? Auth::homeRoute() : 'auth/login');
        }

        $errors = $_SESSION['errors'] ?? []; unset($_SESSION['errors']);
        $this->render('auth/register', ['title' => 'Create your account', 'errors' => $errors, 'programmes' => self::PROGRAMMES], 'layouts/auth');
        clear_old();
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
