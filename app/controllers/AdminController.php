<?php
class AdminController extends Controller
{
    public function usersAction(): void
    {
        $u = $this->requireRole('admin');
        if ($this->isPost()) {
            $this->requireCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $new = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
            if ($id === (int) $u['id']) {
                flash('error', 'You cannot suspend your own account.');
            } elseif (Database::value('SELECT 1 FROM users WHERE id = ?', [$id])) {
                User::setStatus($id, $new);
                Auth::audit((int) $u['id'], 'user_' . $new, "user #$id");
                flash('success', 'Account ' . ($new === 'active' ? 'reactivated.' : 'suspended.'));
            }
            redirect('admin/users', array_filter(['role' => $_GET['role'] ?? '', 'q' => $_GET['q'] ?? '']));
        }
        $role = in_array($_GET['role'] ?? '', ['student', 'lecturer', 'staff', 'admin'], true) ? $_GET['role'] : null;
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->render('admin/users', ['title' => 'Users', 'u' => $u, 'users' => User::all($role, $q), 'role' => $role, 'q' => $q, 'counts' => User::countByRole()]);
    }

    /** The machine learning pipeline page: dataset, training, integration, and the database records it classified. */
    public function mlAction(): void
    {
        $u = $this->requireRole('admin');
        $latest = null; $results = []; $predictedMix = []; $schemaMissing = false;
        try {
            $latest = Database::one('SELECT r.*, u.full_name AS run_by_name FROM ml_runs r LEFT JOIN users u ON u.id = r.run_by ORDER BY r.id DESC LIMIT 1');
            if ($latest) {
                $results = Database::all(
                    'SELECT rr.*, t.reference_no, t.subject, pc.name AS predicted_name, cc.name AS current_name
                       FROM ml_run_results rr JOIN tickets t ON t.id = rr.ticket_id
                       JOIN categories pc ON pc.id = rr.predicted_category_id JOIN categories cc ON cc.id = t.category_id
                      WHERE rr.run_id = ? ORDER BY t.id LIMIT 200', [$latest['id']]);
                $predictedMix = array_map(fn($r) => [$r['name'], (int) $r['n']], Database::all(
                    'SELECT c.name, COUNT(*) n FROM ml_run_results rr JOIN categories c ON c.id = rr.predicted_category_id
                      WHERE rr.run_id = ? GROUP BY c.id, c.name ORDER BY n DESC', [$latest['id']]));
            }
        } catch (PDOException $e) {
            $schemaMissing = true;
        }
        $this->render('admin/ml', [
            'title' => 'Machine learning pipeline', 'u' => $u,
            'dataset' => $this->datasetSummary(), 'metrics' => MLClient::metrics(), 'status' => MLClient::status(),
            'apiUrl' => App::config('ml')['url'],
            'totalRecords' => (int) Database::value('SELECT COUNT(*) FROM tickets'),
            'latest' => $latest, 'results' => $results, 'predictedMix' => $predictedMix, 'schemaMissing' => $schemaMissing,
            'agreement' => Stats::mlAgreement(), 'confusions' => Stats::mlConfusions(),
        ]);
    }

    /** Supervisor step 4: run the trained model over every record in the database and keep an auditable result. */
    public function classifyallAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('admin/ml');
        $this->requireCsrf();
        set_time_limit(180);

        $status = MLClient::status();
        if (!$status['online']) {
            flash('error', 'The classifier service is offline, so nothing was classified. Start it with "py api.py" in the ml folder and try again.');
            redirect('admin/ml');
        }
        $cats = array_column(Database::all('SELECT id, name FROM categories'), 'id', 'name');
        $tickets = Database::all('SELECT id, subject, description, category_id FROM tickets ORDER BY id');
        $t0 = microtime(true);
        $done = $matched = $review = $failed = 0; $confSum = 0.0;

        Database::begin();
        try {
            Database::exec('INSERT INTO ml_runs (run_by, model_version) VALUES (?, ?)', [$u['id'], $status['version'] ?? 'unknown']);
            $runId = Database::insertId();
            foreach ($tickets as $t) {
                $r = MLClient::classify((string) $t['subject'], (string) $t['description']);
                if (($r['source'] ?? '') !== 'ml' || !isset($cats[$r['category']])) { $failed++; continue; }
                $match = (int) $cats[$r['category']] === (int) $t['category_id'] ? 1 : 0;
                $nr = !empty($r['needs_review']) ? 1 : 0;
                Database::exec('INSERT INTO ml_run_results (run_id, ticket_id, predicted_category_id, confidence, priority, matches_current, needs_review) VALUES (?,?,?,?,?,?,?)',
                    [$runId, $t['id'], $cats[$r['category']], $r['confidence'], $r['priority'] ?? null, $match, $nr]);
                $done++; $matched += $match; $review += $nr; $confSum += (float) $r['confidence'];
            }
            Database::exec('UPDATE ml_runs SET records = ?, matched = ?, needs_review = ?, avg_confidence = ?, duration_ms = ? WHERE id = ?',
                [$done, $matched, $review, $done ? round($confSum / $done, 4) : null, (int) round((microtime(true) - $t0) * 1000), $runId]);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('ML batch run failed: ' . $e->getMessage());
            flash('error', 'The run failed and was rolled back. Check that add_ml_runs.sql was imported.');
            redirect('admin/ml');
        }
        Auth::audit((int) $u['id'], 'ml_batch_run', "$done records classified");
        flash('success', "The model classified $done of " . count($tickets) . ' records' . ($failed ? " ($failed could not be classified)" : '') . '.');
        redirect('admin/ml');
    }

    /** Describes the training file for supervisor step 1: records, attributes, class balance, sources. */
    private function datasetSummary(): ?array
    {
        $file = BASE_PATH . '/ml/data/tickets_synthetic.csv';
        if (!is_file($file) || ($h = fopen($file, 'r')) === false) return null;
        $cols = fgetcsv($h, 0, ',', '"', '');
        $ci = array_search('category', $cols, true); $si = array_search('source', $cols, true);
        $total = 0; $classes = []; $sources = [];
        while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if (count($r) < count($cols)) continue;
            $total++;
            $classes[$r[$ci]] = ($classes[$r[$ci]] ?? 0) + 1;
            if ($si !== false) $sources[$r[$si]] = ($sources[$r[$si]] ?? 0) + 1;
        }
        fclose($h);
        arsort($classes); arsort($sources);
        return ['file' => 'ml/data/tickets_synthetic.csv', 'rows' => $total, 'columns' => $cols, 'classes' => $classes, 'sources' => $sources, 'modified' => filemtime($file)];
    }
}
