<?php /** @var array $rows  @var bool $showStudent */ ?>
<?php if (!$rows): ?><p class="empty"><?= e($emptyText ?? 'No requests to show.') ?></p><?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Reference</th><th>Request</th><?php if (!empty($showStudent)): ?><th>Student</th><?php endif; ?><th>Status</th><th>Priority</th><th>Opened</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="ref"><a href="<?= e(url('tickets/show', ['id' => $r['id']])) ?>"><?= e($r['reference_no']) ?></a></td>
            <td><span class="subj"><?= e($r['subject']) ?></span><div class="sub"><?= e($r['category_name']) ?><?= $r['needs_review'] ? ' ' . badge('Needs review', 'review') : '' ?></div></td>
            <?php if (!empty($showStudent)): ?><td><?= e($r['student_name']) ?></td><?php endif; ?>
            <td><?= status_badge($r['status']) ?></td>
            <td><?= priority_badge($r['priority']) ?></td>
            <td class="nowrap muted"><?= e(time_ago($r['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
