<?php
$route = $_GET['r'] ?? 'dashboard/index';
$role = $u['role'];
$unread = Notification::unreadCount((int) $u['id']);
$nav = [['dashboard/index', $role === 'admin' ? 'Overview' : 'Dashboard', 'home']];
if ($role === 'student') {
    $nav[] = ['tickets/index', 'My requests', 'list'];
    $nav[] = ['tickets/create', 'New request', 'plus'];
} elseif ($role === 'admin') {
    $nav[] = ['tickets/index', 'All requests', 'list'];
    $nav[] = ['admin/users', 'Users', 'users'];
    $nav[] = ['admin/ml', 'Classifier', 'chip'];
} else {
    $nav[] = ['tickets/index', $role === 'lecturer' ? 'Assigned requests' : 'Request queue', 'list'];
}
$nav[] = ['notifications/index', 'Notifications', 'bell'];
?>
<!doctype html>
<html lang="en"><head><?php View::partial('head', ['title' => $title ?? '']); ?></head>
<body class="app">
<a class="skip" href="#main">Skip to content</a>
<aside class="side" id="side">
    <a class="brand" href="<?= e(url('dashboard/index')) ?>"><?= logo() ?><span>Campusserve</span></a>
    <nav aria-label="Main">
        <?php foreach ($nav as [$r, $label, $ic]): ?>
            <?php $active = $route === $r || ($r === 'tickets/index' && $route === 'tickets/show') || ($r === 'admin/users' && $route === 'admin/users'); ?>
            <a href="<?= e(url($r)) ?>" class="nav-link<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <?= icon($ic) ?><span><?= e($label) ?></span>
                <?php if ($r === 'notifications/index' && $unread): ?><b class="pill"><?= $unread ?></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (in_array($role, ['student', 'lecturer'], true)): ?>
            <span class="nav-link is-soon" title="Appointment booking is built in the next phase"><?= icon('cal') ?><span>Appointments</span><b class="soon">Soon</b></span>
        <?php endif; ?>
    </nav>
    <div class="side-foot">
        <div class="who">
            <span class="avatar"><?= e(initials($u['full_name'])) ?></span>
            <span class="who-text"><b><?= e($u['full_name']) ?></b><small><?= e(role_label($role)) ?><?= $u['department_name'] ? ', ' . e($u['department_name']) : '' ?></small></span>
        </div>
        <form method="post" action="<?= e(url('auth/logout')) ?>"><?= csrf_field() ?>
            <button class="nav-link nav-out" type="submit"><?= icon('out') ?><span>Sign out</span></button>
        </form>
    </div>
</aside>
<div class="shell">
    <header class="top">
        <button class="menu-btn" type="button" data-toggle="side" aria-label="Open menu"><?= icon('menu') ?></button>
        <h1 class="top-title"><?= e($title ?? '') ?></h1>
        <a class="bell" href="<?= e(url('notifications/index')) ?>" aria-label="Notifications<?= $unread ? ', ' . $unread . ' unread' : '' ?>"><?= icon('bell') ?><?php if ($unread): ?><b class="pill"><?= $unread ?></b><?php endif; ?></a>
    </header>
    <main id="main" class="page">
        <?php View::partial('flashes'); ?>
        <?= $content ?>
    </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body></html>
