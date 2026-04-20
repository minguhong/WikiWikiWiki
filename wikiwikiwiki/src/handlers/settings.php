<?php

declare(strict_types=1);

function settings_url(?string $tab = null): string
{
    $base = url('/settings');
    if ($tab === null || $tab === '') {
        return $base;
    }
    return $base . '?tab=' . rawurlencode(settings_normalize_tab($tab));
}

function settings_normalize_tab(?string $tab): string
{
    $tab = (string) $tab;
    if (!in_array($tab, ['basic', 'edit', 'documents', 'users', 'system'], true)) {
        return 'basic';
    }
    return $tab;
}

function settings_render_tab_error(string $tab, string $messageKey, array $overrides = []): never
{
    flash('error', t($messageKey));
    settings_render_page(array_merge(['activeTab' => $tab], $overrides));
    exit;
}

function settings_system_message_html(string $message): string
{
    $escaped = html($message);
    return preg_replace_callback(
        '/&lt;code&gt;(.*?)&lt;\/code&gt;/s',
        static fn(array $m): string => '<code>' . (string) $m[1] . '</code>',
        $escaped,
    ) ?? $escaped;
}

function settings_session_fallback_path(): string
{
    return CACHE_DIR . '/sessions';
}

function settings_path_label(string $path): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedBase = rtrim(str_replace('\\', '/', BASE_DIR), '/');
    if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
        return substr($normalizedPath, strlen($normalizedBase) + 1);
    }
    return $path;
}

function settings_paths_equivalent(string $left, string $right): bool
{
    $leftRealPath = @realpath($left);
    $rightRealPath = @realpath($right);
    if (is_string($leftRealPath) && is_string($rightRealPath)) {
        return $leftRealPath === $rightRealPath;
    }
    return rtrim($left, '/') === rtrim($right, '/');
}

function settings_session_gc_max_lifetime(): int
{
    return max(60, (int) ini_get('session.gc_maxlifetime'));
}

function settings_session_cleanup_supported(?string $handler = null): bool
{
    $sessionHandler = $handler ?? session_module_name();
    return $sessionHandler === 'files';
}

function settings_session_cleanup_visible(?array $sessionHealth = null): bool
{
    $health = is_array($sessionHealth) ? $sessionHealth : settings_session_health();
    return !empty($health['show_files']);
}

function settings_count_session_files(string $directory): int
{
    if (!is_dir($directory)) {
        return 0;
    }
    $matches = glob($directory . '/sess_*');
    if (!is_array($matches)) {
        return 0;
    }

    $count = 0;
    foreach ($matches as $path) {
        if (is_file($path)) {
            $count++;
        }
    }
    return $count;
}

function settings_session_health(): array
{
    $fallbackPath = settings_session_fallback_path();
    $fallbackLabel = settings_path_label($fallbackPath);
    $filesMessage = sprintf(
        t('settings.system.session_files_count'),
        settings_count_session_files($fallbackPath),
        $fallbackLabel,
    );

    $handler = session_module_name();
    if (!settings_session_cleanup_supported($handler)) {
        $message = sprintf(t('settings.system.session_handler_non_files'), $handler);
        return [
            'path_status' => 'ok',
            'path_message' => $message,
            'show_files' => false,
            'files_status' => 'ok',
            'files_message' => $filesMessage,
        ];
    }

    $sessionPath = session_files_configured_path();
    $isFallbackPath = ($sessionPath !== '' && settings_paths_equivalent($sessionPath, $fallbackPath));

    $pathStatus = 'ok';
    if ($sessionPath === '') {
        $pathMessage = t('settings.system.session_path_default');
    } elseif (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        $pathStatus = 'error';
        $pathMessage = sprintf(t('settings.system.session_path_not_writable'), settings_path_label($sessionPath));
    } elseif ($isFallbackPath) {
        $pathMessage = sprintf(t('settings.system.session_path_fallback'), $fallbackLabel);
    } else {
        $pathMessage = sprintf(t('settings.system.session_path_configured'), settings_path_label($sessionPath));
    }

    $showFiles = ($pathStatus === 'ok' && $isFallbackPath);

    return [
        'path_status' => $pathStatus,
        'path_message' => $pathMessage,
        'show_files' => $showFiles,
        'files_status' => 'ok',
        'files_message' => $filesMessage,
    ];
}

