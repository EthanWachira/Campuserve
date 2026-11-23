<?php
/** Table of appointments. Expects $rows, $u (signed-in user), $emptyText. */
$isLect = $u['role'] === 'lecturer';
$act = function (string $route, int $id, array $extra, string $label, string $cls, ?string $confirm = null): string {
    $h = '<form method="post" action="' . e(url($route)) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
       . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">';
    foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="btn ' . $cls . ' btn-sm" type="submit">' . e($label) . '</button></form>';
};
?>
<?php if (!$rows): ?>
    <p class="empty"><?= e($emptyText ?? 'Nothing here yet.') ?></p>
<?php else: ?>
<div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>When</th><th><?= $isLect ? 'Student' : 'Lecturer' ?></th><th>Purpose</th><th>Status</th><th><span class="sr">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): $past = strtotime($a['starts_at']) < time(); $id = (int) $a['id']; ?>
        <tr>
            <td class="nowrap"><b><?= e(fmt_day($a['starts_at'])) ?></b><div class="sub"><?= e(fmt_time($a['starts_at'])) ?> to <?= e(fmt_time($a['ends_at'])) ?></div></td>
            <td><?= e($isLect ? $a['student_name'] : $a['lecturer_name']) ?><?php if ($isLect): ?><div class="sub"><?= e($a['student_no']) ?></div><?php endif; ?></td>
            <td><?= e($a['purpose']) ?></td>
            <td><?= Appointment::statusBadge($a) ?></td>
            <td><div class="row-actions">
                <?php if (!$past && $a['status'] === 'pending'): ?>
                    <?php if ($isLect): ?>
                        <?= $act('appointments/respond', $id, ['decision' => 'confirm'], 'Confirm', 'btn-primary') ?>
                        <?= $act('appointments/respond', $id, ['decision' => 'decline'], 'Decline', 'btn-danger', 'Decline this appointment request?') ?>
                    <?php else: ?>
                        <?= $act('appointments/cancel', $id, [], 'Withdraw', 'btn-danger', 'Withdraw this appointment request?') ?>
                    <?php endif; ?>
                <?php elseif (!$past && $a['status'] === 'confirmed'): ?>
                    <?= $act('appointments/cancel', $id, [], 'Cancel', 'btn-danger', 'Cancel this confirmed appointment? The other person will be notified.') ?>
                <?php elseif ($past && $a['status'] === 'confirmed' && $isLect): ?>
                    <?= $act('appointments/complete', $id, [], 'Mark completed', 'btn-ghost') ?>
                <?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
