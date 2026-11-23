<?php
/** Appointment booking for students and lecturers. Route examples: ?r=appointments/index, ?r=appointments/book */
class AppointmentsController extends Controller
{
    /** Student: my appointments. Lecturer: requests to answer, confirmed meetings and history. */
    public function indexAction(): void
    {
        $u = $this->requireRole('student', 'lecturer');
        $upcoming = Appointment::forUser($u, 'upcoming');
        $this->render('appointments/index', [
            'title'    => 'Appointments', 'u' => $u,
            'pending'  => array_values(array_filter($upcoming, fn($a) => $a['status'] === 'pending')),
            'upcoming' => $upcoming,
            'history'  => Appointment::forUser($u, 'history'),
        ]);
    }

    /** Student: pick a lecturer and a day, choose a free slot, request it. */
    public function bookAction(): void
    {
        $u = $this->requireRole('student');
        if ($this->isPost()) { $this->store($u); return; }

        $lecturers = Appointment::lecturers();
        $lecturerId = (int) ($_GET['lecturer'] ?? 0);
        $lecturer = null;
        foreach ($lecturers as $l) {
            if ((int) $l['id'] === $lecturerId) { $lecturer = $l; break; }
        }
        $date = trim((string) ($_GET['date'] ?? ''));
        $cfg = Appointment::cfg();
        $slots = $dates = $week = [];
        if ($lecturer) {
            $week = Appointment::availabilityByDay($lecturerId);
            $dates = Appointment::nextDates($lecturerId, (int) $u['id']);
            if ($date !== '') $slots = Appointment::slotsFor($lecturerId, $date, (int) $u['id']);
        }
        $this->render('appointments/book', [
            'title' => 'Book an appointment', 'u' => $u, 'lecturers' => $lecturers, 'lecturer' => $lecturer,
            'date' => $date, 'slots' => $slots, 'dates' => $dates, 'week' => $week, 'cfg' => $cfg,
            'min' => date('Y-m-d'), 'max' => date('Y-m-d', strtotime('+' . (int) $cfg['max_days_ahead'] . ' days')),
        ]);
        clear_old();
    }

