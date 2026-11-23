<?php
/** Queries behind the Reports page and the CSV exports. Always filtered through Ticket::scope() so staff only see their own department. */
class Report
{
    /** Read and validate the filter form. */
    public static function filters(array $u, array $in): array
    {
        $to = self::date($in['to'] ?? '') ?? date('Y-m-d');
        $from = self::date($in['from'] ?? '') ?? date('Y-m-d', strtotime($to . ' -29 day'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if (strtotime($to) - strtotime($from) > 366 * 86400) $from = date('Y-m-d', strtotime($to . ' -366 day'));
        return [
            'from' => $from, 'to' => $to,
            'category' => (int) ($in['category'] ?? 0),
            'department' => $u['role'] === 'admin' ? (int) ($in['department'] ?? 0) : 0,
        ];
    }

    private static function date(string $d): ?string
    {
        $x = DateTime::createFromFormat('Y-m-d', $d);
        return $x && $x->format('Y-m-d') === $d ? $d : null;
    }

    /** @return array{0:string,1:array} where-clause (alias t) and parameters */
    private static function where(array $u, array $f): array
    {
        [$w, $p] = Ticket::scope($u);
        $w .= ' AND t.created_at >= ? AND t.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        array_push($p, $f['from'], $f['to']);
        if ($f['category']) { $w .= ' AND t.category_id = ?'; $p[] = $f['category']; }
        if ($f['department']) { $w .= ' AND t.department_id = ?'; $p[] = $f['department']; }
        return [$w, $p];
    }

    public static function summary(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        $r = Database::one("SELECT COUNT(*) total,
                SUM(t.status IN ('resolved','closed')) resolved,
                SUM(t.status IN ('open','assigned','in_progress')) active,
                SUM(t.priority = 'high') high,
                SUM(t.needs_review = 1) review,
                AVG(IF(t.resolved_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at), NULL)) / 60 avg_hours,
                SUM(" . Sla::metCond() . ") met,
                SUM(t.resolved_at IS NOT NULL) with_resolution,
                SUM(" . Sla::overdueCond() . ") overdue
              FROM tickets t WHERE $w", $p) ?: [];
        $res = (int) ($r['with_resolution'] ?? 0);
        return [
            'total' => (int) ($r['total'] ?? 0), 'resolved' => (int) ($r['resolved'] ?? 0), 'active' => (int) ($r['active'] ?? 0),
            'high' => (int) ($r['high'] ?? 0), 'review' => (int) ($r['review'] ?? 0),
            'avg_hours' => $r['avg_hours'] === null ? null : (float) $r['avg_hours'],
            'sla_rate' => $res ? round((int) $r['met'] / $res * 100, 1) : null,
            'overdue' => (int) ($r['overdue'] ?? 0),
        ];
    }

    public static function byStatus(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        $rows = Database::all("SELECT t.status, COUNT(*) n FROM tickets t WHERE $w GROUP BY t.status", $p);
        $out = array_fill_keys(array_keys(TICKET_STATUSES), 0);
        foreach ($rows as $r) $out[$r['status']] = (int) $r['n'];
        return $out;
    }

    public static function byPriority(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        $rows = Database::all("SELECT t.priority, COUNT(*) n, AVG(IF(t.resolved_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at), NULL)) / 60 h
                                 FROM tickets t WHERE $w GROUP BY t.priority ORDER BY FIELD(t.priority,'high','medium','low')", $p);
        return $rows;
    }

    public static function byCategory(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        return Database::all("SELECT c.name, COUNT(*) n, SUM(t.status IN ('resolved','closed')) resolved,
                                     AVG(IF(t.resolved_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at), NULL)) / 60 h
                                FROM tickets t JOIN categories c ON c.id = t.category_id WHERE $w GROUP BY c.id, c.name ORDER BY n DESC", $p);
    }

    public static function byDepartment(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        return Database::all("SELECT d.name, COUNT(*) n, SUM(t.status IN ('resolved','closed')) resolved,
                                     SUM(" . Sla::overdueCond() . ") overdue,
                                     AVG(IF(t.resolved_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at), NULL)) / 60 h
                                FROM tickets t JOIN departments d ON d.id = t.department_id WHERE $w GROUP BY d.id, d.name ORDER BY n DESC", $p);
    }

    public static function byAssignee(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        return Database::all("SELECT COALESCE(a.full_name, 'Unassigned') name, COUNT(*) n, SUM(t.status IN ('resolved','closed')) resolved,
                                     SUM(" . Sla::overdueCond() . ") overdue,
                                     AVG(IF(t.resolved_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at), NULL)) / 60 h
                                FROM tickets t LEFT JOIN users a ON a.id = t.assigned_to WHERE $w GROUP BY a.id, a.full_name ORDER BY n DESC LIMIT 25", $p);
    }

    /** Requests per day across the filter range (zero-filled, capped at 90 bars; longer ranges are grouped by week). */
    public static function trend(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        $days = (int) ((strtotime($f['to']) - strtotime($f['from'])) / 86400) + 1;
        $rows = Database::all("SELECT DATE(t.created_at) d, COUNT(*) n FROM tickets t WHERE $w GROUP BY DATE(t.created_at)", $p);
        $map = array_column($rows, 'n', 'd');
        $out = [];
        if ($days <= 90) {
            for ($i = 0; $i < $days; $i++) { $d = date('Y-m-d', strtotime($f['from'] . " +$i day")); $out[] = [$d, (int) ($map[$d] ?? 0)]; }
        } else {
            for ($i = 0; $i < $days; $i += 7) {
                $n = 0;
                for ($j = 0; $j < 7 && $i + $j < $days; $j++) $n += (int) ($map[date('Y-m-d', strtotime($f['from'] . ' +' . ($i + $j) . ' day'))] ?? 0);
                $out[] = [date('Y-m-d', strtotime($f['from'] . " +$i day")), $n];
            }
        }
        return $out;
    }

    /** Busiest hours of the day (0-23) for the filtered requests. */
    public static function byHour(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        $rows = Database::all("SELECT HOUR(t.created_at) h, COUNT(*) n FROM tickets t WHERE $w GROUP BY HOUR(t.created_at)", $p);
        $map = array_column($rows, 'n', 'h');
        $out = [];
        for ($h = 0; $h < 24; $h++) $out[] = [sprintf('%02d:00', $h), (int) ($map[$h] ?? 0)];
        return $out;
    }

    /** Full detail rows for the CSV export. */
    public static function detailRows(array $u, array $f): array
    {
        [$w, $p] = self::where($u, $f);
        return Database::all("SELECT t.reference_no, t.subject, s.identifier AS student_no, s.full_name AS student, c.name AS category, d.name AS department,
                                     t.priority, t.status, COALESCE(a.full_name, '') AS assigned_to, t.classification_source, t.needs_review,
                                     mp.confidence, t.created_at, t.resolved_at,
                                     IF(t.resolved_at IS NULL, NULL, ROUND(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) / 60, 2)) AS hours_to_resolve,
                                     " . Sla::hoursCase() . " AS target_hours,
                                     CASE WHEN t.resolved_at IS NOT NULL THEN IF(" . Sla::metCond() . ", 'met', 'missed')
                                          WHEN " . Sla::overdueCond() . " THEN 'overdue' ELSE 'running' END AS sla
                                FROM tickets t JOIN users s ON s.id = t.student_id JOIN categories c ON c.id = t.category_id
                                JOIN departments d ON d.id = t.department_id LEFT JOIN users a ON a.id = t.assigned_to
                                LEFT JOIN ml_predictions mp ON mp.id = (SELECT MIN(id) FROM ml_predictions WHERE ticket_id = t.id)
                               WHERE $w ORDER BY t.created_at", $p);
    }

    /** Appointment figures for the admin. */
    public static function appointmentRows(array $f): array
    {
        return Database::all("SELECT l.full_name AS lecturer, s.full_name AS student, s.identifier AS student_no, a.starts_at, a.ends_at, a.purpose, a.status
                                FROM appointments a JOIN users l ON l.id = a.lecturer_id JOIN users s ON s.id = a.student_id
                               WHERE a.starts_at >= ? AND a.starts_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY a.starts_at", [$f['from'], $f['to']]);
    }

    public static function appointmentSummary(array $f): array
    {
        return Database::all("SELECT l.full_name AS lecturer, COUNT(*) n, SUM(a.status = 'confirmed' OR a.status = 'completed') kept,
                                     SUM(a.status = 'declined') declined, SUM(a.status = 'cancelled') cancelled
                                FROM appointments a JOIN users l ON l.id = a.lecturer_id
                               WHERE a.starts_at >= ? AND a.starts_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY l.id, l.full_name ORDER BY n DESC", [$f['from'], $f['to']]);
    }
}
