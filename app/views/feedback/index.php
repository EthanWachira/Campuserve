<?php
$pct = fn($v) => $v === null ? '-' : round($v * 100, 1) . '%';
?>
<p><a href="<?= e(url('admin/ml')) ?>">&larr; Classifier</a></p>
<div class="page-head"><div><h2>Retraining: the model learns from staff</h2><p>Every time staff confirm or correct a category, the answer becomes a labelled example. Retraining adds those examples to the original dataset and deploys the improved model.</p></div></div>

<?php if (!$ready): ?>
    <p class="flash flash-error">The retraining tables are missing. In phpMyAdmin open the SQL tab and run <b>database/add_ml_retraining.sql</b>, then reload this page.</p>
<?php endif; ?>

<div class="kpis">
    <div class="kpi is-key"><div class="n"><?= (int) $sum['labelled'] ?></div><div class="l">Staff-labelled requests</div></div>
    <div class="kpi"><div class="n"><?= (int) $sum['corrected'] ?></div><div class="l">Corrected by staff</div></div>
    <div class="kpi"><div class="n"><?= (int) $sum['confirmed'] ?></div><div class="l">Confirmed as predicted</div></div>
    <div class="kpi<?= $sum['new'] ? ' is-key' : '' ?>"><div class="n"><?= (int) $sum['new'] ?></div><div class="l">New since the last retraining</div></div>
</div>

<div class="grid-eq">
<section class="sec"><div class="sec-head"><h3>Retrain the model</h3></div>
    <ol class="note" style="line-height:1.7;padding-left:20px;margin-top:0">
        <li>Staff confirm or correct the category on a request.</li>
        <li>The corrected request is stored as a labelled example.</li>
        <li>This button retrains on the original dataset plus those examples. A quarter of the staff-labelled requests are held back as a test the model never trains on.</li>
        <li>The new model is compared with the previous one and goes live straight away, with no restart, only if it is not worse.</li>
    </ol>
    <?php if (!$status['online']): ?><p class="note"><b>The classifier service is offline.</b> Start it with <code>py api.py</code> in the ml folder.</p><?php endif; ?>
    <form method="post" action="<?= e(url('feedback/retrain')) ?>" data-confirm="Retrain the classifier now using <?= (int) $sum['labelled'] ?> staff-labelled requests?"><?= csrf_field() ?>
        <label class="note" style="display:block;margin:8px 0"><input type="checkbox" name="force" value="1"> Deploy even if accuracy drops</label>
        <button class="btn btn-primary" type="submit"<?= ($sum['labelled'] && $ready && $status['online']) ? '' : ' disabled' ?>>Retrain now</button>
    </form>
    <hr style="margin:18px 0;border:0;border-top:1px solid var(--line, #e3e7ef)">
    <p class="label">Automatic retraining</p>
    <form method="post" action="<?= e(url('feedback/settings')) ?>"><?= csrf_field() ?>
        <label class="note" style="display:block;margin:6px 0"><input type="checkbox" name="auto" value="1"<?= $sum['auto'] ? ' checked' : '' ?>> Retrain automatically</label>
        <label class="note" style="display:block;margin:6px 0">once <input type="number" name="threshold" min="1" max="500" value="<?= (int) $sum['threshold'] ?>" style="width:70px"> new staff corrections have been collected</label>
        <p class="note">Currently <?= (int) $sum['new_corrected'] ?> new correction<?= $sum['new_corrected'] == 1 ? '' : 's' ?> waiting. The check runs with the background jobs (every 10 minutes while someone uses the system).</p>
        <button class="btn btn-ghost btn-sm" type="submit">Save</button>
    </form>
    <div class="row-actions" style="justify-content:flex-start;margin-top:14px">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('feedback/download')) ?>">Download labelled data (CSV)</a>
    </div>
</section>
<div>
    <section class="sec"><div class="sec-head"><h3>Examples per category</h3></div><?= bar_list($perCategory) ?></section>
    <section class="sec"><div class="sec-head"><h3>Most common corrections</h3></div>
        <?php if (!$confusions): ?><p class="empty">No corrections yet.</p><?php else: ?>
        <ul class="list"><?php foreach ($confusions as $c): ?><li><span class="t"><?= e($c['predicted']) ?> &rarr; <?= e($c['actual']) ?></span><span class="s"><?= (int) $c['n'] ?>&times;</span></li><?php endforeach; ?></ul><?php endif; ?></section>
</div>
</div>

<section class="sec"><div class="sec-head"><h3>Retraining history</h3></div>
<?php if (!$history): ?><p class="empty">The model has not been retrained yet.</p><?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>When</th><th>Model version</th><th>Trained on</th><th>Original hold-out</th><th>Staff test set</th><th>Result</th></tr></thead>
    <tbody><?php foreach ($history as $m): ?>
    <tr>
        <td class="nowrap"><?= e(fmt_dt($m['trained_at'])) ?><div class="sub"><?= $m['trigger_source'] === 'auto' ? 'automatic' : e($m['trained_by_name'] ?? 'administrator') ?></div></td>
        <td><?= e($m['version']) ?></td>
        <td><?= number_format((int) ($m['prev_trained_rows'] ?? 0)) ?> &rarr; <?= number_format((int) $m['trained_rows']) ?></td>
        <td class="nowrap"><?= $pct($m['prev_synthetic_accuracy'] === null ? null : (float) $m['prev_synthetic_accuracy']) ?> &rarr; <?= $pct((float) $m['synthetic_accuracy']) ?></td>
        <td class="nowrap"><?= $m['real_accuracy'] === null ? 'too few yet' : $pct((float) $m['prev_real_accuracy']) . ' &rarr; ' . $pct((float) $m['real_accuracy']) . ' (' . (int) $m['feedback_test'] . ' requests)' ?></td>
        <td><?= $m['status'] === 'deployed' ? badge('Deployed', 'ok') : badge('Rejected', 'bad') ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
</section>
