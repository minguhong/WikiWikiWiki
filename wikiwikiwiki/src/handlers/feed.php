<?php

declare(strict_types=1);

function rss_feed_content_type(): string
{
    return 'application/rss+xml; charset=utf-8';
}

function atom_feed_content_type(): string
{
    return 'application/atom+xml; charset=utf-8';
}

function json_feed_content_type(): string
{
    return 'application/feed+json; charset=utf-8';
}

function handle_robots(string $method, array $matches): void
{
    require_get_method($method);
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "\n";
    echo 'Sitemap: ' . base_url() . "/sitemap.xml\n";
}

function handle_sitemap(string $method, array $matches): void
{
    require_get_method($method);
    $pages = array_filter(page_all(), fn($p) => page_redirect_target($p) === null);

    header('Content-Type: application/xml; charset=utf-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($pages as $page) {
        $priority = $page === HOME_PAGE ? '1.0' : '0.5';
        $modifiedAt = page_last_modified_at($page) ?? time();
        echo '  <url>' . "\n";
        echo '    <loc>' . html(page_url($page)) . '</loc>' . "\n";
        echo '    <lastmod>' . date('c', $modifiedAt) . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>' . $priority . '</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    echo '</urlset>' . "\n";
}

function feed_recent_pages_without_redirects(int $limit): array
{
    return page_recent_without_redirects($limit, withContent: true);
}

function feed_updated_at(array $pages): int
{
    if ($pages === []) {
        return time();
    }
    $updatedAt = (int) max(array_map(static fn(array $page): int => (int) ($page['modified_at'] ?? 0), $pages));
    return $updatedAt > 0 ? $updatedAt : time();
}

function handle_rss(string $method, array $matches): void
{
    require_get_method($method);
    $pages = feed_recent_pages_without_redirects(FEED_LIMIT);
    $fullBaseUrl = base_url();

    header('Content-Type: ' . rss_feed_content_type());

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0">' . "\n";
    echo '  <channel>' . "\n";
    echo '    <title>' . html(WIKI_TITLE) . '</title>' . "\n";
    echo '    <link>' . html($fullBaseUrl) . '</link>' . "\n";
    echo '    <description>' . html(WIKI_DESCRIPTION) . '</description>' . "\n";
    echo '    <language>' . html(LANGUAGE) . '</language>' . "\n";
    echo '    <lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";
    foreach ($pages as $page) {
        $itemUrl = html(page_url($page['title']));
        echo '    <item>' . "\n";
        echo '      <title>' . html($page['title']) . '</title>' . "\n";
        echo '      <link>' . $itemUrl . '</link>' . "\n";
        echo '      <guid isPermaLink="true">' . $itemUrl . '</guid>' . "\n";
        echo '      <pubDate>' . date(DATE_RSS, $page['modified_at']) . '</pubDate>' . "\n";
        echo '      <description>' . html(extract_description($page['content'] ?? '')) . '</description>' . "\n";
        echo '    </item>' . "\n";
    }
    echo '  </channel>' . "\n";
    echo '</rss>' . "\n";
}

function handle_atom(string $method, array $matches): void
{
    require_get_method($method);
    $pages = feed_recent_pages_without_redirects(FEED_LIMIT);
    $fullBaseUrl = base_url();
    $feedUrl = $fullBaseUrl . '/atom.xml';
    $updatedAt = feed_updated_at($pages);

    header('Content-Type: ' . atom_feed_content_type());

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="' . html(LANGUAGE) . '">' . "\n";
    echo '  <title>' . html(WIKI_TITLE) . '</title>' . "\n";
    echo '  <id>' . html($feedUrl) . '</id>' . "\n";
    echo '  <updated>' . date('c', $updatedAt) . '</updated>' . "\n";
    echo '  <link rel="self" type="application/atom+xml" href="' . html($feedUrl) . '"/>' . "\n";
    echo '  <link href="' . html($fullBaseUrl) . '"/>' . "\n";
    echo '  <subtitle>' . html(WIKI_DESCRIPTION) . '</subtitle>' . "\n";
    echo '  <author><name>' . html(WIKI_TITLE) . '</name></author>' . "\n";
    foreach ($pages as $page) {
        $itemUrl = page_url($page['title']);
        echo '  <entry>' . "\n";
        echo '    <title>' . html((string) $page['title']) . '</title>' . "\n";
        echo '    <id>' . html($itemUrl) . '</id>' . "\n";
        echo '    <link href="' . html($itemUrl) . '"/>' . "\n";
        echo '    <updated>' . date('c', (int) $page['modified_at']) . '</updated>' . "\n";
        echo '    <summary>' . html(extract_description((string) ($page['content'] ?? ''))) . '</summary>' . "\n";
        echo '  </entry>' . "\n";
    }
    echo '</feed>' . "\n";
}

function handle_json_feed(string $method, array $matches): void
{
    require_get_method($method);
    $pages = feed_recent_pages_without_redirects(FEED_LIMIT);
    $fullBaseUrl = base_url();
    $feedUrl = $fullBaseUrl . '/feed.json';

    $items = array_map(static function (array $page): array {
        $itemUrl = page_url((string) ($page['title'] ?? ''));
        return [
            'id' => $itemUrl,
            'url' => $itemUrl,
            'title' => (string) ($page['title'] ?? ''),
            'content_text' => extract_description((string) ($page['content'] ?? '')),
            'date_modified' => date('c', (int) ($page['modified_at'] ?? time())),
        ];
    }, $pages);

    $payload = [
        'version' => 'https://jsonfeed.org/version/1.1',
        'title' => (string) WIKI_TITLE,
        'home_page_url' => $fullBaseUrl,
        'feed_url' => $feedUrl,
        'description' => (string) WIKI_DESCRIPTION,
        'language' => (string) LANGUAGE,
        'authors' => [['name' => (string) WIKI_TITLE]],
        'items' => $items,
    ];

    header('Content-Type: ' . json_feed_content_type());

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        http_response_code(500);
        echo '{"error":"json_encode_failed"}';
        return;
    }

    echo $json . "\n";
}

