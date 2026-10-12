<?php
/* ---------- output & URLs ---------- */
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path_url(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $dir;
}
function url(string $route = 'home/index', array $params = []): string
{
    $q = ['r' => $route] + $params;
    return base_path_url() . '/index.php?' . http_build_query($q);
}
function asset(string $path): string { return base_path_url() . '/assets/' . ltrim($path, '/'); }
/** Notification links are stored as "?r=tickets/show&id=3". */
function link_url(?string $stored): string { return $stored ? base_path_url() . '/index.php' . $stored : '#'; }
function redirect(string $route, array $params = []): void { header('Location: ' . url($route, $params)); exit; }

/* ---------- flash messages & old input ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function with_old(array $data): void { $_SESSION['old'] = $data; }
function old(string $key, $default = '') { return $_SESSION['old'][$key] ?? $default; }
function clear_old(): void { unset($_SESSION['old']); }

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(string $t): bool { return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }

/* ---------- formatting ---------- */
function time_ago(?string $dt): string
{
    if (!$dt) return '';
    $s = max(0, time() - strtotime($dt));
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 86400 * 14) return floor($s / 86400) . ' d ago';
    return date('j M Y', strtotime($dt));
}
function fmt_dt(?string $dt): string { return $dt ? date('j M Y, g:i a', strtotime($dt)) : '-'; }
function fmt_day(?string $dt): string { return $dt ? date('D j M', strtotime($dt)) : '-'; }
function fmt_time(?string $dt): string { return $dt ? date('g:i a', strtotime($dt)) : '-'; }
function hours_label($h): string
{
    if ($h === null) return 'No data yet';
    $h = (float) $h;
    return $h < 48 ? round($h, 1) . ' h' : round($h / 24, 1) . ' days';
}
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim(preg_replace('/^(Dr\.|Prof\.)\s*/', '', $name)));
    return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
}

const TICKET_STATUSES = ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
function status_label(string $s): string { return TICKET_STATUSES[$s] ?? ucfirst($s); }
function status_step(string $s): int { return array_search($s, array_keys(TICKET_STATUSES), true) ?: 0; }
function role_label(string $r): string { return ['student' => 'Student', 'lecturer' => 'Lecturer', 'staff' => 'Support staff', 'admin' => 'Administrator'][$r] ?? $r; }

function badge(string $text, string $kind): string { return '<span class="badge badge-' . e($kind) . '">' . e($text) . '</span>'; }
function status_badge(string $s): string { return badge(status_label($s), $s); }
function priority_badge(string $p): string { return badge(ucfirst($p), 'p-' . $p); }

