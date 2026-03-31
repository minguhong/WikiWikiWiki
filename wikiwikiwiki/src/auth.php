<?php

declare(strict_types=1);

const RATE_LIMIT_MAX_ATTEMPTS_PER_KEY = 20;

function session_cookie_path(): string
{
    $basePath = trim(base_path());
    if ($basePath === '' || $basePath === '.' || $basePath === './') {
        return '/';
    }
    if (!str_starts_with($basePath, '/')) {
        return '/' . ltrim($basePath, '/');
    }
    return $basePath;
}

function session_cookie_name(): string
{
    $path = session_cookie_path();
    return 'WWWSESSID_' . substr(hash('sha256', $path), 0, 12);
}

function session_files_configured_path(): string
{
    $path = trim((string) ini_get('session.save_path'));
    if ($path === '') {
        return '';
    }
    if (str_contains($path, ';')) {
        $parts = explode(';', $path);
        $path = (string) end($parts);
    }
    return trim($path);
}

function session_prepare_save_path(): void
{
    if (session_module_name() !== 'files') {
        return;
    }

    $configuredPath = session_files_configured_path();
    if ($configuredPath !== '' && is_dir($configuredPath) && is_writable($configuredPath)) {
        return;
    }

    $fallbackPath = CACHE_DIR . '/sessions';
    if (!is_dir($fallbackPath) && !@mkdir($fallbackPath, 0775, true) && !is_dir($fallbackPath)) {
        wiki_log('auth.session_save_path_create_failed', [
            'configured_path' => $configuredPath,
            'fallback_path' => $fallbackPath,
        ], 'error');
        return;
    }
    if (!is_writable($fallbackPath)) {
        wiki_log('auth.session_save_path_not_writable', [
            'configured_path' => $configuredPath,
            'fallback_path' => $fallbackPath,
        ], 'error');
        return;
    }
    if (@ini_set('session.save_path', $fallbackPath) === false) {
        wiki_log('auth.session_save_path_set_failed', [
            'configured_path' => $configuredPath,
            'fallback_path' => $fallbackPath,
        ], 'error');
        return;
    }
}

function session_start_once(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_prepare_save_path();
        $cookieName = session_cookie_name();
        if (session_name() !== $cookieName) {
            session_name($cookieName);
        }
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => request_is_https(),
            'cookie_samesite' => 'Lax',
            'cookie_path' => session_cookie_path(),
        ]);
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function verify_csrf(string $token): bool
{
    $sessionToken = csrf_token();
    return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function validate_post_request(): void
{
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        render_error_page(403, '403', t('flash.auth.invalid_csrf'));
    }
    if (!honeypot_passed()) {
        render_error_page(403, '403', t('flash.auth.invalid_csrf'));
    }
}

function is_public(): bool
{
    return EDIT_PERMISSION === 'public';
}

function is_fully_public(): bool
{
    return EDIT_PERMISSION === 'fully_public';
}

function is_private(): bool
{
    return EDIT_PERMISSION === 'private';
}

function can_register(): bool
{
    return !is_private();
}

function can_edit_pages(): bool
{
    return is_fully_public() || is_logged_in();
}

function require_edit_access(): void
{
    if (!can_edit_pages()) {
        require_login();
    }
}

function is_logged_in(): bool
{
    if (!($_SESSION['logged_in'] ?? false)) {
        return false;
    }

    $username = (string) ($_SESSION['username'] ?? '');
    if ($username === '' || user_get($username) === null) {
        unset($_SESSION['logged_in'], $_SESSION['username'], $_SESSION['role']);
        return false;
    }

    return true;
}

function current_user(): ?string
{
    return $_SESSION['username'] ?? null;
}