function settings_config_write_health(): array
{
    $configPath = BASE_DIR . '/config/config.php';
    if (is_file($configPath)) {
        return [
            'path_label' => 'config/config.php',
            'writable' => is_writable($configPath),
        ];
    }

    $configDir = dirname($configPath);
    if (is_dir($configDir)) {
        return [
            'path_label' => 'config/',
            'writable' => is_writable($configDir),
        ];
    }

    return [
        'path_label' => 'config/',
        'writable' => is_dir(BASE_DIR) && is_writable(BASE_DIR),
    ];
}

function settings_system_checks(): array
{
    $requiredPhpVersion = defined('PHP_REQUIRED_VERSION') ? (string) PHP_REQUIRED_VERSION : '8.2.0';
    $phpVersion = PHP_VERSION;
    $phpVersionOk = version_compare($phpVersion, $requiredPhpVersion, '>=');

    $requiredExtensions = required_php_extensions();
    $missingExtensions = array_values(array_filter(
        $requiredExtensions,
        static fn(string $ext): bool => !extension_loaded($ext),
    ));
    $extensionsOk = $missingExtensions === [];

    $storageReport = storage_boot_report();
    $missingDirs = array_values(array_map('basename', (array) ($storageReport['missing_dirs'] ?? [])));
    $notWritableDirs = array_values(array_map('basename', (array) ($storageReport['not_writable_dirs'] ?? [])));
    $storageOk = ($missingDirs === [] && $notWritableDirs === []);
    $storageIssues = [];
    if ($missingDirs !== []) {
        $storageIssues[] = t('settings.system.storage_missing') . ': ' . implode(', ', $missingDirs);
    }
    if ($notWritableDirs !== []) {
        $storageIssues[] = t('settings.system.storage_not_writable') . ': ' . implode(', ', $notWritableDirs);
    }

    $lockOk = !empty($storageReport['lock_ok']);
    $configWriteHealth = settings_config_write_health();
    $configWritable = $configWriteHealth['writable'];
    $bcryptOk = defined('PASSWORD_BCRYPT');
    $sessionHealth = settings_session_health();

    $checks = [
        [
            'label' => t('settings.system.row_php_version'),
            'status' => $phpVersionOk ? 'ok' : 'error',
            'message' => $phpVersionOk
                ? sprintf(t('settings.system.php_version_ok'), $phpVersion)
                : sprintf(t('settings.system.php_version_error'), $phpVersion, $requiredPhpVersion),
        ],
        [
            'label' => t('settings.system.row_required_extensions'),
            'status' => $extensionsOk ? 'ok' : 'error',
            'message' => $extensionsOk
                ? t('settings.system.extensions_ok')
                : sprintf(t('settings.system.extensions_error'), implode(', ', $missingExtensions)),
        ],
        [
            'label' => t('settings.system.row_storage_directories'),
            'status' => $storageOk ? 'ok' : 'error',
            'message' => $storageOk
                ? t('settings.system.storage_ok')
                : implode(' / ', $storageIssues),
        ],
        [
            'label' => t('settings.system.row_session_path'),
            'status' => (string) ($sessionHealth['path_status'] ?? 'ok'),
            'message' => (string) ($sessionHealth['path_message'] ?? ''),
        ],
        [
            'label' => t('settings.system.row_session_files'),
            'status' => (string) ($sessionHealth['files_status'] ?? 'ok'),
            'message' => (string) ($sessionHealth['files_message'] ?? ''),
        ],
        [
            'label' => t('settings.system.row_global_lock'),
            'status' => $lockOk ? 'ok' : 'error',
            'message' => $lockOk
                ? t('settings.system.lock_ok')
                : t('settings.system.lock_error'),
        ],
        [
            'label' => t('settings.system.row_config_writable'),
            'status' => $configWritable ? 'ok' : 'error',
            'message' => $configWritable
                ? sprintf(t('settings.system.config_writable_ok'), $configWriteHealth['path_label'])
                : sprintf(t('settings.system.config_writable_error'), $configWriteHealth['path_label']),
        ],
        [
            'label' => t('settings.system.row_bcrypt'),
            'status' => $bcryptOk ? 'ok' : 'error',
            'message' => $bcryptOk
                ? t('settings.system.bcrypt_ok')
                : t('settings.system.bcrypt_error'),
        ],
    ];

    return $checks;
}

function settings_config_field_value(string $field, array $configValues): string
{
    if (array_key_exists($field, $configValues)) {
        return (string) $configValues[$field];
    }
    return defined($field) ? (string) constant($field) : '';
}

