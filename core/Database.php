<?php
/** Thin PDO wrapper. Every query is a prepared statement. */
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Keep MySQL's NOW() and PHP's time() in the same timezone (lock-outs, "5 min ago", resolution times).
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array   { return self::run($sql, $params)->fetchAll(); }
    public static function one(string $sql, array $params = []): ?array  { $r = self::run($sql, $params)->fetch(); return $r ?: null; }
    public static function value(string $sql, array $params = [])        { $r = self::run($sql, $params)->fetchColumn(); return $r === false ? null : $r; }
    public static function exec(string $sql, array $params = []): int    { return self::run($sql, $params)->rowCount(); }
    public static function insertId(): int                               { return (int) self::pdo()->lastInsertId(); }
    public static function begin(): void    { self::pdo()->beginTransaction(); }
    public static function commit(): void   { self::pdo()->commit(); }
    public static function rollback(): void { if (self::pdo()->inTransaction()) self::pdo()->rollBack(); }
}
