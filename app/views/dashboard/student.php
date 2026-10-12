<div class="page-head">
    <div><h2>Hello, <?= e(explode(' ', $u['full_name'])[0]) ?></h2><p><?= e($u['programme']) ?>, year <?= (int) $u['year_of_study'] ?></p></div>
    <a class="btn btn-primary" href="<?= e(url('tickets/create')) ?>"><?= icon('plus') ?>New request</a>
</div>
<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= $counts['open'] + $counts['assigned'] + $counts['in_progress'] ?></div><div class="l">Active requests</div></div>
    <div class="kpi"><div class="n"><?= $counts['in_progress'] ?></div><div class="l">Being worked on</div></div>
    <div class="kpi"><div class="n"><?= $counts['resolved'] ?></div><div class="l">Waiting for you to close</div></div>
    <div class="kpi"><div class="n"><?= e(hours_label($avgHours)) ?></div><div class="l">Typical time to resolve (last 30 days)</div></div>
</div>
<div class="grid-2">
    <div>
        <section class="sec">
            <div class="sec-head"><h3>Active requests</h3><a href="<?= e(url('tickets/index')) ?>">All requests</a></div>
            <?php if (!$active): ?>
                <p class="empty">Nothing in progress. When you submit a request it appears here with its status.</p>
            <?php else: ?>
            <ul class="list">
                <?php foreach ($active as $t): $step = status_step($t['status']); ?>
                <li>
                    <a class="t" href="<?= e(url('tickets/show', ['id' => $t['id']])) ?>"><?= e($t['subject']) ?></a>
                    <span class="s"><?= e($t['reference_no']) ?> &middot; <?= e($t['department_name']) ?> &middot; <?= status_badge($t['status']) ?> &middot; opened <?= e(time_ago($t['created_at'])) ?></span>
                    <div class="mini-trail" aria-hidden="true"><?php foreach (array_keys(TICKET_STATUSES) as $i => $k): ?><i class="<?= $i <= $step ? 'on' : '' ?><?= $i === $step ? ' last' : '' ?>"></i><?php endforeach; ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($activeTotal > count($active)): ?><p class="note">Showing <?= count($active) ?> of <?= $activeTotal ?>.</p><?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>Recently resolved</h3></div>
            <?php View::partial('ticket_rows', ['rows' => array_slice($recent, 0, 4), 'emptyText' => 'Resolved requests will show here.']); ?>
        </section>
    </div>
    <div>
        <section class="sec">
            <div class="sec-head"><h3>Upcoming appointments</h3></div>
            <?php if (!$appointments): ?><p class="empty">No appointments booked.</p><?php else: ?>
            <ul class="list">
                <?php foreach ($appointments as $a): ?>
                <li><span class="t"><?= e($a['lecturer_name']) ?></span>
                    <span class="s"><?= e(fmt_day($a['starts_at'])) ?>, <?= e(fmt_time($a['starts_at'])) ?> &middot; <?= badge(ucfirst($a['status']), $a['status'] === 'confirmed' ? 'ok' : 'review') ?></span>
                    <div class="s"><?= e($a['purpose']) ?></div></li>
                <?php endforeach; ?>
            </ul><?php endif; ?>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>Latest updates</h3><a href="<?= e(url('notifications/index')) ?>">See all</a></div>
            <?php if (!$notifications): ?><p class="empty">No notifications yet.</p><?php else: ?>
            <ul class="list">
                <?php foreach ($notifications as $n): ?>
                <li><a class="t<?= $n['is_read'] ? '' : ' unread' ?>" href="<?= e(link_url($n['link'])) ?>"><?= e($n['title']) ?></a><span class="s"><?= e(time_ago($n['created_at'])) ?></span></li>
                <?php endforeach; ?>
            </ul><?php endif; ?>
        </section>
    </div>
</div>