/* ---------- small charts (server-rendered, no JS needed) ---------- */
/** Horizontal bars. $rows = [[label, value], ...] */
function bar_list(array $rows, string $unit = ''): string
{
    if (!$rows) return '<p class="empty">Nothing to show yet.</p>';
    $max = max(1, max(array_column($rows, 1)));
    $h = '<ul class="bars">';
    foreach ($rows as [$label, $val]) {
        $w = round($val / $max * 100);
        $h .= '<li><span class="bars-label">' . e($label) . '</span><span class="bars-track"><span class="bars-fill" style="width:' . $w . '%"></span></span><span class="bars-val">' . e($val . $unit) . '</span></li>';
    }
    return $h . '</ul>';
}
/** Vertical mini columns for a daily series. $series = [['2026-10-01', 4], ...] */
function day_columns(array $series): string
{
    $max = max(1, max(array_column($series, 1)));
    $n = count($series);
    $w = 22; $gap = 8; $H = 120;
    $svg = '<svg class="cols" viewBox="0 0 ' . ($n * ($w + $gap)) . ' ' . ($H + 22) . '" role="img" aria-label="Requests submitted per day">';
    foreach ($series as $i => [$d, $v]) {
        $bh = $v ? max(4, round($v / $max * $H)) : 2;
        $x = $i * ($w + $gap);
        $svg .= '<rect x="' . $x . '" y="' . ($H - $bh) . '" width="' . $w . '" height="' . $bh . '" rx="3" class="' . ($v ? 'col' : 'col-zero') . '"><title>' . e(date('j M', strtotime($d)) . ': ' . $v) . '</title></rect>';
        if ($i % 2 === 0 || $n < 9) {
            $svg .= '<text x="' . ($x + $w / 2) . '" y="' . ($H + 16) . '" text-anchor="middle" class="col-label">' . e(date('j', strtotime($d))) . '</text>';
        }
    }
    return $svg . '</svg>';
}
/** Status donut. $counts = ['open' => 3, ...] */
function status_donut(array $counts): string
{
    $total = array_sum($counts);
    $r = 52; $c = 2 * M_PI * $r; $off = 0;
    $svg = '<svg class="donut" viewBox="0 0 140 140" role="img" aria-label="Requests by status"><circle cx="70" cy="70" r="' . $r . '" class="donut-bg"/>';
    foreach (TICKET_STATUSES as $key => $label) {
        $v = (int) ($counts[$key] ?? 0);
        if (!$v || !$total) continue;
        $len = $v / $total * $c;
        $svg .= '<circle cx="70" cy="70" r="' . $r . '" class="donut-seg seg-' . $key . '" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '"><title>' . e($label . ': ' . $v) . '</title></circle>';
        $off += $len;
    }
    $svg .= '<text x="70" y="68" text-anchor="middle" class="donut-n">' . $total . '</text><text x="70" y="86" text-anchor="middle" class="donut-l">requests</text></svg>';
    $svg .= '<ul class="legend">';
    foreach (TICKET_STATUSES as $key => $label) {
        $svg .= '<li><i class="dot seg-' . $key . '"></i>' . e($label) . '<b>' . (int) ($counts[$key] ?? 0) . '</b></li>';
    }
    return $svg . '</ul>';
}

/* ---------- icons (20px, stroke) ---------- */
function icon(string $name): string
{
    static $p = [
        'home'  => '<path d="M3 10.5 10 4l7 6.5V16a1 1 0 0 1-1 1h-3.5v-4.5h-5V17H4a1 1 0 0 1-1-1z"/>',
        'list'  => '<path d="M7 5h10M7 10h10M7 15h10"/><circle cx="3.5" cy="5" r=".8"/><circle cx="3.5" cy="10" r=".8"/><circle cx="3.5" cy="15" r=".8"/>',
        'plus'  => '<path d="M10 4v12M4 10h12"/>',
        'cal'   => '<rect x="3" y="4.5" width="14" height="12" rx="2"/><path d="M3 8.5h14M7 3v3M13 3v3"/>',
        'bell'  => '<path d="M5 14V9a5 5 0 0 1 10 0v5l1.5 1.5h-13zM8.5 17.5h3"/>',
        'users' => '<circle cx="7.5" cy="7" r="2.8"/><path d="M2.5 16c.4-3 2.4-4.5 5-4.5s4.6 1.5 5 4.5"/><circle cx="14" cy="8" r="2.2"/><path d="M14.5 11.8c2 .2 3.3 1.5 3.6 4"/>',
        'chip'  => '<rect x="5" y="5" width="10" height="10" rx="2"/><path d="M8 2.5v2.5M12 2.5v2.5M8 15v2.5M12 15v2.5M2.5 8H5M2.5 12H5M15 8h2.5M15 12h2.5"/>',
        'out'   => '<path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M12 7l3 3-3 3M15 10H8"/>',
        'search'=> '<circle cx="9" cy="9" r="5"/><path d="m13 13 4 4"/>',
        'check' => '<path d="m4 10.5 4 4 8-9"/>',
        'menu'  => '<path d="M3 5.5h14M3 10h14M3 14.5h14"/>',
    ];
    return '<svg class="icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}
function logo(): string
{
    return '<svg class="logo-mark" viewBox="0 0 32 32" aria-hidden="true"><path d="M4 8a2 2 0 0 1 2-2h20a2 2 0 0 1 2 2v4.2a3.8 3.8 0 0 0 0 7.6V24a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-4.2a3.8 3.8 0 0 0 0-7.6z" fill="currentColor"/><path d="M12 16.5l3 3 5.5-6" fill="none" stroke="#F5B83D" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}
