<?php

declare(strict_types=1);

const PAGE_INDEX_FILE_VERSION = 2;

function page_index_path(): string
{
    return CACHE_DIR . '/all.json';
}

function page_index_entry_redirect_target(mixed $entry): ?string
{
    if (!is_array($entry) || !array_key_exists('redirect_target', $entry)) {
        return null;
    }

    $target = trim((string) ($entry['redirect_target'] ?? ''));
    return $target !== '' ? $target : null;
}

function page_index_redirect_target_for(string $title, array $pageIndex): ?string
{
    return page_index_entry_redirect_target($pageIndex[$title] ?? null);
}

function page_redirect_target_from_path(string $path): ?string
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return null;
    }

    try {
        $firstLine = fgets($handle);
    } finally {
        @fclose($handle);
    }

    if (!is_string($firstLine)) {
        return null;
    }

    return page_redirect_target_from_content($firstLine);
}

function page_index_rebuild(): array
{
    $files = scandir(CONTENT_DIR);
    if ($files === false) {
        wiki_log('page.index_rebuild_failed', ['dir' => CONTENT_DIR], 'error');
        return [];
    }

    $index = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with($file, '.txt')) {
            continue;
        }
        $title = file_to_page_title($file);
        if ($title === '') {
            continue;
        }
        $path = CONTENT_DIR . '/' . $file;
        $modified = @filemtime($path);
        $index[$title] = [
            'modified_at' => is_int($modified) ? $modified : 0,
            'redirect_target' => page_redirect_target_from_path($path),
        ];
    }
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    return $index;
}

function page_content_meta(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }

    $files = scandir(CONTENT_DIR);
    if ($files === false) {
        $cache = ['count' => 0, 'signature' => ''];
        return $cache;
    }

    $parts = [];
    $count = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with((string) $file, '.txt')) {
            continue;
        }
        $count++;
        $path = CONTENT_DIR . '/' . $file;
        $mtime = @filemtime($path);
        $size = @filesize($path);
        $parts[] = $file . ':' . (is_int($mtime) ? $mtime : 0) . ':' . (is_int($size) ? $size : 0);
    }
    sort($parts, SORT_STRING);
    $cache = [
        'count' => $count,
        'signature' => sha1(implode('|', $parts)),
    ];
    return $cache;
}

function page_index_save(array $index): bool
{
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    $meta = page_content_meta();
    $pages = [];
    foreach ($index as $title => $entry) {
        $redirectTarget = trim((string) ($entry['redirect_target'] ?? ''));
        $pages[$title] = [
            'modified_at' => isset($entry['modified_at']) ? (int) $entry['modified_at'] : 0,
            'redirect_target' => $redirectTarget !== '' ? $redirectTarget : null,
        ];
    }
    $payload = [
        'version' => PAGE_INDEX_FILE_VERSION,
        'generated_at' => time(),
        'meta' => $meta,
        'pages' => $pages,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return is_string($json) && file_put_atomic(page_index_path(), $json);
}

function page_index_refresh(): void
{
    $rebuilt = page_index_rebuild();
    page_index_save($rebuilt);
    page_index_load(reset: true);
}

function page_index_cached_pages(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $cacheSize = @filesize($path);
    $cacheLimit = max(1, (int) CACHE_FILE_MAX_BYTES);
    if (is_int($cacheSize) && $cacheSize > $cacheLimit) {
        wiki_log('page.index_file_oversize', [
            'path' => $path,
            'size' => $cacheSize,
            'limit' => $cacheLimit,
        ], 'warning');
        return null;
    }

    $contentDirMtime = @filemtime(CONTENT_DIR);
    $indexMtime = @filemtime($path);
    if (is_int($contentDirMtime) && is_int($indexMtime) && $contentDirMtime > $indexMtime) {
        return null;
    }

    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded) || !isset($decoded['pages']) || !is_array($decoded['pages'])) {
        return null;
    }
    if (($decoded['version'] ?? 0) !== PAGE_INDEX_FILE_VERSION) {
        return null;
    }

    $savedMeta = is_array($decoded['meta'] ?? null) ? $decoded['meta'] : null;
    $currentMeta = page_content_meta();
    if (
        !is_array($savedMeta)
        || !isset($savedMeta['count'], $savedMeta['signature'])
        || (int) $savedMeta['count'] !== (int) $currentMeta['count']
        || (string) $savedMeta['signature'] !== (string) $currentMeta['signature']
    ) {
        return null;
    }

    return $decoded['pages'];
}

function page_index_load(bool $reset = false, bool $hydrate = true, bool $resetMeta = true): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
        if ($resetMeta) {
            page_content_meta(reset: true);
        }
        if (!$hydrate) {
            return [];
        }
    }
    if (!$hydrate) {
        return [];
    }
    if (is_array($cache)) {
        return $cache;
    }

    $cachedPages = page_index_cached_pages(page_index_path());
    if (!is_array($cachedPages)) {
        $rebuilt = page_index_rebuild();
        if (!page_index_save($rebuilt)) {
            wiki_log('page.index_save_failed', ['path' => page_index_path()], 'error');
        }
        $cache = $rebuilt;
        return $cache;
    }

    $index = [];
    foreach ($cachedPages as $title => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryTitle = (string) $title;
        if ($entryTitle === '') {
            continue;
        }
        $index[$entryTitle] = [
            'modified_at' => isset($entry['modified_at']) ? (int) $entry['modified_at'] : 0,
            'redirect_target' => page_index_entry_redirect_target($entry),
        ];
    }
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    $cache = $index;
    return $cache;
}

function page_index_invalidate_cache(): void
{
    page_index_load(reset: true, hydrate: false, resetMeta: false);
}
