<div class="page-head"><div><h2>Book an appointment</h2>
    <p>Choose a lecturer and a day, then pick a time. Each appointment is <?= (int) $cfg['slot_minutes'] ?> minutes and the lecturer confirms your request.</p></div></div>
<div class="grid-2">
<div>
<?php if (!$lecturers): ?>
    <p class="empty">No lecturer has published availability yet. Please check back later.</p>
<?php else: ?>
    <form method="get" action="<?= e(base_path_url() . '/index.php') ?>" class="inline-form book-pick" data-autosubmit>
        <input type="hidden" name="r" value="appointments/book">
        <div class="field"><label for="lecturer">Lecturer</label>
            <select id="lecturer" name="lecturer">
                <option value="">Choose a lecturer</option>
                <?php foreach ($lecturers as $l): ?>
                    <option value="<?= (int) $l['id'] ?>"<?= $lecturer && (int) $lecturer['id'] === (int) $l['id'] ? ' selected' : '' ?>><?= e($l['full_name']) ?><?= $l['school_code'] ? ' (' . e($l['school_code']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="field"><label for="date">Day</label>
            <input type="date" id="date" name="date" min="<?= e($min) ?>" max="<?= e($max) ?>" value="<?= e($date) ?>"></div>
        <button class="btn btn-ghost" type="submit">Show times</button>
    </form>

    <?php if ($lecturer): ?>
        <section class="sec">
            <div class="sec-head"><h3><?= e($lecturer['full_name']) ?></h3></div>
            <div class="week">
                <?php foreach (Appointment::DAYS as $d => $label): ?>
                    <div class="<?= isset($week[$d]) ? 'on' : 'off' ?>"><b><?= $label ?></b><?php if (isset($week[$d])): foreach ($week[$d] as [$s, $e2]): ?><?= e($s . ' to ' . $e2) ?><br><?php endforeach; else: ?>Not available<?php endif; ?></div>
                <?php endforeach; ?>
            </div>
            <?php if ($dates): ?>
                <p class="label" style="margin-top:16px">Next days with free times</p>
                <div class="chips">
                    <?php foreach ($dates as $d): ?>
                        <a class="chip-btn" href="<?= e(url('appointments/book', ['lecturer' => $lecturer['id'], 'date' => $d])) ?>"><?= e(fmt_day($d)) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="note">No free times in the next <?= (int) $cfg['max_days_ahead'] ?> days.</p>
            <?php endif; ?>
        </section>

        <?php if ($date !== ''): ?>
            <?php $free = array_filter($slots, fn($s) => $s['free']); ?>
            <section class="sec">
                <div class="sec-head"><h3>Times on <?= e(fmt_day($date)) ?></h3></div>
                <?php if (!$slots): ?>
                    <p class="empty"><?= e($lecturer['full_name']) ?> does not offer appointments on this day. Try one of the days above.</p>
                <?php elseif (!$free): ?>
                    <p class="empty">Nothing is free on this day. Try another day.</p>
                <?php endif; ?>
                <?php if ($slots): ?>
                <form method="post" action="<?= e(url('appointments/book')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="lecturer" value="<?= (int) $lecturer['id'] ?>">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <fieldset class="slots" style="border:0;padding:0;margin:0 0 18px"><legend class="sr">Choose a time</legend>
                        <?php foreach ($slots as $s): ?>
                            <label class="slot<?= $s['free'] ? '' : ' is-taken' ?>">
                                <input type="radio" name="time" value="<?= e($s['time']) ?>"<?= $s['free'] ? ' required' : ' disabled' ?>>
                                <span><?= e($s['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if ($free): ?>
                        <div class="field">
                            <label for="purpose">What do you want to discuss?</label>
                            <textarea id="purpose" class="short" name="purpose" maxlength="255" required><?= e(old('purpose')) ?></textarea>
                            <div class="hint">One or two sentences, for example the unit code or the topic of your project.</div>
                        </div>
                        <button class="btn btn-primary" type="submit">Request appointment</button>
                        <a class="btn btn-ghost" href="<?= e(url('appointments/index')) ?>">Cancel</a>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
<aside class="panel-side">
    <h3>How it works</h3>
    <ul style="margin:0;padding-left:18px;color:var(--ink-soft)">
        <li>Pick a day the lecturer is available and choose a free time.</li>
        <li>The lecturer is notified and confirms or declines.</li>
        <li>You are notified of the answer. You can withdraw a request or cancel before the time.</li>
        <li>You can hold up to <?= (int) $cfg['max_active_per_student'] ?> upcoming appointments at once.</li>
    </ul>
</aside>
</div>
