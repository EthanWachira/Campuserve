<div class="page-head"><div><h2>New request</h2><p>Describe the problem in your own words. We pick the department for you.</p></div></div>
<div class="grid-2">
<form method="post" action="<?= e(url('tickets/create')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="subject">Title</label>
        <input type="text" id="subject" name="subject" maxlength="150" value="<?= e(old('subject')) ?>" class="<?= isset($errors['subject']) ? 'is-invalid' : '' ?>" placeholder="For example: Missing results for last semester" required>
        <?php if (isset($errors['subject'])): ?><div class="err"><?= e($errors['subject']) ?></div><?php endif; ?>
    </div>
    <div class="field">
        <label for="description">What happened?</label>
        <textarea id="description" name="description" maxlength="3000" class="<?= isset($errors['description']) ? 'is-invalid' : '' ?>" required><?= e(old('description')) ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="err"><?= e($errors['description']) ?></div><?php else: ?><div class="hint">Include course or unit codes, dates and anything you already tried.</div><?php endif; ?>
    </div>
    <p class="label">Where it will go</p>
    <div class="preview" id="preview" data-endpoint="<?= e(url('api/classify')) ?>" aria-live="polite"><p class="idle">Keep typing and we will show the department that will receive this.</p></div>
    <button class="btn btn-primary" type="submit">Submit request</button>
    <a class="btn btn-ghost" href="<?= e(url('dashboard/index')) ?>">Cancel</a>
</form>
<aside class="panel-side">
    <h3>What happens next</h3>
    <ul style="margin:0;padding-left:18px;color:var(--ink-soft)">
        <li>You get a reference number straight away.</li>
        <li>The department is notified and assigns a person.</li>
        <li>You are notified each time the status changes. You can reply on the request at any time.</li>
    </ul>
</aside>
</div>
