<?php $step = status_step($t['status']); $steps = array_values(TICKET_STATUSES); ?>
<p><a href="<?= e(url('tickets/index')) ?>">&larr; All requests</a></p>
<div class="ticket-head">
    <div class="th-top">
        <div>
            <span class="ref"><?= e($t['reference_no']) ?></span>
            <h2><?= e($t['subject']) ?></h2>
            <?= status_badge($t['status']) ?> <?= priority_badge($t['priority']) ?> <?= $t['needs_review'] && $manage ? badge('Category needs checking', 'review') : '' ?>
        </div>
        <dl class="facts" style="min-width:260px">
            <div><dt>Department</dt><dd><?= e($t['department_name']) ?></dd></div>
            <div><dt>Handled by</dt><dd><?= e($t['assignee_name'] ?? 'Not yet assigned') ?></dd></div>
            <div><dt>Opened</dt><dd><?= e(fmt_dt($t['created_at'])) ?></dd></div>
        </dl>
    </div>
    <div class="th-cut" aria-hidden="true"></div>
    <div class="th-bottom">
        <ol class="steps-h" aria-label="Progress">
            <?php foreach ($steps as $i => $label): ?><li class="<?= $i <= $step ? 'on' : '' ?><?= $i === $step ? ' cur' : '' ?>"><?= e($label) ?></li><?php endforeach; ?>
        </ol>
    </div>
</div>

<div class="grid-2">
<div>
    <section class="sec">
        <div class="sec-head"><h3>Request</h3></div>
        <?php if ($manage): ?><p class="note"><?= e($t['student_name']) ?> (<?= e($t['student_no']) ?>), <?= e($t['programme']) ?></p><?php endif; ?>
        <div class="desc"><?= e($t['description']) ?></div>
        <?php if ($t['predicted_name'] && $manage): ?>
        <div class="class-box">
            <b>Automatic classification:</b> <?= e($t['predicted_name']) ?>
            <span class="conf" title="<?= round($t['ml_confidence'] * 100) ?>%"><i style="width:<?= round($t['ml_confidence'] * 100) ?>%"></i></span><?= round($t['ml_confidence'] * 100) ?>% sure
            <?php if ((int) $t['predicted_category_id'] !== (int) $t['category_id']): ?><br>Corrected by staff to <b><?= e($t['category_name']) ?></b>.<?php endif; ?>
        </div>
        <?php elseif ($t['classification_source'] === 'rules' && $manage): ?>
        <div class="class-box">Classified by keyword fallback because the classifier service was unavailable. Please check the category.</div>
        <?php endif; ?>
    </section>
    <section class="sec">
        <div class="sec-head"><h3>Activity</h3></div>
        <?php if (!$updates): ?><p class="empty">No activity yet. You will be notified when someone responds.</p><?php else: ?>
        <ul class="timeline">
            <?php foreach ($updates as $up): $staff = in_array($up['role'], ['staff', 'lecturer', 'admin'], true); ?>
            <li class="<?= $up['is_internal'] ? 'is-internal' : ($staff ? 'is-staff' : '') ?>">
                <div class="who-line"><b><?= e($up['full_name'] ?? 'System') ?></b> &middot; <?= e(fmt_dt($up['created_at'])) ?>
                    <?php if ($up['is_internal']): ?><span class="internal-tag">Internal note</span><?php endif; ?>
                    <?php if ($up['new_status']): ?> &middot; <?= status_badge($up['new_status']) ?><?php endif; ?></div>
                <p class="msg"><?= e($up['message']) ?></p>
            </li>
            <?php endforeach; ?>
        </ul><?php endif; ?>
    </section>
</div>

<aside>
<?php if ($manage): ?>
    <div class="panel-side">
        <h3>Respond</h3>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="status">
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status"><?php foreach (TICKET_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="message">Message to the student</label><textarea id="message" name="message" maxlength="2000"></textarea></div>
            <button class="btn btn-primary btn-sm" type="submit">Send update</button>
        </form>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="note">
            <div class="field"><label for="note">Internal note (students cannot see this)</label><textarea id="note" name="message" maxlength="2000"></textarea></div>
            <button class="btn btn-ghost btn-sm" type="submit">Save note</button>
        </form>
    </div>
    <div class="panel-side">
        <h3>Owner</h3>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="assign">
            <div class="field"><label for="assignee">Assign to</label>
                <select id="assignee" name="assignee"><?php foreach ($assignees as $a): ?><option value="<?= (int) $a['id'] ?>"<?= (int) $t['assigned_to'] === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['full_name']) ?> (<?= e(role_label($a['role'])) ?>)</option><?php endforeach; ?></select></div>
            <button class="btn btn-ghost btn-sm" type="submit">Assign</button>
        </form>
    </div>
    <div class="panel-side">
        <h3>Category</h3>
        <p class="note" style="margin-top:0">Currently <b><?= e($t['category_name']) ?></b>. Changing it re-routes the request to that category's department.</p>
        <?php if ($t['predicted_name']): ?>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="confirm_category">
            <button class="btn btn-ghost btn-sm" type="submit"><?= icon('check') ?>Category is correct</button>
        </form><?php endif; ?>
        <form method="post" action="<?= e(url('tickets/update')) ?>" data-confirm="Change the category? The request may move to another department."><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="reclassify">
            <div class="field"><label for="category">Change to</label>
                <select id="category" name="category"><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $t['category_id'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['department_name']) ?>)</option><?php endforeach; ?></select></div>
            <button class="btn btn-ghost btn-sm" type="submit">Change category</button>
        </form>
    </div>
<?php else: ?>
    <div class="panel-side">
        <h3>Reply</h3>
        <?php if ($t['status'] === 'closed'): ?>
            <p class="note" style="margin-top:0">This request is closed. Reopen it if the problem is back.</p>
        <?php else: ?>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="reply">
            <div class="field"><label for="message">Add a message</label><textarea id="message" name="message" maxlength="2000" required></textarea></div>
            <button class="btn btn-primary btn-sm" type="submit">Send reply</button>
        </form>
        <?php endif; ?>
        <?php if ($t['status'] === 'resolved'): ?>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="close">
            <p class="note" style="margin-top:0">Staff marked this resolved. Is it sorted?</p>
            <button class="btn btn-primary btn-sm" type="submit">Yes, close it</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($t['status'], ['resolved', 'closed'], true)): ?>
        <form method="post" action="<?= e(url('tickets/update')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="action" value="reopen">
            <button class="btn btn-ghost btn-sm" type="submit">Problem is back: reopen</button>
        </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
</aside>
</div>
