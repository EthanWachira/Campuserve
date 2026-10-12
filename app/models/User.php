<?php
class User
{
    public static function findByEmail(string $email): ?array
    {
        return Database::one('SELECT * FROM users WHERE email = ?', [strtolower(trim($email))]);
    }

    public static function emailOrIdTaken(string $email, string $identifier): bool
    {
        return (bool) Database::value('SELECT 1 FROM users WHERE email = ? OR identifier = ? LIMIT 1', [strtolower(trim($email)), trim($identifier)]);
    }

    public static function createStudent(array $d): int
    {
        Database::exec(
            'INSERT INTO users (role, identifier, full_name, email, password_hash, programme, year_of_study) VALUES ("student",?,?,?,?,?,?)',
            [trim($d['identifier']), trim($d['full_name']), strtolower(trim($d['email'])), password_hash($d['password'], PASSWORD_DEFAULT), $d['programme'], (int) $d['year_of_study']]);
        return Database::insertId();
    }

    public static function all(?string $role = null, string $q = ''): array
    {
        $where = []; $p = [];
        if ($role) { $where[] = 'u.role = ?'; $p[] = $role; }
        if ($q !== '') { $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.identifier LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
        $sql = 'SELECT u.id, u.role, u.identifier, u.full_name, u.email, u.status, u.last_login_at, d.name AS department_name
                  FROM users u LEFT JOIN departments d ON d.id = u.department_id'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY FIELD(u.role,"admin","staff","lecturer","student"), u.full_name';
        return Database::all($sql, $p);
    }

    public static function countByRole(): array
    {
        $rows = Database::all('SELECT role, COUNT(*) n FROM users GROUP BY role');
        return array_column($rows, 'n', 'role');
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::exec('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function staffIn(int $departmentId): array
    {
        return Database::all('SELECT id FROM users WHERE role = "staff" AND department_id = ? AND status = "active"', [$departmentId]);
    }
}
