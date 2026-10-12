<div class="page-head"><div><h2>Notifications</h2></div>
<?php if ($items): ?><form method="post" action="<?= e(url('notifications/read')) ?>"><?= csrf_field() ?><input type="hidden" name="all" value="1"><button class="btn btn-ghost btn-sm" type="submit">Mark all as read</button></form><?php endif; ?></div>
<?php if (!$items): ?><p class="empty">You are all caught up.</p><?php else: ?>
<ul class="list">
<?php foreach ($items as $n): ?>
    <li><form method="post" action="<?= e(url('notifications/read')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
        <button type="submit" class="t<?= $n['is_read'] ? '' : ' unread' ?>" style="background:none;border:0;padding:0;font:inherit;font-weight:600;color:var(--ink);cursor:pointer;text-align:left"><?= e($n['title']) ?></button></form>
        <?php if ($n['body']): ?><span class="s"><?= e($n['body']) ?></span><br><?php endif; ?><span class="s"><?= e(time_ago($n['created_at'])) ?></span></li>
<?php endforeach; ?>
</ul><?php endif; ?>
