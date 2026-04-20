<?php

declare(strict_types=1);

const RATE_LIMIT_MAX_ATTEMPTS_PER_KEY = 20;
const REMEMBER_LOGIN_LIFETIME = 2592000;
const REMEMBER_LOGIN_ROTATION_GRACE = 300;

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

function remember_login_cookie_name(): string
{
    $path = session_cookie_path();
    return 'WWWREMEMBER_' . substr(hash('sha256', $path), 0, 12);
}

function remember_login_path(): string
{
    return USERS_DIR . '/.remember_tokens.json';
}

function remember_login_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_login_set_cookie(string $value, int $expiresAt): void
{
    $cookieName = remember_login_cookie_name();
    $cookiePath = session_cookie_path();
    setcookie($cookieName, $value, remember_login_cookie_options($expiresAt) + ['path' => $cookiePath]);
    $_COOKIE[$cookieName] = $value;
}

function remember_login_clear_cookie(): void
{
    $cookieName = remember_login_cookie_name();
    $cookiePath = session_cookie_path();
    $cookieOptions = remember_login_cookie_options(time() - 3600);
    setcookie($cookieName, '', $cookieOptions + ['path' => $cookiePath]);
    if ($cookiePath !== '/') {
        setcookie($cookieName, '', $cookieOptions + ['path' => '/']);
    }
    unset($_COOKIE[$cookieName]);
}

function remember_login_parse_cookie(string $value): ?array
{
    $value = trim($value);
    if ($value === '' || !str_contains($value, ':')) {
        return null;
    }

    [$selector, $validator] = explode(':', $value, 2);
    $selector = strtolower(trim($selector));
    $validator = strtolower(trim($validator));
    if (!preg_match('/^[a-f0-9]{16,64}$/', $selector)) {
        return null;
    }
    if (!preg_match('/^[a-f0-9]{32,256}$/', $validator)) {
        return null;
    }

    return [
        'selector' => $selector,
        'validator' => $validator,
    ];
}

function auth_user_fingerprint(array $user): string
{
    $username = user_normalize_username((string) ($user['username'] ?? ''));
    $passwordHash = (string) ($user['password_hash'] ?? '');
    $createdAt = $user['created_at'] ?? 0;
    if (!is_int($createdAt)) {
        $createdAt = is_numeric($createdAt) ? (int) $createdAt : 0;
    }
    if ($createdAt < 0) {
        $createdAt = 0;
    }
    if ($username === '' || $passwordHash === '') {
        return '';
    }
    return hash('sha256', $username . "\n" . $createdAt . "\n" . $passwordHash);
}

function session_clear_auth_state(): void
{
    unset($_SESSION['logged_in'], $_SESSION['username'], $_SESSION['role'], $_SESSION['user_fingerprint']);
}

function session_store_authenticated_identity(string $username, string $role, string $fingerprint = ''): void
{
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    if ($fingerprint === '') {
        unset($_SESSION['user_fingerprint']);
        return;
    }
    $_SESSION['user_fingerprint'] = $fingerprint;
}

function session_store_authenticated_user(array $user): void
{
    session_store_authenticated_identity(
        (string) ($user['username'] ?? ''),
        (string) ($user['role'] ?? ''),
        auth_user_fingerprint($user),
    );
}

function remember_login_prune_tokens(array $tokens): array
{
    $pruned = [];
    $now = time();

    foreach ($tokens as $selector => $record) {
        $selector = strtolower(trim((string) $selector));
        if (!preg_match('/^[a-f0-9]{16,64}$/', $selector) || !is_array($record)) {
            continue;
        }

        $username = user_normalize_username((string) ($record['username'] ?? ''));
        $userFingerprint = strtolower(trim((string) ($record['user_fingerprint'] ?? '')));
        $validatorHash = strtolower(trim((string) ($record['validator_hash'] ?? '')));
        $expiresAt = $record['expires_at'] ?? 0;
        if (!is_int($expiresAt)) {
            $expiresAt = is_numeric($expiresAt) ? (int) $expiresAt : 0;
        }

        if (
            $username === ''
            || !preg_match('/^[a-f0-9]{64}$/', $userFingerprint)
            || !preg_match('/^[a-f0-9]{64}$/', $validatorHash)
            || $expiresAt < $now
        ) {
            continue;
        }

        $pruned[$selector] = [
            'username' => $username,
            'user_fingerprint' => $userFingerprint,
            'validator_hash' => $validatorHash,
            'expires_at' => $expiresAt,
        ];
    }

    if (count($pruned) > 500) {
        uasort($pruned, static fn(array $left, array $right): int => $left['expires_at'] <=> $right['expires_at']);
        $pruned = array_slice($pruned, -500, null, true);
    }

    return $pruned;
}

