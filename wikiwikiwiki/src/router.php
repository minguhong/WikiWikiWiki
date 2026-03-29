<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function any(string $pattern, string $handler): void
    {
        if (!function_exists($handler)) {
            throw new InvalidArgumentException('Unknown route handler: ' . $handler);
        }
        $this->routes[] = [$pattern, $handler];
    }

    
    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes as [$pattern, $handler]) {
            if (str_starts_with($pattern, '#')) {
                if (preg_match($pattern, $uri, $matches)) {
                    $handler($method, $matches);
                    return;
                }
            } elseif ($pattern === $uri) {
                $handler($method, []);
                return;
            }
        }

        render_404_page($uri);
    }
}

require_once __DIR__ . '/handlers/auth.php';
require_once __DIR__ . '/handlers/wiki.php';
require_once __DIR__ . '/handlers/history.php';
require_once __DIR__ . '/handlers/search.php';
require_once __DIR__ . '/handlers/discover.php';
require_once __DIR__ . '/handlers/settings.php';
require_once __DIR__ . '/handlers/feed.php';
require_once __DIR__ . '/handlers/media.php';
require_once __DIR__ . '/handlers/api.php';

$requestUri = request_string($_SERVER, 'REQUEST_URI', '/');

$uriPath = explode('?', $requestUri, 2)[0];
$uri = rawurldecode('/' . ltrim($uriPath, '/'));
$basePath = base_path();
$canonicalRedirect = configured_base_url_redirect_target(
    $requestUri,
    $basePath,
    (string) BASE_URL,
    detected_base_url(),
);
if ($canonicalRedirect !== null) {
    redirect($canonicalRedirect, 301);
}
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
}
if ($uri === '') {
    $uri = '/';
}
$method = request_string($_SERVER, 'REQUEST_METHOD', 'GET');
$viewPath = WIKI_ROUTE_PATH;

if (user_count() === 0 && $uri !== '/install') {
    redirect(url('/install'));
}

$router = new Router();

$router->any('/', 'handle_home');

$router->any('/new', 'handle_new_page');
$router->any('/all', 'handle_all_pages');
$router->any('/discover', 'handle_discover');
$router->any('/random', 'handle_random');
$router->any('/search', 'handle_search');

$router->any('/login', 'handle_login');
$router->any('/logout', 'handle_logout');
$router->any('/install', 'handle_install');
$router->any('/register', 'handle_register');
$router->any('/account', 'handle_account');

$router->any('/settings', 'handle_settings');

$router->any('/favicon.svg', 'handle_favicon_svg');
$router->any('/og-image.png', 'handle_og_image_png');
$router->any('/robots.txt', 'handle_robots');
$router->any('/sitemap.xml', 'handle_sitemap');
$router->any('/rss.xml', 'handle_rss');
$router->any('/atom.xml', 'handle_atom');
$router->any('/feed.json', 'handle_json_feed');
$router->any('/llms.txt', 'handle_llms');
$router->any('/llms-full.txt', 'handle_llms_full');

$router->any('/api/all', 'handle_api_all');
$router->any('#^/api/wiki/(.+)$#', 'handle_api_wiki');

$router->any('#^/tags/(.+)$#', 'handle_tag_pages');

$router->any("#^/{$viewPath}/(.+)/edit\$#", 'handle_wiki_edit');
$router->any("#^/{$viewPath}/(.+)/history/(\\d{14})\$#", 'handle_history_version');
$router->any("#^/{$viewPath}/(.+)/history\$#", 'handle_history_list');
$router->any("#^/{$viewPath}/(.+)/backlinks\$#", 'handle_wiki_backlinks');
$router->any("#^/{$viewPath}/(.+)\\.(md|txt)\$#", 'handle_wiki_raw');
$router->any("#^/{$viewPath}/(.+)\$#", 'handle_wiki_page');

$router->dispatch($method, $uri);
