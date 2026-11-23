<div class="page-head"><div><h2>Audit log</h2><p>The latest 200 matching events.</p></div></div>
<form class="filters" method="get" action="<?= e(url('settings/audit')) ?>"><input type="hidden" name="r" value="settings/audit">
    <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, detail or IP address"></div>
    <div class="field"><label for="action">Event</label><select id="action" name="action"><option value="">All events</option><?php foreach ($actions as $a): ?><option<?= $action === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Apply</button>
</form>
<?php if (!$rows): ?><p class="empty">No events match.</p><?php else: ?>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Event</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="nowrap muted"><?= e(fmt_dt($r['created_at'])) ?></td><td><?= e($r['full_name'] ?? 'System / visitor') ?></td><td><?= e($r['action']) ?></td><td class="muted"><?= e($r['detail'] ?? '') ?></td><td class="muted"><?= e($r['ip_address'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