function remember_login_prune_rotated_selectors(array $selectors): array
{
    $pruned = [];
    $now = time();

    foreach ($selectors as $selector => $expiresAt) {
        $selector = strtolower(trim((string) $selector));
        if (!preg_match('/^[a-f0-9]{16,64}$/', $selector)) {
            continue;
        }

        if (!is_int($expiresAt)) {
            $expiresAt = is_numeric($expiresAt) ? (int) $expiresAt : 0;
        }

        if ($expiresAt < $now) {
            continue;
        }

        $pruned[$selector] = $expiresAt;
    }

    if (count($pruned) > 500) {
        asort($pruned);
        $pruned = array_slice($pruned, -500, null, true);
    }

    return $pruned;
}

function remember_login_load_store_unlocked(): array
{
    $path = remember_login_path();
    if (!is_file($path)) {
        return ['tokens' => [], 'rotated' => []];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return ['tokens' => [], 'rotated' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        wiki_log('auth.remember_login_decode_failed', ['path' => $path], 'warning');
        return ['tokens' => [], 'rotated' => []];
    }

    $tokens = $decoded;
    $rotated = [];
    if (array_key_exists('tokens', $decoded) || array_key_exists('rotated', $decoded)) {
        $tokens = is_array($decoded['tokens'] ?? null) ? $decoded['tokens'] : [];
        $rotated = is_array($decoded['rotated'] ?? null) ? $decoded['rotated'] : [];
    }

    return [
        'tokens' => remember_login_prune_tokens($tokens),
        'rotated' => remember_login_prune_rotated_selectors($rotated),
    ];
}

function remember_login_load_unlocked(): array
{
    return remember_login_load_store_unlocked()['tokens'];
}

function remember_login_save_unlocked(array $tokens, array $rotated = []): bool
{
    $json = json_encode([
        'tokens' => remember_login_prune_tokens($tokens),
        'rotated' => remember_login_prune_rotated_selectors($rotated),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        wiki_log('auth.remember_login_encode_failed', ['path' => remember_login_path()], 'error');
        return false;
    }

    if (!file_put_atomic(remember_login_path(), $json)) {
        wiki_log('auth.remember_login_save_failed', ['path' => remember_login_path()], 'error');
        return false;
    }

    return true;
}

function remember_login_issue(string $username): bool
{
    $username = user_normalize_username($username);
    $user = $username !== '' ? user_get($username) : null;
    $userFingerprint = is_array($user) ? auth_user_fingerprint($user) : '';
    if ($username === '' || !is_array($user) || $userFingerprint === '') {
        return false;
    }

    $issued = wiki_with_lock(static function () use ($username, $userFingerprint): ?array {
        $store = remember_login_load_store_unlocked();
        $tokens = $store['tokens'];
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = time() + REMEMBER_LOGIN_LIFETIME;
        $tokens[$selector] = [
            'username' => $username,
            'user_fingerprint' => $userFingerprint,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt,
        ];

        if (!remember_login_save_unlocked($tokens, $store['rotated'])) {
            return null;
        }

        return [
            'cookie' => $selector . ':' . $validator,
            'expires_at' => $expiresAt,
        ];
    }, false, null);

    if (!is_array($issued)) {
        return false;
    }

    remember_login_set_cookie((string) ($issued['cookie'] ?? ''), (int) ($issued['expires_at'] ?? 0));
    return true;
}

function remember_login_revoke_current(): bool
{
    $current = remember_login_parse_cookie((string) ($_COOKIE[remember_login_cookie_name()] ?? ''));
    if (!is_array($current)) {
        remember_login_clear_cookie();
        return true;
    }

    $revoked = wiki_with_lock(static function () use ($current): bool {
        $store = remember_login_load_store_unlocked();
        $tokens = $store['tokens'];
        $rotated = $store['rotated'];
        if (!isset($tokens[$current['selector']])) {
            return true;
        }
        unset($tokens[$current['selector']]);
        unset($rotated[$current['selector']]);
        return remember_login_save_unlocked($tokens, $rotated);
    }, false, false);

    if ($revoked !== true) {
        return false;
    }

    remember_login_clear_cookie();
    return true;
}

function remember_login_revoke_all_for_user(string $username): bool
{
    $username = user_normalize_username($username);
    if ($username === '') {
        return true;
    }

    $revoked = wiki_with_lock(static function () use ($username): bool {
        $store = remember_login_load_store_unlocked();
        $tokens = $store['tokens'];
        $changed = false;
        foreach ($tokens as $selector => $record) {
            if (user_normalize_username((string) ($record['username'] ?? '')) !== $username) {
                continue;
            }
            unset($tokens[$selector]);
            $changed = true;
        }

        return !$changed || remember_login_save_unlocked($tokens, $store['rotated']);
    }, false, false);

    return $revoked === true;
}

function remember_login_restore(): void
{
    if (is_logged_in()) {
        return;
    }

    $current = remember_login_parse_cookie((string) ($_COOKIE[remember_login_cookie_name()] ?? ''));
    if (!is_array($current)) {
        return;
    }

    $restored = wiki_with_lock(static function () use ($current): array {
        $store = remember_login_load_store_unlocked();
        $tokens = $store['tokens'];
        $rotated = $store['rotated'];
        $record = $tokens[$current['selector']] ?? null;
        if (!is_array($record)) {
            if (isset($rotated[$current['selector']])) {
                return ['status' => 'rotated'];
            }
            return ['status' => 'missing'];
        }

        $validatorHash = strtolower(trim((string) ($record['validator_hash'] ?? '')));
        if ($validatorHash === '' || !hash_equals($validatorHash, hash('sha256', $current['validator']))) {
            unset($tokens[$current['selector']]);
            unset($rotated[$current['selector']]);
            if (!remember_login_save_unlocked($tokens, $rotated)) {
                return ['status' => 'failed'];
            }
            return ['status' => 'invalid'];
        }

        $username = user_normalize_username((string) ($record['username'] ?? ''));
        $user = $username !== '' ? user_get($username) : null;
        $userFingerprint = is_array($user) ? auth_user_fingerprint($user) : '';
        $tokenFingerprint = strtolower(trim((string) ($record['user_fingerprint'] ?? '')));
        if (!is_array($user) || $userFingerprint === '' || !hash_equals($userFingerprint, $tokenFingerprint)) {
            unset($tokens[$current['selector']]);
            unset($rotated[$current['selector']]);
            if (!remember_login_save_unlocked($tokens, $rotated)) {
                return ['status' => 'failed'];
            }
            return ['status' => 'invalid'];
        }

        unset($tokens[$current['selector']]);
        $rotationExpiresAt = (int) ($record['expires_at'] ?? 0);
        $rotated[$current['selector']] = min(
            $rotationExpiresAt > 0 ? $rotationExpiresAt : time() + REMEMBER_LOGIN_ROTATION_GRACE,
            time() + REMEMBER_LOGIN_ROTATION_GRACE,
        );
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = time() + REMEMBER_LOGIN_LIFETIME;
        $tokens[$selector] = [
            'username' => $user['username'],
            'user_fingerprint' => $userFingerprint,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt,
        ];
        if (!remember_login_save_unlocked($tokens, $rotated)) {
            return ['status' => 'failed'];
        }

        return [
            'status' => 'restored',
            'username' => $user['username'],
            'role' => (string) ($user['role'] ?? ''),
            'user_fingerprint' => $userFingerprint,
            'cookie' => $selector . ':' . $validator,
            'expires_at' => $expiresAt,
        ];
    }, false, ['status' => 'failed']);

    if (!is_array($restored)) {
        return;
    }
    $status = (string) ($restored['status'] ?? '');
    if ($status === 'failed' || $status === 'rotated') {
        return;
    }
    if ($status !== 'restored') {
        remember_login_clear_cookie();
        return;
    }

    session_regenerate_id(true);
    session_store_authenticated_identity(
        (string) ($restored['username'] ?? ''),
        (string) ($restored['role'] ?? ''),
        (string) ($restored['user_fingerprint'] ?? ''),
    );
    remember_login_set_cookie((string) ($restored['cookie'] ?? ''), (int) ($restored['expires_at'] ?? 0));
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
    $user = $username !== '' ? user_get($username) : null;
    if ($username === '' || !is_array($user)) {
        session_clear_auth_state();
        return false;
    }

    $fingerprint = auth_user_fingerprint($user);
    if ($fingerprint === '') {
        session_clear_auth_state();
        return false;
    }

    $storedFingerprint = strtolower(trim((string) ($_SESSION['user_fingerprint'] ?? '')));
    if ($storedFingerprint === '') {
        session_clear_auth_state();
        return false;
    }
    if (!hash_equals($fingerprint, $storedFingerprint)) {
        session_clear_auth_state();
        return false;
    }

    session_store_authenticated_user($user);

    return true;
}

function current_user(): ?string
{
    if (!is_logged_in()) {
        return null;
    }
    return $_SESSION['username'] ?? null;
}

function current_role(): string
{
    if (!is_logged_in()) {
        return '';
    }
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

function register_rate_limit_key(string $username): string
{
    return 'register:' . client_ip() . ':' . user_normalize_username($username);
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

function rate_limit_save(array $limits): bool
{
    $json = json_encode($limits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        wiki_log('auth.rate_limit_encode_failed', ['path' => rate_limit_path()], 'error');
        return false;
    }

    if (!file_put_atomic(rate_limit_path(), $json)) {
        wiki_log('auth.rate_limit_save_failed', ['path' => rate_limit_path()], 'error');
        return false;
    }

    return true;
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
        return !rate_limit_save($limits);
    }, false, true);
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
    session_store_authenticated_user($user);
    flash('success', t('flash.auth.login_success'));
    return true;
}

function logout(bool $withFlash = true, bool $revokeRemember = true): bool
{
    $cookieName = session_name();
    if ($revokeRemember) {
        if (!remember_login_revoke_current()) {
            return false;
        }
    } else {
        remember_login_clear_cookie();
    }
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
    return true;
}
