<?php

declare(strict_types=1);

const WIKI_LINK_PATTERN = '/\[\[([^|\[\]]*)(?:\s*\|([^\[\]]*))?\]\]/';
const STUB_MIN_LENGTH = 200;

function page_title_forbidden_chars(): array
{
    return ['\\', '<', '>', '[', ']', ':', ';', '"', '#', '%', '|', '?', '*'];
}

function page_title_input_pattern(): string
{
    static $pattern = null;
    if ($pattern !== null) {
        return $pattern;
    }
    $hex = array_map(
        static fn(string $char): string => sprintf('\\x%02X', ord($char)),
        page_title_forbidden_chars(),
    );
    $pattern = '[^' . implode('', $hex) . ']+';
    return $pattern;
}

function page_title_forbidden_chars_help_html(): string
{
    static $help = null;
    if ($help !== null) {
        return $help;
    }
    $parts = array_map(static function (string $char): string {
        if ($char === '\\') {
            return '<code>&#92;</code>';
        }
        return '<code>' . htmlspecialchars($char, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '</code>';
    }, page_title_forbidden_chars());

    $help = implode(' ', $parts);
    return $help;
}

function title_to_filename(string $title): string
{
    $name = str_replace('/', '--', $title);
    return basename($name) . '.txt';
}

function page_path(string $title): string
{
    return CONTENT_DIR . '/' . title_to_filename($title);
}

function sanitize_page_title(string $title): string
{
    $title = str_replace(page_title_forbidden_chars(), '', $title);
    $title = str_replace("\0", '', $title);
    
    $title = str_replace('_', ' ', $title);
    $title = preg_replace('/-{2,}/', '', $title) ?? $title;
    $title = preg_replace('/\/+/', '/', $title) ?? $title;
    $title = preg_replace('/\s+/', ' ', $title) ?? $title;
    $title = trim($title, " \t\n\r\0\x0B/.");

    if (mb_strlen($title) > PAGE_TITLE_MAX_LENGTH) {
        $title = mb_substr($title, 0, PAGE_TITLE_MAX_LENGTH);
        $title = trim($title, " \t\n\r\0\x0B/.");
    }

    return $title;
}

function page_title_uses_reserved_route_suffix(string $title): bool
{
    $normalized = trim($title);
    if ($normalized === '') {
        return false;
    }

    $lower = mb_strtolower($normalized, 'UTF-8');
    return preg_match('#/history/\d{14}$#', $lower) === 1
        || preg_match('#/(?:edit|history|backlinks)$#', $lower) === 1
        || preg_match('/\.(?:md|txt)$/', $lower) === 1;
}

function page_redirect_target_from_content(string $content): ?string
{
    if ($content === '') {
        return null;
    }
    $firstLine = strtok($content, "\n");
    if (!is_string($firstLine)) {
        return null;
    }
    if (preg_match('/^\(redirect:\s*(.*?)\s*\)\s*$/i', trim($firstLine), $m) !== 1) {
        return null;
    }
    $target = trim((string) $m[1]);
    return $target !== '' ? $target : null;
}

function page_title_fits_filename_limit(string $title): bool
{
    
    return strlen(title_to_filename($title)) <= 255;
}

function page_history_base(string $title): string
{
    $base = pathinfo(title_to_filename($title), PATHINFO_FILENAME);

    
    
    $reserved = 1 + 14 + 1 + USERNAME_MAX_LENGTH + 4;
    $maxBaseBytes = 255 - $reserved;
    if (strlen($base) <= $maxBaseBytes) {
        return $base;
    }

    $hash = substr(sha1($base), 0, 12);
    $prefixMaxBytes = max(1, $maxBaseBytes - 1 - strlen($hash));
    $prefix = mb_strcut($base, 0, $prefixMaxBytes, 'UTF-8');
    return $prefix . '-' . $hash;
}

function page_all(?int $limit = null, bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $cache = array_map(static fn(int|string $title): string => (string) $title, array_keys(page_index_load()));
        $cache = array_values(array_filter($cache, static fn(string $title): bool => $title !== ''));
    }
    if ($limit !== null) {
        return array_slice($cache, 0, $limit);
    }

    return $cache;
}