function handle_llms(string $method, array $matches): void
{
    require_get_method($method);
    $allPages = page_all();
    $recentPages = page_recent_without_redirects(FEED_LIMIT);
    $allTags = tag_all();
    $fullBaseUrl = base_url();

    header('Content-Type: text/plain; charset=utf-8');

    echo '# ' . WIKI_TITLE . "\n\n";
    echo '> ' . WIKI_DESCRIPTION . "\n\n";
    echo '## ' . t('title.recent') . "\n\n";

    foreach ($recentPages as $page) {
        echo '- [' . $page['title'] . '](' . page_url($page['title'], '.md') . ')';
        echo ': ' . date('Y-m-d H:i:s', $page['modified_at']) . "\n";
    }
    echo "\n";

    echo '## ' . t('title.all_documents') . "\n\n";
    foreach ($allPages as $page) {
        if (page_redirect_target($page) !== null) {
            continue;
        }
        echo '- [' . $page . '](' . page_url($page, '.md') . ')' . "\n";
    }

    if ($allTags !== []) {
        echo "\n";
        echo '## ' . t('title.all_tags') . "\n\n";
        foreach ($allTags as $tag) {
            echo '- [#' . $tag . '](' . $fullBaseUrl . '/tags/' . rawurlencode($tag) . ')' . "\n";
        }
    }
}

function handle_llms_full(string $method, array $matches): void
{
    require_get_method($method);
    $allPages = page_all();

    header('Content-Type: text/plain; charset=utf-8');
    echo '# ' . WIKI_TITLE . "\n\n";
    echo '> ' . WIKI_DESCRIPTION . "\n\n";
    echo '## ' . t('title.all_documents') . "\n\n";
    echo "---\n\n";
    foreach ($allPages as $page) {
        if (page_redirect_target($page) !== null) {
            continue;
        }
        $content = rtrim(page_get($page) ?? '');
        echo "# $page\n\n";
        echo $content;
        echo "\n\n---\n\n";
    }
}