function settings_basic_fields(
    array $configValues,
    array $themes,
    string $configuredTheme,
    ?array $themeHealth,
    string $baseUrlPlaceholder,
): array {
    $fields = [];

    foreach (config_editable_fields() as $field) {
        $value = settings_config_field_value($field, $configValues);
        $item = [
            'id' => $field,
            'label' => t(config_field_translation_key($field), $field),
            'kind' => 'input',
            'inputType' => 'text',
            'value' => $value,
            'required' => true,
            'placeholder' => '',
            'help' => '',
            'pattern' => '',
            'maxlength' => 0,
            'spellcheck' => '',
            'options' => [],
        ];

        if ($field === 'LANGUAGE') {
            $item['kind'] = 'select';
            $item['options'] = array_map(
                static fn(string $lang): array => [
                    'value' => $lang,
                    'label' => language_display_name($lang),
                    'selected' => $lang === $value,
                ],
                allowed_languages(),
            );
        } elseif ($field === 'WIKI_TITLE') {
            $item['placeholder'] = t('field.placeholder.wiki_title');
        } elseif ($field === 'WIKI_DESCRIPTION') {
            $item['placeholder'] = t('field.placeholder.wiki_description');
        } elseif ($field === 'HOME_PAGE') {
            $item['pattern'] = page_title_input_pattern();
            $item['maxlength'] = PAGE_TITLE_MAX_LENGTH;
            $item['spellcheck'] = 'false';
            $item['placeholder'] = t('field.placeholder.home_page');
            $item['help'] = sprintf(t('field.help.page_title'), page_title_forbidden_chars_help_html());
        } elseif ($field === 'BASE_URL') {
            $item['inputType'] = 'url';
            $item['required'] = false;
            $item['spellcheck'] = 'false';
            $item['placeholder'] = $baseUrlPlaceholder !== '' ? $baseUrlPlaceholder : 'https://wikiwikiwiki.wiki';
        } elseif ($field === 'TIMEZONE') {
            $item['required'] = false;
            $item['spellcheck'] = 'false';
            $item['placeholder'] = date_default_timezone_get();
        } elseif ($field === 'THEME') {
            $item['kind'] = 'select';
            $item['required'] = false;
            $item['options'][] = [
                'value' => '',
                'label' => t('settings.theme_default'),
                'selected' => $value === '',
            ];
            foreach ($themes as $themeOption) {
                $themeValue = (string) $themeOption;
                $item['options'][] = [
                    'value' => $themeValue,
                    'label' => $themeValue,
                    'selected' => $themeValue === $value,
                ];
            }
            if ($configuredTheme !== '' && $themeHealth !== null && (($themeHealth['exists'] ?? false) !== true)) {
                $item['help'] = t('settings.theme_status_missing_dir');
            }
        }

        $fields[] = $item;
    }

    return $fields;
}

function settings_edit_permission_options(string $current): array
{
    $labels = [
        'private' => t('settings.edit_permission_private'),
        'public' => t('settings.edit_permission_public'),
        'fully_public' => t('settings.edit_permission_fully_public'),
    ];

    $options = [];
    foreach (allowed_edit_permissions() as $permission) {
        $options[] = [
            'value' => $permission,
            'label' => $labels[$permission] ?? $permission,
            'selected' => $permission === $current,
        ];
    }
    return $options;
}

function settings_user_rows(array $users, string $currentUsername): array
{
    return array_map(
        static function (array $userRow) use ($currentUsername): array {
            $username = (string) ($userRow['username'] ?? '');
            $role = (string) ($userRow['role'] ?? '');
            return [
                'username' => $username,
                'roleLabel' => $role === 'admin' ? t('settings.role_admin') : t('settings.role_editor'),
                'canDelete' => $username !== $currentUsername,
            ];
        },
        $users,
    );
}

function settings_system_rows(array $systemChecks): array
{
    return array_map(
        static function (array $systemCheck): array {
            $status = (string) ($systemCheck['status'] ?? 'error');
            return [
                'label' => (string) ($systemCheck['label'] ?? ''),
                'statusText' => $status === 'ok' ? t('settings.system.status_ok') : t('settings.system.status_error'),
                'message' => (string) ($systemCheck['message'] ?? ''),
            ];
        },
        $systemChecks,
    );
}

