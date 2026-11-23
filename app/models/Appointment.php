<?php
/**
 * Appointment booking. A lecturer publishes weekly availability windows; a student books one slot
 * (default 30 minutes) inside a window; the lecturer confirms or declines.
 *
 * Tunable values live in config/config.php under 'appointments'. Any key left out uses the default below,
 * so existing config files keep working.
 */
class Appointment
{
    private const DEFAULTS = [
        'slot_minutes'           => 30,   // length of one appointment
        'max_days_ahead'         => 30,   // how far ahead a student may book
        'min_lead_minutes'       => 60,   // no booking may start sooner than this from now
        'max_active_per_student' => 3,    // pending + confirmed upcoming appointments per student
    ];

    /** Days a lecturer can offer (ISO weekday numbers, as stored in lecturer_availability). */
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'];

    public static function cfg(): array
    {
        return (App::config('appointments') ?? []) + self::DEFAULTS;
    }

    /* ---------- reading ---------- */

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT a.*, s.full_name AS student_name, s.identifier AS student_no, l.full_name AS lecturer_name
               FROM appointments a
               JOIN users s ON s.id = a.student_id
               JOIN users l ON l.id = a.lecturer_id
              WHERE a.id = ?', [$id]);
    }

    public static function upcomingForStudent(int $studentId, int $limit = 5): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS lecturer_name FROM appointments a JOIN users u ON u.id = a.lecturer_id
              WHERE a.student_id = ? AND a.starts_at >= NOW() AND a.status IN ("pending","confirmed")
              ORDER BY a.starts_at LIMIT ' . (int) $limit, [$studentId]);
    }

    public static function upcomingForLecturer(int $lecturerId, int $limit = 8): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS student_name, u.identifier AS student_no FROM appointments a JOIN users u ON u.id = a.student_id
              WHERE a.lecturer_id = ? AND a.starts_at >= NOW() AND a.status IN ("pending","confirmed")
              ORDER BY a.starts_at LIMIT ' . (int) $limit, [$lecturerId]);
    }

    /** Appointments for the signed-in student or lecturer. $scope: 'upcoming' (open and in the future) or 'history'. */
    public static function forUser(array $u, string $scope): array
    {
        $col = $u['role'] === 'lecturer' ? 'a.lecturer_id' : 'a.student_id';
        $open = 'a.starts_at >= NOW() AND a.status IN ("pending","confirmed")';
        $sql = 'SELECT a.*, s.full_name AS student_name, s.identifier AS student_no, l.full_name AS lecturer_name
                  FROM appointments a
                  JOIN users s ON s.id = a.student_id
                  JOIN users l ON l.id = a.lecturer_id
                 WHERE ' . $col . ' = ? AND ';
        $sql .= $scope === 'upcoming' ? "$open ORDER BY a.starts_at" : "NOT ($open) ORDER BY a.starts_at DESC LIMIT 30";
        return Database::all($sql, [$u['id']]);
    }

    /** Pending and confirmed count only while still in the future, so expired requests do not inflate the numbers. */
    public static function countsForLecturer(int $lecturerId): array
    {
        $rows = Database::all(
            'SELECT status, COUNT(*) n FROM appointments
              WHERE lecturer_id = ? AND (status NOT IN ("pending","confirmed") OR starts_at >= NOW())
              GROUP BY status', [$lecturerId]);
        return array_column($rows, 'n', 'status');
    }

    public static function pendingCount(int $lecturerId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM appointments WHERE lecturer_id = ? AND status = "pending" AND starts_at >= NOW()', [$lecturerId]);
    }

    /** Lecturers a student can book: active accounts that have published at least one window. */
    public static function lecturers(): array
    {
        return Database::all(
            'SELECT u.id, u.full_name, s.code AS school_code, s.name AS school_name
               FROM users u LEFT JOIN schools s ON s.id = u.school_id
              WHERE u.role = "lecturer" AND u.status = "active"
                AND EXISTS (SELECT 1 FROM lecturer_availability la WHERE la.lecturer_id = u.id)
              ORDER BY s.code IS NULL, s.code, u.full_name');
    }

    public static function statusBadge(array $a): string
    {
        $past = strtotime($a['starts_at']) < time();
        return match ($a['status']) {
            'pending'   => $past ? badge('Expired', 'closed') : badge('Pending', 'review'),
            'confirmed' => badge('Confirmed', 'ok'),
            'declined'  => badge('Declined', 'bad'),
            'cancelled' => badge('Cancelled', 'closed'),
            'completed' => badge('Completed', 'resolved'),
            default     => badge(ucfirst($a['status']), 'closed'),
        };
    }

    /* ---------- availability ---------- */

    public static function availability(int $lecturerId): array
    {
        return Database::all('SELECT day_of_week, start_time, end_time FROM lecturer_availability WHERE lecturer_id = ? ORDER BY day_of_week, start_time', [$lecturerId]);
    }

    /** @return array<int, array<int, array{0:string,1:string}>>  day number => [[start, end], ...] as HH:MM */
    public static function availabilityByDay(int $lecturerId): array
    {
        $out = [];
        foreach (self::availability($lecturerId) as $a) {
            $out[(int) $a['day_of_week']][] = [substr($a['start_time'], 0, 5), substr($a['end_time'], 0, 5)];
        }
        return $out;
    }

    /** Half-hour choices offered in the availability editor. */
    public static function timeOptions(): array
    {
        $t = [];
        for ($m = 6 * 60; $m <= 21 * 60; $m += 30) $t[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        return $t;
    }

    /** @param array<int, array{0:int,1:string,2:string}> $windows [day, 'HH:MM', 'HH:MM'] already validated */
    public static function saveAvailability(int $lecturerId, array $windows): void
    {
        Database::begin();
        try {
            Database::exec('DELETE FROM lecturer_availability WHERE lecturer_id = ?', [$lecturerId]);
            foreach ($windows as [$dow, $start, $end]) {
                Database::exec('INSERT INTO lecturer_availability (lecturer_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)',
                    [$lecturerId, $dow, $start . ':00', $end . ':00']);
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /* ---------- slots ---------- */

    /**
     * Every slot a lecturer offers on $date (YYYY-MM-DD), each marked free or not.
     * A slot is not free if it is too soon, overlaps a pending/confirmed appointment of the lecturer,
     * or overlaps one the student already holds with anybody.
     * @return array<int, array{time:string,label:string,starts_at:string,ends_at:string,free:bool}>
     */
    public static function slotsFor(int $lecturerId, string $date, int $studentId): array
    {
        $cfg = self::cfg();
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date) return [];

        $now = new DateTimeImmutable('now');
        $today = $now->setTime(0, 0);
        if ($day < $today || $day > $today->modify('+' . (int) $cfg['max_days_ahead'] . ' days')) return [];

        $windows = Database::all(
            'SELECT start_time, end_time FROM lecturer_availability WHERE lecturer_id = ? AND day_of_week = ? ORDER BY start_time',
            [$lecturerId, (int) $day->format('N')]);
        if (!$windows) return [];

        $busy = [];
        $rows = Database::all(
            'SELECT starts_at, ends_at FROM appointments
              WHERE status IN ("pending","confirmed") AND (lecturer_id = ? OR student_id = ?) AND starts_at <= ? AND ends_at >= ?',
            [$lecturerId, $studentId, $date . ' 23:59:59', $date . ' 00:00:00']);
        foreach ($rows as $b) $busy[] = [strtotime($b['starts_at']), strtotime($b['ends_at'])];

        $earliest = $now->modify('+' . (int) $cfg['min_lead_minutes'] . ' minutes')->getTimestamp();
        $step = new DateInterval('PT' . (int) $cfg['slot_minutes'] . 'M');
        $slots = [];
        foreach ($windows as $w) {
            [$sh, $sm] = array_map('intval', explode(':', $w['start_time']));
            [$eh, $em] = array_map('intval', explode(':', $w['end_time']));
            $t = $day->setTime($sh, $sm);
            $end = $day->setTime($eh, $em);
            while (($next = $t->add($step)) <= $end) {
                $s = $t->getTimestamp();
                $e = $next->getTimestamp();
                $free = $s >= $earliest;
                foreach ($busy as [$bs, $be]) {
                    if ($s < $be && $e > $bs) { $free = false; break; }
                }
                $slots[$t->format('H:i')] = [
                    'time' => $t->format('H:i'), 'label' => $t->format('g:i a'),
                    'starts_at' => $t->format('Y-m-d H:i:s'), 'ends_at' => $next->format('Y-m-d H:i:s'),
                    'free' => $free,
                ];
                $t = $next;
            }
        }
        ksort($slots);
        return array_values($slots);
    }

    /** The next few days on which this lecturer still has a free slot (YYYY-MM-DD). */
    public static function nextDates(int $lecturerId, int $studentId, int $limit = 6): array
    {
        $cfg = self::cfg();
        $offered = array_keys(self::availabilityByDay($lecturerId));
        $today = new DateTimeImmutable('today');
        $out = [];
        for ($i = 0; $i <= (int) $cfg['max_days_ahead'] && count($out) < $limit; $i++) {
            $d = $today->modify("+$i days");
            if (!in_array((int) $d->format('N'), $offered, true)) continue;
            foreach (self::slotsFor($lecturerId, $d->format('Y-m-d'), $studentId) as $s) {
                if ($s['free']) { $out[] = $d->format('Y-m-d'); break; }
            }
        }
        return $out;
    }

    /* ---------- writing ---------- */

    /**
     * Books a slot as "pending". Runs in a transaction that locks both users' rows, so two students
     * cannot take the same slot and one student cannot double-book at the same moment.
     * @return array{0:bool,1:int|string} [true, new appointment id] or [false, message for the student]
     */
    public static function book(int $studentId, int $lecturerId, string $date, string $time, string $purpose): array
    {
        $cfg = self::cfg();
        Database::begin();
        try {
            Database::all('SELECT id FROM users WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [$studentId, $lecturerId]);

            $slot = null;
            foreach (self::slotsFor($lecturerId, $date, $studentId) as $s) {
                if ($s['time'] === $time) { $slot = $s; break; }
            }
            if (!$slot || !$slot['free']) {
                Database::rollback();
                return [false, 'That time is no longer available. Please pick another slot.'];
            }

            $active = (int) Database::value(
                'SELECT COUNT(*) FROM appointments WHERE student_id = ? AND status IN ("pending","confirmed") AND starts_at >= NOW()', [$studentId]);
            if ($active >= (int) $cfg['max_active_per_student']) {
                Database::rollback();
                return [false, 'You already have ' . $active . ' upcoming appointments. Cancel one, or wait until one has taken place, before booking another.'];
            }

            Database::exec('INSERT INTO appointments (student_id, lecturer_id, starts_at, ends_at, purpose, status) VALUES (?,?,?,?,?, "pending")',
                [$studentId, $lecturerId, $slot['starts_at'], $slot['ends_at'], $purpose]);
            $id = Database::insertId();
            Database::commit();
            return [true, $id];
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /** Moves an appointment to $to only if it is currently in one of the $from statuses. False means someone got there first. */
    public static function transition(int $id, array $from, string $to): bool
    {
        $in = implode(',', array_fill(0, count($from), '?'));
        return Database::exec("UPDATE appointments SET status = ? WHERE id = ? AND status IN ($in)", array_merge([$to, $id], $from)) > 0;
    }
}
