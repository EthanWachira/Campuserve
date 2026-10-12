<?php
class DashboardController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireLogin();
        match ($u['role']) {
            'student'  => $this->student($u),
            'lecturer' => $this->lecturer($u),
            'staff'    => $this->staff($u),
            'admin'    => $this->admin($u),
        };
    }

    private function student(array $u): void
    {
        $counts = Stats::statusCounts($u);
        $active = Ticket::search($u, ['active' => 1])['rows'];
        $this->render('dashboard/student', [
            'title' => 'My requests', 'u' => $u, 'counts' => $counts,
            'active' => array_slice($active, 0, 5), 'activeTotal' => count($active),
            'recent' => Ticket::search($u, ['status' => 'resolved'])['rows'],
            'appointments' => Appointment::upcomingForStudent((int) $u['id']),
            'notifications' => Notification::recent((int) $u['id'], 5),
            'avgHours' => Stats::avgResolutionHours(['role' => 'admin'] + $u),
        ]);
    }

    private function staff(array $u): void
    {
        $counts = Stats::statusCounts($u);
        $queue = Ticket::search($u, ['active' => 1]);
        $this->render('dashboard/staff', [
            'title' => $u['department_name'] . ' queue', 'u' => $u, 'counts' => $counts,
            'queue' => array_slice($queue['rows'], 0, 8), 'queueTotal' => $queue['total'],
            'review' => Ticket::search($u, ['review' => 1, 'active' => 1])['rows'],
            'mine' => Ticket::search($u, ['mine' => 1, 'active' => 1])['total'],
            'avgHours' => Stats::avgResolutionHours($u),
            'resolvedWeek' => Stats::resolvedSince($u, 7),
            'perDay' => Stats::perDay($u, 14),
            'byCategory' => Stats::byCategory($u),
            'ml' => Stats::mlAgreement((int) $u['department_id']),
        ]);
    }

    private function lecturer(array $u): void
    {
        $this->render('dashboard/lecturer', [
            'title' => 'Lecturer dashboard', 'u' => $u,
            'appointments' => Appointment::upcomingForLecturer((int) $u['id']),
            'apptCounts' => Appointment::countsForLecturer((int) $u['id']),
            'availability' => Appointment::availability((int) $u['id']),
            'assigned' => Ticket::search($u, ['active' => 1]),
            'counts' => Stats::statusCounts($u),
        ]);
    }

    private function admin(array $u): void
    {
        $counts = Stats::statusCounts($u);
        $this->render('dashboard/admin', [
            'title' => 'System overview', 'u' => $u, 'counts' => $counts,
            'roles' => User::countByRole(),
            'avgHours' => Stats::avgResolutionHours($u),
            'perDay' => Stats::perDay($u, 14),
            'byDept' => Stats::byDepartment(),
            'ml' => Stats::mlAgreement(),
            'mlStatus' => MLClient::status(),
            'unassigned' => Ticket::search($u, ['status' => 'open'])['total'],
            'audit' => Stats::recentAudit(7),
        ]);
    }
}
