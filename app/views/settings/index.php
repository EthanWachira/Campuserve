<div class="page-head"><div><h2>Settings</h2><p>Switches and service targets for the whole system.</p></div>
    <div class="row-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('settings/outbox')) ?>">Email outbox</a><a class="btn btn-ghost btn-sm" href="<?= e(url('settings/audit')) ?>">Audit log</a><a class="btn btn-ghost btn-sm" href="<?= e(url('catalog/index')) ?>">Categories &amp; departments</a></div></div>
<form method="post" action="<?= e(url('settings/index')) ?>" style="max-width:720px">
    <?= csrf_field() ?>
    <section class="sec"><div class="sec-head"><h3>Behaviour</h3></div>
        <?php foreach ([
            'auto_assign' => ['Automatic assignment', 'New requests go to the person in the owning department with the fewest open requests.'],
            'email_notifications' => ['Email notifications', 'Also email every notification. Users can opt out on their profile.'],
            'appointment_reminders' => ['Appointment reminders', 'Remind both people about a day ahead and an hour ahead.'],
            'jobs_enabled' => ['Automatic background jobs', 'Overdue alerts, reminders and email retries run by themselves (at most every 10 minutes).'],
        ] as $k => [$label, $hint]): ?>
        <div class="toggle-row"><label for="<?= $k ?>"><b><?= e($label) ?></b><small><?= e($hint) ?></small></label>
            <input type="checkbox" id="<?= $k ?>" name="<?= $k ?>" value="1"<?= $s[$k] === '1' ? ' checked' : '' ?>></div>
        <?php endforeach; ?>
    </section>
    <section class="sec"><div class="sec-head"><h3>Service targets (hours to resolve)</h3></div>
        <p class="note">A request still active after this time is flagged Overdue and staff and administrators are alerted once.</p>
        <div class="row2" style="margin-top:14px;grid-template-columns:repeat(3,1fr)">
            <div class="field"><label for="sla_high">High priority</label><input type="number" min="1" max="720" id="sla_high" name="sla_high" value="<?= e($s['sla_high']) ?>"></div>
            <div class="field"><label for="sla_medium">Medium priority</label><input type="number" min="1" max="720" id="sla_medium" name="sla_medium" value="<?= e($s['sla_medium']) ?>"></div>
            <div class="field"><label for="sla_low">Low priority</label><input type="number" min="1" max="720" id="sla_low" name="sla_low" value="<?= e($s['sla_low']) ?>"></div>
        </div>
    </section>
    <button class="btn btn-primary" type="submit">Save settings</button>
</form>
<section class="sec" style="max-width:720px;margin-top:28px"><div class="sec-head"><h3>Status</h3></div>
    <dl class="kv"><dt>Mail driver</dt><dd><?= e($mailDriver) ?><?= $mailDriver === 'log' ? ' (emails are saved in storage/mail, not sent)' : '' ?></dd>
        <dt>Emails</dt><dd><?= (int) $mailStats['sent'] ?> sent, <?= (int) $mailStats['pending'] ?> pending, <?= (int) $mailStats['failed'] ?> failed</dd>
        <dt>Background jobs last ran</dt><dd><?= $jobsLast ? e(time_ago($jobsLast)) : 'Never' ?></dd></dl>
    <form method="post" action="<?= e(url('settings/runjobs')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Run jobs now</button></form>
</section>
