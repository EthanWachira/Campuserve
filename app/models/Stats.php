<?php
/** Aggregates behind the dashboards. Every method takes the ticket scope so each role only sees its own numbers. */
class Stats
{
    public static function statusCounts(array $u): array
    {
        [$w, $p] = Ticket::scope($u);
        $rows = Database::all("SELECT t.status, COUNT(*) n FROM tickets t WHERE $w GROUP BY t.status", $p);
        $out = array_fill_keys(array_keys(TICKET_STATUSES), 0);
        foreach ($rows as $r) $out[$r['status']] = (int) $r['n'];
        return $out;
    }

    /** Average hours from submission to resolution over the last $days days. Null when nothing resolved. */
    public static function avgResolutionHours(array $u, int $days = 30): ?float
    {
        [$w, $p] = Ticket::scope($u);
        $v = Database::value("SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at)) / 60 FROM tickets t
                               WHERE $w AND t.resolved_at IS NOT NULL AND t.resolved_at >= DATE_SUB(NOW(), INTERVAL $days DAY)", $p);
        return $v === null ? null : (float) $v;
    }

    public static function resolvedSince(array $u, int $days): int
    {
        [$w, $p] = Ticket::scope($u);
        return (int) Database::value("SELECT COUNT(*) FROM tickets t WHERE $w AND t.resolved_at >= DATE_SUB(NOW(), INTERVAL $days DAY)", $p);
    }

    public static function byCategory(array $u): array
    {
        [$w, $p] = Ticket::scope($u);
        $rows = Database::all("SELECT c.name, COUNT(*) n FROM tickets t JOIN categories c ON c.id = t.category_id WHERE $w GROUP BY c.id, c.name ORDER BY n DESC", $p);
        return array_map(fn($r) => [$r['name'], (int) $r['n']], $rows);
    }

    public static function byDepartment(): array
    {
        $rows = Database::all('SELECT d.name, COUNT(t.id) n FROM departments d LEFT JOIN tickets t ON t.department_id = d.id GROUP BY d.id, d.name ORDER BY n DESC');
        return array_map(fn($r) => [$r['name'], (int) $r['n']], $rows);
    }

    /** Requests submitted per day for the last $days days (zero-filled). */
    public static function perDay(array $u, int $days = 14): array
    {
        [$w, $p] = Ticket::scope($u);
        $rows = Database::all("SELECT DATE(t.created_at) d, COUNT(*) n FROM tickets t WHERE $w AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY) GROUP BY DATE(t.created_at)", $p);
        $map = array_column($rows, 'n', 'd');
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $out[] = [$d, (int) ($map[$d] ?? 0)];
        }
        return $out;
    }

    /** How often staff kept the model's category. Only counts predictions a human has reviewed. */
    public static function mlAgreement(?int $departmentId = null): array
    {
        $sql = 'SELECT COUNT(*) reviewed, SUM(mp.predicted_category_id = mp.final_category_id) agreed, AVG(mp.confidence) avg_conf
                  FROM ml_predictions mp JOIN tickets t ON t.id = mp.ticket_id WHERE mp.final_category_id IS NOT NULL';
        $p = [];
        if ($departmentId) { $sql .= ' AND t.department_id = ?'; $p[] = $departmentId; }
        $r = Database::one($sql, $p) ?: [];
        $reviewed = (int) ($r['reviewed'] ?? 0);
        return [
            'reviewed' => $reviewed,
            'agreed' => (int) ($r['agreed'] ?? 0),
            'rate' => $reviewed ? round(((int) $r['agreed']) / $reviewed * 100, 1) : null,
            'avg_conf' => $reviewed ? round((float) $r['avg_conf'] * 100, 1) : null,
            'pending' => (int) Database::value('SELECT COUNT(*) FROM ml_predictions WHERE final_category_id IS NULL'),
        ];
    }

    /** Where the model was wrong: predicted -> corrected-to pairs. */
    public static function mlConfusions(int $limit = 6): array
    {
        return Database::all(
            'SELECT p.name AS predicted, f.name AS actual, COUNT(*) n
               FROM ml_predictions mp JOIN categories p ON p.id = mp.predicted_category_id JOIN categories f ON f.id = mp.final_category_id
              WHERE mp.final_category_id <> mp.predicted_category_id GROUP BY p.name, f.name ORDER BY n DESC LIMIT ' . (int) $limit);
    }

    public static function confidenceBands(): array
    {
        $r = Database::one('SELECT SUM(confidence < 0.45) low, SUM(confidence >= 0.45 AND confidence < 0.75) mid, SUM(confidence >= 0.75) high FROM ml_predictions') ?: [];
        return [['Below 45% (flagged for review)', (int) ($r['low'] ?? 0)], ['45% to 74%', (int) ($r['mid'] ?? 0)], ['75% and above', (int) ($r['high'] ?? 0)]];
    }

    public static function recentAudit(int $limit = 8): array
    {
        return Database::all('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . (int) $limit);
    }
}
