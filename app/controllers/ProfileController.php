<?php
class ProfileController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireLogin();
        $row = Database::one('SELECT * FROM users WHERE id = ?', [$u['id']]);

        if ($this->isPost()) {
            $this->requireCsrf();
            $name = trim((string) ($_POST['full_name'] ?? ''));
            $emailPref = !empty($_POST['email_notifications']) ? 1 : 0;
            $errors = [];
            if (mb_strlen($name) < 3 || mb_strlen($name) > 120) $errors[] = 'Enter your full name (3 to 120 characters).';
            $programme = $row['programme']; $year = $row['year_of_study'];
            if ($u['role'] === 'student') {
                $programme = (string) ($_POST['programme'] ?? '');
                $year = (int) ($_POST['year_of_study'] ?? 0);
                if (!in_array($programme, AuthController::PROGRAMMES, true)) $errors[] = 'Choose your programme.';
                if ($year < 1 || $year > 6) $errors[] = 'Choose your year of study.';
            }
            if ($errors) { flash('error', implode(' ', $errors)); redirect('profile/index'); }
            Database::exec('UPDATE users SET full_name = ?, programme = ?, year_of_study = ?, email_notifications = ? WHERE id = ?', [$name, $programme, $year, $emailPref, $u['id']]);
            Auth::audit((int) $u['id'], 'profile_updated', null);
            flash('success', 'Your profile was updated.');
            redirect('profile/index');
        }
        $this->render('profile/index', ['title' => 'My profile', 'u' => $u, 'row' => $row, 'programmes' => AuthController::PROGRAMMES]);
    }

    public function passwordAction(): void
    {
        $u = $this->requireLogin();
        if (!$this->isPost()) redirect('profile/index');
        $this->requireCsrf();
        $row = Database::one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
        $min = App::config('security')['min_password_len'];
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
            Auth::audit((int) $u['id'], 'password_change_failed', 'wrong current password');
            flash('error', 'Your current password is not correct.');
        } elseif (strlen($new) < $min || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            flash('error', "Use at least $min characters with letters and numbers.");
        } elseif ($new !== (string) ($_POST['new_password_confirm'] ?? '')) {
            flash('error', 'The two new passwords do not match.');
        } else {
            Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            Auth::audit((int) $u['id'], 'password_changed', null);
            Mailer::queue((int) $u['id'], $u['email'], $u['full_name'], '[Campuserve] Your password was changed', 'Your password was changed',
                "Your Campuserve password was just changed.\nIf this was not you, contact the system administrator immediately.");
            flash('success', 'Password changed.');
        }
        redirect('profile/index');
    }
}
