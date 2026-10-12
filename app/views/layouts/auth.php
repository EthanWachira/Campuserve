<!doctype html>
<html lang="en"><head><?php View::partial('head', ['title' => $title ?? '']); ?></head>
<body class="auth">
<section class="auth-art" aria-hidden="true">
    <a class="brand" href="<?= e(url('home/index')) ?>"><?= logo() ?><span>CampuServe</span></a>
    <div class="stub-demo">
        <div class="stub-top"><span>Request</span><b>CS-2026-000142</b></div>
        <ol class="trail">
            <li class="done">Submitted</li>
            <li class="done">Routed to Finance Office</li>
            <li class="now">Assigned to Peter O.</li>
            <li>Resolved</li>
        </ol>
    </div>
    <p class="auth-line">Every request gets a number, an owner and a status you can check.</p>
</section>
<main id="main" class="auth-form">
    <?php View::partial('flashes'); ?>
    <?= $content ?>
</main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body></html>
