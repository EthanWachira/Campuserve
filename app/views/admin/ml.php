<?php
$attrNotes = ['text' => 'the wording of the request (the input the model reads)', 'category' => 'the correct category (the label the model must predict)',
              'issue_id' => 'groups paraphrases of one issue, so testing uses unseen issues', 'source' => 'how the row was produced'];
$cv = $metrics['group_cv_scores'] ?? [];
?>
<div class="page-head"><div><h2>Machine learning pipeline</h2>
    <p>The four stages: a labelled dataset, training, integration into this application, and classification of the records in the database.</p></div></div>

<div class="kpis">
    <div class="kpi"><div class="n"><?= $dataset ? number_format($dataset['rows']) : '-' ?></div><div class="l">1. Records in the training dataset</div></div>
    <div class="kpi"><div class="n"><?= $metrics ? round($metrics['holdout']['accuracy'] * 100) . '%' : '-' ?></div><div class="l">2. Accuracy on unseen issues</div></div>
    <div class="kpi<?= $status['online'] ? ' is-key' : ' is-alert' ?>"><div class="n"><?= $status['online'] ? 'Online' : 'Offline' ?></div><div class="l">3. Classifier service in the application</div></div>
    <div class="kpi"><div class="n"><?= $latest ? (int) $latest['records'] . ' of ' . $totalRecords : '0 of ' . $totalRecords ?></div><div class="l">4. Database records classified</div></div>
</div>

<section class="sec" id="step1">
    <div class="sec-head"><h3>1. The dataset the model learns from</h3></div>
    <?php if (!$dataset): ?><p class="empty">Training file not found. Run <b>py generate_dataset.py</b> in the ml folder.</p><?php else: ?>
    <div class="grid-eq">
        <div>
            <dl class="kv">
                <dt>File</dt><dd><?= e($dataset['file']) ?></dd>
                <dt>Records</dt><dd><?= number_format($dataset['rows']) ?> labelled university service requests</dd>
                <dt>Categories to predict</dt><dd><?= count($dataset['classes']) ?></dd>
                <dt>Updated</dt><dd><?= e(date('j M Y, g:i a', $dataset['modified'])) ?></dd>
            </dl>
            <p class="label" style="margin-top:18px">Attributes of each record</p>
            <ul class="list">
                <?php foreach ($dataset['columns'] as $c): ?><li><span class="t"><?= e($c) ?></span><span class="s"><?= e($attrNotes[$c] ?? '') ?></span></li><?php endforeach; ?>
            </ul>
            <p class="note">Written for this project from real university processes (fees and M-Pesa payments, HELB, CATs, supplementary exams, attachment, hostels). It is a constructed dataset, not Strathmore records, so its scores are optimistic. See the external test in the report.</p>
        </div>
        <div>
            <p class="label">Records per category</p>
            <?= bar_list(array_map(null, array_keys($dataset['classes']), array_values($dataset['classes']))) ?>
            <p class="label" style="margin-top:18px">How the rows were produced</p>
            <?= bar_list(array_map(null, array_keys($dataset['sources']), array_values($dataset['sources']))) ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<section class="sec" id="step2">
    <div class="sec-head"><h3>2. Training the model on that dataset</h3></div>
    <?php if (!$metrics): ?><p class="empty">No trained model found. Run <b>py train.py</b> in the ml folder.</p><?php else: ?>
    <div class="grid-eq">
        <div>
            <dl class="kv">
                <dt>Selected algorithm</dt><dd><?= e(str_replace('_', ' ', $metrics['model'])) ?></dd>
                <dt>Model version</dt><dd><?= e($metrics['version']) ?></dd>
                <dt>Features</dt><dd>TF-IDF on words (1-2 grams) and characters (3-5 grams)</dd>
                <dt>Trained on</dt><dd><?= number_format((int) $metrics['trained_rows']) ?> records</dd>
                <dt>Hold-out accuracy</dt><dd><?= round($metrics['holdout']['accuracy'] * 100, 1) ?>% on <?= (int) $metrics['holdout']['test_rows'] ?> unseen records</dd>
                <dt>Hold-out macro F1</dt><dd><?= round($metrics['holdout']['macro_f1'] * 100, 1) ?>%</dd>
                <?php if (!empty($metrics['external_validation'])): ?>
                <dt>External test</dt><dd><?= round($metrics['external_validation']['accuracy'] * 100, 1) ?>% on <?= (int) $metrics['external_validation']['rows'] ?> independently written questions</dd>
                <?php endif; ?>
            </dl>
        </div>
        <div>
            <p class="label">Models compared (accuracy on unseen issue types)</p>
            <?= bar_list(array_map(fn($k, $v) => [ucfirst(str_replace('_', ' ', $k)), round($v['accuracy'] * 100)], array_keys($cv), array_values($cv)), '%') ?>
            <p class="note"><?= e($metrics['note']) ?></p>
        </div>
    </div>
    <?php endif; ?>
</section>

