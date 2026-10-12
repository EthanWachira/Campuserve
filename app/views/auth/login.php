<h1>Sign in</h1>
<p class="sub">Use your CampuServe email and password.</p>
<form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" autocomplete="username" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <div class="pw">
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="button" data-pw="password" aria-pressed="false">Show</button>
        </div>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
</form>
<p class="auth-alt">New student? <a href="<?= e(url('auth/register')) ?>">Create an account</a>. <a href="<?= e(url('home/index')) ?>">Back to the home page</a>.</p>

<?php if (!empty($demoUsers)): ?>
<div class="demo-box">
    <h2>Demo accounts</h2>
    <p>This is a prototype with sample data. Pick a role to fill the form (password: Password@123).</p>
    <div class="demo-list">
        <?php foreach ($demoUsers as $d): ?>
            <button type="button" class="btn btn-ghost btn-sm" data-fill-email="<?= e($d['email']) ?>"><?= e(role_label($d['role'])) ?></button>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
