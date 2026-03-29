<?php

declare(strict_types=1);

function page_search_index_path(): string
{
    return CACHE_DIR . '/search.json';
}

function page_search_normalize_text(string $text): string
{
    $customTagPattern = '/\((?:video|iframe|image|codepen|map|arena|wikipedia|recent|wanted|random):[^\r\n]*\)/iu';
    $redirectPattern = '/^\(redirect:\s*.*?\)\s*$/mi';
    $applyRegex = static function (string $pattern, string $replacement = '') use (&$text): void {
        $text = preg_replace($pattern, $replacement, $text) ?? $text;
    };

    $applyRegex($redirectPattern);
    $applyRegex('/!\[\[[^\[\]\r\n]+\]\]/u');
    $applyRegex('/<!--[\s\S]*?-->/');
    $applyRegex($customTagPattern);
    $text = strip_code_blocks($text);
    $applyRegex('/!\[([^\]]*)\]\([^)]+\)/');
    $applyRegex('/\[([^\]]+)\]\([^)]+\)/', '$1');
    $applyRegex('/\[\[([^\]|]+)(?:\|[^\]]+)?\]\]/', '$1');
    $text = strip_tags($text);
    $applyRegex('/^#{1,6}\s+/m');
    $applyRegex('/[*_~]+/');
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

function page_search_index_entry(string $title, ?string $content = null): array
{
    if ($content === null) {
        $content = page_get($title) ?? '';
    }
    $normalized = page_search_normalize_text($content);
    return [
        'title' => $title,
        'content' => $normalized,
        'preview' => markdown_to_preview_text(extract_description($content, 120)),
    ];
}

function page_search_index_rebuild(): array
{
    $allIndex = page_index_load();
    $searchIndex = [];
    foreach ($allIndex as $title => $entry) {
        $entryTitle = (string) $title;
        if ($entryTitle === '') {
            continue;
        }
        $searchIndex[$entryTitle] = page_search_index_entry($entryTitle);
    }
    ksort($searchIndex, SORT_NATURAL | SORT_FLAG_CASE);
    return $searchIndex;
}

function page_search_index_save(array $index): bool
{
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    $meta = page_content_meta();
    $payload = [
        'version' => PAGE_INDEX_FILE_VERSION,
        'generated_at' => time(),
        'meta' => $meta,
        'pages' => $index,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return is_string($json) && file_put_atomic(page_search_index_path(), $json);
}

function page_search_index_load(bool $reset = false, bool $hydrate = true, bool $resetMeta = true): array
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

    $cachedPages = page_index_cached_pages(page_search_index_path());
    if (!is_array($cachedPages)) {
        $rebuilt = page_search_index_rebuild();
        if (!page_search_index_save($rebuilt)) {
            wiki_log('page.search_index_save_failed', ['path' => page_search_index_path()], 'error');
        }
        $cache = $rebuilt;
        return $cache;
    }

    $index = [];
    foreach ($cachedPages as $title => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryTitle = (string) ($entry['title'] ?? $title);
        if ($entryTitle === '') {
            continue;
        }
        $searchText = (string) ($entry['content'] ?? '');
        $index[$entryTitle] = [
            'title' => $entryTitle,
            'content' => $searchText,
            'preview' => (string) ($entry['preview'] ?? ''),
        ];
    }
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    $cache = $index;
    return $cache;
}

function page_search_index_invalidate_cache(): void
{
    page_search_index_load(reset: true, hydrate: false, resetMeta: false);
}

function search_snippet(string $text, string $query, int $window = 60): string
{
    
    
    

    $pos = mb_stripos($text, $query);
    if ($pos === false) {
        return '';
    }

    $start = max(0, $pos - $window);
    $end = min(mb_strlen($text), $pos + mb_strlen($query) + $window);

    if ($start > 0) {
        $spacePos = mb_strpos($text, ' ', $start);
        $start = ($spacePos !== false) ? $spacePos + 1 : $start;
    }
    if ($end < mb_strlen($text)) {
        $spacePos = mb_strrpos(mb_substr($text, 0, $end), ' ');
        $end = ($spacePos !== false && $spacePos > $start) ? $spacePos : $end;
    }

    $snippet = mb_substr($text, $start, $end - $start);
    $prefix = $start > 0 ? '…' : '';
    $suffix = $end < mb_strlen($text) ? '…' : '';

    $escaped = htmlspecialchars($snippet, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $marked = preg_replace(
        '/' . preg_quote(htmlspecialchars($query, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '/') . '/iu',
        '<mark>$0</mark>',
        $escaped,
    );

    return $prefix . $marked . $suffix;
}

function page_search_scopes(): array
{
    return ['all', 'title', 'content'];
}

function page_search_normalize_scope(string $scope): string
{
    $scope = strtolower(trim($scope));
    return in_array($scope, page_search_scopes(), true) ? $scope : 'all';
}

function page_search(string $query, string $scope = 'all'): array
{
    $query = page_search_normalize_query($query);
    $scope = page_search_normalize_scope($scope);
    if ($query === '') {
        return [];
    }

    $results = [];
    $queryLower = mb_strtolower($query);
    $includeTitle = $scope !== 'content';
    $includeContent = $scope !== 'title';

    foreach (page_search_index_load() as $entry) {
        $title = (string) $entry['title'];
        $score = 0;
        $snippet = '';
        $titleLower = mb_strtolower($title);

        if ($includeTitle) {
            if ($titleLower === $queryLower) {
                $score += 120;
            } elseif (str_starts_with($titleLower, $queryLower)) {
                $score += 80;
            } elseif (str_contains($titleLower, $queryLower)) {
                $score += 40;
            }
        }

        if ($includeContent) {
            $searchText = (string) ($entry['content'] ?? '');
            if ($searchText !== '') {
                $searchTextLower = mb_strtolower($searchText);
                $contentMatches = substr_count($searchTextLower, $queryLower);
                if ($contentMatches > 0) {
                    $score += min($contentMatches * 5, 30);
                    $snippet = search_snippet($searchText, $query);
                }
                if ($snippet === '') {
                    $snippet = html((string) $entry['preview']);
                }
            }
        }

        if ($score > 0) {
            $results[] = [
                'title' => $title,
                'score' => $score,
                'snippet' => $snippet,
            ];
        }
    }

    usort($results, function ($a, $b): int {
        $scoreCompare = $b['score'] <=> $a['score'];
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }
        return strcasecmp($a['title'], $b['title']);
    });

    return $results;
}

function page_search_normalize_query(string $query): string
{
    $query = trim((string) preg_replace('/\s+/u', ' ', $query));
    if ($query === '') {
        return '';
    }
    if (mb_strlen($query) > SEARCH_QUERY_MAX_LENGTH) {
        return mb_substr($query, 0, SEARCH_QUERY_MAX_LENGTH);
    }
    return $query;
}
