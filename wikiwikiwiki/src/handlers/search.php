<?php

declare(strict_types=1);

function search_scope_options(): array
{
    return [
        'all' => t('search.scope.all'),
        'title' => t('search.scope.title'),
        'content' => t('search.scope.content'),
    ];
}

function handle_search(string $method, array $matches): void
{
    require_get_method($method);
    $query = page_search_normalize_query(request_string($_GET, 'q'));
    $scope = page_search_normalize_scope(request_string($_GET, 'scope'));
    $results = $query !== '' ? page_search($query, $scope) : [];
    $viewResults = array_map(static function (array $result): array {
        $title = (string) ($result['title'] ?? '');
        $parentPath = null;
        $redirectTarget = '';
        $redirectTargetRaw = page_redirect_target($title);
        if ($redirectTargetRaw !== null && preg_match('/^https?:\/\//i', $redirectTargetRaw) !== 1) {
            $redirectTarget = normalize_internal_redirect_title($redirectTargetRaw) ?? '';
        }
        if (str_contains($title, '/')) {
            $lastSlashPos = strrpos($title, '/');
            if ($lastSlashPos !== false) {
                $parentPath = substr($title, 0, $lastSlashPos);
            }
        }
        $result['parentPath'] = $parentPath;
        $result['redirectTarget'] = $redirectTarget;
        return $result;
    }, $results);
    $hasQuery = $query !== '';
    $hasResults = $viewResults !== [];
    $searchAllLimit = max(1, PAGE_LIST_LIMIT - 1);
    $randomPages = ($hasQuery && !$hasResults) ? page_random(PAGE_LIST_LIMIT) : [];
    $allPages = !$hasQuery ? page_all($searchAllLimit) : [];
    $recentPages = !$hasQuery ? page_recent_without_redirects(20) : [];
    render('search', [
        'pageTitle' => $hasQuery ? t('title.search') . ': ' . $query : t('title.search'),
        'query' => $query,
        'scope' => $scope,
        'scopeOptions' => search_scope_options(),
        'results' => $viewResults,
        'resultCount' => count($viewResults),
        'hasQuery' => $hasQuery,
        'hasResults' => $hasResults,
        'randomPages' => $randomPages,
        'allPages' => $allPages,
        'recentPages' => $recentPages,
    ]);
}