function page_recent(int $limit = 10, bool $withContent = false, bool $reset = false): array
{
    static $sorted = null;
    if ($reset) {
        $sorted = null;
    }
    if ($sorted === null) {
        $pageIndex = page_index_load();
        $indexEntries = [];
        foreach ($pageIndex as $title => $entry) {
            $indexEntries[] = [
                'title' => (string) $title,
                'modified_at' => (int) ($entry['modified_at'] ?? 0),
            ];
        }
        $redirectCache = [];
        $isRedirect = static function (string $title) use (&$redirectCache, $pageIndex): bool {
            if (array_key_exists($title, $redirectCache)) {
                return $redirectCache[$title];
            }
            $redirectCache[$title] = page_index_redirect_target_for($title, $pageIndex) !== null;
            return $redirectCache[$title];
        };
        usort($indexEntries, static function (array $a, array $b) use ($isRedirect): int {
            $modifiedCompare = $b['modified_at'] <=> $a['modified_at'];
            if ($modifiedCompare !== 0) {
                return $modifiedCompare;
            }
            $redirectCompare = ((int) $isRedirect((string) $a['title'])) <=> ((int) $isRedirect((string) $b['title']));
            if ($redirectCompare !== 0) {
                return $redirectCompare;
            }
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });
        $sorted = $indexEntries;
    }

    $pages = array_slice($sorted, 0, $limit);

    if ($withContent) {
        foreach ($pages as &$p) {
            $p['content'] = page_get($p['title']);
        }
    }

    return $pages;
}

function page_recent_without_redirects(int $limit = 10, bool $withContent = false): array
{
    $safeLimit = max(1, $limit);
    $pageIndex = page_index_load();
    $recentPageCount = count(page_all());
    $recent = $recentPageCount > 0 ? page_recent($recentPageCount, false) : [];
    $pages = [];

    foreach ($recent as $row) {
        $title = (string) ($row['title'] ?? '');
        if ($title === '') {
            continue;
        }
        if (page_index_redirect_target_for($title, $pageIndex) !== null) {
            continue;
        }

        if ($withContent) {
            $row['content'] = page_get($title);
        }
        $pages[] = $row;
        if (count($pages) >= $safeLimit) {
            break;
        }
    }

    return $pages;
}

function page_random(int $limit = 10): array
{
    $pageIndex = page_index_load();
    $pages = array_values(array_filter(
        page_all(),
        fn(string $title) => page_index_redirect_target_for($title, $pageIndex) === null,
    ));
    shuffle($pages);
    return array_map(
        fn(string $title) => ['title' => $title],
        array_slice($pages, 0, $limit),
    );
}

function page_backlinks(string $title, bool $reset = false): array
{
    static $cache = [];
    if ($reset) {
        $cache = [];
        return [];
    }
    if (isset($cache[$title])) {
        return $cache[$title];
    }

    $backlinks = [];
    foreach (page_related_index_load() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $pageTitle = (string) ($entry['title'] ?? '');
        if ($pageTitle === '' || $pageTitle === $title) {
            continue;
        }
        $links = isset($entry['links']) && is_array($entry['links']) ? array_map('strval', $entry['links']) : [];
        if (in_array($title, $links, true)) {
            $backlinks[] = $pageTitle;
        }
    }

    $cache[$title] = $backlinks;
    return $backlinks;
}

function page_parent(string $title): ?string
{
    $pos = strrpos($title, '/');
    if ($pos === false) {
        return null;
    }
    return substr($title, 0, $pos);
}

function page_children(string $title): array
{
    $pageIndex = page_index_load();
    $prefix = $title . '/';
    $children = [];
    foreach (page_all() as $page) {
        if (str_starts_with($page, $prefix)
            && !str_contains(substr($page, strlen($prefix)), '/')
            && page_index_redirect_target_for($page, $pageIndex) === null) {
            $children[] = $page;
        }
    }
    return $children;
}

function page_siblings(string $title): array
{
    $parent = page_parent($title);
    if ($parent === null) {
        return [];
    }
    return array_values(array_filter(
        page_children($parent),
        fn(string $page): bool => $page !== $title,
    ));
}

function tag_all(bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $tags = [];
    foreach (page_related_index_load() as $entry) {
        $entryTags = isset($entry['tags']) && is_array($entry['tags']) ? $entry['tags'] : [];
        foreach ($entryTags as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
    }

    $unique = array_unique($tags);
    sort($unique);
    $cache = $unique;
    return $cache;
}

function page_by_tag(string $tag): array
{
    $pages = [];
    foreach (page_related_index_load() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryTitle = (string) ($entry['title'] ?? '');
        if ($entryTitle === '') {
            continue;
        }
        $entryTags = isset($entry['tags']) && is_array($entry['tags']) ? array_map('strval', $entry['tags']) : [];
        if (in_array($tag, $entryTags, true)) {
            $pages[] = $entryTitle;
        }
    }

    return $pages;
}
