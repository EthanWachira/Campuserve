<?php
class ApiController extends Controller
{
    /**
     * Live classification preview used by the landing page demo and the new-request form.
     * Nothing is saved. Rate-limited per session because the landing page is public.
     */
    public function classifyAction(): void
    {
        if (!$this->isPost()) $this->json(['error' => 'POST required.'], 405);
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!csrf_valid((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) $this->json(['error' => 'Session expired. Reload the page.'], 419);

        $limit = App::config('security')['demo_classify_per_minute'];
        $now = time();
        $hits = array_filter($_SESSION['classify_hits'] ?? [], fn($t) => $t > $now - 60);
        if (count($hits) >= $limit) $this->json(['error' => 'Too many tries. Wait a minute and try again.'], 429);
        $hits[] = $now;
        $_SESSION['classify_hits'] = $hits;

        $text = trim((string) ($body['text'] ?? ''));
        if (mb_strlen($text) < 10) $this->json(['error' => 'Write a little more so the classifier has something to work with.'], 422);
        $text = mb_substr($text, 0, 1500);

        $r = MLClient::classify('', $text);
        $cat = Database::one('SELECT c.name, d.name AS department FROM categories c JOIN departments d ON d.id = c.department_id WHERE c.name = ?', [$r['category']]);
        $this->json([
            'category' => $cat['name'] ?? 'General Enquiries',
            'department' => $cat['department'] ?? 'Registry',
            'confidence' => round((float) $r['confidence'] * 100),
            'priority' => $r['priority'],
            'needs_review' => (bool) $r['needs_review'],
            'source' => $r['source'],
            'alternatives' => array_map(fn($a) => ['category' => $a['category'], 'confidence' => round($a['confidence'] * 100)], $r['alternatives'] ?? []),
        ]);
    }
}
