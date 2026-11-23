<h1>Choose a new password</h1>
<p class="sub">Use at least <?= (int) App::config('security')['min_password_len'] ?> characters with letters and numbers.</p>
<form method="post" action="<?= e(url('auth/reset')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field">
        <label for="password">New password</label>
        <div class="pw">
            <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
            <button type="button" data-pw="password" aria-pressed="false">Show</button>
        </div>
    </div>
    <div class="field">
        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Update password</button>
</form>
