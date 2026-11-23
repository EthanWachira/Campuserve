<?php
/** Service-level targets: hours allowed to resolve a request, by priority (editable under Settings). */
class Sla
{
    private static ?array $hours = null;

    public static function hours(string $priority): int
    {
        if (self::$hours === null) {
            self::$hours = [
                'high'   => max(1, (int) Settings::get('sla_high', '8')),
                'medium' => max(1, (int) Settings::get('sla_medium', '24')),
                'low'    => max(1, (int) Settings::get('sla_low', '72')),
            ];
        }
        return self::$hours[$priority] ?? self::$hours['low'];
    }

    public static function resetCache(): void { self::$hours = null; }

    /** SQL expression: target hours for the ticket in alias $a. */
    public static function hoursCase(string $a = 't'): string
    {
        return "CASE $a.priority WHEN 'high' THEN " . self::hours('high') . " WHEN 'medium' THEN " . self::hours('medium') . ' ELSE ' . self::hours('low') . ' END';
    }

    /** SQL condition: still active and past its target. */
    public static function overdueCond(string $a = 't'): string
    {
        return "$a.status IN ('open','assigned','in_progress') AND TIMESTAMPDIFF(MINUTE, $a.created_at, NOW()) > (" . self::hoursCase($a) . ') * 60';
    }

    /** SQL condition: resolved within target. */
    public static function metCond(string $a = 't'): string
    {
        return "$a.resolved_at IS NOT NULL AND TIMESTAMPDIFF(MINUTE, $a.created_at, $a.resolved_at) <= (" . self::hoursCase($a) . ') * 60';
    }

    public static function countOverdue(array $u): int
    {
        [$w, $p] = Ticket::scope($u);
        return (int) Database::value('SELECT COUNT(*) FROM tickets t WHERE ' . $w . ' AND ' . self::overdueCond(), $p);
    }

    /** ok | risk | overdue | met | missed */
    public static function state(array $t): string
    {
        $due = strtotime($t['created_at']) + self::hours($t['priority']) * 3600;
        if (in_array($t['status'], ['resolved', 'closed'], true)) {
            return ($t['resolved_at'] && strtotime($t['resolved_at']) <= $due) ? 'met' : 'missed';
        }
        $elapsed = time() - strtotime($t['created_at']);
        $allowed = self::hours($t['priority']) * 3600;
        return $elapsed > $allowed ? 'overdue' : ($elapsed > 0.75 * $allowed ? 'risk' : 'ok');
    }

    public static function badge(array $t): string
    {
        return match (self::state($t)) {
            'overdue' => ' ' . badge('Overdue', 'bad'),
            'risk'    => ' ' . badge('Due soon', 'review'),
            default   => '',
        };
    }

    public static function describe(array $t): string
    {
        $h = self::hours($t['priority']);
        $due = strtotime($t['created_at']) + $h * 3600;
        return match (self::state($t)) {
            'met'     => "Target $h h: met",
            'missed'  => "Target $h h: missed",
            'overdue' => "Target $h h: overdue by " . hours_label((time() - $due) / 3600),
            default   => "Target $h h: due " . date('D j M, g:i a', $due),
        };
    }
}
