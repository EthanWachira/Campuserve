<?php
/** Administrator: manage departments and request categories. */
class CatalogController extends Controller
{
    public function indexAction(): void
    {
        $u = $this->requireRole('admin');
        $this->render('catalog/index', [
            'title' => 'Categories & departments', 'u' => $u,
            'categories' => Database::all('SELECT c.*, d.name AS department_name, (SELECT COUNT(*) FROM tickets t WHERE t.category_id = c.id) AS tickets FROM categories c JOIN departments d ON d.id = c.department_id ORDER BY c.id'),
            'departments' => Database::all('SELECT d.*, (SELECT COUNT(*) FROM categories c WHERE c.department_id = d.id) AS cats, (SELECT COUNT(*) FROM users x WHERE x.department_id = d.id) AS people FROM departments d ORDER BY d.name'),
        ]);
    }

    public function savecategoryAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('catalog/index');
        $this->requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $dept = (int) ($_POST['department_id'] ?? 0);
        $desc = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 255) ?: null;
        if (!Database::value('SELECT 1 FROM departments WHERE id = ?', [$dept])) { flash('error', 'Choose a department.'); redirect('catalog/index'); }
        if ($id) {
            if (!Database::value('SELECT 1 FROM categories WHERE id = ?', [$id])) App::notFound();
            Database::exec('UPDATE categories SET department_id = ?, description = ? WHERE id = ?', [$dept, $desc, $id]);
            Auth::audit((int) $u['id'], 'category_edited', "category #$id");
            flash('success', 'Category updated. New requests in it now route to the chosen department.');
        } else {
            if (mb_strlen($name) < 3 || mb_strlen($name) > 100) { flash('error', 'Give the category a name (3 to 100 characters).'); redirect('catalog/index'); }
            if (Database::value('SELECT 1 FROM categories WHERE name = ?', [$name])) { flash('error', 'A category with that name already exists.'); redirect('catalog/index'); }
            Database::exec('INSERT INTO categories (name, department_id, description) VALUES (?,?,?)', [$name, $dept, $desc]);
            Auth::audit((int) $u['id'], 'category_created', $name);
            flash('success', 'Category added. Staff can pick it when correcting a request; the classifier will only predict it after retraining.');
        }
        redirect('catalog/index');
    }

    public function deletecategoryAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('catalog/index');
        $this->requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $used = (int) Database::value('SELECT (SELECT COUNT(*) FROM tickets WHERE category_id = ?) + (SELECT COUNT(*) FROM ml_predictions WHERE predicted_category_id = ? OR final_category_id = ?)', [$id, $id, $id]);
        if ($used) flash('error', 'This category is used by existing requests and cannot be deleted.');
        else { Database::exec('DELETE FROM categories WHERE id = ?', [$id]); Auth::audit((int) $u['id'], 'category_deleted', "category #$id"); flash('success', 'Category deleted.'); }
        redirect('catalog/index');
    }

    public function savedepartmentAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('catalog/index');
        $this->requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $email = trim((string) ($_POST['email'] ?? '')) ?: null;
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) { flash('error', 'Give the department a name (3 to 100 characters).'); redirect('catalog/index'); }
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) { flash('error', 'The code must be 2 to 10 letters or digits.'); redirect('catalog/index'); }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error', 'Enter a valid department email.'); redirect('catalog/index'); }
        try {
            if ($id) Database::exec('UPDATE departments SET name = ?, code = ?, email = ? WHERE id = ?', [$name, $code, $email, $id]);
            else Database::exec('INSERT INTO departments (name, code, email) VALUES (?,?,?)', [$name, $code, $email]);
            Auth::audit((int) $u['id'], $id ? 'department_edited' : 'department_created', $name);
            flash('success', $id ? 'Department updated.' : 'Department added.');
        } catch (PDOException $e) {
            flash('error', 'That name or code is already used by another department.');
        }
        redirect('catalog/index');
    }
}
