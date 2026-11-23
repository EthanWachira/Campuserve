<?php
/**
 * Continuous learning: gathers the labels staff have given (confirmed or corrected categories),
 * asks the Python service to retrain, records the before/after figures, and tracks which feedback each model used.
 * Needs database/add_ml_retraining.sql (tables ml_models and ml_feedback_used).
 */
class MLTrainer
{
    /** One labelled example per ticket: the text and the category staff settled on. */
    private const LABELLED = 'SELECT t.id AS ticket_id, t.subject, t.description, fc.name AS category,
                                     (mp.final_category_id <> mp.predicted_category_id) AS corrected, mp.id AS prediction_id
                                FROM ml_predictions mp
                                JOIN tickets t ON t.id = mp.ticket_id
                                JOIN categories fc ON fc.id = mp.final_category_id
                               WHERE mp.final_category_id IS NOT NULL
                                 AND mp.id = (SELECT MIN(id) FROM ml_predictions WHERE ticket_id = t.id)
                               ORDER BY t.id';

    public static function ready(): bool
    {
        try { Database::value('SELECT 1 FROM ml_models LIMIT 1'); return true; }
        catch (PDOException $e) { return false; }
    }

    public static function labelled(): array
    {
        return Database::all(self::LABELLED);
    }

    /** Counts shown on the dashboard. "new" = labelled but not yet used by a deployed model. */
    public static function summary(): array
    {
        $rows = self::labelled();
        $used = self::ready() ? array_flip(array_map('intval', array_column(Database::all('SELECT ticket_id FROM ml_feedback_used'), 'ticket_id'))) : [];
        $new = $newCorrected = 0;
        foreach ($rows as $r) {
            if (!isset($used[(int) $r['ticket_id']])) { $new++; if ((int) $r['corrected'] === 1) $newCorrected++; }
        }
        $corrected = count(array_filter($rows, fn($r) => (int) $r['corrected'] === 1));
        return ['labelled' => count($rows), 'corrected' => $corrected, 'confirmed' => count($rows) - $corrected,
                'new' => $new, 'new_corrected' => $newCorrected,
                'auto' => Settings::get('ml_auto_retrain', '0') === '1',
                'threshold' => max(1, (int) Settings::get('ml_retrain_threshold', '10'))];
    }

    public static function history(int $limit = 10): array
    {
        if (!self::ready()) return [];
        return Database::all('SELECT m.*, u.full_name AS trained_by_name FROM ml_models m LEFT JOIN users u ON u.id = m.trained_by
                               ORDER BY m.id DESC LIMIT ' . (int) $limit);
    }

    /** The model currently serving requests (latest deployed run), or null if nothing was retrained yet. */
    public static function current(): ?array
    {
        if (!self::ready()) return null;
        return Database::one('SELECT m.*, u.full_name AS trained_by_name FROM ml_models m LEFT JOIN users u ON u.id = m.trained_by
                               WHERE m.status = "deployed" ORDER BY m.id DESC LIMIT 1');
    }

    public static function deployedCount(): int
    {
        return self::ready() ? (int) Database::value('SELECT COUNT(*) FROM ml_models WHERE status = "deployed"') : 0;
    }

    /**
     * Retrain now. Returns ['ok' => bool, 'message' => string, 'model' => ?array].
     * @param string $trigger 'manual' (administrator clicked) or 'auto' (background job)
     */
    public static function retrain(?int $userId, bool $force = false, string $trigger = 'manual'): array
    {
        @set_time_limit(300);
        if (!self::ready()) return ['ok' => false, 'message' => 'The retraining tables are missing. Run database/add_ml_retraining.sql in phpMyAdmin first.', 'model' => null];
        $rows = self::labelled();
        if (!$rows) return ['ok' => false, 'message' => 'No staff-labelled requests yet. Staff confirm or correct categories on a request first.', 'model' => null];

        $payload = ['force' => $force, 'rows' => array_map(fn($r) => [
            'ticket_id' => (int) $r['ticket_id'],
            'text' => trim(preg_replace('/\s+/', ' ', $r['subject'] . '. ' . $r['description'])),
            'category' => $r['category'],
        ], $rows)];
        [$code, $res] = MLClient::postJson('/retrain', $payload, 300.0);
        if ($code === 0) return ['ok' => false, 'message' => 'The classifier service is offline. Start it with "py api.py" in the ml folder, then try again.', 'model' => null];
        if ($code !== 200 || !is_array($res) || !isset($res['version'])) {
            return ['ok' => false, 'message' => is_array($res) && isset($res['error']) ? (string) $res['error'] : 'Retraining failed (service returned HTTP ' . $code . ').', 'model' => null];
        }

        $f = fn($k) => isset($res[$k]) && $res[$k] !== null ? $res[$k] : null;
        Database::begin();
        try {
            Database::exec('INSERT INTO ml_models (version, previous_version, status, trigger_source, base_rows, trained_rows, prev_trained_rows, feedback_rows, feedback_train,
                                feedback_test, corrected_rows, synthetic_accuracy, prev_synthetic_accuracy, real_accuracy, prev_real_accuracy, macro_f1, categories_learned, note, duration_ms, trained_by)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$res['version'], $f('previous_version'), $res['status'] === 'deployed' ? 'deployed' : 'rejected', $trigger === 'auto' ? 'auto' : 'manual',
                 (int) $res['base_rows'], (int) $res['trained_rows'], $f('prev_trained_rows'), (int) $res['feedback_rows'], (int) $res['feedback_train'],
                 (int) $res['feedback_test'], (int) $res['corrected_rows'], $f('synthetic_accuracy'), $f('prev_synthetic_accuracy'),
                 $f('real_accuracy'), $f('prev_real_accuracy'), $f('macro_f1'), json_encode($res['categories_learned'] ?? new stdClass()),
                 mb_substr((string) ($res['message'] ?? ''), 0, 255), (int) ($res['duration_ms'] ?? 0), $userId]);
            $modelId = Database::insertId();
            if ($res['status'] === 'deployed') {
                foreach (array_chunk(array_map('intval', $res['used_ticket_ids'] ?? []), 200) as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '(?, ?)'));
                    $params = [];
                    foreach ($chunk as $tid) { $params[] = $tid; $params[] = $modelId; }
                    Database::exec("INSERT INTO ml_feedback_used (ticket_id, model_id) VALUES $ph ON DUPLICATE KEY UPDATE model_id = VALUES(model_id)", $params);
                }
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            error_log('ML retrain bookkeeping failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'The model was retrained but its record could not be saved: ' . $e->getMessage(), 'model' => null];
        }
        Auth::audit($userId, 'ml_retrain_' . $res['status'], $res['version'] . ' (' . $trigger . ')');
        return ['ok' => $res['status'] === 'deployed', 'message' => (string) $res['message'], 'model' => $res];
    }

    /** Background job: retrain when enough new corrections have piled up and the switch is on. */
    public static function autoRetrainIfDue(): void
    {
        if (!self::ready() || Settings::get('ml_auto_retrain', '0') !== '1') return;
        $s = self::summary();
        if ($s['new_corrected'] < $s['threshold']) return;
        if (!MLClient::status()['online']) return;
        // back off for an hour after a rejected run so the job does not retry on every cycle
        $last = Database::one('SELECT status, trained_at FROM ml_models ORDER BY id DESC LIMIT 1');
        if ($last && $last['status'] === 'rejected' && strtotime((string) $last['trained_at']) > time() - 3600) return;
        self::retrain(null, false, 'auto');
    }
}
