<?php

declare(strict_types=1);

function page_related_index_path(): string
{
    return CACHE_DIR . '/related.json';
}

function page_related_extract(string $content): array
{
    $protected = strip_code_blocks($content);
    [$protected] = preserve_literal_placeholder_tokens($protected);

    $parserPlaceholders = [];
    $parserCounter = 0;
    $protected = preserve_markdown_links_and_images($protected, $parserPlaceholders, $parserCounter);
    $protected = preserve_inline_code_spans($protected, $parserPlaceholders, $parserCounter);

    $links = [];
    $appendLink = static function (string $rawTitle) use (&$links): void {
        $title = sanitize_page_title(trim($rawTitle));
        if ($title !== '') {
            $links[] = $title;
        }
    };

    if (preg_match_all(WIKI_LINK_PATTERN, $protected, $matches)) {
        foreach ($matches[1] as $linked) {
            $appendLink((string) $linked);
        }
    }

    if (preg_match_all('/!\[\[([^\[\]\r\n]+)\]\]/u', $protected, $transclusions)) {
        foreach ($transclusions[1] as $included) {
            $appendLink((string) $included);
        }
    }

    $links = array_values(array_unique($links));

    
    
    $withoutWikiLinkSyntax = preg_replace('/!?\[\[[^\[\]\r\n]+\]\]/u', ' ', $protected) ?? $protected;

    $tags = [];
    if (preg_match_all('/(?:^|(?<=\s))#([\p{L}\p{N}_-]+)/u', $withoutWikiLinkSyntax, $tagMatches)) {
        foreach ($tagMatches[1] as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
    }
    $tags = array_values(array_unique($tags));

    return [
        'links' => $links,
        'tags' => $tags,
    ];
}

function page_related_index_entry(string $title, ?string $content = null): array
{
    if ($content === null) {
        $content = page_get($title) ?? '';
    }
    $extracted = page_related_extract($content);
    return [
        'title' => $title,
        'links' => $extracted['links'],
        'tags' => $extracted['tags'],
    ];
}

function page_related_index_rebuild(): array
{
    $allIndex = page_index_load();
    $relatedIndex = [];
    foreach ($allIndex as $title => $entry) {
        $entryTitle = (string) $title;
        if ($entryTitle === '') {
            continue;
        }
        $relatedIndex[$entryTitle] = page_related_index_entry($entryTitle);
    }
    ksort($relatedIndex, SORT_NATURAL | SORT_FLAG_CASE);
    return $relatedIndex;
}

function page_related_index_save(array $index): bool
{
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    $contentMeta = page_content_meta();
    $payload = [
        'version' => PAGE_INDEX_FILE_VERSION,
        'generated_at' => time(),
        'meta' => $contentMeta,
        'pages' => $index,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    return is_string($json) && file_put_atomic(page_related_index_path(), $json);
}

function page_related_index_refresh(): void
{
    $rebuilt = page_related_index_rebuild();
    page_related_index_save($rebuilt);
    page_related_index_load(reset: true);
}

function page_related_index_normalize_cached_pages(array $cachedPages): array
{
    $normalizeStringList = static fn(mixed $value): array => is_array($value)
        ? array_values(array_filter(array_map('strval', $value), static fn(string $item): bool => $item !== ''))
        : [];

    $index = [];
    foreach ($cachedPages as $title => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryTitle = (string) ($entry['title'] ?? $title);
        if ($entryTitle === '') {
            continue;
        }
        $links = $normalizeStringList($entry['links'] ?? null);
        $tags = $normalizeStringList($entry['tags'] ?? null);
        $index[$entryTitle] = [
            'title' => $entryTitle,
            'links' => array_values(array_unique($links)),
            'tags' => array_values(array_unique($tags)),
        ];
    }
    ksort($index, SORT_NATURAL | SORT_FLAG_CASE);
    return $index;
}

function page_related_index_load(bool $reset = false, bool $hydrate = true, bool $resetMeta = true): array
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

    $cachedPages = page_index_cached_pages(page_related_index_path());
    if (!is_array($cachedPages)) {
        $locked = page_with_content_read_lock(static function (): array {
            $cachedPagesLocked = page_index_cached_pages(page_related_index_path());
            if (is_array($cachedPagesLocked)) {
                return page_related_index_normalize_cached_pages($cachedPagesLocked);
            }

            $rebuilt = page_related_index_rebuild();
            if (!page_related_index_save($rebuilt)) {
                wiki_log('page.related_index_save_failed', ['path' => page_related_index_path()], 'error');
            }
            return $rebuilt;
        }, null);

        if (!is_array($locked)) {
            wiki_log('page.related_index_lock_fallback_rebuild', ['path' => page_related_index_path()], 'warning');
            $cache = page_related_index_rebuild();
            return $cache;
        }

        $cache = $locked;
        return $cache;
    }

    $cache = page_related_index_normalize_cached_pages($cachedPages);
    return $cache;
}

function page_related_index_invalidate_cache(): void
{
    page_related_index_load(reset: true, hydrate: false, resetMeta: false);
}
