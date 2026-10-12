<?php $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri']; $av = []; foreach ($availability as $a) { $av[$a['day_of_week']][] = substr($a['start_time'], 0, 5) . ' to ' . substr($a['end_time'], 0, 5); } ?>
<div class="page-head"><div><h2><?= e($u['full_name']) ?></h2><p>Appointments and requests assigned to you.</p></div></div>
<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= (int) ($apptCounts['pending'] ?? 0) ?></div><div class="l">Appointment requests waiting</div></div>
    <div class="kpi"><div class="n"><?= (int) ($apptCounts['confirmed'] ?? 0) ?></div><div class="l">Confirmed appointments</div></div>
    <div class="kpi"><div class="n"><?= $assigned['total'] ?></div><div class="l">Assigned requests open</div></div>
    <div class="kpi"><div class="n"><?= $counts['resolved'] + $counts['closed'] ?></div><div class="l">Requests resolved</div></div>
</div>
<div class="grid-2">
    <div>
        <section class="sec">
            <div class="sec-head"><h3>Upcoming appointments</h3></div>
            <?php if (!$appointments): ?><p class="empty">No upcoming appointments.</p><?php else: ?>
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th>When</th><th>Student</th><th>Purpose</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($appointments as $a): ?>
                <tr><td class="nowrap"><b><?= e(fmt_day($a['starts_at'])) ?></b><div class="sub"><?= e(fmt_time($a['starts_at'])) ?></div></td><td><?= e($a['student_name']) ?><div class="sub"><?= e($a['student_no']) ?></div></td><td><?= e($a['purpose']) ?></td><td><?= badge(ucfirst($a['status']), $a['status'] === 'confirmed' ? 'ok' : 'review') ?></td></tr>
            <?php endforeach; ?></tbody></table></div><?php endif; ?>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>Requests assigned to you</h3><a href="<?= e(url('tickets/index')) ?>">All</a></div>
            <?php View::partial('ticket_rows', ['rows' => array_slice($assigned['rows'], 0, 6), 'showStudent' => true, 'emptyText' => 'Nothing assigned to you right now.']); ?>
        </section>
    </div>
    <section class="sec">
        <div class="sec-head"><h3>Weekly availability</h3></div>
        <div class="week">
            <?php foreach ($days as $d => $label): ?>
                <div class="<?= isset($av[$d]) ? 'on' : 'off' ?>"><b><?= $label ?></b><?= isset($av[$d]) ? e(implode(', ', $av[$d])) : 'Not available' ?></div>
            <?php endforeach; ?>
        </div>
        <p class="note">Editing availability and confirming appointments arrives with the booking module.</p>
    </section>
</div>
