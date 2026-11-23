<?php
class ReportsController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireRole('admin', 'staff');
        $f = Report::filters($u, $_GET);
        $this->render('reports/index', [
            'title' => 'Reports', 'u' => $u, 'f' => $f,
            'sum' => Report::summary($u, $f),
            'status' => Report::byStatus($u, $f),
            'priority' => Report::byPriority($u, $f),
            'category' => Report::byCategory($u, $f),
            'department' => $u['role'] === 'admin' ? Report::byDepartment($u, $f) : [],
            'assignee' => Report::byAssignee($u, $f),
            'trend' => Report::trend($u, $f),
            'hours' => Report::byHour($u, $f),
            'appts' => $u['role'] === 'admin' ? Report::appointmentSummary($f) : [],
            'categories' => Ticket::categories(),
            'departments' => $u['role'] === 'admin' ? Database::all('SELECT id, name FROM departments ORDER BY name') : [],
        ]);
    }

    /** CSV download. type = tickets | summary | appointments (admin only). */
    public function exportAction(): void
    {
        $u = $this->requireRole('admin', 'staff');
        $f = Report::filters($u, $_GET);
        $type = (string) ($_GET['type'] ?? 'tickets');
        $stamp = $f['from'] . '_to_' . $f['to'];
        Auth::audit((int) $u['id'], 'report_export', "$type $stamp");

        if ($type === 'summary') {
            $s = Report::summary($u, $f);
            $rows = [['Measure', 'Value'], ['Period', $f['from'] . ' to ' . $f['to']], ['Requests', $s['total']], ['Resolved or closed', $s['resolved']],
                     ['Still active', $s['active']], ['High priority', $s['high']], ['Category needs review', $s['review']],
                     ['Average hours to resolve', $s['avg_hours'] === null ? '' : round($s['avg_hours'], 2)],
                     ['Resolved within service target (%)', $s['sla_rate'] ?? ''], ['Currently overdue', $s['overdue']], [], ['By category', 'Requests', 'Resolved', 'Avg hours']];
            foreach (Report::byCategory($u, $f) as $r) $rows[] = [$r['name'], $r['n'], $r['resolved'], $r['h'] === null ? '' : round((float) $r['h'], 2)];
            if ($u['role'] === 'admin') {
                $rows[] = []; $rows[] = ['By department', 'Requests', 'Resolved', 'Avg hours', 'Overdue'];
                foreach (Report::byDepartment($u, $f) as $r) $rows[] = [$r['name'], $r['n'], $r['resolved'], $r['h'] === null ? '' : round((float) $r['h'], 2), $r['overdue']];
            }
            $rows[] = []; $rows[] = ['By assignee', 'Requests', 'Resolved', 'Avg hours', 'Overdue'];
            foreach (Report::byAssignee($u, $f) as $r) $rows[] = [$r['name'], $r['n'], $r['resolved'], $r['h'] === null ? '' : round((float) $r['h'], 2), $r['overdue']];
            $this->csv("campuserve-summary-$stamp.csv", $rows);
        }
        if ($type === 'appointments') {
            if ($u['role'] !== 'admin') { http_response_code(403); View::render('errors/403', ['title' => 'Access denied'], 'layouts/plain'); exit; }
            $rows = [['Lecturer', 'Student', 'Admission no.', 'Starts', 'Ends', 'Purpose', 'Status']];
            foreach (Report::appointmentRows($f) as $r) $rows[] = [$r['lecturer'], $r['student'], $r['student_no'], $r['starts_at'], $r['ends_at'], $r['purpose'], $r['status']];
            $this->csv("campuserve-appointments-$stamp.csv", $rows);
        }
        $rows = [['Reference', 'Subject', 'Admission no.', 'Student', 'Category', 'Department', 'Priority', 'Status', 'Assigned to', 'Classified by',
                  'Needs review', 'Model confidence', 'Opened', 'Resolved', 'Hours to resolve', 'Target hours', 'Service target']];
        foreach (Report::detailRows($u, $f) as $r) {
            $rows[] = [$r['reference_no'], $r['subject'], $r['student_no'], $r['student'], $r['category'], $r['department'], $r['priority'], $r['status'],
                       $r['assigned_to'], $r['classification_source'], $r['needs_review'] ? 'yes' : 'no', $r['confidence'] === null ? '' : round((float) $r['confidence'] * 100, 1) . '%',
                       $r['created_at'], $r['resolved_at'] ?? '', $r['hours_to_resolve'] ?? '', $r['target_hours'], $r['sla']];
        }
        $this->csv("campuserve-requests-$stamp.csv", $rows);
    }

    /** Stream a UTF-8 CSV that opens correctly in Excel. Cells starting with = + - @ are neutralised (CSV injection). */
    private function csv(string $name, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '', $name) . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map(function ($v) {
                $v = (string) $v;
                return $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false && !is_numeric($v) ? "'" . $v : $v;
            }, $row));
        }
        fclose($out);
        exit;
    }
}
