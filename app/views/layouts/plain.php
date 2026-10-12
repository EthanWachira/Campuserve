<!doctype html>
<html lang="en"><head><?php View::partial('head', ['title' => $title ?? '']); ?></head>
<body class="plain">
<main class="plain-box"><?= $content ?></main>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body></html>
