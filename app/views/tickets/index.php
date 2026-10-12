<?php $rows = $result['rows']; $qs = fn(array $o) => url('tickets/index', array_filter(array_merge(['status' => $filters['status'], 'category' => $filters['category'], 'q' => $filters['q'], 'review' => $filters['review'] ? 1 : '', 'mine' => $filters['mine'] ? 1 : ''], $o))); ?>
<div class="page-head">
    <div><h2><?= e($title) ?></h2><p><?= (int) $result['total'] ?> request<?= $result['total'] === 1 ? '' : 's' ?><?= $filters['review'] ? ' needing a category check' : '' ?></p></div>
    <?php if ($u['role'] === 'student'): ?><a class="btn btn-primary" href="<?= e(url('tickets/create')) ?>"><?= icon('plus') ?>New request</a><?php endif; ?>
</div>
<form class="filters" method="get" action="<?= e(url('tickets/index')) ?>">
    <input type="hidden" name="r" value="tickets/index">
    <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Reference, title<?= $u['role'] !== 'student' ? ' or student' : '' ?>"></div>
    <div class="field"><label for="status">Status</label>
        <select id="status" name="status"><option value="">Any status</option><?php foreach (TICKET_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="category">Category</label>
        <select id="category" name="category"><option value="">Any category</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $filters['category'] === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($u['role'] !== 'student'): ?>
        <label class="check"><input type="checkbox" name="review" value="1"<?= $filters['review'] ? ' checked' : '' ?>>Needs review</label>
        <label class="check"><input type="checkbox" name="mine" value="1"<?= $filters['mine'] ? ' checked' : '' ?>>Assigned to me</label>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit">Apply</button>
    <a class="btn btn-ghost" href="<?= e(url('tickets/index')) ?>">Clear</a>
</form>
<?php View::partial('ticket_rows', ['rows' => $rows, 'showStudent' => $u['role'] !== 'student', 'emptyText' => 'No requests match these filters.']); ?>
<?php if ($result['pages'] > 1): ?>
<nav class="pager" aria-label="Pages">
    <?php if ($result['page'] > 1): ?><a href="<?= e($qs(['page' => $result['page'] - 1])) ?>">Previous</a><?php endif; ?>
    <span class="cur"><?= $result['page'] ?></span><span>of <?= $result['pages'] ?></span>
    <?php if ($result['page'] < $result['pages']): ?><a href="<?= e($qs(['page' => $result['page'] + 1])) ?>">Next</a><?php endif; ?>
</nav>
<?php endif; ?>
