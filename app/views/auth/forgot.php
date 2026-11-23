<h1>Forgot your password?</h1>
<p class="sub">Enter your Campuserve email and we will send you a link to choose a new one.</p>
<form method="post" action="<?= e(url('auth/forgot')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="username" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
</form>
<p class="auth-alt"><a href="<?= e(url('auth/login')) ?>">Back to sign in</a></p>
