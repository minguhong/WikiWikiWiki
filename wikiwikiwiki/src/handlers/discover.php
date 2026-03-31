<?php

declare(strict_types=1);

function handle_all_pages(string $method, array $matches): void
{
    require_get_method($method);
    $pages = page_all();
    $totalDocumentCount = count($pages);
    $totalPaginationPageCount = max(1, (int) ceil($totalDocumentCount / ALL_PAGES_LIMIT));

    $requestedPage = (int) request_string($_GET, 'page', '1');
    if ($requestedPage < 1) {
        $requestedPage = 1;
    }
    $currentPage = min($requestedPage, $totalPaginationPageCount);
    $offset = ($currentPage - 1) * ALL_PAGES_LIMIT;
    $pageItems = array_slice($pages, $offset, ALL_PAGES_LIMIT);

    $letters = [];
    $redirects = [];
    foreach ($pageItems as $page) {
        $first = mb_substr($page, 0, 1);
        $letters[$first][] = $page;
        $target = page_redirect_target($page);
        if ($target !== null) {
            $redirects[$page] = $target;
        }
    }

    $allBaseUrl = url('/all');
    $pageUrl = static function (int $page) use ($allBaseUrl): string {
        return $page <= 1 ? $allBaseUrl : ($allBaseUrl . '?page=' . $page);
    };

    render('all', [
        'allPages' => $pageItems,
        'totalDocumentCount' => $totalDocumentCount,
        'letters' => $letters,
        'redirects' => $redirects,
        'currentPage' => $currentPage,
        'totalPaginationPageCount' => $totalPaginationPageCount,
        'prevPageUrl' => $currentPage > 1 ? $pageUrl($currentPage - 1) : null,
        'nextPageUrl' => $currentPage < $totalPaginationPageCount ? $pageUrl($currentPage + 1) : null,
    ]);
}

function handle_discover(string $method, array $matches): void
{
    require_get_method($method);
    $discoverAllLimit = max(1, PAGE_LIST_LIMIT - 1);

    render('discover', [
        'recentPages' => page_recent_without_redirects(PAGE_LIST_LIMIT),
        'randomPages' => page_random(PAGE_LIST_LIMIT),
        'stubPages' => page_stub(PAGE_LIST_LIMIT),
        'wantedPages' => page_wanted(PAGE_LIST_LIMIT),
        'orphanedPages' => page_orphaned(PAGE_LIST_LIMIT),
        'redirectPages' => page_redirects(PAGE_LIST_LIMIT),
        'allPages' => page_all($discoverAllLimit),
        'allTags' => tag_all(),
    ]);
}

function handle_tag_pages(string $method, array $matches): void
{
    require_get_method($method);
    $tag = url_to_page_title(rawurldecode((string) $matches[1]));
    if ($tag === '') {
        render_error_page(400, t('error.invalid.title_label'), t('error.invalid.title_message'));
    }
    $pages = page_by_tag($tag);
    $allTags = tag_all();
    render('tags', [
        'pageTitle' => '#' . $tag,
        'tag' => $tag,
        'tagPages' => $pages,
        'allTags' => $allTags,
    ]);
}
