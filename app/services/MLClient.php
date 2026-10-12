<?php
/**
 * Talks to the Python classification API (ml/api.py).
 * If the API is down or slow, a keyword fallback keeps ticket submission working and
 * flags the ticket for human review, so a student is never blocked by the ML service.
 */
class MLClient
{
    private const KEYWORDS = [
        'IT Support' => ['password', 'login', 'log in', 'wifi', 'wi-fi', 'portal', 'email', 'outlook', 'vpn', 'network', 'printer', 'printing', 'software', 'account locked', 'elearning', 'lab computer'],
        'Academic Records & Results' => ['transcript', 'results', 'grade', 'marks', 'remark', 'gpa', 'certificate', 'academic record', 'graduation', 'deferral'],
        'Fees & Billing Enquiries' => ['fee', 'fees', 'tuition', 'invoice', 'receipt', 'refund', 'bursary', 'instalment', 'payment', 'm-pesa', 'mpesa', 'arrears', 'balance'],
        'Timetable & Examinations' => ['timetable', 'exam', 'cat ', 'clash', 'lecture schedule', 'supplementary', 'exam card', 'venue'],
        'Admissions & Registration' => ['register', 'registration', 'admission', 'intake', 'programme change', 'transfer', 'unit registration', 'student number'],
        'Library Services' => ['library', 'book', 'journal', 'borrow', 'loan', 'overdue', 'catalogue', 'e-resource'],
        'Student Welfare' => ['counsel', 'stress', 'mental', 'harass', 'welfare', 'hardship', 'bereave', 'family emergency', 'safety', 'disability'],
        'Facilities & Accommodation' => ['hostel', 'room', 'leak', 'lights', 'air conditioning', 'parking', 'lift', 'washroom', 'water', 'window', 'key'],
        'Academic Advising' => ['advisor', 'adviser', 'lecturer', 'supervisor', 'consultation', 'recommendation', 'elective', 'mentor', 'project topic'],
    ];
    private const HIGH = '/\b(urgent|emergency|asap|immediately|today|tomorrow|deadline|harass\w*|unsafe|safety|locked out|missed (an )?exam|bereave\w*)\b/i';
    private const MEDIUM = '/\b(clash|missing|wrong|incorrect|blocked|not working|cannot|can\'?t|unable|error|overdue|expired|failed|refund|twice)\b/i';

    public static function classify(string $subject, string $description): array
    {
        $r = self::callApi('/classify', ['subject' => $subject, 'description' => $description], (float) App::config('ml')['timeout']);
        if (is_array($r) && isset($r['category'], $r['confidence'])) {
            $r['source'] = 'ml';
            return $r;
        }
        return self::fallback($subject . ' ' . $description);
    }

    /** @return array{online:bool, version:?string} */
    public static function status(): array
    {
        $r = self::callApi('/health', null, 1.0);
        return ['online' => is_array($r) && ($r['status'] ?? '') === 'ok', 'version' => $r['model_version'] ?? null];
    }

    public static function metrics(): ?array
    {
        $f = App::config('ml')['metrics_file'];
        return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    private static function callApi(string $path, ?array $payload, float $timeout): ?array
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init(rtrim(App::config('ml')['url'], '/') . $path);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(1.5, $timeout)];
        if ($payload !== null) {
            $opts += [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                      CURLOPT_POSTFIELDS => json_encode($payload)];
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    private static function fallback(string $text): array
    {
        $t = strtolower($text);
        $best = null; $bestScore = 0;
        foreach (self::KEYWORDS as $cat => $words) {
            $score = 0;
            foreach ($words as $w) { if (str_contains($t, $w)) $score++; }
            if ($score > $bestScore) { $best = $cat; $bestScore = $score; }
        }
        $priority = preg_match(self::HIGH, $text) ? 'high' : (preg_match(self::MEDIUM, $text) ? 'medium' : 'low');
        return [
            'source' => 'rules',
            'category' => $best ?? 'General Enquiries',
            'confidence' => $best ? min(0.6, 0.3 + 0.1 * $bestScore) : 0.0,
            'department' => null,
            'priority' => $priority,
            'needs_review' => true,
            'alternatives' => [],
            'model_version' => 'keyword-fallback',
        ];
    }
}
