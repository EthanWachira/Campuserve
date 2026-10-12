<div class="page-head"><div><h2>Users</h2><p><?= array_sum($counts) ?> accounts</p></div></div>
<form class="filters" method="get" action="<?= e(url('admin/users')) ?>"><input type="hidden" name="r" value="admin/users">
    <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email or admission number"></div>
    <div class="field"><label for="role">Role</label><select id="role" name="role"><option value="">All roles</option><?php foreach (['student', 'lecturer', 'staff', 'admin'] as $r): ?><option value="<?= $r ?>"<?= $role === $r ? ' selected' : '' ?>><?= e(role_label($r)) ?> (<?= (int) ($counts[$r] ?? 0) ?>)</option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit">Apply</button>
</form>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Role</th><th>Department</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($users as $x): ?>
    <tr><td><span class="subj"><?= e($x['full_name']) ?></span><div class="sub"><?= e($x['email']) ?> &middot; <?= e($x['identifier']) ?></div></td>
        <td><?= e(role_label($x['role'])) ?></td><td><?= e($x['department_name'] ?? '-') ?></td><td class="muted nowrap"><?= $x['last_login_at'] ? e(time_ago($x['last_login_at'])) : 'Never' ?></td>
        <td><?= badge(ucfirst($x['status']), $x['status'] === 'active' ? 'ok' : 'bad') ?></td>
        <td class="right"><?php if ((int) $x['id'] !== (int) $u['id']): ?>
            <form method="post" action="<?= e(url('admin/users', array_filter(['role' => $role, 'q' => $q]))) ?>" data-confirm="<?= $x['status'] === 'active' ? 'Suspend this account? They will be signed out.' : 'Reactivate this account?' ?>">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="status" value="<?= $x['status'] === 'active' ? 'suspended' : 'active' ?>">
                <button class="btn btn-sm <?= $x['status'] === 'active' ? 'btn-danger' : 'btn-ghost' ?>" type="submit"><?= $x['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
