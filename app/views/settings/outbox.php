<div class="page-head"><div><h2>Email outbox</h2><p><?= (int) $stats['sent'] ?> sent, <?= (int) $stats['pending'] ?> pending, <?= (int) $stats['failed'] ?> failed &middot; driver: <?= e($driver) ?></p></div>
    <div class="row-actions">
        <form method="post" action="<?= e(url('settings/test')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Send me a test email</button></form>
        <?php if ($stats['failed']): ?><form method="post" action="<?= e(url('settings/retry')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Retry failed</button></form><?php endif; ?>
    </div></div>
<form class="filters" method="get" action="<?= e(url('settings/outbox')) ?>"><input type="hidden" name="r" value="settings/outbox">
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php foreach (['pending', 'sent', 'failed'] as $x): ?><option value="<?= $x ?>"<?= $status === $x ? ' selected' : '' ?>><?= ucfirst($x) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Apply</button>
</form>
<?php if (!$rows): ?><p class="empty">No emails yet.</p><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>To</th><th>Subject</th><th>Status</th><th>Tries</th><th>Queued</th><th>Error</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['to_email']) ?></td><td><?= e($r['subject']) ?></td>
    <td><?= badge(ucfirst($r['status']), $r['status'] === 'sent' ? 'ok' : ($r['status'] === 'failed' ? 'bad' : 'review')) ?></td><td><?= (int) $r['attempts'] ?></td>
    <td class="muted nowrap"><?= e(time_ago($r['created_at'])) ?></td><td class="muted"><?= e($r['last_error'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
