<?php

declare(strict_types=1);

function handle_login(string $method, array $matches): void
{
    if (user_count() === 0) {
        redirect(url('/install'));
    }

    if (is_logged_in()) {
        redirect(url('/'));
    }

    $page = normalize_return_path(request_string($_GET, 'page'));
    $loginUrl = url('/login') . ($page !== '' ? '?page=' . rawurlencode($page) : '');

    if ($method === 'POST') {
        validate_post_request();
        $inputUsername = request_trimmed($_POST, 'username');

        $loginResult = login($inputUsername, request_string($_POST, 'password'));

        if ($loginResult === true) {
            redirect($page ?: url('/'));
        }

        if ($loginResult === 'rate_limited') {
            flash('error', t('flash.auth.login_rate_limited'));
        } else {
            flash('error', t('flash.auth.invalid_password'));
        }

        render('login', [
            'pageTitle' => t('title.login'),
            'loginActionUrl' => $loginUrl,
            'inputUsername' => $inputUsername,
        ]);
        return;
    }

    render('login', [
        'pageTitle' => t('title.login'),
        'loginActionUrl' => $loginUrl,
    ]);
}

function handle_install(string $method, array $matches): void
{
    if (user_count() > 0) {
        redirect(url('/login'));
    }

    if (is_logged_in()) {
        redirect(url('/'));
    }

    $installReport = install_environment_report();
    $installErrors = $installReport['installErrors'];
    $installWarnings = $installReport['installWarnings'];
    $installReady = $installReport['installReady'];
    $selectedInstallLanguage = install_selected_language();
    $installConfig = install_config_defaults($selectedInstallLanguage);
    $languageOptions = language_catalog();
    $renderInstall = static function (array $overrides = []) use (
        $installConfig,
        $installErrors,
        $installReady,
        $installWarnings,
        $languageOptions,
        $selectedInstallLanguage
    ): void {
        $view = array_merge([
            'pageTitle' => t('title.install'),
            'wikiTitle' => (string) ($installConfig['wiki_title'] ?? WIKI_TITLE),
            'wikiDescription' => (string) ($installConfig['wiki_description'] ?? WIKI_DESCRIPTION),
            'installReady' => $installReady,
            'installStatusMessage' => $installReady
                ? t('message.install.status_ok')
                : t('message.install.status_fail'),
            'installErrors' => $installErrors,
            'installWarnings' => $installWarnings,
            'installConfig' => $installConfig,
            'languageOptions' => $languageOptions,
            'selectedInstallLanguage' => $selectedInstallLanguage,
        ], $overrides);
        $renderConfig = is_array($view['installConfig'] ?? null) ? $view['installConfig'] : [];
        $view['installEditPermissionOptions'] = install_edit_permission_options((string) ($renderConfig['edit_permission'] ?? EDIT_PERMISSION));

        render('install', $view);
    };
    $renderInstallError = static function (string $messageKey, array $overrides = []) use ($renderInstall): void {
        flash('error', t($messageKey));
        $renderInstall($overrides);
    };

    if ($method === 'POST') {
        validate_post_request();
        $inputUsername = request_trimmed($_POST, 'username');
        $inputInstallConfig = install_request_values($selectedInstallLanguage);

        if (!$installReady) {
            $renderInstallError('message.install.status_fail', [
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }

        $installRateKey = 'install:' . client_ip();
        if (rate_limit_check_and_record($installRateKey, 5, 600)) {
            $renderInstallError('flash.auth.install_rate_limited', [
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }

        $configValues = install_config_from_request($selectedInstallLanguage);
        $validation = validate_config_values($configValues);
        if (!$validation['ok']) {
            $renderInstallError($validation['error'], [
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }
        if (!save_config($validation['values'])) {
            $renderInstallError('flash.auth.install_config_save_failed', [
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }

        $password = request_string($_POST, 'password');
        $confirm = request_string($_POST, 'confirm_password');
        $credentialValidation = auth_validate_new_user_credentials($inputUsername, $password, $confirm);
        if (!$credentialValidation['ok']) {
            flash('error', $credentialValidation['error']);
            $renderInstall([
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }
        if (!auth_create_user($credentialValidation['username'], $password, 'admin')) {
            $renderInstallError('flash.auth.install_admin_create_failed', [
                'installConfig' => $inputInstallConfig,
                'inputUsername' => $inputUsername,
            ]);
            return;
        }
        $nextHomePage = (string) ($validation['values']['HOME_PAGE'] ?? HOME_PAGE);
        $defaultPageSource = t('default_page');
        $installTranslations = install_language_translations($selectedInstallLanguage);
        if (isset($installTranslations['default_page']) && is_string($installTranslations['default_page'])) {
            $candidateDefaultPage = trim($installTranslations['default_page']);
            if ($candidateDefaultPage !== '') {
                $defaultPageSource = $candidateDefaultPage;
            }
        }
        create_default_page_for_title($nextHomePage, $credentialValidation['username'], $defaultPageSource);

        flash_and_redirect('success', t('flash.auth.register_success'), url('/login'));
    }

    $renderInstall();
}

function install_config_writable(string $configPath): bool
{
    $configDir = dirname($configPath);
    if (is_file($configPath)) {
        return is_writable($configPath);
    }
    if (is_dir($configDir)) {
        return is_writable($configDir);
    }
    
    return true;
}

function install_environment_report(): array
{
    $configPath = BASE_DIR . '/config/config.php';
    $configWritable = install_config_writable($configPath);
    $missingExtensions = array_values(array_filter(
        required_php_extensions(),
        static fn(string $ext): bool => !extension_loaded($ext),
    ));
    $optionalExtensions = [
        'zip' => class_exists('ZipArchive'),
        'intl' => class_exists('Normalizer'),
        'gd' => extension_loaded('gd'),
    ];
    $installWarnings = array_map(
        static fn(string $ext): string => t('message.install.warning_missing_extension') . ': ' . $ext,
        array_keys(array_filter($optionalExtensions, static fn(bool $available): bool => !$available)),
    );
    $bcryptAvailable = defined('PASSWORD_BCRYPT');

    $storageReport = storage_boot_report();
    $phpVersion = PHP_VERSION;
    $requiredPhpVersion = defined('PHP_REQUIRED_VERSION') ? (string) PHP_REQUIRED_VERSION : '8.2.0';
    $phpVersionOk = version_compare($phpVersion, $requiredPhpVersion, '>=');
    $installErrors = array_merge(
        array_map(
            static fn(string $dir): string => t('message.install.error_not_writable') . ': ' . $dir,
            (array) ($storageReport['not_writable_dirs'] ?? []),
        ),
        array_map(
            static fn(string $dir): string => t('message.install.error_missing_directory') . ': ' . $dir,
            (array) ($storageReport['missing_dirs'] ?? []),
        ),
        (!empty($storageReport['lock_ok']) ? [] : [t('message.install.error_lock_check_failed')]),
        $configWritable ? [] : [t('message.install.error_config_not_writable') . ': ' . $configPath],
        array_map(
            static fn(string $ext): string => t('message.install.error_missing_extension') . ': ' . $ext,
            $missingExtensions,
        ),
        $bcryptAvailable ? [] : [t('message.install.error_bcrypt_unavailable')],
        $phpVersionOk ? [] : [sprintf(t('message.install.error_php_version'), $requiredPhpVersion, $phpVersion)],
    );
    $installReady = !empty($storageReport['ok'])
        && $phpVersionOk
        && $configWritable
        && $missingExtensions === []
        && $bcryptAvailable;

    return [
        'installReady' => $installReady,
        'installErrors' => $installErrors,
        'installWarnings' => $installWarnings,
    ];
}

function install_selected_language(): string
{
    $candidate = request_trimmed($_POST, 'language', request_trimmed($_GET, 'language', (string) LANGUAGE));
    $allowed = allowed_languages();
    if (in_array($candidate, $allowed, true)) {
        return $candidate;
    }
    if (in_array((string) LANGUAGE, $allowed, true)) {
        return (string) LANGUAGE;
    }
    return $allowed[0] ?? 'ko';
}

function install_language_seed(string $language): array
{
    $translations = install_language_translations($language);
    return [
        'wiki_title' => install_language_seed_value($translations, 'install.default.wiki_title', (string) WIKI_TITLE),
        'wiki_description' => install_language_seed_value($translations, 'install.default.wiki_description', (string) WIKI_DESCRIPTION),
        'timezone' => install_language_seed_value($translations, 'install.default.timezone', (string) TIMEZONE),
        'home_page' => install_language_seed_value($translations, 'install.default.home_page', (string) HOME_PAGE),
    ];
}

function install_language_translations(string $language): array
{
    static $cache = [];
    if (isset($cache[$language])) {
        return $cache[$language];
    }

    $merged = [];
    foreach (i18n_directories() as $directory) {
        $path = $directory . '/i18n/' . $language . '.json';
        if (!is_file($path)) {
            continue;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }
        $merged = array_replace($merged, $decoded);
    }

    $cache[$language] = $merged;
    return $cache[$language];
}

function install_language_seed_value(array $translations, string $key, string $fallback): string
{
    if (!array_key_exists($key, $translations) || !is_string($translations[$key])) {
        return $fallback;
    }
    return trim($translations[$key]);
}

function install_config_defaults(string $language): array
{
    $seed = install_language_seed($language);
    $defaultEditPermission = (string) EDIT_PERMISSION;
    if (!in_array($defaultEditPermission, allowed_edit_permissions(), true)) {
        $defaultEditPermission = 'private';
    }
    return [
        'wiki_title' => (string) ($seed['wiki_title'] ?? WIKI_TITLE),
        'wiki_description' => (string) ($seed['wiki_description'] ?? WIKI_DESCRIPTION),
        'language' => $language,
        'timezone' => (string) ($seed['timezone'] ?? TIMEZONE),
        'home_page' => (string) ($seed['home_page'] ?? HOME_PAGE),
        'edit_permission' => $defaultEditPermission,
    ];
}

function install_edit_permission_options(string $current): array
{
    $labels = [
        'private' => t('settings.edit_permission_private'),
        'public' => t('settings.edit_permission_public'),
        'fully_public' => t('settings.edit_permission_fully_public'),
    ];
    $allowed = allowed_edit_permissions();
    $selected = in_array($current, $allowed, true) ? $current : ($allowed[0] ?? 'private');

    $options = [];
    foreach ($allowed as $permission) {
        $options[] = [
            'value' => $permission,
            'label' => $labels[$permission] ?? $permission,
            'selected' => $permission === $selected,
        ];
    }

    return $options;
}

function install_request_values(string $selectedLanguage): array
{
    $defaults = install_config_defaults($selectedLanguage);
    $defaultEditPermission = (string) ($defaults['edit_permission'] ?? EDIT_PERMISSION);
    $editPermission = request_trimmed($_POST, 'edit_permission', $defaultEditPermission);
    if (!in_array($editPermission, allowed_edit_permissions(), true)) {
        $editPermission = $defaultEditPermission;
    }

    return [
        'wiki_title' => request_trimmed($_POST, 'wiki_title', (string) $defaults['wiki_title']),
        'wiki_description' => request_trimmed($_POST, 'wiki_description', (string) $defaults['wiki_description']),
        'language' => request_trimmed($_POST, 'language', (string) $defaults['language']),
        'timezone' => request_trimmed($_POST, 'timezone', (string) $defaults['timezone']),
        'home_page' => request_trimmed($_POST, 'home_page', (string) $defaults['home_page']),
        'edit_permission' => $editPermission,
    ];
}

function install_config_from_request(string $selectedLanguage): array
{
    $values = install_request_values($selectedLanguage);

    return [
        'WIKI_TITLE' => $values['wiki_title'],
        'WIKI_DESCRIPTION' => $values['wiki_description'],
        'LANGUAGE' => $values['language'],
        'TIMEZONE' => $values['timezone'],
        'HOME_PAGE' => $values['home_page'],
        'BASE_URL' => (string) BASE_URL,
        'THEME' => (string) THEME,
        'EDIT_PERMISSION' => $values['edit_permission'],
    ];
}

function handle_register(string $method, array $matches): void
{
    if (user_count() === 0) {
        redirect(url('/install'));
    }

    if (!can_register()) {
        flash_and_redirect('error', t('flash.auth.register_disabled'), url('/login'));
    }

    if (is_logged_in()) {
        redirect(url('/'));
    }
    $renderRegister = static function (array $overrides = []): void {
        render('register', array_merge([
            'pageTitle' => t('title.register'),
        ], $overrides));
    };

    if ($method === 'POST') {
        validate_post_request();
        $inputUsername = request_trimmed($_POST, 'username');

        
        $registerRateKey = 'register:' . client_ip();
        if (rate_limit_check_and_record($registerRateKey, 3, 600)) {
            flash('error', t('flash.auth.register_rate_limited'));
            $renderRegister([
                'inputUsername' => $inputUsername,
            ]);
            return;
        }

        $password = request_string($_POST, 'password');
        $confirm = request_string($_POST, 'confirm_password');
        $validation = auth_validate_new_user_credentials(
            $inputUsername,
            $password,
            $confirm,
        );
        if (!$validation['ok']) {
            flash('error', $validation['error']);
            $renderRegister([
                'inputUsername' => $inputUsername,
            ]);
            return;
        }
        if (!auth_create_user($validation['username'], $password, 'editor')) {
            flash('error', t('flash.auth.register_failed'));
            $renderRegister([
                'inputUsername' => $inputUsername,
            ]);
            return;
        }

        flash_and_redirect('success', t('flash.auth.register_success'), url('/login'));
    }

    $renderRegister();
}

function account_normalize_tab(?string $tab): string
{
    $tab = (string) $tab;
    if (!in_array($tab, ['account-info', 'account-change-password', 'account-delete-account'], true)) {
        return 'account-info';
    }
    return $tab;
}

function account_url(?string $tab = null): string
{
    $base = url('/account');
    if ($tab === null || $tab === '') {
        return $base;
    }
    return $base . '?tab=' . rawurlencode(account_normalize_tab($tab));
}

function handle_account(string $method, array $matches): void
{
    require_login();
    $activeTab = account_normalize_tab(request_string($_GET, 'tab', 'account-info'));

    if ($method === 'POST') {
        validate_post_request();

        $username = current_user() ?? '';
        $activeTab = account_normalize_tab(request_string($_POST, 'tab', $activeTab));
        $action = request_string($_POST, 'action', 'change_password');
        auth_dispatch_account_action($username, $action, $activeTab);
    }

    $accountUsername = current_user() ?? '';
    render('account', [
        'pageTitle' => t('title.account'),
        'activeTab' => $activeTab,
        'username' => $accountUsername,
        'role' => current_role(),
    ]);
}

function auth_dispatch_account_action(string $username, string $action, string $activeTab): never
{
    if ($action === 'delete_account') {
        auth_account_delete($username, $activeTab);
    }
    if ($action === 'change_password') {
        auth_account_change_password($username, $activeTab);
    }

    flash_and_redirect('error', t('flash.auth.invalid_account_action'), account_url($activeTab));
}

function auth_validate_new_user_credentials(
    string $inputUsername,
    #[\SensitiveParameter]
    string $password,
    #[\SensitiveParameter]
    string $confirm,
): array {
    $username = user_normalize_username($inputUsername);

    if ($username === '' || !username_is_valid($username)) {
        return ['ok' => false, 'username' => '', 'error' => t('flash.auth.username_invalid')];
    }
    if (!password_is_valid($password)) {
        return ['ok' => false, 'username' => '', 'error' => t('flash.auth.password_too_short')];
    }
    if ($password !== $confirm) {
        return ['ok' => false, 'username' => '', 'error' => t('flash.auth.password_mismatch')];
    }
    if (user_get($username) !== null) {
        return ['ok' => false, 'username' => '', 'error' => t('flash.auth.username_taken')];
    }
    return ['ok' => true, 'username' => $username, 'error' => ''];
}

function auth_create_user(string $username, #[\SensitiveParameter] string $password, string $role): bool
{
    $passwordHash = password_hash_bcrypt_safe($password);
    if ($passwordHash === null) {
        return false;
    }

    return user_create($username, [
        'username' => $username,
        'password_hash' => $passwordHash,
        'role' => $role,
        'created_at' => time(),
    ]);
}

function auth_account_delete(string $username, string $activeTab = 'account-delete-account'): never
{
    $accountUrl = account_url($activeTab);
    $deletePassword = request_string($_POST, 'delete_password');
    $userRecord = user_get($username);
    if (!$userRecord || !password_verify($deletePassword, $userRecord['password_hash'])) {
        flash_and_redirect('error', t('flash.auth.invalid_password'), $accountUrl);
    }

    $deleteResult = user_delete_checked($username);
    if (($deleteResult['ok'] ?? false) !== true) {
        if (($deleteResult['error'] ?? '') === 'last_admin') {
            flash_and_redirect('error', t('flash.auth.cannot_delete_last_admin'), $accountUrl);
        }
        flash_and_redirect('error', t('flash.auth.account_delete_failed'), $accountUrl);
    }
    logout(false);
    flash_and_redirect('success', t('flash.auth.account_deleted'), url('/'));
}

function auth_account_change_password(string $username, string $activeTab = 'account-change-password'): never
{
    $accountUrl = account_url($activeTab);
    $current = request_string($_POST, 'current_password');
    $new = request_string($_POST, 'new_password');
    $confirm = request_string($_POST, 'confirm_password');

    $user = user_get($username);
    if (!$user || !password_verify($current, $user['password_hash'])) {
        flash_and_redirect('error', t('flash.auth.invalid_password'), $accountUrl);
    }

    if ($new === '' || !password_is_valid($new)) {
        flash_and_redirect('error', t('flash.auth.password_too_short'), $accountUrl);
    }
    if ($new !== $confirm) {
        flash_and_redirect('error', t('flash.auth.password_mismatch'), $accountUrl);
    }

    $nextHash = password_hash_bcrypt_safe($new);
    if ($nextHash === null) {
        flash_and_redirect('error', t('flash.auth.password_change_failed'), $accountUrl);
    }
    $user['password_hash'] = $nextHash;
    if (!user_update($username, $user)) {
        flash_and_redirect('error', t('flash.auth.password_change_failed'), $accountUrl);
    }

    flash_and_redirect('success', t('flash.auth.password_changed'), $accountUrl);
}

function handle_logout(string $method, array $matches): void
{
    require_login();
    require_get_method($method);

    $page = normalize_return_path(request_string($_GET, 'page'));
    logout();
    redirect($page ?: url('/'));
}
