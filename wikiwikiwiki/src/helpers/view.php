<?php

declare(strict_types=1);

function base_meta_title(string $wikiTitle, string $pageTitle = ''): string
{
    return $pageTitle !== '' ? $pageTitle . ' – ' . $wikiTitle : $wikiTitle;
}

function base_meta_description(string $wikiDescription, string $description = ''): string
{
    return $description !== '' ? $description : $wikiDescription;
}

function language_tag_from_locale(string $locale, string $fallback = 'en'): string
{
    $locale = trim($locale);
    if ($locale !== '' && preg_match('/^([a-z]{2,12})(?:[_-][a-z0-9]{2,12})?$/i', $locale, $m) === 1) {
        return strtolower((string) $m[1]);
    }

    $fallback = trim($fallback);
    if ($fallback !== '' && preg_match('/^[a-z]{2,12}$/i', $fallback) === 1) {
        return strtolower($fallback);
    }

    return 'en';
}

function render(string $template, array $data = []): void
{
    header('Cache-Control: no-store');

    $view = $data;
    $view['description'] ??= '';
    $view['pageTitle'] ??= $view['page'] ?? t('title.' . $template, '');
    $view['recentPages'] ??= page_recent_without_redirects(5);

    extract($view, EXTR_SKIP);

    $description = (string) $view['description'];
    $pageTitle = (string) $view['pageTitle'];
    $page = (string) ($view['page'] ?? '');
    $wikiTitle = (string) ($view['wikiTitle'] ?? WIKI_TITLE);
    $wikiDescription = (string) ($view['wikiDescription'] ?? WIKI_DESCRIPTION);
    $languageCode = t('language.locale', LANGUAGE);
    $language = language_tag_from_locale($languageCode, LANGUAGE);
    $basePath = base_path();
    $csrfToken = csrf_token();
    $flashes = pull_flashes();
    $ogUrl = ($page !== '') ? page_url($page) : base_url();
    $recentPages = is_array($view['recentPages']) ? $view['recentPages'] : page_recent_without_redirects(5);
    $metaTitle = base_meta_title($wikiTitle, $pageTitle);
    $metaDescription = base_meta_description($wikiDescription, $description);
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
        'lastEditor' => page_history_latest_author($title),
        'sourceFile' => $title . '.txt',
        'parent' => $parent,
        'children' => $children,
        'siblings' => $siblings,
    ];

    if ($parsed['type'] === 'redirect') {
        $redirectTargetRaw = (string) $parsed['target'];
        $redirectTarget = preg_match('/^https?:\/\//i', $redirectTargetRaw)
            ? null
            : normalize_internal_redirect_title($redirectTargetRaw);
        $viewData = $common + ['content' => $parsed['content'] ?? ''];
        if ($redirectTarget !== null) {
            $viewData['redirectTarget'] = $redirectTarget;
        }
        render('view', $viewData);
    } else {
        render('view', $common + [
            'content' => $parsed['content'],
            'description' => extract_description($content),
        ]);
    }
}

function base_menu_items(string $currentRequestUri): array
{
    $returnPath = rawurlencode($currentRequestUri);
    $items = [
        [
            'href' => url('/discover'),
            'label' => t('nav.discover'),
            'shortcut' => '',
        ],
    ];

    if (is_logged_in()) {
        $items[] = [
            'href' => url('/new'),
            'label' => t('nav.new'),
            'shortcut' => 'new',
        ];
        $items[] = [
            'href' => url('/account'),
            'label' => t('nav.account'),
            'shortcut' => '',
        ];
        if (is_admin()) {
            $items[] = [
                'href' => url('/settings'),
                'label' => t('nav.settings'),
                'shortcut' => '',
            ];
        }
        $items[] = [
            'href' => url('/logout') . '?page=' . $returnPath,
            'label' => t('nav.logout'),
            'shortcut' => '',
        ];
        return $items;
    }

    if (is_fully_public()) {
        $items[] = [
            'href' => url('/new'),
            'label' => t('nav.new'),
            'shortcut' => 'new',
        ];
    }

    $items[] = [
        'href' => url('/login') . '?page=' . $returnPath,
        'label' => t('nav.login'),
        'shortcut' => '',
    ];

    if (can_register()) {
        $items[] = [
            'href' => url('/register'),
            'label' => t('nav.register'),
            'shortcut' => '',
        ];
    }

    return $items;
}
