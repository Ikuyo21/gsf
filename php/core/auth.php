<?php
session_start();

function base_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos = strrpos($script, '/php/');
    if ($pos !== false) {
        return substr($script, 0, $pos);
    }
    return '';
}

function e(?string $val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin';
}

function is_student(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Student';
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . base_url() . '/php/auth/login.php');
        exit;
    }
}

function require_student(): void {
    require_login();
    if (!is_student()) {
        header('Location: ' . base_url() . '/php/admin/');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

function require_guest(): void {
    if (is_logged_in()) {
        if (is_admin()) {
            header('Location: ' . base_url() . '/php/admin/');
        } else {
            header('Location: ' . base_url() . '/php/dashboard/');
        }
        exit;
    }
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): string {
    return $_SESSION['user_name'] ?? '';
}

function current_user_role(): string {
    return $_SESSION['user_role'] ?? 'Student';
}

function current_user_avatar(): string {
    return $_SESSION['user_avatar'] ?? '';
}

function user_pref(string $key, string $default = ''): string {
    return $_SESSION['user_prefs'][$key] ?? $default;
}

function load_user_prefs(PDO $pdo, int $uid): void {
    $stmt = $pdo->prepare('SELECT primary_color, secondary_color, text_color, font_choice, avatar, banner, bg_type, bg_color FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['user_prefs'] = [
            'primary_color' => $row['primary_color'] ?? '#009688',
            'secondary_color' => $row['secondary_color'] ?? '#ffffff',
            'text_color' => $row['text_color'] ?? '#333333',
            'font_choice' => $row['font_choice'] ?? 'Satoshi',
            'bg_type' => $row['bg_type'] ?? 'default',
            'bg_color' => $row['bg_color'] ?? '#f0f2f5'
        ];
        $_SESSION['user_avatar'] = $row['avatar'] ?? '';
        $_SESSION['user_banner'] = $row['banner'] ?? '';
    }
}

function update_last_active(PDO $pdo, int $uid): void {
    $pdo->prepare('UPDATE users SET last_active = NOW() WHERE id = ?')->execute([$uid]);
}

function is_user_online(PDO $pdo, int $uid): bool {
    $stmt = $pdo->prepare('SELECT last_active FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || !$row['last_active']) return false;
    return (time() - strtotime($row['last_active'])) < 300;
}

function set_flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function avatar_url(?string $avatar): string {
    if ($avatar && file_exists(__DIR__ . '/../../uploads/avatars/' . $avatar)) {
        return base_url() . '/uploads/avatars/' . $avatar;
    }
    return base_url() . '/css/default_avatar.svg';
}

function banner_url(?string $banner): string {
    if ($banner && file_exists(__DIR__ . '/../../uploads/banners/' . $banner)) {
        return base_url() . '/uploads/banners/' . $banner;
    }
    return '';
}

function time_ago(string $datetime): string {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

function filter_bad_words(PDO $pdo, string $text): string {
    $stmt = $pdo->query('SELECT word FROM bad_words');
    $words = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($words as $w) {
        $pattern = '/\b' . preg_quote($w, '/') . '\b/iu';
        $text = preg_replace($pattern, str_repeat('*', mb_strlen($w)), $text);
    }
    return $text;
}

function get_programs(): array {
    return [
        'Diploma in Computer Science',
        'Diploma in Computer Science (Cybersecurity)',
        'Bachelor of Computer Science (Software Engineering)',
        'Bachelor of Computer Science (Computer Networking)',
        'Bachelor of Computer Science (Cybersecurity)',
        'Bachelor of Computer Science (Multimedia)',
        'Bachelor of Computer Science (Artificial Intelligence)'
    ];
}

function is_light_color(string $hex): bool {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return false;
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    return $luminance > 0.45;
}

function check_muted(PDO $pdo, int $uid): ?string {
    $stmt = $pdo->prepare('SELECT muted_until FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row && $row['muted_until'] && strtotime($row['muted_until']) > time()) {
        return $row['muted_until'];
    }
    return null;
}

function mute_user(PDO $pdo, int $uid, int $seconds = 60): void {
    $until = date('Y-m-d H:i:s', time() + $seconds);
    $pdo->prepare('UPDATE users SET muted_until = ? WHERE id = ?')->execute([$until, $uid]);
}

function contains_bad_words(PDO $pdo, string $text): bool {
    $stmt = $pdo->query('SELECT word FROM bad_words');
    $words = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($words as $w) {
        $pattern = '/\b' . preg_quote($w, '/') . '\b/iu';
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    return false;
}
