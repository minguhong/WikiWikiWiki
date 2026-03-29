<?php

declare(strict_types=1);

const USERNAME_MIN_LENGTH = 2;
const USERNAME_MAX_LENGTH = 20;
const PASSWORD_MIN_LENGTH = 8;
const PAGE_TITLE_MAX_LENGTH = 80;
const HISTORY_KEEP_COUNT = 50;
const SEARCH_QUERY_MAX_LENGTH = 200;

function username_is_valid(string $username): bool
{
    return preg_match(
        '/^[a-z0-9]{' . USERNAME_MIN_LENGTH . ',' . USERNAME_MAX_LENGTH . '}$/',
        $username,
    ) === 1;
}

function password_is_valid(string $password): bool
{
    $charLength = mb_strlen($password);
    $byteLength = strlen($password);
    return $charLength >= PASSWORD_MIN_LENGTH && $byteLength <= 72;
}

function wiki_version(): string
{
    static $version = null;
    if ($version === null) {
        $file = BASE_DIR . '/VERSION';
        if (!file_exists($file)) {
            $version = '';
        } else {
            $raw = file_get_contents($file);
            $version = is_string($raw) ? ' ' . trim($raw) : '';
        }
    }
    return $version;
}

function wiki_log(string $event, array $context = [], string $level = 'info'): void
{
    if (wiki_test_silent_logs_enabled()) {
        return;
    }

    $payload = [
        'ts' => date('c'),
        'level' => $level,
        'event' => $event,
        'request_id' => substr(hash('sha256', ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli')), 0, 12),
        'context' => $context,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($json)) {
        error_log($json);
    } else {
        error_log('WikiWikiWiki log serialization failed: ' . $event);
    }
}

function wiki_test_silent_logs_enabled(): bool
{
    $value = getenv('WIKI_TEST_SILENT_LOGS');
    return is_string($value) && $value === '1';
}


function file_to_page_title(string $filename): string
{
    $title = pathinfo($filename, PATHINFO_FILENAME);
    $title = str_replace('--', '/', $title);
    return normalize_filename($title);
}

function normalize_filename(string $filename): string
{
    if (class_exists('Normalizer')) {
        return Normalizer::normalize($filename, Normalizer::NFC);
    }
    return $filename;
}

function page_title_to_url(string $title): string
{
    $parts = explode('/', $title);
    $encoded = array_map(fn($p) => str_replace(' ', '_', $p), $parts);
    return implode('/', $encoded);
}

function url_to_page_title(string $url): string
{
    $parts = explode('/', $url);
    $decoded = array_map(fn($p) => str_replace('_', ' ', $p), $parts);
    $title = implode('/', $decoded);
    return sanitize_page_title($title);
}

function request_is_https(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1' || $https === 'true') {
        return true;
    }
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $remoteAddr = strtolower((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($forwardedProto === 'https' && ($remoteAddr === '127.0.0.1' || $remoteAddr === '::1')) {
        return true;
    }
    return isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443';
}

function detected_base_url(): string
{
    $isHttps = request_is_https();
    $protocol = $isHttps ? 'https' : 'http';
    $rawHost = trim($_SERVER['HTTP_HOST'] ?? '');
    $host = $rawHost !== '' ? $rawHost : 'localhost';
    $normalizedHostWithPort = normalize_host_with_port($host);
    if ($normalizedHostWithPort === '') {
        $fallbackHost = normalize_host_with_port((string) ($_SERVER['SERVER_NAME'] ?? ''));
        $host = $fallbackHost !== '' ? $fallbackHost : 'localhost';
    } else {
        $host = $normalizedHostWithPort;
    }

    $basePath = base_path();

    return $protocol . '://' . $host . $basePath;
}

function base_url(): string
{
    if (defined('BASE_URL') && BASE_URL !== '') {
        return rtrim(BASE_URL, '/');
    }

    return detected_base_url();
}

function base_path(bool $reset = false): string
{
    static $cache = null;
    if ($reset) {
        $cache = null;
        return '';
    }
    if ($cache !== null) {
        return $cache;
    }
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = str_replace('\\', '/', dirname($scriptName));
    $cache = $basePath === '/' ? '' : $basePath;
    return $cache;
}

function page_url(string $title, string $suffix = ''): string
{
    $urlName = page_title_to_url($title);
    return base_url() . '/' . WIKI_ROUTE_PATH . '/' . $urlName . $suffix;
}

function normalize_internal_redirect_title(string $target): ?string
{
    $title = sanitize_page_title(trim($target));
    return $title !== '' ? $title : null;
}

function markdown_line_is_list_item(string $line): bool
{
    return preg_match('/^\s*(?:>\s*)*(?:[-+*]|\d+\.)\s+/', $line) === 1;
}

function markdown_indent_width(string $line): int
{
    $width = 0;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
        $ch = $line[$i];
        if ($ch === ' ') {
            $width++;
            continue;
        }
        if ($ch === "\t") {
            $width += 4;
            continue;
        }
        break;
    }
    return $width;
}

function markdown_lines_before_offset(string $content, int $offset): array
{
    if ($offset <= 0) {
        return ['', null];
    }

    $prefix = substr($content, 0, $offset);
    $parts = preg_split('/\R/u', $prefix);
    if (!is_array($parts) || $parts === []) {
        return ['', null];
    }

    if (end($parts) === '') {
        array_pop($parts);
    }
    if ($parts === []) {
        return ['', null];
    }

    $previousLine = (string) array_pop($parts);
    if (trim($previousLine) !== '') {
        return [$previousLine, $previousLine];
    }

    for ($i = count($parts) - 1; $i >= 0; $i--) {
        $line = (string) $parts[$i];
        if (trim($line) !== '') {
            return [$previousLine, $line];
        }
    }

    return [$previousLine, null];
}

function markdown_should_preserve_indented_code_block(string $content, int $offset, string $firstLine): bool
{
    if ($offset <= 0) {
        return true;
    }

    [$previousLine, $previousNonEmptyLine] = markdown_lines_before_offset($content, $offset);
    if (trim($previousLine) !== '') {
        return false;
    }

    if ($previousNonEmptyLine !== null && markdown_line_is_list_item($previousNonEmptyLine)) {
        return markdown_indent_width($firstLine) >= 8;
    }

    return true;
}

function url(string $path = '/', ?string $suffix = null): string
{
    $basePath = base_path();

    if ($path === '/') {
        return $basePath ?: '/';
    }

    if (str_starts_with($path, '/')) {
        return $basePath . $path;
    }

    $urlName = page_title_to_url($path);
    $url = $basePath . '/' . WIKI_ROUTE_PATH . '/' . $urlName;

    if ($suffix !== null) {
        $url .= $suffix;
    }

    return $url;
}

function security_headers(): array
{
    $headers = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; frame-src https://www.youtube.com https://player.vimeo.com https://www.are.na https://codepen.io https://www.google.com https://maps.google.com https:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' https: data:; script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://www.statcounter.com https://www.googletagmanager.com https://plausible.io https://cloud.umami.is https://gc.zgo.at; connect-src 'self' https://c.statcounter.com https://cloudflareinsights.com https://www.google-analytics.com https://region1.google-analytics.com https://plausible.io https://cloud.umami.is https://gc.zgo.at",
    ];

    if (request_is_https()) {
        $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
    }

    return $headers;
}

function apply_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    foreach (security_headers() as $name => $value) {
        header($name . ': ' . $value);
    }
}

