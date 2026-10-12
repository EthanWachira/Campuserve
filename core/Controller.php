<?php
abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

    protected function requireLogin(): array
    {
        $u = Auth::user();
        if (!$u) {
            flash('error', 'Sign in to continue.');
            redirect('auth/login');
        }
        return $u;
    }

    protected function requireRole(string ...$roles): array
    {
        $u = $this->requireLogin();
        if (!in_array($u['role'], $roles, true)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Access denied'], 'layouts/plain');
            exit;
        }
        return $u;
    }

    protected function requireCsrf(): void
    {
        if (!csrf_valid($_POST['_csrf'] ?? '')) {
            http_response_code(419);
            View::render('errors/419', ['title' => 'Session expired'], 'layouts/plain');
            exit;
        }
    }
}
