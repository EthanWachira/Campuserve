<div class="page-head"><div><h2>Weekly availability</h2>
    <p>Students can book <?= (int) $slotMin ?>-minute appointments inside these windows. Leave a window blank for no availability that day.</p></div></div>
<form method="post" action="<?= e(url('appointments/availability')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>Day</th><th>Window 1</th><th>Window 2 (optional)</th></tr></thead>
        <tbody>
        <?php foreach (Appointment::DAYS as $dow => $label): ?>
            <tr>
                <td><b><?= $label ?></b></td>
                <?php for ($j = 0; $j < 2; $j++): [$cs, $ce] = $current[$dow][$j] ?? ['', '']; ?>
                    <td><div class="win">
                        <select name="av[<?= $dow ?>][<?= $j ?>][start]" aria-label="<?= e($label) ?> window <?= $j + 1 ?> start">
                            <option value="">--</option>
                            <?php foreach ($times as $t): ?><option value="<?= e($t) ?>"<?= $t === $cs ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                        </select>
                        <span>to</span>
                        <select name="av[<?= $dow ?>][<?= $j ?>][end]" aria-label="<?= e($label) ?> window <?= $j + 1 ?> end">
                            <option value="">--</option>
                            <?php foreach ($times as $t): ?><option value="<?= e($t) ?>"<?= $t === $ce ? ' selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
                        </select>
                    </div></td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="note">Changing your hours does not affect appointments that are already booked.</p>
    <p style="margin-top:18px">
        <button class="btn btn-primary" type="submit">Save availability</button>
        <a class="btn btn-ghost" href="<?= e(url('appointments/index')) ?>">Back</a>
    </p>
</form>
