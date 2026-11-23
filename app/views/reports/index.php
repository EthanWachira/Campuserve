<?php
$q = ['from' => $f['from'], 'to' => $f['to'], 'category' => $f['category'] ?: null, 'department' => $f['department'] ?: null];
$q = array_filter($q, fn($v) => $v !== null);
$nz = fn($h) => $h === null ? '-' : hours_label((float) $h);
?>
<div class="page-head">
    <div><h2>Reports</h2><p><?= e(fmt_day($f['from'])) ?> to <?= e(fmt_day($f['to'])) ?><?= $u['role'] === 'staff' ? ' &middot; ' . e($u['department_name']) : '' ?></p></div>
    <div class="row-actions no-print">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('reports/export', $q + ['type' => 'tickets'])) ?>">Export requests (CSV)</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('reports/export', $q + ['type' => 'summary'])) ?>">Export summary (CSV)</a>
        <?php if ($u['role'] === 'admin'): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('reports/export', $q + ['type' => 'appointments'])) ?>">Export appointments (CSV)</a><?php endif; ?>
        <button class="btn btn-primary btn-sm" type="button" data-print>Print / save as PDF</button>
    </div>
</div>

<form class="filters no-print" method="get" action="<?= e(url('reports/index')) ?>"><input type="hidden" name="r" value="reports/index">
    <div class="field"><label for="from">From</label><input type="date" id="from" name="from" value="<?= e($f['from']) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
    <div class="field"><label for="to">To</label><input type="date" id="to" name="to" value="<?= e($f['to']) ?>" max="<?= e(date('Y-m-d')) ?>"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>
        <?php foreach ($categories as $c): if ($u['role'] === 'staff' && (int) $c['department_id'] !== (int) $u['department_id']) continue; ?>
            <option value="<?= (int) $c['id'] ?>"<?= $f['category'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($departments): ?><div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All departments</option>
        <?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"<?= $f['department'] === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <button class="btn btn-primary" type="submit">Apply</button>
</form>

<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= $sum['total'] ?></div><div class="l">Requests in period</div></div>
    <div class="kpi"><div class="n"><?= $sum['resolved'] ?></div><div class="l">Resolved or closed</div></div>
    <div class="kpi"><div class="n"><?= $sum['active'] ?></div><div class="l">Still active</div></div>
    <div class="kpi"><div class="n"><?= e($nz($sum['avg_hours'])) ?></div><div class="l">Average time to resolve</div></div>
    <div class="kpi"><div class="n"><?= $sum['sla_rate'] === null ? '-' : $sum['sla_rate'] . '%' ?></div><div class="l">Resolved within target</div></div>
    <div class="kpi<?= $sum['overdue'] ? ' is-alert' : '' ?>"><div class="n"><?= $sum['overdue'] ?></div><div class="l">Overdue right now</div></div>
</div>

<div class="grid-eq">
    <section class="sec"><div class="sec-head"><h3><?= count($trend) > 0 && (strtotime($f['to']) - strtotime($f['from'])) / 86400 >= 90 ? 'Requests per week' : 'Requests per day' ?></h3></div>
        <?= $sum['total'] ? day_columns($trend) : '<p class="empty">No requests in this period.</p>' ?></section>
    <section class="sec"><div class="sec-head"><h3>By status</h3></div><div class="donut-wrap"><?= status_donut($status) ?></div></section>
</div>

<div class="grid-eq">
    <section class="sec"><div class="sec-head"><h3>By category</h3></div>
        <?php if (!$category): ?><p class="empty">Nothing to show yet.</p><?php else: ?>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Category</th><th class="right">Requests</th><th class="right">Resolved</th><th class="right">Avg time</th></tr></thead><tbody>
        <?php foreach ($category as $r): ?><tr><td><?= e($r['name']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right"><?= (int) $r['resolved'] ?></td><td class="right nowrap"><?= e($nz($r['h'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></section>
    <section class="sec"><div class="sec-head"><h3>By priority</h3></div>
        <?php if (!$priority): ?><p class="empty">Nothing to show yet.</p><?php else: ?>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Priority</th><th class="right">Requests</th><th class="right">Target</th><th class="right">Avg time</th></tr></thead><tbody>
        <?php foreach ($priority as $r): ?><tr><td><?= priority_badge($r['priority']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right"><?= Sla::hours($r['priority']) ?> h</td><td class="right nowrap"><?= e($nz($r['h'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></section>
</div>

<?php if ($department): ?>
<section class="sec"><div class="sec-head"><h3>By department</h3></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Department</th><th class="right">Requests</th><th class="right">Resolved</th><th class="right">Overdue</th><th class="right">Avg time</th></tr></thead><tbody>
    <?php foreach ($department as $r): ?><tr><td><?= e($r['name']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right"><?= (int) $r['resolved'] ?></td><td class="right"><?= (int) $r['overdue'] ?></td><td class="right nowrap"><?= e($nz($r['h'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
<?php endif; ?>

<div class="grid-eq">
    <section class="sec"><div class="sec-head"><h3>Workload by person</h3></div>
        <?php if (!$assignee): ?><p class="empty">Nothing to show yet.</p><?php else: ?>
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Handled by</th><th class="right">Requests</th><th class="right">Resolved</th><th class="right">Overdue</th><th class="right">Avg time</th></tr></thead><tbody>
        <?php foreach ($assignee as $r): ?><tr><td><?= e($r['name']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right"><?= (int) $r['resolved'] ?></td><td class="right"><?= (int) $r['overdue'] ?></td><td class="right nowrap"><?= e($nz($r['h'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?></section>
    <section class="sec"><div class="sec-head"><h3>When requests arrive (hour of day)</h3></div>
        <?= array_sum(array_column($hours, 1)) ? bar_list(array_values(array_filter($hours, fn($h) => $h[1] > 0))) : '<p class="empty">Nothing to show yet.</p>' ?></section>
</div>

<?php if ($appts): ?>
<section class="sec"><div class="sec-head"><h3>Appointments by lecturer</h3></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Lecturer</th><th class="right">Booked</th><th class="right">Confirmed or done</th><th class="right">Declined</th><th class="right">Cancelled</th></tr></thead><tbody>
    <?php foreach ($appts as $r): ?><tr><td><?= e($r['lecturer']) ?></td><td class="right"><?= (int) $r['n'] ?></td><td class="right"><?= (int) $r['kept'] ?></td><td class="right"><?= (int) $r['declined'] ?></td><td class="right"><?= (int) $r['cancelled'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
<?php endif; ?>
<p class="note print-only">Generated <?= e(date('j M Y, g:i a')) ?> by <?= e($u['full_name']) ?>.</p>
