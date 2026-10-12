<?php
class AdminController extends Controller
{
    public function usersAction(): void
    {
        $u = $this->requireRole('admin');
        if ($this->isPost()) {
            $this->requireCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $new = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
            if ($id === (int) $u['id']) {
                flash('error', 'You cannot suspend your own account.');
            } elseif (Database::value('SELECT 1 FROM users WHERE id = ?', [$id])) {
                User::setStatus($id, $new);
                Auth::audit((int) $u['id'], 'user_' . $new, "user #$id");
                flash('success', 'Account ' . ($new === 'active' ? 'reactivated.' : 'suspended.'));
            }
            redirect('admin/users', array_filter(['role' => $_GET['role'] ?? '', 'q' => $_GET['q'] ?? '']));
        }
        $role = in_array($_GET['role'] ?? '', ['student', 'lecturer', 'staff', 'admin'], true) ? $_GET['role'] : null;
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->render('admin/users', ['title' => 'Users', 'u' => $u, 'users' => User::all($role, $q), 'role' => $role, 'q' => $q, 'counts' => User::countByRole()]);
    }

    public function mlAction(): void
    {
        $u = $this->requireRole('admin');
        $this->render('admin/ml', [
            'title' => 'Classifier performance', 'u' => $u,
            'metrics' => MLClient::metrics(), 'status' => MLClient::status(),
            'agreement' => Stats::mlAgreement(), 'confusions' => Stats::mlConfusions(),
            'bands' => Stats::confidenceBands(),
        ]);
    }
}
