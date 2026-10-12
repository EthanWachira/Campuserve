<h1>Create your account</h1>
<p class="sub">For students. Lecturers and staff are added by the system administrator.</p>
<form method="post" action="<?= e(url('auth/register')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" autocomplete="name" value="<?= e(old('full_name')) ?>" class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" required>
        <?php if (isset($errors['full_name'])): ?><div class="err"><?= e($errors['full_name']) ?></div><?php endif; ?>
    </div>
    <div class="row2">
        <div class="field">
            <label for="identifier">Admission number</label>
            <input type="text" id="identifier" name="identifier" value="<?= e(old('identifier')) ?>" class="<?= isset($errors['identifier']) ? 'is-invalid' : '' ?>" required>
            <?php if (isset($errors['identifier'])): ?><div class="err"><?= e($errors['identifier']) ?></div><?php endif; ?>
        </div>
        <div class="field">
            <label for="year_of_study">Year of study</label>
            <select id="year_of_study" name="year_of_study" class="<?= isset($errors['year_of_study']) ? 'is-invalid' : '' ?>" required>
                <option value="">Choose</option>
                <?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>"<?= (int) old('year_of_study') === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?>
            </select>
            <?php if (isset($errors['year_of_study'])): ?><div class="err"><?= e($errors['year_of_study']) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="field">
        <label for="programme">Programme</label>
        <select id="programme" name="programme" class="<?= isset($errors['programme']) ? 'is-invalid' : '' ?>" required>
            <option value="">Choose your programme</option>
            <?php foreach ($programmes as $p): ?><option<?= old('programme') === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
        </select>
        <?php if (isset($errors['programme'])): ?><div class="err"><?= e($errors['programme']) ?></div><?php endif; ?>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" value="<?= e(old('email')) ?>" class="<?= isset($errors['email']) ? 'is-invalid' : '' ?>" required>
        <?php if (isset($errors['email'])): ?><div class="err"><?= e($errors['email']) ?></div><?php endif; ?>
    </div>
    <div class="row2">
        <div class="field">
            <label for="password">Password</label>
            <div class="pw">
                <input type="password" id="password" name="password" autocomplete="new-password" class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                <button type="button" data-pw="password" aria-pressed="false">Show</button>
            </div>
            <?php if (isset($errors['password'])): ?><div class="err"><?= e($errors['password']) ?></div><?php else: ?><div class="hint">At least <?= (int) App::config('security')['min_password_len'] ?> characters, with letters and numbers.</div><?php endif; ?>
        </div>
        <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" class="<?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required>
            <?php if (isset($errors['password_confirm'])): ?><div class="err"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
        </div>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
</form>
<p class="auth-alt">Already registered? <a href="<?= e(url('auth/login')) ?>">Sign in</a>.</p>
