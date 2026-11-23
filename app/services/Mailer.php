<?php
/**
 * Email delivery. Every message is written to the email_queue table first, then sent after the page
 * has been returned to the browser (so a slow mail server never slows a request). Anything that fails
 * stays queued and is retried by cron/send_mail.php, up to mail.max_attempts times.
 *
 * Drivers (config mail.driver): "log" writes the message to storage/mail/ ; "smtp" sends it for real.
 */
class Mailer
{
    private static bool $hooked = false;

    /** Queue one email. Returns false when the address is empty or the queue table is missing. */
    public static function queue(?int $userId, string $toEmail, ?string $toName, string $subject, string $heading, string $bodyText, ?string $linkPath = null, ?string $buttonLabel = null): bool
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return false;
        $linkUrl = $linkPath ? self::absolute($linkPath) : null;
        $html = self::htmlTemplate($heading, $bodyText, $linkUrl, $buttonLabel ?: 'Open in Campuserve');
        $text = $heading . "\n\n" . $bodyText . ($linkUrl ? "\n\n" . ($buttonLabel ?: 'Open in Campuserve') . ': ' . $linkUrl : '') . "\n\n-- Campuserve";
        try {
            Database::exec('INSERT INTO email_queue (user_id, to_email, to_name, subject, body_html, body_text) VALUES (?,?,?,?,?,?)',
                [$userId, $toEmail, $toName, mb_substr($subject, 0, 200), $html, $text]);
        } catch (Throwable $e) { error_log('Mailer queue failed: ' . $e->getMessage()); return false; }
        self::hookShutdown();
        return true;
    }

    /** Queue an email for a user id, honouring the admin on/off switch. Used by Notification::send(). */
    public static function toUser(int $userId, string $subject, string $body, ?string $link): void
    {
        if (Settings::get('email_notifications', '1') !== '1') return;
        $u = Database::one('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$u || $u['status'] !== 'active' || (int) ($u['email_notifications'] ?? 1) !== 1) return;
        self::queue($userId, $u['email'], $u['full_name'], '[Campuserve] ' . $subject, $subject, $body !== '' ? $body : $subject, $link);
    }

    /** Send queued messages. Returns [sent, failed]. */
    public static function flush(int $limit = 15): array
    {
        $cfg = App::config('mail');
        $rows = Database::all('SELECT * FROM email_queue WHERE status = "pending" AND attempts < ? ORDER BY id LIMIT ' . (int) $limit, [$cfg['max_attempts']]);
        $sent = 0; $failed = 0;
        foreach ($rows as $m) {
            try {
                $cfg['driver'] === 'smtp' ? self::smtpSend($cfg, $m) : self::logSend($m);
                Database::exec('UPDATE email_queue SET status = "sent", sent_at = NOW(), attempts = attempts + 1, last_error = NULL WHERE id = ?', [$m['id']]);
                $sent++;
            } catch (Throwable $e) {
                $attempts = (int) $m['attempts'] + 1;
                Database::exec('UPDATE email_queue SET attempts = ?, status = ?, last_error = ? WHERE id = ?',
                    [$attempts, $attempts >= $cfg['max_attempts'] ? 'failed' : 'pending', mb_substr($e->getMessage(), 0, 250), $m['id']]);
                $failed++;
            }
        }
        return [$sent, $failed];
    }

    public static function stats(): array
    {
        try {
            $r = Database::all('SELECT status, COUNT(*) n FROM email_queue GROUP BY status');
            return ['pending' => 0, 'sent' => 0, 'failed' => 0] + array_column($r, 'n', 'status');
        } catch (Throwable $e) { return ['pending' => 0, 'sent' => 0, 'failed' => 0]; }
    }

    /* ---------- internals ---------- */

    private static function hookShutdown(): void
    {
        if (self::$hooked || PHP_SAPI === 'cli') return;
        self::$hooked = true;
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            else { @ob_end_flush(); @flush(); }
            ignore_user_abort(true);
            try { self::flush(10); } catch (Throwable $e) { error_log('Mailer flush failed: ' . $e->getMessage()); }
        });
    }

    public static function absolute(string $path): string
    {
        return App::config('app_url') . '/index.php' . $path;     // $path looks like "?r=tickets/show&id=3"
    }

    private static function logSend(array $m): void
    {
        $dir = BASE_PATH . '/storage/mail';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('Cannot create storage/mail');
        $name = date('Ymd-His') . '-' . $m['id'] . '.txt';
        $body = 'To: ' . $m['to_name'] . ' <' . $m['to_email'] . ">\nSubject: " . $m['subject'] . "\nDate: " . date('r') . "\n\n" . $m['body_text'] . "\n";
        if (file_put_contents("$dir/$name", $body) === false) throw new RuntimeException('Cannot write mail log');
    }

    private static function smtpSend(array $c, array $m): void
    {
        $host = ($c['encryption'] === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = @stream_socket_client($host . ':' . $c['port'], $errno, $errstr, $c['timeout']);
        if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
        stream_set_timeout($fp, $c['timeout']);
        try {
            self::expect($fp, [220]);
            self::cmd($fp, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);
            if ($c['encryption'] === 'tls') {
                self::cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS handshake failed');
                self::cmd($fp, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);
            }
            if ($c['username'] !== '') {
                self::cmd($fp, 'AUTH LOGIN', [334]);
                self::cmd($fp, base64_encode($c['username']), [334]);
                self::cmd($fp, base64_encode($c['password']), [235]);
            }
            self::cmd($fp, 'MAIL FROM:<' . $c['from_email'] . '>', [250]);
            self::cmd($fp, 'RCPT TO:<' . $m['to_email'] . '>', [250, 251]);
            self::cmd($fp, 'DATA', [354]);
            $b = 'cs' . bin2hex(random_bytes(8));
            $hdr = 'From: ' . self::encName($c['from_name']) . ' <' . $c['from_email'] . ">\r\n"
                 . 'To: ' . ($m['to_name'] ? self::encName($m['to_name']) . ' ' : '') . '<' . $m['to_email'] . ">\r\n"
                 . 'Subject: =?UTF-8?B?' . base64_encode($m['subject']) . "?=\r\n"
                 . 'Date: ' . date('r') . "\r\nMIME-Version: 1.0\r\n"
                 . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n";
            $part = fn(string $type, string $body) => "--$b\r\nContent-Type: $type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body));
            $data = $hdr . "\r\n" . $part('text/plain', $m['body_text']) . $part('text/html', $m['body_html']) . "--$b--\r\n";
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
            fwrite($fp, $data . "\r\n.\r\n");
            self::expect($fp, [250]);
            @fwrite($fp, "QUIT\r\n");
        } finally { @fclose($fp); }
    }

    private static function encName(string $n): string { return '=?UTF-8?B?' . base64_encode($n) . '?='; }

    private static function cmd($fp, string $line, array $ok): void { fwrite($fp, $line . "\r\n"); self::expect($fp, $ok); }

    private static function expect($fp, array $ok): void
    {
        $resp = '';
        while (($l = fgets($fp, 515)) !== false) { $resp .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        if (!in_array((int) substr($resp, 0, 3), $ok, true)) throw new RuntimeException('SMTP: ' . trim(substr($resp, 0, 200)));
    }

    private static function htmlTemplate(string $heading, string $body, ?string $link, string $button): string
    {
        $btn = $link ? '<p style="margin:24px 0"><a href="' . e($link) . '" style="background:#1F5F4A;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">' . e($button) . '</a></p>'
                       . '<p style="font-size:12px;color:#6b7280">If the button does not work, copy this link:<br>' . e($link) . '</p>' : '';
        return '<!doctype html><html><body style="margin:0;background:#f4f5f2;font-family:Segoe UI,Arial,sans-serif;color:#1f2933">'
            . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">'
            . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:#1F5F4A;color:#fff;padding:16px 28px;font-size:18px;font-weight:700">Campuserve</td></tr>'
            . '<tr><td style="padding:28px"><h1 style="margin:0 0 12px;font-size:20px">' . e($heading) . '</h1>'
            . '<p style="margin:0;line-height:1.55;white-space:pre-line">' . e($body) . '</p>' . $btn . '</td></tr>'
            . '<tr><td style="padding:14px 28px;background:#f4f5f2;font-size:12px;color:#6b7280">Sent by Campuserve. Please do not reply to this email.</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
