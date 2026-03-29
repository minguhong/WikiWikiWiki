<?php

declare(strict_types=1);

function page_wanted(?int $limit = null, bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $allPages = page_all();
        $existingSet = array_fill_keys($allPages, true);
        $relatedIndex = page_related_index_load();
        $wantedByTitle = [];

        foreach ($allPages as $page) {
            $entry = $relatedIndex[$page] ?? null;
            if (!is_array($entry) || !isset($entry['links']) || !is_array($entry['links'])) {
                continue;
            }
            foreach ($entry['links'] as $linked) {
                $linked = trim((string) $linked);
                if ($linked !== '' && !isset($existingSet[$linked]) && !isset($wantedByTitle[$linked])) {
                    $wantedByTitle[$linked] = ['title' => $linked];
                }
            }
        }

        $cache = array_values($wantedByTitle);
    }

    if ($limit !== null) {
        return array_slice($cache, 0, $limit);
    }

    return $cache;
}

function page_orphaned(?int $limit = null, bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $allPages = page_all();
        $existingSet = array_fill_keys($allPages, true);
        $pageIndex = page_index_load();
        $hasChildren = [];
        foreach ($allPages as $page) {
            $pos = strrpos($page, '/');
            if ($pos === false) {
                continue;
            }
            $parent = substr($page, 0, $pos);
            if (isset($existingSet[$parent])) {
                $hasChildren[$parent] = true;
            }
        }

        $hasBacklinks = [];
        foreach (page_related_index_load() as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sourceTitle = (string) ($entry['title'] ?? '');
            if ($sourceTitle === '') {
                continue;
            }
            $links = isset($entry['links']) && is_array($entry['links']) ? $entry['links'] : [];
            foreach ($links as $linked) {
                $linkedTitle = trim((string) $linked);
                if ($linkedTitle === '' || $linkedTitle === $sourceTitle) {
                    continue;
                }
                if (isset($existingSet[$linkedTitle])) {
                    $hasBacklinks[$linkedTitle] = true;
                }
            }
        }

        $orphaned = [];
        foreach ($allPages as $page) {
            if (page_index_redirect_target_for($page, $pageIndex) !== null) {
                continue;
            }
            $pos = strrpos($page, '/');
            $hasParent = false;
            if ($pos !== false) {
                $parent = substr($page, 0, $pos);
                $hasParent = isset($existingSet[$parent]);
            }
            if ($hasParent || isset($hasChildren[$page]) || isset($hasBacklinks[$page])) {
                continue;
            }
            $orphaned[] = $page;
        }

        $cache = $orphaned;
    }

    if ($limit !== null) {
        return array_slice($cache, 0, $limit);
    }

    return $cache;
}

function page_redirects(?int $limit = null, bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $rows = [];
        $pageIndex = page_index_load();

        foreach (page_all() as $title) {
            $target = page_index_redirect_target_for($title, $pageIndex);
            if ($target === null) {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'target' => $target,
                'modified_at' => (int) (($pageIndex[$title]['modified_at'] ?? 0)),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $modifiedCompare = (int) $b['modified_at'] <=> (int) $a['modified_at'];
            if ($modifiedCompare !== 0) {
                return $modifiedCompare;
            }
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        $cache = array_values(array_map(
            static fn(array $row): array => [
                'title' => (string) $row['title'],
                'target' => (string) $row['target'],
            ],
            $rows,
        ));
    }

    if ($limit !== null) {
        return array_slice($cache, 0, $limit);
    }

    return $cache;
}

function page_stub(?int $limit = null, bool $reset = false): array
{
    static $cache = null;
    if ($reset) {
        $cache = null;
    }
    if ($cache === null) {
        $allPages = page_all();
        $relatedIndex = page_related_index_load();
        $pageIndex = page_index_load();
        $searchIndex = page_search_index_load();
        $rows = [];

        foreach ($allPages as $title) {
            if (page_index_redirect_target_for($title, $pageIndex) !== null) {
                continue;
            }

            $searchText = '';
            $searchEntry = $searchIndex[$title] ?? null;
            if (is_array($searchEntry)) {
                $searchText = (string) ($searchEntry['content'] ?? '');
            }
            if ($searchText === '') {
                $searchText = page_search_normalize_text(page_get($title) ?? '');
            }

            $bodyLength = mb_strlen($searchText);

            $relatedEntry = $relatedIndex[$title] ?? null;
            $links = isset($relatedEntry['links']) && is_array($relatedEntry['links']) ? $relatedEntry['links'] : [];
            $tags = isset($relatedEntry['tags']) && is_array($relatedEntry['tags']) ? $relatedEntry['tags'] : [];

            $isStub = $bodyLength < STUB_MIN_LENGTH || $links === [] || $tags === [];
            if (!$isStub) {
                continue;
            }

            $rows[] = [
                'title' => $title,
                'modified_at' => (int) (($pageIndex[$title]['modified_at'] ?? 0)),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $modifiedCompare = $a['modified_at'] <=> $b['modified_at'];
            if ($modifiedCompare !== 0) {
                return $modifiedCompare;
            }
            return strcasecmp((string) $a['title'], (string) $b['title']);
        });

        $cache = array_values(array_map(
            static fn(array $row): array => ['title' => (string) ($row['title'] ?? '')],
            array_filter($rows, static fn(array $row): bool => (string) ($row['title'] ?? '') !== ''),
        ));
    }

    if ($limit !== null) {
        return array_slice($cache, 0, $limit);
    }

    return $cache;
}
