<div class="page-head"><div><h2>Categories &amp; departments</h2><p>Where each kind of request is routed.</p></div></div>
<section class="sec"><div class="sec-head"><h3>Categories</h3></div>
    <p class="note">Category names match the labels the classifier predicts, so existing names cannot be renamed. You can change where a category routes and its description. Changing the department affects new requests only.</p>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Category</th><th>Routes to</th><th>Description</th><th class="right">Requests</th><th></th></tr></thead><tbody>
    <?php foreach ($categories as $c): ?>
    <tr><form method="post" action="<?= e(url('catalog/savecategory')) ?>" id="cat<?= (int) $c['id'] ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form>
        <td><span class="subj"><?= e($c['name']) ?></span></td>
        <td><select name="department_id" form="cat<?= (int) $c['id'] ?>"><?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $c['department_id'] === (int) $d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></td>
        <td><input type="text" name="description" form="cat<?= (int) $c['id'] ?>" maxlength="255" value="<?= e($c['description'] ?? '') ?>"></td>
        <td class="right"><?= (int) $c['tickets'] ?></td>
        <td class="right"><div class="row-actions"><button class="btn btn-sm btn-ghost" type="submit" form="cat<?= (int) $c['id'] ?>">Save</button>
            <?php if (!(int) $c['tickets']): ?><form method="post" action="<?= e(url('catalog/deletecategory')) ?>" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form><?php endif; ?></div></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <h4 style="margin:22px 0 8px">Add a category</h4>
    <form method="post" action="<?= e(url('catalog/savecategory')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="0">
        <input type="text" name="name" placeholder="Name" maxlength="100" required>
        <select name="department_id"><?php foreach ($departments as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?></select>
        <input type="text" name="description" placeholder="Description (optional)" maxlength="255">
        <button class="btn btn-primary btn-sm" type="submit">Add</button></form>
</section>
<section class="sec" style="margin-top:32px"><div class="sec-head"><h3>Departments</h3></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Code</th><th>Email</th><th class="right">Categories</th><th class="right">People</th><th></th></tr></thead><tbody>
    <?php foreach ($departments as $d): ?>
    <tr><form method="post" action="<?= e(url('catalog/savedepartment')) ?>" id="dep<?= (int) $d['id'] ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"></form>
        <td><input type="text" name="name" form="dep<?= (int) $d['id'] ?>" value="<?= e($d['name']) ?>" maxlength="100" required></td>
        <td><input type="text" name="code" form="dep<?= (int) $d['id'] ?>" value="<?= e($d['code']) ?>" maxlength="10" size="8" required></td>
        <td><input type="email" name="email" form="dep<?= (int) $d['id'] ?>" value="<?= e($d['email'] ?? '') ?>"></td>
        <td class="right"><?= (int) $d['cats'] ?></td><td class="right"><?= (int) $d['people'] ?></td>
        <td class="right"><button class="btn btn-sm btn-ghost" type="submit" form="dep<?= (int) $d['id'] ?>">Save</button></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <h4 style="margin:22px 0 8px">Add a department</h4>
    <form method="post" action="<?= e(url('catalog/savedepartment')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="0">
        <input type="text" name="name" placeholder="Name" maxlength="100" required><input type="text" name="code" placeholder="Code" maxlength="10" size="8" required>
        <input type="email" name="email" placeholder="Email (optional)"><button class="btn btn-primary btn-sm" type="submit">Add</button></form>
</section>
