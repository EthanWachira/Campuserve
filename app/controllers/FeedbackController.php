<?php
/** Administrator: turn staff confirmations and corrections into training data for the classifier. */
class FeedbackController extends Controller
{
    private const SQL = 'SELECT t.subject, t.description, fc.name AS category, (mp.final_category_id <> mp.predicted_category_id) AS corrected
                           FROM ml_predictions mp JOIN tickets t ON t.id = mp.ticket_id JOIN categories fc ON fc.id = mp.final_category_id
                          WHERE mp.final_category_id IS NOT NULL AND mp.id = (SELECT MIN(id) FROM ml_predictions WHERE ticket_id = t.id) ORDER BY t.id';

    public function indexAction(): void
    {
        $u = $this->requireRole('admin');
        $rows = Database::all(self::SQL);
        $corrected = count(array_filter($rows, fn($r) => (int) $r['corrected'] === 1));
        $file = BASE_PATH . '/ml/data/corrections.csv';
        $this->render('feedback/index', [
            'title' => 'Retraining data', 'u' => $u, 'total' => count($rows), 'corrected' => $corrected, 'confirmed' => count($rows) - $corrected,
            'sum' => MLTrainer::summary(), 'history' => MLTrainer::history(8), 'ready' => MLTrainer::ready(), 'status' => MLClient::status(),
            'fileTime' => is_file($file) ? filemtime($file) : null, 'confusions' => Stats::mlConfusions(8),
            'perCategory' => array_map(fn($r) => [$r['name'], (int) $r['n']], Database::all('SELECT fc.name, COUNT(*) n FROM ml_predictions mp JOIN categories fc ON fc.id = mp.final_category_id WHERE mp.final_category_id IS NOT NULL GROUP BY fc.id, fc.name ORDER BY n DESC')),
        ]);
    }

    /** The learning step: retrain from staff feedback, deploy the new model, record before/after figures. */
    public function retrainAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('feedback/index');
        $this->requireCsrf();
        $r = MLTrainer::retrain((int) $u['id'], ($_POST['force'] ?? '') === '1', 'manual');
        if ($r['model'] === null) {
            flash('error', $r['message']);
        } elseif ($r['ok']) {
            $m = $r['model'];
            $bits = [];
            if ($m['prev_synthetic_accuracy'] !== null) $bits[] = 'original hold-out accuracy ' . round($m['prev_synthetic_accuracy'] * 100, 1) . '% to ' . round($m['synthetic_accuracy'] * 100, 1) . '%';
            if ($m['real_accuracy'] !== null) $bits[] = 'staff-labelled test requests ' . round($m['prev_real_accuracy'] * 100, 1) . '% to ' . round($m['real_accuracy'] * 100, 1) . '%';
            flash('success', 'The model learned from ' . (int) $m['feedback_train'] . ' staff-labelled requests and is now live (' . $m['version'] . ')' . ($bits ? ': ' . implode('; ', $bits) . '.' : '.'));
        } else {
            flash('error', $r['message'] . ' Tick "Deploy even if accuracy drops" to override.');
        }
        redirect('admin/ml');
    }

    /** Automatic retraining switch and threshold. */
    public function settingsAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('feedback/index');
        $this->requireCsrf();
        $on = ($_POST['auto'] ?? '') === '1';
        $n = min(500, max(1, (int) ($_POST['threshold'] ?? 10)));
        try {
            Settings::set('ml_auto_retrain', $on ? '1' : '0');
            Settings::set('ml_retrain_threshold', (string) $n);
            Auth::audit((int) $u['id'], 'ml_auto_retrain_' . ($on ? 'on' : 'off'), "threshold $n");
            flash('success', $on ? "Automatic retraining is on: the model retrains once $n new staff corrections have been collected." : 'Automatic retraining is off. Use the Retrain button when you want the model to learn.');
        } catch (PDOException $e) {
            flash('error', 'The settings table is missing. Run database/add_auto_assign.sql first.');
        }
        redirect('feedback/index');
    }

    /** Browser download of the labelled examples (text,category). */
    public function downloadAction(): void
    {
        $u = $this->requireRole('admin');
        Auth::audit((int) $u['id'], 'ml_feedback_download', null);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="corrections.csv"');
        echo $this->build(Database::all(self::SQL));
        exit;
    }

    /** Write ml/data/corrections.csv so `python train.py --extra data/corrections.csv` can use it. */
    public function prepareAction(): void
    {
        $u = $this->requireRole('admin');
        if (!$this->isPost()) redirect('feedback/index');
        $this->requireCsrf();
        $rows = Database::all(self::SQL);
        if (!$rows) { flash('error', 'No reviewed requests yet. Staff confirm or correct categories first.'); redirect('feedback/index'); }
        $dir = BASE_PATH . '/ml/data';
        if (!is_dir($dir) || file_put_contents($dir . '/corrections.csv', $this->build($rows)) === false) {
            flash('error', 'Could not write ml/data/corrections.csv. Use the download button instead.');
        } else {
            Auth::audit((int) $u['id'], 'ml_feedback_prepared', count($rows) . ' rows');
            flash('success', count($rows) . ' labelled examples written to ml/data/corrections.csv.');
        }
        redirect('feedback/index');
    }

    private function build(array $rows): string
    {
        $h = fopen('php://temp', 'w+');
        fputcsv($h, ['text', 'category']);
        foreach ($rows as $r) {
            $text = trim(preg_replace('/\s+/', ' ', $r['subject'] . '. ' . $r['description']));
            fputcsv($h, [$text, $r['category']]);
        }
        rewind($h);
        $csv = stream_get_contents($h);
        fclose($h);
        return $csv;
    }
}
