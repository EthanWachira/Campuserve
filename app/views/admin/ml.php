<div class="page-head"><div><h2>Classifier performance</h2><p>How well automatic routing works, from training and from staff feedback.</p></div></div>
<div class="kpis">
    <div class="kpi"><div class="n"><?= $status['online'] ? 'Online' : 'Offline' ?></div><div class="l">API status</div></div>
    <div class="kpi is-key"><div class="n"><?= $agreement['rate'] === null ? '-' : $agreement['rate'] . '%' ?></div><div class="l">Category kept by staff (<?= $agreement['reviewed'] ?> reviewed)</div></div>
    <div class="kpi"><div class="n"><?= $agreement['pending'] ?></div><div class="l">Predictions awaiting review</div></div>
    <div class="kpi"><div class="n"><?= $metrics ? round($metrics['holdout']['accuracy'] * 100) . '%' : '-' ?></div><div class="l">Hold-out accuracy (training)</div></div>
</div>
<div class="grid-eq">
<section class="sec"><div class="sec-head"><h3>Model</h3></div>
<?php if (!$metrics): ?><p class="empty">Run ml/train.py to create metrics.json.</p><?php else: ?>
    <dl class="kv"><dt>Algorithm</dt><dd><?= e(str_replace('_', ' ', $metrics['model'])) ?></dd><dt>Version</dt><dd><?= e($metrics['version']) ?></dd>
    <dt>Training rows</dt><dd><?= (int) $metrics['trained_rows'] ?></dd>
    <dt>Unseen-issue CV accuracy</dt><dd><?= round($metrics['group_cv_scores'][$metrics['model']]['accuracy'] * 100, 1) ?>%</dd>
    <dt>Macro F1 (hold-out)</dt><dd><?= round($metrics['holdout']['macro_f1'] * 100, 1) ?>%</dd></dl>
    <p class="note"><?= e($metrics['note']) ?></p>
<?php endif; ?></section>
<section class="sec"><div class="sec-head"><h3>Confidence of all predictions</h3></div><?= bar_list($bands) ?></section>
<section class="sec"><div class="sec-head"><h3>Most common corrections</h3></div>
<?php if (!$confusions): ?><p class="empty">No corrections recorded yet.</p><?php else: ?>
    <ul class="list"><?php foreach ($confusions as $c): ?><li><span class="t"><?= e($c['predicted']) ?> &rarr; <?= e($c['actual']) ?></span><span class="s"><?= (int) $c['n'] ?> time<?= $c['n'] == 1 ? '' : 's' ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
</div>
