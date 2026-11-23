<?php $v = fn($k, $d = '') => old($k, $row[$k] ?? $d); $role = $v('role', 'student'); $self = $row && (int) $row['id'] === (int) $u['id']; ?>
<p><a href="<?= e(url('admin/users')) ?>">&larr; Users</a></p>
<div class="page-head"><div><h2><?= $row ? 'Edit account' : 'New account' ?></h2><p><?= $row ? e($row['email']) : 'The person receives an email with a link to choose their own password.' ?></p></div></div>
<form method="post" action="<?= e(url('accounts/edit', $row ? ['id' => $row['id']] : [])) ?>" novalidate style="max-width:640px">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
    <div class="row2">
        <div class="field"><label for="role">Role</label><select id="role" name="role"<?= $self ? ' disabled' : '' ?>><?php foreach (['student', 'lecturer', 'staff', 'admin'] as $r): ?><option value="<?= $r ?>"<?= $role === $r ? ' selected' : '' ?>><?= e(role_label($r)) ?></option><?php endforeach; ?></select><?= $self ? '<div class="hint">You cannot change your own role.</div>' : '' ?></div>
        <div class="field"><label for="identifier">Admission / staff number</label><input type="text" id="identifier" name="identifier" value="<?= e($v('identifier')) ?>" required></div>
    </div>
    <div class="field"><label for="full_name">Full name</label><input type="text" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>" required></div>
    <div class="row2">
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e($v('email')) ?>" required></div>
        <div class="field"><label for="date_of_birth">Date of birth (optional)</label><input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($v('date_of_birth')) ?>"></div>
    </div>
    <div class="field"><label for="department_id">Department (staff and lecturers)</label><select id="department_id" name="department_id"><option value="">None</option><?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $v('department_id') === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select>
        <div class="hint">Staff must have one. Lecturers who should receive academic requests belong to the Faculty department.</div></div>
    <div class="row2">
        <div class="field"><label for="programme">Programme (students)</label><select id="programme" name="programme"><option value="">-</option><?php foreach ($programmes as $p): ?><option<?= $v('programme') === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="year_of_study">Year of study (students)</label><select id="year_of_study" name="year_of_study"><option value="">-</option><?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>"<?= (int) $v('year_of_study') === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= $row ? 'Save changes' : 'Create account and email link' ?></button>
</form>
