<div class="page-head">
    <div><h2><?= e($u['department_name']) ?></h2><p>Your department's queue, ordered by urgency and age.</p></div>
    <a class="btn btn-primary" href="<?= e(url('tickets/index', ['mine' => 1])) ?>">My assigned requests</a>
</div>
<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= $queueTotal ?></div><div class="l">Open in queue</div></div>
    <div class="kpi"><div class="n"><?= $counts['open'] ?></div><div class="l">Not yet assigned</div></div>
    <div class="kpi"><div class="n"><?= $mine ?></div><div class="l">Assigned to me</div></div>
    <div class="kpi<?= count($review) ? ' is-alert' : '' ?>"><div class="n"><?= count($review) ?></div><div class="l">Category needs checking</div></div>
    <div class="kpi"><div class="n"><?= $resolvedWeek ?></div><div class="l">Resolved this week</div></div>
    <div class="kpi"><div class="n"><?= e(hours_label($avgHours)) ?></div><div class="l">Average time to resolve (30 days)</div></div>
</div>
<?php if ($review): ?>
<section class="sec">
    <div class="sec-head"><h3>Check these categories first</h3><a href="<?= e(url('tickets/index', ['review' => 1])) ?>">View all</a></div>
    <p class="note">The classifier was unsure about these. Confirm or correct the category; each answer teaches the next model.</p>
    <?php View::partial('ticket_rows', ['rows' => array_slice($review, 0, 5), 'showStudent' => true]); ?>
</section>
<?php endif; ?>
<div class="grid-2">
    <section class="sec">
        <div class="sec-head"><h3>Queue</h3><a href="<?= e(url('tickets/index')) ?>">Open full list</a></div>
        <?php View::partial('ticket_rows', ['rows' => $queue, 'showStudent' => true, 'emptyText' => 'The queue is empty.']); ?>
    </section>
    <div>
        <section class="sec">
            <div class="sec-head"><h3>Requests per day</h3></div>
            <?= day_columns($perDay) ?>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>By category</h3></div>
            <?= bar_list($byCategory) ?>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>Classifier in this department</h3></div>
            <?php if ($ml['rate'] === null): ?><p class="empty">No reviewed requests yet.</p><?php else: ?>
            <dl class="kv"><dt>Category kept by staff</dt><dd><?= $ml['rate'] ?>% (<?= $ml['agreed'] ?> of <?= $ml['reviewed'] ?>)</dd><dt>Average confidence</dt><dd><?= $ml['avg_conf'] ?>%</dd></dl>
            <?php endif; ?>
        </section>
    </div>
</div>