function settings_prepare_render_data(array $view): array
{
    $prepared = $view;
    $activeTab = settings_normalize_tab((string) ($prepared['activeTab'] ?? 'basic'));
    $themes = array_values((array) ($prepared['themes'] ?? []));
    $configuredTheme = (string) ($prepared['configuredTheme'] ?? '');
    $themeHealth = $prepared['themeHealth'] ?? null;
    $configValues = (array) ($prepared['configValues'] ?? []);
    $inputNewUsername = (string) ($prepared['inputNewUsername'] ?? '');
    $systemChecks = (array) ($prepared['systemChecks'] ?? []);
    $baseUrlPlaceholder = (string) ($prepared['baseUrlPlaceholder'] ?? detected_base_url());
    $editPermission = (string) ($prepared['editPermission'] ?? EDIT_PERMISSION);
    $users = (array) ($prepared['users'] ?? []);
    $sessionHealth = settings_session_health();
    $showSessionCleanupAction = array_key_exists('showSessionCleanupAction', $prepared)
        ? (bool) $prepared['showSessionCleanupAction']
        : settings_session_cleanup_visible($sessionHealth);

    $prepared['activeTab'] = $activeTab;
    $prepared['themes'] = $themes;
    $prepared['configuredTheme'] = $configuredTheme;
    $prepared['themeHealth'] = is_array($themeHealth) ? $themeHealth : null;
    $prepared['configValues'] = $configValues;
    $prepared['inputNewUsername'] = $inputNewUsername;
    $prepared['systemChecks'] = $systemChecks;
    $prepared['baseUrlPlaceholder'] = $baseUrlPlaceholder;
    $prepared['editPermission'] = $editPermission;
    $prepared['users'] = $users;
    $prepared['showSessionCleanupAction'] = $showSessionCleanupAction;

    $prepared['basicFields'] = settings_basic_fields(
        $configValues,
        $themes,
        $configuredTheme,
        $prepared['themeHealth'],
        $baseUrlPlaceholder,
    );
    $prepared['editPermissionOptions'] = settings_edit_permission_options($editPermission);
    $prepared['userRows'] = settings_user_rows($users, (string) (current_user() ?? ''));
    $prepared['systemRows'] = settings_system_rows($systemChecks);
    $prepared['showUserCreateForm'] = is_private();

    return $prepared;
}

function settings_render_page(array $overrides = []): void
{
    $configuredTheme = trim((string) (defined('THEME') ? THEME : ''));

    $base = [
        'pageCount' => count(page_all()),
        'tagCount' => count(tag_all()),
        'contentSize' => dir_size(CONTENT_DIR),
        'historySize' => dir_size(HISTORY_DIR),
        'cacheSize' => dir_size(CACHE_DIR),
        'userCount' => user_count(),
        'userSize' => dir_size(USERS_DIR),
        'users' => user_all(),
        'editPermission' => EDIT_PERMISSION,
        'themeName' => THEME_NAME,
        'themes' => available_themes(),
        'baseUrlPlaceholder' => detected_base_url(),
        'activeTab' => settings_normalize_tab(request_string($_GET, 'tab', 'basic')),
        'configuredTheme' => $configuredTheme,
        'themeHealth' => $configuredTheme === '' ? null : theme_health($configuredTheme),
        'systemChecks' => settings_system_checks(),
    ];
    render('settings', settings_prepare_render_data(array_merge($base, $overrides)));
}

function settings_save_config(): void
{
    $optionalFields = [
        'BASE_URL' => true,
        'THEME' => true,
        'TIMEZONE' => true,
    ];
    $values = [];
    foreach (config_editable_fields() as $field) {
        $values[$field] = request_trimmed($_POST, $field);
    }
    foreach ($values as $field => $val) {
        if ($val !== '' || isset($optionalFields[$field])) {
            continue;
        }
        settings_render_tab_error('basic', 'flash.config.empty', ['configValues' => $values]);
    }

    $validated = validate_config_values($values);
    if (!$validated['ok']) {
        settings_render_tab_error('basic', $validated['error'], ['configValues' => $values]);
    }

    if (save_config($validated['values'])) {
        $nextHomePage = (string) ($validated['values']['HOME_PAGE'] ?? '');
        if ($nextHomePage !== '') {
            $nextLanguage = (string) ($validated['values']['LANGUAGE'] ?? LANGUAGE);
            $defaultPageSource = null;
            if ($nextLanguage !== '' && in_array($nextLanguage, allowed_languages(), true) && $nextLanguage !== (string) LANGUAGE) {
                $translations = install_language_translations($nextLanguage);
                $translated = trim((string) ($translations['default_page'] ?? ''));
                if ($translated !== '') {
                    $defaultPageSource = $translated;
                }
            }
            create_default_page_for_title($nextHomePage, current_user(), $defaultPageSource);
        }
        flash_success_and_redirect(settings_url('basic'));
    }
    settings_render_tab_error('basic', 'flash.settings.save_config_failed', ['configValues' => $values]);
}

