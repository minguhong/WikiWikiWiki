<?php

declare(strict_types=1);

function handle_api_all(string $method, array $matches): void
{
    require_api_methods($method, ['GET']);
    if (str_contains(request_string($_SERVER, 'REQUEST_URI'), '?')) {
        respond_json_error('invalid_query', 'Query parameters are not allowed', 400);
    }

    $index = page_index_load();
    $pages = [];
    foreach ($index as $title => $entry) {
        $redirectTarget = page_index_entry_redirect_target($entry);
        $pages[] = [
            'title' => (string) $title,
            'modified_at' => (int) ($entry['modified_at'] ?? 0),
            'url' => page_url((string) $title),
            'redirect_target' => $redirectTarget,
        ];
    }

    respond_json(['pages' => $pages]);
}

function handle_api_wiki(string $method, array $matches): void
{
    require_api_methods($method, ['GET']);
    $title = api_wiki_title_or_400($matches);

    $content = page_get($title);
    if ($content === null) {
        respond_not_found_json();
    }

    respond_json([
        'title' => $title,
        'content' => $content,
        'modified_at' => page_last_modified_at($title),
        'url' => page_url($title),
        'redirect_target' => page_redirect_target($title),
    ]);
}

function api_wiki_title_or_400(array $matches): string
{
    $title = url_to_page_title(rawurldecode(request_string($matches, 1)));
    if ($title === '') {
        respond_json_error('invalid_title', 'Invalid title', 400);
    }
    return $title;
}
