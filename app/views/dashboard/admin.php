<div class="page-head"><div><h2>System overview</h2><p>Everything across the university, in one place.</p></div>
    <a class="btn btn-ghost" href="<?= e(url('admin/ml')) ?>">Classifier performance</a></div>
<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= array_sum($counts) ?></div><div class="l">Total requests</div></div>
    <div class="kpi<?= $unassigned ? ' is-alert' : '' ?>"><div class="n"><?= $unassigned ?></div><div class="l">Waiting for an owner</div></div>
    <div class="kpi"><div class="n"><?= e(hours_label($avgHours)) ?></div><div class="l">Average time to resolve (30 days)</div></div>
    <div class="kpi"><div class="n"><?= $ml['rate'] === null ? '-' : $ml['rate'] . '%' ?></div><div class="l">Classifier category kept by staff</div></div>
    <div class="kpi"><div class="n"><?= array_sum($roles) ?></div><div class="l">Users (<?= (int) ($roles['student'] ?? 0) ?> students)</div></div>
</div>
<div class="grid-eq">
    <section class="sec"><div class="sec-head"><h3>Requests per day, last 14 days</h3></div><?= day_columns($perDay) ?></section>
    <section class="sec"><div class="sec-head"><h3>Requests by status</h3></div><div class="donut-wrap"><?= status_donut($counts) ?></div></section>
    <section class="sec"><div class="sec-head"><h3>Load by department</h3></div><?= bar_list($byDept) ?></section>
    <div>
        <section class="sec">
            <div class="sec-head"><h3>Classifier service</h3><a href="<?= e(url('admin/ml')) ?>">Details</a></div>
            <dl class="kv"><dt>API status</dt><dd><?= $mlStatus['online'] ? badge('Online', 'ok') : badge('Offline: keyword fallback in use', 'bad') ?></dd>
            <dt>Model version</dt><dd><?= e($mlStatus['version'] ?? 'unknown') ?></dd>
            <dt>Predictions reviewed</dt><dd><?= $ml['reviewed'] ?> (<?= $ml['pending'] ?> awaiting review)</dd></dl>
        </section>
        <section class="sec">
            <div class="sec-head"><h3>Recent activity</h3></div>
            <ul class="list"><?php foreach ($audit as $a): ?><li><span class="t"><?= e(str_replace('_', ' ', $a['action'])) ?></span><span class="s"><?= e($a['full_name'] ?? 'Unknown user') ?> &middot; <?= e(time_ago($a['created_at'])) ?></span></li><?php endforeach; ?></ul>
        </section>
    </div>
</div>
