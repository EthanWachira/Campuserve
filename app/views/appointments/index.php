<?php $isLect = $u['role'] === 'lecturer'; $confirmed = array_values(array_filter($upcoming, fn($a) => $a['status'] === 'confirmed')); ?>
<div class="page-head">
    <div><h2>Appointments</h2><p><?= $isLect ? 'Answer requests and keep your weekly availability up to date.' : 'Meet a lecturer about your project, your units or advising.' ?></p></div>
    <?php if ($isLect): ?>
        <a class="btn btn-ghost" href="<?= e(url('appointments/availability')) ?>"><?= icon('cal') ?>Set availability</a>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('appointments/book')) ?>"><?= icon('plus') ?>Book appointment</a>
    <?php endif; ?>
</div>

<?php if ($isLect): ?>
    <section class="sec">
        <div class="sec-head"><h3>Waiting for your response</h3></div>
        <?php View::partial('appointment_rows', ['rows' => $pending, 'u' => $u, 'emptyText' => 'No requests waiting. New ones appear here and in your notifications.']); ?>
    </section>
    <section class="sec">
        <div class="sec-head"><h3>Confirmed</h3></div>
        <?php View::partial('appointment_rows', ['rows' => $confirmed, 'u' => $u, 'emptyText' => 'No confirmed appointments coming up.']); ?>
    </section>
<?php else: ?>
    <section class="sec">
        <div class="sec-head"><h3>Upcoming</h3></div>
        <?php View::partial('appointment_rows', ['rows' => $upcoming, 'u' => $u, 'emptyText' => 'No appointments booked. Use Book appointment to request a time with a lecturer.']); ?>
    </section>
<?php endif; ?>

<section class="sec">
    <div class="sec-head"><h3>History</h3></div>
    <?php View::partial('appointment_rows', ['rows' => $history, 'u' => $u, 'emptyText' => 'Past, cancelled and declined appointments will show here.']); ?>
</section>
