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

function base_og_image_url(): string
{
    return base_url() . '/og-image.png';
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
