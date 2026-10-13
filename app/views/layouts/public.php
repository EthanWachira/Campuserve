<!doctype html>
<html lang="en"><head><?php View::partial('head', ['title' => $title ?? '']); ?></head>
<body class="public">
<a class="skip" href="#main">Skip to content</a>
<header class="pub-head">
    <a class="brand brand-dark" href="<?= e(url('home/index')) ?>"><?= logo() ?><span>Campusserve</span></a>
    <nav class="pub-nav" aria-label="Main">
        <a href="#how">How it works</a>
        <a href="#routing">Where requests go</a>
        <?php if (!empty($user)): ?>
            <a class="btn btn-primary" href="<?= e(url('dashboard/index')) ?>">Open dashboard</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="<?= e(url('auth/login')) ?>">Sign in</a>
            <a class="btn btn-primary" href="<?= e(url('auth/register')) ?>">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main id="main"><?php View::partial('flashes'); ?><?= $content ?></main>
<footer class="pub-foot">
    <div><a class="brand brand-dark" href="<?= e(url('home/index')) ?>"><?= logo() ?><span>Campusserve</span></a></div>
    
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body></html>