function html(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function css(string|array $files): string
{
    if (is_string($files)) {
        $files = [$files];
    }
    return implode("\n  ", array_map(
        fn($f) => '<link rel="stylesheet" href="' . asset($f) . '">',
        $files,
    ));
}

function js(string|array $files): string
{
    if (is_string($files)) {
        $files = [$files];
    }
    return implode("\n  ", array_map(
        fn($f) => '<script src="' . asset($f) . '"></script>',
        $files,
    ));
}

function asset(string $file): string
{
    static $cache = [];
    $file = ltrim($file, '/');
    if (!isset($cache[$file])) {
        $basePath = base_path();
        $themeAsset = THEME_DIR . '/assets/' . $file;
        if (THEME_ENABLED && is_file($themeAsset)) {
            $version = filemtime($themeAsset) ?: time();
            $cache[$file] = $basePath . '/' . THEME_PATH . '/assets/' . $file . '?v=' . $version;
        } else {
            $engineAsset = WIKI_DIR . '/assets/' . $file;
            $version = filemtime($engineAsset) ?: time();
            $cache[$file] = $basePath . '/' . WIKI_PATH . '/assets/' . $file . '?v=' . $version;
        }
    }
    return $cache[$file];
}

function template_file(string $template): string
{
    $template = trim($template);
    $themeTemplate = THEME_DIR . '/templates/' . $template . '.php';
    if (THEME_ENABLED && is_file($themeTemplate)) {
        return $themeTemplate;
    }
    return TEMPLATE_DIR . '/' . $template . '.php';
}

function honeypot_field(): string
{
    return '<input type="text" name="website" value="" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">';
}

function render(string $template, array $data = []): void
{
    header('Cache-Control: no-store');

    $view = $data;
    $view['description'] ??= '';
    $view['pageTitle'] ??= $view['page'] ?? t('title.' . $template, '');
    $view['recentPages'] ??= page_recent(5);

    extract($view, EXTR_SKIP);

    $description = (string) $view['description'];
    $pageTitle = (string) $view['pageTitle'];
    $page = (string) ($view['page'] ?? '');
    $wikiTitle = (string) ($view['wikiTitle'] ?? WIKI_TITLE);
    $wikiDescription = (string) ($view['wikiDescription'] ?? WIKI_DESCRIPTION);
    $language = LANGUAGE;
    $languageCode = t('language.locale', LANGUAGE);
    $basePath = base_path();
    $csrfToken = csrf_token();
    $flashes = pull_flashes();
    $ogUrl = ($page !== '') ? page_url($page) : base_url();
    $recentPages = is_array($view['recentPages']) ? $view['recentPages'] : page_recent(5);
    $metaTitle = base_meta_title($wikiTitle, $pageTitle);
    $metaDescription = base_meta_description($wikiDescription, $description);
    $ogImageUrl = base_og_image_url();
    $menuItems = base_menu_items((string) ($_SERVER['REQUEST_URI'] ?? ''));

    ob_start();
    include template_file($template);
    $section = (string) ob_get_clean();

    include template_file('base');
}

function render_view(string $title): void
{
    $content = page_get($title);
    if ($content === null) {
        if (can_edit_pages()) {
            redirect(url($title, '/edit'));
        }
        http_response_code(404);
        render('404', [
            'page' => $title,
            'requestedPage' => $title,
            'randomPages' => page_random(PAGE_LIST_LIMIT),
        ]);
        return;
    }

    $parsed = parse_content($content, ['source_title' => $title]);
    $parent = page_parent($title);
    $children = page_children($title);
    $siblings = page_siblings($title);

    $common = [
        'page' => $title,
        'modifiedAt' => page_last_modified_at($title),
        'sourceFile' => $title . '.txt',
        'parent' => $parent,
        'children' => $children,
        'siblings' => $siblings,
    ];

    if ($parsed['type'] === 'redirect') {
        $redirectTargetRaw = (string) $parsed['target'];
        if (preg_match('/^https?:\/\//i', $redirectTargetRaw)) {
            $redirectTarget = '';
        } else {
            $redirectTarget = normalize_internal_redirect_title($redirectTargetRaw) ?? '';
        }
        render('view', $common + [
            'redirectTarget' => $redirectTarget,
            'content' => $parsed['content'] ?? '',
        ]);
    } else {
        render('view', $common + [
            'content' => $parsed['content'],
            'description' => extract_description($content),
        ]);
    }
}
