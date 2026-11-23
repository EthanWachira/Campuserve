<div class="page-head"><div><h2>My profile</h2><p><?= e(role_label($u['role'])) ?><?= $u['department_name'] ? ', ' . e($u['department_name']) : '' ?></p></div></div>
<div class="grid-eq">
<section class="sec"><div class="sec-head"><h3>Details</h3></div>
    <form method="post" action="<?= e(url('profile/index')) ?>" novalidate style="margin-top:16px">
        <?= csrf_field() ?>
        <div class="field"><label>Email</label><input type="text" value="<?= e($row['email']) ?>" disabled><div class="hint">Sign-in email. Ask the administrator to change it.</div></div>
        <div class="field"><label>Admission / staff number</label><input type="text" value="<?= e($row['identifier']) ?>" disabled></div>
        <div class="field"><label for="full_name">Full name</label><input type="text" id="full_name" name="full_name" value="<?= e($row['full_name']) ?>" required></div>
        <?php if ($u['role'] === 'student'): ?>
        <div class="row2">
            <div class="field"><label for="programme">Programme</label><select id="programme" name="programme"><?php foreach ($programmes as $p): ?><option<?= $row['programme'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="year_of_study">Year of study</label><select id="year_of_study" name="year_of_study"><?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>"<?= (int) $row['year_of_study'] === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select></div>
        </div>
        <?php endif; ?>
        <div class="toggle-row"><label for="email_notifications"><b>Email me about updates</b><small>Request updates, assignments and appointment reminders. In-app notifications always stay on.</small></label>
            <input type="checkbox" id="email_notifications" name="email_notifications" value="1"<?= (int) $row['email_notifications'] === 1 ? ' checked' : '' ?>></div>
        <p style="margin-top:18px"><button class="btn btn-primary" type="submit">Save changes</button></p>
    </form>
</section>
<section class="sec"><div class="sec-head"><h3>Change password</h3></div>
    <form method="post" action="<?= e(url('profile/password')) ?>" novalidate style="margin-top:16px">
        <?= csrf_field() ?>
        <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" autocomplete="new-password" required><div class="hint">At least <?= (int) App::config('security')['min_password_len'] ?> characters with letters and numbers.</div></div>
        <div class="field"><label for="new_password_confirm">Confirm new password</label><input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" required></div>
        <button class="btn btn-primary" type="submit">Change password</button>
    </form>
</section>
</div>
