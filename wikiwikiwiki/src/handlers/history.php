<?php

declare(strict_types=1);

function handle_history_list(string $method, array $matches): void
{
    require_get_method($method);
    $title = route_title_or_400($matches);

    $versions = array_map(function (string $file) use ($title): array {
        $parsed = page_history_parse_filename($title, $file);
        return [
            'timestamp' => $parsed['timestamp'],
            'date' => timestamp_to_date($parsed['timestamp']),
            'author' => $parsed['author'],
        ];
    }, page_history($title));

    render('history', [
        'page' => $title,
        'versions' => $versions,
    ]);
}

function handle_history_version(string $method, array $matches): void
{
    $title = route_title_or_400($matches);
    $timestamp = request_string($matches, 2);

    if ($method === 'POST') {
        require_login();
        validate_post_request();
        $action = request_string($_POST, 'action', 'restore');

        if ($action === 'delete_version') {
            require_admin();
            if (!page_history_delete_version($title, $timestamp)) {
                flash('error', t('flash.page.history_delete_failed'));
            } else {
                flash('success', t('flash.page.history_deleted'));
            }
            redirect(url($title, '/history'));
        }
        require_admin();

        $snapshot = page_history_get($title, $timestamp);
        if ($snapshot === null) {
            render_404_error();
        }
        if (!page_save($title, $snapshot, contentIsNormalized: true)) {
            flash('error', t('flash.page.save_failed'));
            redirect(url($title, '/history/' . $timestamp));
        }
        flash('success', t('flash.page.restored'));
        redirect(url($title));
    }

    $snapshot = page_history_get($title, $timestamp);
    if ($snapshot === null) {
        render_404_error();
    }

    $allEntries = array_map(
        fn(string $f) => page_history_parse_filename($title, $f),
        page_history($title),
    );
    $allTimestamps = array_column($allEntries, 'timestamp');
    $currentIndex = array_search($timestamp, $allTimestamps, true);

    
    $newerTimestamp = null;
    if ($currentIndex !== false) {
        for ($i = (int) $currentIndex - 1; $i >= 0; $i--) {
            if ($allEntries[$i]['author'] !== '_deleted') {
                $newerTimestamp = $allEntries[$i]['timestamp'];
                break;
            }
        }
    }
    $olderTimestamp = null;
    if ($currentIndex !== false) {
        $counter = count($allEntries);
        for ($i = (int) $currentIndex + 1; $i < $counter; $i++) {
            if ($allEntries[$i]['author'] !== '_deleted') {
                $olderTimestamp = $allEntries[$i]['timestamp'];
                break;
            }
        }
    }

    $parsed = parse_content($snapshot, ['source_title' => $title]);

    
    
    
    $immediatePrev = ($currentIndex !== false && (int) $currentIndex + 1 < count($allEntries))
        ? $allEntries[(int) $currentIndex + 1]
        : null;
    if ($immediatePrev !== null && $immediatePrev['author'] !== '_deleted') {
        $olderContent = page_history_get($title, $immediatePrev['timestamp']) ?? '';
    } else {
        $olderContent = '';
    }
    $diff = diff_lines($olderContent, $snapshot);
    $diffTooLarge = $diff !== [] && $diff[0]['type'] === 'too_large';

    render('history', [
        'page' => $title,
        'timestamp' => $timestamp,
        'date' => timestamp_to_date($timestamp),
        'author' => page_history_author($title, $timestamp),
        'content' => $parsed['content'] ?? '',
        'snapshot' => $snapshot,
        'newerTimestamp' => $newerTimestamp,
        'olderTimestamp' => $olderTimestamp,
        'canRestore' => $newerTimestamp !== null || page_get($title) === null,
        'diff' => $diff,
        'diffTooLarge' => $diffTooLarge,
    ]);
}
