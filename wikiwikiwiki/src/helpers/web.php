<?php

declare(strict_types=1);

function request_string(array $source, int|string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return is_string($value) ? $value : (string) $value;
}

function request_trimmed(array $source, int|string $key, string $default = ''): string
{
    return trim(request_string($source, $key, $default));
}

function configured_base_url_redirect_target(
    string $requestUri,
    string $basePath,
    string $configuredBaseUrl,
    string $detectedBaseUrl,
): ?string {
    $configured = rtrim(trim($configuredBaseUrl), '/');
    if ($configured === '') {
        return null;
    }

    $detected = rtrim(trim($detectedBaseUrl), '/');
    if ($detected !== '' && $detected === $configured) {
        return null;
    }

    $parts = explode('?', $requestUri, 2);
    $rawPath = request_string($parts, 0, '/');
    $query = request_string($parts, 1, '');

    $path = trim($rawPath);
    if ($path === '') {
        $path = '/';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . ltrim($path, '/');
    }

    $normalizedBasePath = rtrim($basePath, '/');
    if ($normalizedBasePath === '/') {
        $normalizedBasePath = '';
    }
    if ($normalizedBasePath !== '') {
        if ($path === $normalizedBasePath) {
            $path = '/';
        } elseif (str_starts_with($path, $normalizedBasePath . '/')) {
            $path = substr($path, strlen($normalizedBasePath));
        }
    }
    if ($path === '') {
        $path = '/';
    }

    $target = $configured . $path;
    if ($query !== '') {
        $target .= '?' . $query;
    }

    return $target;
}

function normalize_return_path(string $path): string
{
    $path = trim($path);
    if ($path !== '' && !preg_match('/^\/[^\/\\\\]/', $path)) {
        return '';
    }
    return $path;
}

function require_get_method(string $method): void
{
    if ($method === 'GET') {
        return;
    }
    header('Allow: GET');
    render_error_page(405, '405', t('error.request.method_not_allowed'));
}

function require_api_methods(string $method, array $allowedMethods): void
{
    $allowed = array_values(array_filter(array_map(
        static fn($item): string => strtoupper(trim((string) $item)),
        $allowedMethods,
    ), static fn(string $item): bool => $item !== ''));
    if ($allowed === []) {
        $allowed = ['GET'];
    }

    if (in_array($method, $allowed, true)) {
        return;
    }

    $allowedHeader = implode(', ', $allowed);
    header('Allow: ' . $allowedHeader);
    respond_json_error('method_not_allowed', $allowedHeader . ' only', 405);
}

function render_error_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    render('error', [
        'title' => $title,
        'pageTitle' => $title,
        'message' => $message,
    ]);
    exit;
}

function render_404_error(): never
{
    render_error_page(404, '404', t('message.page.not_found'));
}

function render_404_page(string $page): never
{
    $requestedPage = ltrim(rawurldecode($page), '/');
    http_response_code(404);
    render('404', [
        'page' => $page,
        'requestedPage' => $requestedPage,
        'randomPages' => page_random(PAGE_LIST_LIMIT),
    ]);
    exit;
}

function route_title_or_400(array $matches, int $index = 1): string
{
    $raw = request_string($matches, $index);
    $title = url_to_page_title(rawurldecode($raw));
    if ($title === '') {
        render_error_page(400, t('error.invalid.title_label'), t('error.invalid.title_message'));
    }
    return $title;
}

function respond_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        wiki_log('response.json_encode_failed', ['status' => $status], 'error');
        http_response_code(500);
        $json = '{"error":{"code":"json_encode_failed","message":"JSON encoding failed"}}';
    }
    echo $json;
    exit;
}

function respond_not_found_json(): never
{
    respond_json_error('not_found', 'Not found', 404);
}

function respond_json_error(string $code, string $message, int $status): never
{
    respond_json([
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $status);
}

function redirect(string $url, int $code = 302): never
{
    $url = str_replace(["\r", "\n"], '', $url);
    header("Location: $url", true, $code);
    exit;
}

function flash_and_redirect(string $type, string $message, string $url, int $code = 302): never
{
    flash($type, $message);
    redirect($url, $code);
}

function flash_success_and_redirect(string $url, int $code = 302): never
{
    flash_and_redirect('success', t('flash.action.done'), $url, $code);
}
