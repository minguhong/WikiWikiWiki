<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('WIKI_DIR', dirname(__DIR__));        
define('BASE_DIR', dirname(WIKI_DIR));        
define('WIKI_PATH', basename(WIKI_DIR));      
define('THEMES_DIR', BASE_DIR . '/themes');
define('THEMES_PATH', basename(THEMES_DIR));

define('CONTENT_DIR', BASE_DIR . '/content');
define('HISTORY_DIR', BASE_DIR . '/history');
define('USERS_DIR', BASE_DIR . '/users');
define('CACHE_DIR', BASE_DIR . '/cache');
define('CACHE_FILE_MAX_BYTES', 5 * 1024 * 1024);
define('TEMPLATE_DIR', WIKI_DIR . '/templates');
define('WIKI_ROUTE_PATH', 'wiki');
define('PAGE_LIST_LIMIT', 20);
define('ALL_PAGES_LIMIT', 100);
define('FEED_LIMIT', 10);
define('PHP_REQUIRED_VERSION', '8.2.0');

require_once BASE_DIR . '/config/config.php';

function theme_name_is_valid(string $name): bool
{
    return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) === 1;
}

defined('WIKI_TITLE') or define('WIKI_TITLE', 'WikiWikiWiki');
defined('WIKI_DESCRIPTION') or define('WIKI_DESCRIPTION', 'A wiki made with WikiWikiWiki');
defined('HOME_PAGE') or define('HOME_PAGE', 'Welcome');
defined('TIMEZONE') or define('TIMEZONE', '');
defined('EDIT_PERMISSION') or define('EDIT_PERMISSION', 'private');
defined('THEME') or define('THEME', '');
defined('BASE_URL') or define('BASE_URL', '');

$themeName = trim((string) THEME);
if (!theme_name_is_valid($themeName)) {
    $themeName = '';
}
$themeEnabled = $themeName !== '' && is_dir(THEMES_DIR . '/' . $themeName);
if (!$themeEnabled) {
    $themeName = '';
}
define('THEME_NAME', $themeName);
define('THEME_ENABLED', $themeEnabled);
define('THEME_DIR', $themeEnabled ? THEMES_DIR . '/' . $themeName : THEMES_DIR . '/.inactive');
define('THEME_PATH', $themeName === '' ? '' : THEMES_PATH . '/' . $themeName);

function i18n_directories(): array
{
    $directories = [WIKI_DIR];
    if (THEME_ENABLED) {
        $directories[] = THEME_DIR;
    }
    return $directories;
}

function i18n_language_codes(array $directories): array
{
    $codes = [];
    foreach ($directories as $directory) {
        foreach (glob($directory . '/i18n/*.json') ?: [] as $langFile) {
            $name = pathinfo($langFile, PATHINFO_FILENAME);
            if (preg_match('/^[a-z]{2,12}$/', $name) === 1) {
                $codes[] = $name;
            }
        }
    }
    $codes = array_values(array_unique($codes));
    sort($codes, SORT_STRING);
    return $codes;
}

function default_language_code(): string
{
    return 'en';
}

function resolve_language(array $directories): string
{
    $allowedLanguages = i18n_language_codes($directories);
    if ($allowedLanguages === []) {
        return default_language_code();
    }

    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $isInstallRequest = is_string($requestPath) && preg_match('#/install/?$#', $requestPath) === 1;
    if ($isInstallRequest) {
        $requestedLanguage = trim((string) ($_POST['language'] ?? ($_GET['language'] ?? '')));
        if (in_array($requestedLanguage, $allowedLanguages, true)) {
            return $requestedLanguage;
        }
    }

    if (defined('LANGUAGE') && in_array(LANGUAGE, $allowedLanguages, true)) {
        return LANGUAGE;
    }

    return $allowedLanguages[0];
}

function load_translation_file(string $path): array
{
    $raw = @file_get_contents($path);
    if (!is_string($raw)) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function load_translations(string $language, array $directories): array
{
    $merged = [];
    foreach ($directories as $directory) {
        $path = $directory . '/i18n/' . $language . '.json';
        if (!is_file($path)) {
            continue;
        }
        $merged = array_replace($merged, load_translation_file($path));
    }
    return $merged;
}

$translationDirectories = i18n_directories();
$language = resolve_language($translationDirectories);
if (!defined('LANGUAGE')) {
    define('LANGUAGE', $language);
}
define('TRANSLATIONS', load_translations($language, $translationDirectories));

require_once WIKI_DIR . '/src/load.php';
load_wiki_core(WIKI_DIR);

$configuredTimezone = defined('TIMEZONE') ? trim((string) TIMEZONE) : '';
$serverTimezone = (string) date_default_timezone_get();
$timezoneToApply = $configuredTimezone !== '' ? $configuredTimezone : $serverTimezone;
if ($timezoneToApply === '' || !@date_default_timezone_set($timezoneToApply)) {
    @date_default_timezone_set('UTC');
    wiki_log('bootstrap.invalid_timezone', [
        'configured' => $configuredTimezone,
        'server' => $serverTimezone,
        'fallback' => 'UTC',
    ], 'warning');
}

apply_security_headers();
ensure_directories();
fail_fast_on_storage_unavailable();
clear_config_theme_if_missing();
create_default_page();
session_start_once();
migrate_to_multi_user();
remember_login_restore();