function settings_update_edit_permission(): void
{
    $newPermission = request_string($_POST, 'edit_permission');
    if (!in_array($newPermission, allowed_edit_permissions(), true)) {
        settings_render_tab_error('edit', 'flash.settings.invalid_edit_permission', [
            'editPermission' => $newPermission,
        ]);
    }

    if (!save_config(['EDIT_PERMISSION' => $newPermission])) {
        settings_render_tab_error('edit', 'flash.settings.save_edit_permission_failed', [
            'editPermission' => $newPermission,
        ]);
    }

    flash_success_and_redirect(settings_url('edit'));
}

function settings_export_documents(): void
{
    $tmpFile = export_zip();
    if (!is_string($tmpFile) || !is_file($tmpFile) || !is_readable($tmpFile)) {
        if (is_string($tmpFile) && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
        settings_render_tab_error('documents', 'flash.settings.export_failed');
    }

    $prefix = 'WikiWikiWiki-export';
    $filename = $prefix . '-' . date('Y-m-d-His') . '.zip';
    $size = @filesize($tmpFile);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

function settings_history_cleanup_base(string $path): string
{
    $filename = basename($path);
    if (preg_match('/^(.*)\.\d{14}(?:\.[^.]+)?\.txt$/', $filename, $matches) !== 1) {
        return '';
    }
    return (string) $matches[1];
}

function settings_cleanup_document_history_unlocked(): int|false
{
    $historyFiles = glob(HISTORY_DIR . '/*.txt');
    if (!is_array($historyFiles) || $historyFiles === []) {
        page_history('', reset: true);
        return 0;
    }

    rsort($historyFiles, SORT_STRING);
    $keptBases = [];
    $deleted = 0;

    foreach ($historyFiles as $path) {
        if (!is_file($path)) {
            continue;
        }

        $base = settings_history_cleanup_base($path);
        if ($base === '') {
            continue;
        }

        if (!isset($keptBases[$base])) {
            $keptBases[$base] = true;
            continue;
        }

        if (!@unlink($path) && file_exists($path)) {
            wiki_log('settings.history_cleanup_unlink_failed', ['path' => $path], 'warning');
            page_history('', reset: true);
            return false;
        }

        $deleted++;
    }

    page_history('', reset: true);
    return $deleted;
}

function settings_cleanup_document_history(): void
{
    $deleted = wiki_with_lock(
        static fn() => settings_cleanup_document_history_unlocked(),
        false,
        null,
    );

    if (!is_int($deleted)) {
        flash_and_redirect(
            'error',
            t('flash.settings.cleanup_history_failed'),
            settings_url('documents'),
        );
    }

    flash_and_redirect(
        'success',
        sprintf(t('flash.settings.cleanup_history_done'), $deleted),
        settings_url('documents'),
    );
}

function settings_create_user(): void
{
    if (!is_private()) {
        settings_render_tab_error('users', 'flash.settings.create_user_private_only');
    }

    $inputNewUsername = request_trimmed($_POST, 'username');
    $newUsername = user_normalize_username($inputNewUsername);
    $newPassword = request_string($_POST, 'password');

    if (!username_is_valid($newUsername)) {
        settings_render_tab_error('users', 'flash.auth.username_invalid', [
            'inputNewUsername' => $inputNewUsername,
        ]);
    }
    if (!password_is_valid($newPassword)) {
        settings_render_tab_error('users', 'flash.auth.password_too_short', [
            'inputNewUsername' => $inputNewUsername,
        ]);
    }
    if (user_get($newUsername) !== null) {
        settings_render_tab_error('users', 'flash.auth.username_taken', [
            'inputNewUsername' => $inputNewUsername,
        ]);
    }

    $passwordHash = password_hash_bcrypt_safe($newPassword);
    if ($passwordHash === null) {
        settings_render_tab_error('users', 'flash.settings.create_user_failed', [
            'inputNewUsername' => $inputNewUsername,
        ]);
    }

    $created = user_create($newUsername, [
        'username' => $newUsername,
        'password_hash' => $passwordHash,
        'role' => 'editor',
        'created_at' => time(),
    ]);
    if (!$created) {
        settings_render_tab_error('users', 'flash.settings.create_user_failed', [
            'inputNewUsername' => $inputNewUsername,
        ]);
    }
    flash_success_and_redirect(settings_url('users'));
}

function settings_delete_user(): void
{
    $target = user_normalize_username(request_trimmed($_POST, 'username'));
    if ($target === '' || $target === current_user()) {
        settings_render_tab_error('users', 'flash.settings.invalid_delete_user');
    }

    if (!remember_login_revoke_all_for_user($target)) {
        settings_render_tab_error('users', 'flash.settings.delete_user_failed');
    }

    $deleteResult = user_delete_checked($target);
    if (($deleteResult['ok'] ?? false) !== true) {
        if (($deleteResult['error'] ?? '') === 'last_admin') {
            settings_render_tab_error('users', 'flash.auth.cannot_delete_last_admin');
        }
        settings_render_tab_error('users', 'flash.settings.delete_user_failed');
    }
    flash_success_and_redirect(settings_url('users'));
}

function settings_cleanup_expired_sessions_unlocked(string $fallbackPath, int $maxLifetime): int
{
    if (!is_dir($fallbackPath)) {
        return 0;
    }

    $matches = glob($fallbackPath . '/sess_*');
    if (!is_array($matches)) {
        return 0;
    }

    $deleted = 0;
    $cutoff = time() - max(60, $maxLifetime);
    foreach ($matches as $path) {
        if (!is_file($path)) {
            continue;
        }

        $modifiedAt = @filemtime($path);
        if (!is_int($modifiedAt)) {
            continue;
        }
        if ($modifiedAt >= $cutoff) {
            continue;
        }
        if (@unlink($path)) {
            $deleted++;
            continue;
        }
        wiki_log('settings.session_cleanup_unlink_failed', ['path' => $path], 'warning');
    }

    return $deleted;
}

function settings_cleanup_expired_sessions(): void
{
    if (!settings_session_cleanup_visible()) {
        settings_render_tab_error('system', 'flash.settings.cleanup_sessions_unavailable');
    }

    $fallbackPath = settings_session_fallback_path();
    $deleted = wiki_with_lock(
        fn() => settings_cleanup_expired_sessions_unlocked($fallbackPath, settings_session_gc_max_lifetime()),
        false,
        null,
    );

    if (!is_int($deleted)) {
        flash_and_redirect(
            'error',
            t('flash.settings.cleanup_sessions_failed'),
            settings_url('system'),
        );
    }

    flash_and_redirect(
        'success',
        t('flash.settings.cleanup_sessions_done'),
        settings_url('system'),
    );
}

function settings_action_map_by_tab(): array
{
    return [
        'basic' => [
            'save_config' => 'settings_save_config',
        ],
        'edit' => [
            'update_edit_permission' => 'settings_update_edit_permission',
        ],
        'documents' => [
            'export_documents' => 'settings_export_documents',
            'cleanup_document_history' => 'settings_cleanup_document_history',
        ],
        'users' => [
            'create_user' => 'settings_create_user',
            'delete_user' => 'settings_delete_user',
        ],
        'system' => [
            'cleanup_expired_sessions' => 'settings_cleanup_expired_sessions',
        ],
    ];
}

function settings_dispatch_post_action(string $action, string $redirectTab): bool
{
    
    $tabActions = settings_action_map_by_tab()[$redirectTab] ?? [];
    if ($tabActions === []) {
        return false;
    }

    $handler = $tabActions[$action] ?? null;
    if (!is_callable($handler)) {
        return false;
    }
    $handler();
    return true;
}

function handle_settings(string $method, array $matches): void
{
    require_admin();
    $activeTab = settings_normalize_tab(request_string($_GET, 'tab', 'basic'));

    if ($method === 'POST') {
        validate_post_request();
        $action = request_string($_POST, 'action');
        $redirectTab = settings_normalize_tab(request_string($_POST, 'tab', 'basic'));
        if (!settings_dispatch_post_action($action, $redirectTab)) {
            wiki_log('settings.action_denied', [
                'action' => $action,
                'tab' => $redirectTab,
                'user' => current_user(),
            ], 'warning');
            settings_render_tab_error($redirectTab, 'flash.settings.invalid_action');
        }
    }

    settings_render_page([
        'activeTab' => $activeTab,
    ]);
}
