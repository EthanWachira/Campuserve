<?php
class Ticket
{
    public const PER_PAGE = 12;

    /* ---------- access rules ---------- */

    /** SQL fragment (alias t) limiting which tickets a user may see. */
    public static function scope(array $u): array
    {
        return match ($u['role']) {
            'student'  => ['t.student_id = ?', [$u['id']]],
            'staff'    => ['(t.department_id = ? OR t.assigned_to = ?)', [$u['department_id'], $u['id']]],
            'lecturer' => ['t.assigned_to = ?', [$u['id']]],
            default    => ['1 = 1', []],
        };
    }

    public static function canView(array $u, array $t): bool
    {
        return match ($u['role']) {
            'student'  => (int) $t['student_id'] === (int) $u['id'],
            'staff'    => (int) $t['department_id'] === (int) $u['department_id'] || (int) $t['assigned_to'] === (int) $u['id'],
            'lecturer' => (int) $t['assigned_to'] === (int) $u['id'],
            'admin'    => true,
            default    => false,
        };
    }

    public static function canManage(array $u, array $t): bool
    {
        return $u['role'] !== 'student' && self::canView($u, $t);
    }

    /* ---------- reads ---------- */

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT t.*, s.full_name AS student_name, s.identifier AS student_no, s.programme, s.email AS student_email,
                    c.name AS category_name, d.name AS department_name, a.full_name AS assignee_name,
                    mp.confidence AS ml_confidence, mp.predicted_category_id, pc.name AS predicted_name, mp.model_version
               FROM tickets t
               JOIN users s ON s.id = t.student_id
               JOIN categories c ON c.id = t.category_id
               JOIN departments d ON d.id = t.department_id
               LEFT JOIN users a ON a.id = t.assigned_to
               LEFT JOIN ml_predictions mp ON mp.id = (SELECT MIN(id) FROM ml_predictions WHERE ticket_id = t.id)
               LEFT JOIN categories pc ON pc.id = mp.predicted_category_id
              WHERE t.id = ?', [$id]);
    }

    /** @return array{rows:array,total:int,pages:int,page:int} */
    public static function search(array $u, array $f = [], int $page = 1): array
    {
        [$where, $params] = self::scope($u);
        $conds = [$where];
        if (!empty($f['status']) && isset(TICKET_STATUSES[$f['status']])) { $conds[] = 't.status = ?'; $params[] = $f['status']; }
        if (!empty($f['active'])) { $conds[] = 't.status IN ("open","assigned","in_progress")'; }
        if (!empty($f['category'])) { $conds[] = 't.category_id = ?'; $params[] = (int) $f['category']; }
        if (!empty($f['review'])) { $conds[] = 't.needs_review = 1'; }
        if (!empty($f['mine'])) { $conds[] = 't.assigned_to = ?'; $params[] = $u['id']; }
        if (!empty($f['q'])) {
            $conds[] = '(t.reference_no LIKE ? OR t.subject LIKE ? OR s.full_name LIKE ?)';
            array_push($params, '%' . $f['q'] . '%', '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        $from = ' FROM tickets t JOIN users s ON s.id = t.student_id JOIN categories c ON c.id = t.category_id
                  JOIN departments d ON d.id = t.department_id LEFT JOIN users a ON a.id = t.assigned_to
                  WHERE ' . implode(' AND ', $conds);
        $total = (int) Database::value('SELECT COUNT(*)' . $from, $params);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $order = $u['role'] === 'student'
            ? 't.created_at DESC'
            : 'FIELD(t.status,"open","assigned","in_progress","resolved","closed"), FIELD(t.priority,"high","medium","low"), t.created_at';
        $rows = Database::all(
            'SELECT t.*, s.full_name AS student_name, c.name AS category_name, d.name AS department_name, a.full_name AS assignee_name'
            . $from . ' ORDER BY ' . $order . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public static function updates(int $ticketId, bool $includeInternal): array
    {
        return Database::all(
            'SELECT tu.*, u.full_name, u.role FROM ticket_updates tu LEFT JOIN users u ON u.id = tu.user_id
              WHERE tu.ticket_id = ?' . ($includeInternal ? '' : ' AND tu.is_internal = 0') . ' ORDER BY tu.created_at, tu.id', [$ticketId]);
    }

    public static function categories(): array
    {
        return Database::all('SELECT c.id, c.name, c.department_id, d.name AS department_name FROM categories c JOIN departments d ON d.id = c.department_id ORDER BY c.id');
    }

    /** People a ticket in this department can be assigned to. */
    public static function assignees(int $departmentId): array
    {
        return Database::all(
            'SELECT id, full_name, role FROM users WHERE status = "active" AND (
                (role = "staff" AND department_id = ?) OR (role = "lecturer" AND ? = (SELECT id FROM departments WHERE code = "FAC")))
             ORDER BY role, full_name', [$departmentId, $departmentId]);
    }

    /* ---------- writes ---------- */

    /** @return array{0:int,1:string} [id, reference] */
    public static function create(array $student, string $subject, string $description, array $cls): array
    {
        $cat = Database::one('SELECT id, department_id FROM categories WHERE name = ?', [$cls['category']])
            ?? Database::one('SELECT id, department_id FROM categories WHERE name = "General Enquiries"');
        Database::begin();
        try {
            Database::exec(
                'INSERT INTO tickets (reference_no, student_id, subject, description, category_id, department_id, priority, classification_source, needs_review)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                ['PENDING-' . bin2hex(random_bytes(6)), $student['id'], $subject, $description, $cat['id'], $cat['department_id'],
                 in_array($cls['priority'], ['low', 'medium', 'high'], true) ? $cls['priority'] : 'low',
                 $cls['source'], !empty($cls['needs_review']) ? 1 : 0]);
            $id = Database::insertId();
            $ref = sprintf('CS-%s-%06d', date('Y'), $id);
            Database::exec('UPDATE tickets SET reference_no = ? WHERE id = ?', [$ref, $id]);
            if ($cls['source'] === 'ml') {
                Database::exec('INSERT INTO ml_predictions (ticket_id, predicted_category_id, confidence, model_version) VALUES (?,?,?,?)',
                    [$id, $cat['id'], $cls['confidence'], $cls['model_version']]);
            }
            foreach (User::staffIn((int) $cat['department_id']) as $s) {
                Notification::send((int) $s['id'], 'New request ' . $ref, $subject, '?r=tickets/show&id=' . $id);
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
        return [$id, $ref];
    }

    public static function addUpdate(int $ticketId, ?int $userId, string $message, ?string $old = null, ?string $new = null, bool $internal = false): void
    {
        Database::exec('INSERT INTO ticket_updates (ticket_id, user_id, message, old_status, new_status, is_internal) VALUES (?,?,?,?,?,?)',
            [$ticketId, $userId, $message, $old, $new, $internal ? 1 : 0]);
        Database::exec('UPDATE tickets SET updated_at = NOW() WHERE id = ?', [$ticketId]);
    }

    public static function changeStatus(array $t, string $new, array $actor, string $message): void
    {
        if (!isset(TICKET_STATUSES[$new]) || $new === $t['status']) return;
        $resolvedSql = in_array($new, ['resolved', 'closed'], true) ? 'COALESCE(resolved_at, NOW())' : 'NULL';
        Database::exec("UPDATE tickets SET status = ?, resolved_at = $resolvedSql WHERE id = ?", [$new, $t['id']]);
        self::addUpdate((int) $t['id'], (int) $actor['id'], $message !== '' ? $message : 'Status changed to ' . status_label($new) . '.', $t['status'], $new);
        Notification::send((int) $t['student_id'], $t['reference_no'] . ' is now ' . status_label($new), $message !== '' ? mb_strimwidth($message, 0, 140, '...') : null, '?r=tickets/show&id=' . $t['id']);
    }

    public static function assign(array $t, int $assigneeId, array $actor): bool
    {
        $ok = array_filter(self::assignees((int) $t['department_id']), fn($a) => (int) $a['id'] === $assigneeId);
        if (!$ok) return false;
        $name = array_values($ok)[0]['full_name'];
        $newStatus = $t['status'] === 'open' ? 'assigned' : $t['status'];
        Database::exec('UPDATE tickets SET assigned_to = ?, status = ? WHERE id = ?', [$assigneeId, $newStatus, $t['id']]);
        self::addUpdate((int) $t['id'], (int) $actor['id'], "Assigned to $name.", $t['status'], $newStatus);
        if ($assigneeId !== (int) $actor['id']) {
            Notification::send($assigneeId, 'Request ' . $t['reference_no'] . ' assigned to you', $t['subject'], '?r=tickets/show&id=' . $t['id']);
        }
        Notification::send((int) $t['student_id'], 'Your request ' . $t['reference_no'] . ' was assigned', "Assigned to $name.", '?r=tickets/show&id=' . $t['id']);
        return true;
    }

    /** Staff confirm the model's category as correct. Feeds the accuracy report. */
    public static function confirmCategory(array $t, array $actor): void
    {
        Database::exec('UPDATE tickets SET needs_review = 0 WHERE id = ?', [$t['id']]);
        Database::exec('UPDATE ml_predictions SET final_category_id = ? WHERE ticket_id = ?', [$t['category_id'], $t['id']]);
        self::addUpdate((int) $t['id'], (int) $actor['id'], 'Category confirmed: ' . $t['category_name'] . '.', null, null, true);
    }

    /** Staff correct the category. Re-routes the request when the department changes. */
    public static function reclassify(array $t, int $categoryId, array $actor): bool
    {
        $cat = Database::one('SELECT id, name, department_id FROM categories WHERE id = ?', [$categoryId]);
        if (!$cat || (int) $cat['id'] === (int) $t['category_id']) return false;
        $moved = (int) $cat['department_id'] !== (int) $t['department_id'];
        $open = in_array($t['status'], ['open', 'assigned', 'in_progress'], true);
        Database::exec('UPDATE tickets SET category_id = ?, department_id = ?, needs_review = 0,
                               assigned_to = IF(?, NULL, assigned_to), status = IF(? AND ?, "open", status) WHERE id = ?',
            [$cat['id'], $cat['department_id'], $moved ? 1 : 0, $moved ? 1 : 0, $open ? 1 : 0, $t['id']]);
        Database::exec('UPDATE ml_predictions SET final_category_id = ? WHERE ticket_id = ?', [$cat['id'], $t['id']]);
        self::addUpdate((int) $t['id'], (int) $actor['id'], 'Category changed from ' . $t['category_name'] . ' to ' . $cat['name'] . ($moved ? ' and re-routed.' : '.'), null, null, true);
        if ($moved) {
            foreach (User::staffIn((int) $cat['department_id']) as $s) {
                Notification::send((int) $s['id'], 'Re-routed request ' . $t['reference_no'], $t['subject'], '?r=tickets/show&id=' . $t['id']);
            }
        }
        return true;
    }
}