<section class="sec" id="step3">
    <div class="sec-head"><h3>3. The model inside the application</h3></div>
    <div class="grid-eq">
        <div>
            <dl class="kv">
                <dt>Classifier service</dt><dd><?= $status['online'] ? badge('Online', 'ok') : badge('Offline: keyword fallback in use', 'bad') ?></dd>
                <dt>Address</dt><dd><?= e($apiUrl) ?>/classify</dd>
                <dt>Model serving requests</dt><dd><?= e($status['version'] ?? 'unknown') ?></dd>
            </dl>
            <p class="note">When a student submits a request, the application sends its text to this service. It replies with a category, a confidence, the owning department and a priority, and the request is routed automatically. Below 45% confidence a staff member is asked to check it.</p>
        </div>
        <div class="panel-side">
            <h3>Try it now</h3>
            <div class="chips">
                <button type="button" class="chip-btn" data-example="My M-Pesa fee payment is not showing on my statement and I am blocked from registering">Fee payment missing</button>
                <button type="button" class="chip-btn" data-example="The hostel has had no water since morning">Hostel water</button>
                <button type="button" class="chip-btn" data-example="I need a supervisor for my final year project">Project supervisor</button>
            </div>
            <textarea id="demo-text" data-endpoint="<?= e(url('api/classify')) ?>" maxlength="1500" placeholder="Type a student request..."></textarea>
            <p style="margin:12px 0"><button type="button" id="demo-go" class="btn btn-primary btn-sm">Show where it would go</button></p>
            <div class="preview" id="demo-result" aria-live="polite"><p class="idle">The model's answer appears here.</p></div>
        </div>
    </div>
</section>

<section class="sec" id="step4">
    <div class="sec-head"><h3>4. Classifying the records in the database</h3></div>
    <?php if ($schemaMissing): ?>
        <p class="flash flash-error">The tables that store classification runs are missing. In phpMyAdmin, open the SQL tab and run <b>database/add_ml_runs.sql</b>, then reload this page.</p>
    <?php else: ?>
    <div class="page-head" style="margin-bottom:10px">
        <p class="muted" style="margin:0"><?= $totalRecords ?> requests are stored in the database. The button below sends each one through the trained model and records the answer.</p>
        <form method="post" action="<?= e(url('admin/classifyall')) ?>" data-confirm="Run the model over all <?= $totalRecords ?> records in the database?"><?= csrf_field() ?>
            <button class="btn btn-primary" type="submit" <?= $status['online'] ? '' : 'disabled' ?>>Classify all records now</button>
        </form>
    </div>
    <?php if (!$status['online']): ?><p class="note">Start the classifier service first (<b>py api.py</b>) to enable this.</p><?php endif; ?>

    <?php if (!$latest): ?>
        <p class="empty">No run yet. Click the button to classify the database.</p>
    <?php else: $rate = $latest['records'] ? round($latest['matched'] / $latest['records'] * 100, 1) : 0; ?>
        <div class="kpis">
            <div class="kpi is-key"><div class="n"><?= (int) $latest['records'] ?></div><div class="l">Records classified</div></div>
            <div class="kpi"><div class="n"><?= $rate ?>%</div><div class="l">Same as the category stored on the request</div></div>
            <div class="kpi"><div class="n"><?= $latest['avg_confidence'] !== null ? round($latest['avg_confidence'] * 100) . '%' : '-' ?></div><div class="l">Average confidence</div></div>
            <div class="kpi"><div class="n"><?= (int) $latest['needs_review'] ?></div><div class="l">Flagged for staff review</div></div>
            <div class="kpi"><div class="n"><?= round(($latest['duration_ms'] ?? 0) / 1000, 1) ?> s</div><div class="l">Time taken</div></div>
        </div>
        <p class="note" style="margin-bottom:14px">Run #<?= (int) $latest['id'] ?> by <?= e($latest['run_by_name'] ?? 'unknown') ?>, <?= e(fmt_dt($latest['started_at'])) ?>, model <?= e($latest['model_version']) ?>.
            Agreement compares the model with the category already stored on each request. For the demo records that category was set by the same generator that wrote the training data, so this is a consistency check, not independent validation.</p>
        <div class="grid-2">
            <div class="tbl-wrap"><table class="tbl">
                <thead><tr><th>Request</th><th>Model says</th><th>Confidence</th><th>Stored category</th><th>Result</th></tr></thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('tickets/show', ['id' => $r['ticket_id']])) ?>" class="ref"><?= e($r['reference_no']) ?></a><div class="sub"><?= e(mb_strimwidth($r['subject'], 0, 46, '...')) ?></div></td>
                        <td><?= e($r['predicted_name']) ?></td>
                        <td class="nowrap"><?= round($r['confidence'] * 100) ?>%<?= $r['needs_review'] ? ' ' . badge('Review', 'review') : '' ?></td>
                        <td><?= e($r['current_name']) ?></td>
                        <td><?= $r['matches_current'] ? badge('Match', 'ok') : badge('Differs', 'bad') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <div>
                <p class="label">What the model decided</p>
                <?= bar_list($predictedMix) ?>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="sec">
    <div class="sec-head"><h3>Feedback from staff</h3></div>
    <p class="note" style="margin-top:0">When staff confirm or correct a category, that answer is stored as a labelled example for retraining the model.</p>
    <dl class="kv"><dt>Predictions reviewed by staff</dt><dd><?= $agreement['reviewed'] ?></dd>
        <dt>Category kept by staff</dt><dd><?= $agreement['rate'] === null ? '-' : $agreement['rate'] . '%' ?></dd>
        <dt>Awaiting review</dt><dd><?= $agreement['pending'] ?></dd></dl>
    <?php if ($confusions): ?>
    <ul class="list" style="margin-top:10px"><?php foreach ($confusions as $c): ?><li><span class="t"><?= e($c['predicted']) ?> &rarr; <?= e($c['actual']) ?></span><span class="s">corrected <?= (int) $c['n'] ?> time<?= $c['n'] == 1 ? '' : 's' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
