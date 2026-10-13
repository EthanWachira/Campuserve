<?php
/** Application bootstrap: config, autoloading, session, security headers and routing. */
class App
{
    private static array $config = [];

    public static function boot(): void
    {
        self::$config = require BASE_PATH . '/config/config.php';
        date_default_timezone_set(self::$config['timezone']);

        spl_autoload_register(function (string $class): void {
            foreach (['core', 'app/controllers', 'app/models', 'app/services'] as $dir) {
                $f = BASE_PATH . "/$dir/$class.php";
                if (is_file($f)) { require_once $f; return; }
            }
        });
        require_once BASE_PATH . '/core/helpers.php';

        session_name('Campusserve');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                                   'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "font-src https://fonts.gstatic.com; script-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'");
    }

    public static function config(?string $key = null)
    {
        return $key === null ? self::$config : (self::$config[$key] ?? null);
    }

    /** Route format: ?r=controller/action  (default home/index). */
    public static function dispatch(): void
    {
        $route = $_GET['r'] ?? 'home/index';
        if (!is_string($route) || !preg_match('#^([a-z]+)/([a-z]+)$#', $route, $m)) { self::notFound(); }
        $class = ucfirst($m[1]) . 'Controller';
        $action = $m[2] . 'Action';
        if (!class_exists($class) || !method_exists($class, $action)) { self::notFound(); }
        try {
            (new $class())->$action();
        } catch (PDOException $e) {
            error_log('Campusserve DB error: ' . $e->getMessage());
            http_response_code(500);
            View::render('errors/500', ['title' => 'Database unavailable'], 'layouts/plain');
        }
    }

    public static function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Page not found'], 'layouts/plain');
        exit;
    }
}