function current_role(): string
{
    return (string) ($_SESSION['role'] ?? '');
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', t('flash.auth.login_required'));
        $page = normalize_return_path((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $loginUrl = url('/login') . ($page ? '?page=' . rawurlencode($page) : '');
        redirect($loginUrl);
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', t('flash.auth.admin_required'));
        redirect(url('/'));
    }
}

function honeypot_passed(): bool
{
    if (is_private()) {
        return true;
    }
    return empty($_POST['website'] ?? '');
}

function client_ip(): string
{
    $ip = trim($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'unknown';
    }
    return $ip;
}

function rate_limit_path(): string
{
    return USERS_DIR . '/.rate_limits.json';
}

function rate_limit_normalize_key(string $key): string
{
    $normalized = (string) preg_replace('/[^a-z0-9:_-]/i', '', strtolower(trim($key)));
    return $normalized !== '' ? $normalized : 'unknown';
}

function rate_limit_load(): array
{
    $path = rate_limit_path();
    if (!file_exists($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        rate_limit_save([]);
        return [];
    }
    return $decoded;
}

function rate_limit_save(array $limits): void
{
    $json = json_encode($limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (is_string($json)) {
        if (!file_put_atomic(rate_limit_path(), $json)) {
            wiki_log('auth.rate_limit_save_failed', ['path' => rate_limit_path()], 'error');
        }
    }
}

function rate_limit_prune(array $limits, int $window): array
{
    $minTs = time() - max($window, 60);
    $latestByKey = [];
    foreach ($limits as $key => $attempts) {
        $normalizedKey = rate_limit_normalize_key((string) $key);
        if (!is_array($attempts)) {
            unset($limits[$key]);
            continue;
        }
        $filtered = array_values(array_filter($attempts, fn($ts) => is_int($ts) && $ts >= $minTs));
        if ($filtered === []) {
            unset($limits[$key]);
            continue;
        }
        $filtered = array_slice($filtered, -RATE_LIMIT_MAX_ATTEMPTS_PER_KEY);
        unset($limits[$key]);
        $limits[$normalizedKey] = $filtered;
        $latestByKey[$normalizedKey] = end($filtered);
    }

    $maxKeys = 500;
    if (count($limits) > $maxKeys) {
        arsort($latestByKey);
        $allowed = array_slice(array_keys($latestByKey), 0, $maxKeys);
        $allowedSet = array_fill_keys($allowed, true);
        foreach (array_keys($limits) as $key) {
            if (!isset($allowedSet[$key])) {
                unset($limits[$key]);
            }
        }
    }
    return $limits;
}

function rate_limit_check_and_record(string $key, int $limit, int $window): bool
{
    return wiki_with_lock(function () use ($key, $limit, $window): bool {
        $key = rate_limit_normalize_key($key);
        $limits = rate_limit_prune(rate_limit_load(), $window);
        $attempts = $limits[$key] ?? [];
        if (count($attempts) >= $limit) {
            rate_limit_save($limits);
            return true;
        }
        $attempts[] = time();
        $limits[$key] = array_slice($attempts, -RATE_LIMIT_MAX_ATTEMPTS_PER_KEY);
        rate_limit_save($limits);
        return false;
    }, false, false);
}

function rate_limit_reset(string $key): void
{
    wiki_with_lock(function () use ($key): bool {
        $key = rate_limit_normalize_key($key);
        $limits = rate_limit_load();
        unset($limits[$key]);
        rate_limit_save($limits);
        return true;
    }, false, false);
}

function login(string $username, #[\SensitiveParameter] string $password): bool|string
{
    $window = 300;
    $limit = 5;
    $username = user_normalize_username($username);
    $rateKey = 'login:' . client_ip() . ':' . $username;

    if (rate_limit_check_and_record($rateKey, $limit, $window)) {
        return 'rate_limited';
    }

    
    $dummyHash = '$2y$10$vS1QyEljN4f4Oyl6N8Qm0u0C6x5hHBpjz2uVH54camzatoorktr1K'; 
    $user = user_get($username);
    $passwordHash = is_array($user) ? (string) ($user['password_hash'] ?? '') : '';
    $hashToVerify = $passwordHash !== '' ? $passwordHash : $dummyHash;
    $passwordOk = password_verify($password, $hashToVerify);
    if (!is_array($user) || $passwordHash === '' || !$passwordOk) {
        return false;
    }

    session_regenerate_id(true);
    rate_limit_reset($rateKey);
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    flash('success', t('flash.auth.login_success'));
    return true;
}

function logout(bool $withFlash = true): void
{
    $cookieName = session_name();
    session_destroy();
    $cookiePath = session_cookie_path();
    $cookieOptions = [
        'expires' => time() - 3600,
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie($cookieName, '', $cookieOptions + ['path' => $cookiePath]);
    if ($cookiePath !== '/') {
        
        setcookie($cookieName, '', $cookieOptions + ['path' => '/']);
    }
    
    setcookie('PHPSESSID', '', $cookieOptions + ['path' => '/']);
    session_start_once();
    if ($withFlash) {
        flash('success', t('flash.auth.logout_success'));
    }
}
