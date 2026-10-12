<?php
class Auth
{
    private static ?array $cached = null;

    public static function user(): ?array
    {
        if (self::$cached !== null) return self::$cached ?: null;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) { self::$cached = []; return null; }
        $u = Database::one(
            'SELECT u.id, u.role, u.identifier, u.full_name, u.email, u.department_id, u.programme, u.year_of_study, u.status,
                    d.name AS department_name
               FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?', [$id]);
        if (!$u || $u['status'] !== 'active') { self::logout(false); self::$cached = []; return null; }
        self::$cached = $u;
        return $u;
    }

    /** @return array{0:bool,1:string} [success, message] */
    public static function attempt(string $email, string $password): array
    {
        $cfg = App::config('security');
        $generic = 'Email or password is incorrect.';
        $u = Database::one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);

        if (!$u) {                                   // keep timing similar whether or not the account exists
            password_verify($password, '$2y$10$I5BvWGhMTPsC2.okSmS.AeqBRVOfXDJXlHudq0hswftGV5dGLDU4m');
            self::audit(null, 'login_failed', 'unknown email');
            return [false, $generic];
        }
        if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $mins = max(1, (int) ceil((strtotime($u['locked_until']) - time()) / 60));
            return [false, "Too many failed attempts. Try again in $mins minute" . ($mins === 1 ? '' : 's') . '.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $fails = (int) $u['failed_attempts'] + 1;
            if ($fails >= $cfg['max_failed_logins']) {
                Database::exec('UPDATE users SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?', [$cfg['lock_minutes'], $u['id']]);
                self::audit((int) $u['id'], 'account_locked', "after $fails failed attempts");
                return [false, "Too many failed attempts. This account is locked for {$cfg['lock_minutes']} minutes."];
            }
            Database::exec('UPDATE users SET failed_attempts = ? WHERE id = ?', [$fails, $u['id']]);
            self::audit((int) $u['id'], 'login_failed', 'wrong password');
            return [false, $generic];
        }
        if ($u['status'] !== 'active') {
            return [false, 'This account is suspended. Contact the system administrator.'];
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Database::exec('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?', [$u['id']]);
        self::audit((int) $u['id'], 'login', null);
        self::$cached = null;
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        return [true, ''];
    }

    public static function logout(bool $log = true): void
    {
        if ($log && !empty($_SESSION['uid'])) self::audit((int) $_SESSION['uid'], 'logout', null);
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
            session_destroy();
            session_start();
        }
        self::$cached = null;
    }

    public static function audit(?int $userId, string $action, ?string $detail): void
    {
        try {
            Database::exec('INSERT INTO audit_log (user_id, action, detail, ip_address) VALUES (?,?,?,?)',
                [$userId, $action, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) { error_log('audit failed: ' . $e->getMessage()); }
    }

    public static function homeRoute(): string { return 'dashboard/index'; }
}
