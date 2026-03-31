<?php

declare(strict_types=1);

function sanitize_translation_html(string $value): string
{
    $value = (string) preg_replace('/<\s*(script|style)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $value);
    $value = strip_tags($value, '<a><strong><em><code><mark><kbd>');

    $value = (string) preg_replace('/<(strong|em|code|mark|kbd)\b[^>]*>/i', '<$1>', $value);

    $value = (string) preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
        $tag = $matches[0];
        if (!preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $hrefMatch)) {
            return '<a>';
        }

        $href = '';
        if ($hrefMatch[1] !== '') {
            $href = $hrefMatch[1];
        } elseif ($hrefMatch[2] !== '') {
            $href = $hrefMatch[2];
        } elseif ($hrefMatch[3] !== '') {
            $href = $hrefMatch[3];
        }

        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '') {
            return '<a>';
        }

        if (
            !preg_match('~^(https?://|/|#)~i', $href)
            && !str_starts_with($href, './')
            && !str_starts_with($href, '../')
        ) {
            return '<a>';
        }

        $target = '';
        if (preg_match('/\btarget\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $targetMatch)) {
            if ($targetMatch[1] !== '') {
                $target = $targetMatch[1];
            } elseif ($targetMatch[2] !== '') {
                $target = $targetMatch[2];
            } elseif ($targetMatch[3] !== '') {
                $target = $targetMatch[3];
            }
            $target = trim(html_entity_decode($target, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (strcasecmp($target, '_blank') === 0) {
            return '<a href="' . html($href) . '" target="_blank" rel="noopener noreferrer">';
        }

        return '<a href="' . html($href) . '">';
    }, $value);

    return $value;
}

function t(string $key, ?string $fallback = null): string
{
    static $cache = [];
    if (array_key_exists($key, TRANSLATIONS)) {
        $cacheKey = 't:' . $key;
        if (!isset($cache[$cacheKey])) {
            $cache[$cacheKey] = sanitize_translation_html((string) TRANSLATIONS[$key]);
        }
        return $cache[$cacheKey];
    }

    $raw = $fallback ?? $key;
    $cacheKey = 'f:' . $raw;
    if (!isset($cache[$cacheKey])) {
        $cache[$cacheKey] = sanitize_translation_html($raw);
    }
    return $cache[$cacheKey];
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