    private function store(array $u): void
    {
        $this->requireCsrf();
        $lecturerId = (int) ($_POST['lecturer'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $purpose = trim((string) ($_POST['purpose'] ?? ''));
        $back = ['lecturer' => $lecturerId, 'date' => $date];

        $lecturer = Database::one('SELECT id, full_name FROM users WHERE id = ? AND role = "lecturer" AND status = "active"', [$lecturerId]);
        if (!$lecturer) {
            flash('error', 'Choose a lecturer to book with.');
            redirect('appointments/book');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            flash('error', 'Pick one of the available times.');
            redirect('appointments/book', $back);
        }
        $len = mb_strlen($purpose);
        if ($len < 5 || $len > 255) {
            with_old(['purpose' => $purpose]);
            flash('error', 'Tell the lecturer what the meeting is about (5 to 255 characters).');
            redirect('appointments/book', $back);
        }

        [$ok, $result] = Appointment::book((int) $u['id'], $lecturerId, $date, $time, $purpose);
        if (!$ok) {
            with_old(['purpose' => $purpose]);
            flash('error', (string) $result);
            redirect('appointments/book', $back);
        }

        $a = Appointment::find((int) $result);
        Notification::send($lecturerId, mb_substr('New appointment request from ' . $u['full_name'], 0, 150),
            fmt_day($a['starts_at']) . ', ' . fmt_time($a['starts_at']) . '. ' . $purpose, '?r=appointments/index');
        Auth::audit((int) $u['id'], 'appointment_requested', '#' . $a['id'] . ' with lecturer ' . $lecturerId);
        clear_old();
        flash('success', 'Appointment requested for ' . fmt_day($a['starts_at']) . ', ' . fmt_time($a['starts_at'])
            . '. You will be notified when ' . $lecturer['full_name'] . ' responds.');
        redirect('appointments/index');
    }

    /** Lecturer: confirm or decline a pending request. */
    public function respondAction(): void
    {
        $u = $this->requireRole('lecturer');
        if (!$this->isPost()) redirect('appointments/index');
        $this->requireCsrf();
        $a = $this->participant($u, (int) ($_POST['id'] ?? 0));
        $decision = (string) ($_POST['decision'] ?? '');
        if (!in_array($decision, ['confirm', 'decline'], true)) redirect('appointments/index');
        if (strtotime($a['starts_at']) < time()) {
            flash('error', 'That time has already passed.');
            redirect('appointments/index');
        }

        $to = $decision === 'confirm' ? 'confirmed' : 'declined';
        if (!Appointment::transition((int) $a['id'], ['pending'], $to)) {
            flash('info', 'That request was already handled.');
            redirect('appointments/index');
        }
        Notification::send((int) $a['student_id'], 'Your appointment was ' . $to,
            fmt_day($a['starts_at']) . ', ' . fmt_time($a['starts_at']) . ' with ' . $u['full_name'] . '.', '?r=appointments/index');
        Auth::audit((int) $u['id'], 'appointment_' . $to, '#' . $a['id']);
        flash('success', $to === 'confirmed' ? 'Appointment confirmed. ' . $a['student_name'] . ' has been notified.'
                                              : 'Request declined. ' . $a['student_name'] . ' has been notified.');
        redirect('appointments/index');
    }

    /** Student: withdraw a request or cancel a confirmed appointment. Lecturer: cancel a confirmed appointment. */
    public function cancelAction(): void
    {
        $u = $this->requireRole('student', 'lecturer');
        if (!$this->isPost()) redirect('appointments/index');
        $this->requireCsrf();
        $a = $this->participant($u, (int) ($_POST['id'] ?? 0));
        if (strtotime($a['starts_at']) < time()) {
            flash('error', 'That appointment has already passed.');
            redirect('appointments/index');
        }

        $from = $u['role'] === 'lecturer' ? ['confirmed'] : ['pending', 'confirmed'];   // lecturers decline pending requests instead
        if (!Appointment::transition((int) $a['id'], $from, 'cancelled')) {
            flash('info', 'That appointment can no longer be cancelled.');
            redirect('appointments/index');
        }
        $other = $u['role'] === 'lecturer' ? (int) $a['student_id'] : (int) $a['lecturer_id'];
        Notification::send($other, mb_substr('Appointment cancelled by ' . $u['full_name'], 0, 150),
            fmt_day($a['starts_at']) . ', ' . fmt_time($a['starts_at']) . '.', '?r=appointments/index');
        Auth::audit((int) $u['id'], 'appointment_cancelled', '#' . $a['id']);
        flash('success', 'Appointment cancelled. The other person has been notified.');
        redirect('appointments/index');
    }

    /** Lecturer: record that a confirmed meeting took place. */
    public function completeAction(): void
    {
        $u = $this->requireRole('lecturer');
        if (!$this->isPost()) redirect('appointments/index');
        $this->requireCsrf();
        $a = $this->participant($u, (int) ($_POST['id'] ?? 0));
        if (strtotime($a['starts_at']) > time()) {
            flash('error', 'You can mark an appointment completed once its time has started.');
            redirect('appointments/index');
        }
        if (Appointment::transition((int) $a['id'], ['confirmed'], 'completed')) {
            Auth::audit((int) $u['id'], 'appointment_completed', '#' . $a['id']);
            flash('success', 'Marked as completed.');
        }
        redirect('appointments/index');
    }

    /** Lecturer: edit weekly availability (up to two windows per weekday). */
    public function availabilityAction(): void
    {
        $u = $this->requireRole('lecturer');
        $times = Appointment::timeOptions();
        $slotMin = (int) Appointment::cfg()['slot_minutes'];
        $current = Appointment::availabilityByDay((int) $u['id']);

        if ($this->isPost()) {
            $this->requireCsrf();
            $windows = []; $errors = []; $posted = [];
            foreach (Appointment::DAYS as $dow => $label) {
                $day = [];
                for ($j = 0; $j < 2; $j++) {
                    $cell = $_POST['av'][$dow][$j] ?? null;
                    $s = is_array($cell) && is_string($cell['start'] ?? null) ? trim($cell['start']) : '';
                    $e = is_array($cell) && is_string($cell['end'] ?? null) ? trim($cell['end']) : '';
                    if ($s === '' && $e === '') continue;
                    $posted[$dow][$j] = [$s, $e];
                    if (!in_array($s, $times, true) || !in_array($e, $times, true) || $s >= $e) {
                        $errors[] = "$label: choose a start time that is before the end time.";
                        continue;
                    }
                    if ((strtotime("1970-01-01 $e") - strtotime("1970-01-01 $s")) / 60 < $slotMin) {
                        $errors[] = "$label: a window must be at least $slotMin minutes long.";
                        continue;
                    }
                    $day[] = [$s, $e];
                }
                usort($day, fn($x, $y) => strcmp($x[0], $y[0]));
                for ($k = 1; $k < count($day); $k++) {
                    if ($day[$k][0] < $day[$k - 1][1]) { $errors[] = "$label: the two windows overlap."; break; }
                }
                foreach ($day as [$s, $e]) $windows[] = [$dow, $s, $e];
            }
            if (!$errors) {
                Appointment::saveAvailability((int) $u['id'], $windows);
                Auth::audit((int) $u['id'], 'availability_updated', count($windows) . ' window(s)');
                flash('success', 'Availability saved. Students can now book inside these windows.');
                redirect('appointments/availability');
            }
            flash('error', implode(' ', array_unique($errors)));
            $current = $posted;   // show what they typed, not what is stored
            foreach ($current as $dow => $ws) $current[$dow] = array_values($ws);
        }

        $this->render('appointments/availability', [
            'title' => 'Availability', 'u' => $u, 'current' => $current, 'times' => $times, 'slotMin' => $slotMin,
        ]);
    }

    /** Loads an appointment the signed-in user is part of. Anything else is a 404 so ids cannot be probed. */
    private function participant(array $u, int $id): array
    {
        $a = Appointment::find($id);
        if (!$a || !in_array((int) $u['id'], [(int) $a['student_id'], (int) $a['lecturer_id']], true)) App::notFound();
        // Respond/complete are lecturer actions: the lecturer on the appointment, not just any lecturer.
        if ($u['role'] === 'lecturer' && (int) $a['lecturer_id'] !== (int) $u['id']) App::notFound();
        return $a;
    }
}
