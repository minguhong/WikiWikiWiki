<?php

declare(strict_types=1);

function timestamp_to_date(string $timestamp): string
{
    $dateTime = DateTime::createFromFormat('YmdHis', $timestamp);
    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : '';
}

const PAGE_BACKUP_PATH_MAX_ATTEMPTS = 1000;

function page_backup_path(string $title, ?string $author = null): ?string
{
    $base = page_history_base($title);
    $tsSeed = time();
    $ts = null;
    for ($attempt = 0; $attempt < PAGE_BACKUP_PATH_MAX_ATTEMPTS; $attempt++) {
        $candidate = date('YmdHis', $tsSeed);
        $sameTimestampFiles = glob(HISTORY_DIR . '/' . $base . '.' . $candidate . '*.txt') ?: [];
        if ($sameTimestampFiles === []) {
            $ts = $candidate;
            break;
        }
        $tsSeed++;
    }
    if ($ts === null) {
        wiki_log('page.backup_path_exhausted', [
            'title' => $title,
            'attempts' => PAGE_BACKUP_PATH_MAX_ATTEMPTS,
        ], 'error');
        return null;
    }

    $username = $author !== null
        ? trim($author)
        : trim((string) (current_user() ?? ''));
    if ($username !== '') {
        return HISTORY_DIR . '/' . $base . '.' . $ts . '.' . $username . '.txt';
    }
    return HISTORY_DIR . '/' . $base . '.' . $ts . '.txt';
}

function page_history(string $title, bool $reset = false): array
{
    static $cache = [];
    if ($reset) {
        if ($title === '') {
            $cache = [];
            return [];
        }
        unset($cache[$title]);
    }
    if (!isset($cache[$title])) {
        $base = page_history_base($title);
        $files = glob(HISTORY_DIR . '/' . $base . '.*.txt') ?: [];
        rsort($files);
        $cache[$title] = $files;
    }
    return $cache[$title];
}

function page_history_seed_first_if_missing(string $title, ?string $author = null): bool
{
    $path = page_path($title);
    if (!is_file($path)) {
        return false;
    }

    if (page_history($title, reset: true) !== []) {
        return false;
    }

    $backupPath = page_backup_path($title, $author);
    if ($backupPath === null) {
        wiki_log('page.history_seed_write_failed', ['title' => $title, 'path' => ''], 'warning');
        return false;
    }
    $content = @file_get_contents($path);
    if (!is_string($content) || !file_put_atomic($backupPath, $content)) {
        wiki_log('page.history_seed_write_failed', ['title' => $title, 'path' => $backupPath], 'warning');
        return false;
    }

    page_history($title, reset: true);
    return true;
}

function page_history_parse_filename(string $title, string $filepath): array
{
    $base = page_history_base($title);
    $stem = basename($filepath, '.txt');
    $rest = substr($stem, strlen($base) + 1);
    if (preg_match('/^(\d{14})\.(.+)$/', $rest, $m)) {
        return ['timestamp' => $m[1], 'author' => $m[2]];
    }
    if (preg_match('/^(\d{14})$/', $rest, $m)) {
        return ['timestamp' => $m[1], 'author' => ''];
    }
    return ['timestamp' => '', 'author' => ''];
}

function page_history_get(string $title, string $timestamp): ?string
{
    if (!preg_match('/^\d{14}$/', $timestamp)) {
        return null;
    }
    foreach (page_history($title) as $file) {
        $parsed = page_history_parse_filename($title, $file);
        if ($parsed['timestamp'] === $timestamp) {
            if ($parsed['author'] === '_deleted') {
                return null;
            }
            $content = file_get_contents($file);
            return $content === false ? null : $content;
        }
    }
    return null;
}

function page_history_author(string $title, string $timestamp): string
{
    if (!preg_match('/^\d{14}$/', $timestamp)) {
        return '';
    }
    foreach (page_history($title) as $file) {
        $parsed = page_history_parse_filename($title, $file);
        if ($parsed['timestamp'] === $timestamp) {
            return $parsed['author'];
        }
    }
    return '';
}

function page_history_latest_author(string $title): string
{
    foreach (page_history($title) as $file) {
        $parsed = page_history_parse_filename($title, $file);
        if (($parsed['author'] ?? '') === '_deleted') {
            continue;
        }
        return (string) ($parsed['author'] ?? '');
    }
    return '';
}

function page_history_delete_version(string $title, string $timestamp): bool
{
    if (!preg_match('/^\d{14}$/', $timestamp)) {
        return false;
    }

    return wiki_with_lock(function () use ($title, $timestamp): bool {
        foreach (page_history($title, reset: true) as $file) {
            $parsed = page_history_parse_filename($title, $file);
            if ($parsed['timestamp'] !== $timestamp || $parsed['author'] === '_deleted') {
                continue;
            }
            if (!@unlink($file) && file_exists($file)) {
                wiki_log('page.history_delete_unlink_failed', ['title' => $title, 'path' => $file], 'warning');
                return false;
            }
            page_history($title, reset: true);
            return true;
        }
        return false;
    }, false, false);
}

function page_history_prune(string $title, int $keep = HISTORY_KEEP_COUNT): void
{
    $files = page_history($title, reset: true);

    
    $markers = array_filter($files, fn($f) => str_contains(basename($f), '._deleted.'));
    $edits = array_values(array_filter($files, fn($f) => !str_contains(basename($f), '._deleted.')));

    foreach (array_slice($edits, $keep) as $old) {
        if (!@unlink($old) && file_exists($old)) {
            wiki_log('page.history_prune_unlink_failed', ['title' => $title, 'path' => $old], 'warning');
        }
    }

    
    
    foreach (array_slice(array_values($markers), $keep) as $old) {
        if (!@unlink($old) && file_exists($old)) {
            wiki_log('page.history_prune_unlink_failed', ['title' => $title, 'path' => $old], 'warning');
        }
    }

    page_history($title, reset: true);
}

function page_history_delete_all(string $title): void
{
    foreach (page_history($title, reset: true) as $file) {
        if (!@unlink($file) && file_exists($file)) {
            wiki_log('page.history_delete_unlink_failed', ['title' => $title, 'path' => $file], 'warning');
        }
    }
    page_history($title, reset: true);
}
