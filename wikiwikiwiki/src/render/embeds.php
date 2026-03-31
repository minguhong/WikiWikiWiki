<?php

declare(strict_types=1);

function parse_size_options(string $value): array
{
    $width = null;
    $height = null;

    if (preg_match('/\s+width:\s*(\d+(?:px|%|em|rem))/i', $value, $m)) {
        $width = $m[1];
        $value = trim(preg_replace('/\s+width:\s*\d+(?:px|%|em|rem)/i', '', $value) ?? $value);
    }
    if (preg_match('/\s+height:\s*(\d+(?:px|%|em|rem))/i', $value, $m)) {
        $height = $m[1];
        $value = trim(preg_replace('/\s+height:\s*\d+(?:px|%|em|rem)/i', '', $value) ?? $value);
    }

    return [$value, $width, $height];
}

function render_embed_invalid(string $original, string $errorKey = 'error.invalid.value'): string
{
    return '<span class="invalid" title="' . html(t($errorKey)) . '">' . html($original) . '</span>';
}

function render_embed_figure(string $innerHtml, ?string $width = null, ?string $caption = null): string
{
    $figureStyle = $width ? ' style="width: ' . html($width) . ';"' : '';
    $html = '<figure' . $figureStyle . '>' . $innerHtml;
    if ($caption !== null) {
        $html .= '<figcaption>' . $caption . '</figcaption>';
    }
    $html .= '</figure>';
    return $html;
}

function render_embed_video(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);
    if ($value === '') {
        return render_embed_invalid($original);
    }

    $iframeStyle = $height ? ' style="height: ' . html($height) . ';"' : '';

    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $value, $m)) {
        $iframe = '<iframe' . $iframeStyle . ' src="https://www.youtube.com/embed/' . html($m[1]) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        return render_embed_figure($iframe, $width);
    }
    if (preg_match('/vimeo\.com\/(\d+)/', $value, $m)) {
        $iframe = '<iframe' . $iframeStyle . ' src="https://player.vimeo.com/video/' . html($m[1]) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        return render_embed_figure($iframe, $width);
    }

    return render_embed_invalid($original, 'error.invalid.url');
}

function render_embed_image(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);

    $caption = null;
    if (preg_match('/\s+caption:\s*(.+?)(?:\s+(?:width|height):|\s*$)/i', $value, $captionMatch)) {
        $caption = trim($captionMatch[1]);
        $value = trim(preg_replace('/\s+caption:\s*.+?(?=\s+(?:width|height):|\s*$)/i', '', $value) ?? $value);
    }

    if ($value === '' || !preg_match('#^https?://#i', $value)) {
        return render_embed_invalid($original, 'error.invalid.url');
    }

    $alt = $caption ? html($caption) : '';
    $figureStyle = $width ? ' style="max-width: ' . html($width) . ';"' : '';
    $imgStyles = [];
    if ($width) {
        $imgStyles[] = 'width: 100%';
    }
    if ($height) {
        $imgStyles[] = 'height: ' . html($height);
    }
    $imgStyle = $imgStyles !== [] ? ' style="' . implode('; ', $imgStyles) . ';"' : '';

    
    $html = '<figure' . $figureStyle . '>';
    $html .= '<img src="' . html($value) . '" alt="' . $alt . '" loading="lazy"' . $imgStyle . '>';
    if ($caption) {
        $html .= '<figcaption>' . html($caption) . '</figcaption>';
    }
    $html .= '</figure>';
    return $html;
}

function render_embed_iframe(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);
    if ($value === '' || preg_match('#^https://#i', $value) !== 1) {
        return render_embed_invalid($original, 'error.invalid.url');
    }

    $iframeStyle = $height ? ' style="height: ' . html($height) . ';"' : '';
    $iframe = '<iframe' . $iframeStyle . ' src="' . html($value) . '" sandbox="allow-scripts allow-same-origin allow-popups" frameborder="0"></iframe>';
    $caption = '<a class="iframe-link" href="' . html($value) . '" target="_blank" rel="noopener noreferrer">' . html($value) . '</a>';
    return render_embed_figure($iframe, $width, $caption);
}

function render_embed_codepen(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);
    if ($value === '') {
        return render_embed_invalid($original);
    }

    $username = null;
    $penId = null;

    if (preg_match('/codepen\.io\/pen\/([a-zA-Z0-9]+)/', $value, $m)
        && !preg_match('/codepen\.io\/[^\/\s]+\/pen\//', $value)) {
        $penId = $m[1];
    } elseif (preg_match('/codepen\.io\/([^\/\s]+)\/(?:pen|embed)\/([a-zA-Z0-9]+)/', $value, $m)) {
        $username = $m[1];
        $penId = $m[2];
    } elseif (preg_match('/^([^\/\s]+)\/(?:pen\/)?([a-zA-Z0-9]+)$/', $value, $m)) {
        $username = $m[1];
        $penId = $m[2];
    }

    if ($penId === null) {
        return render_embed_invalid($original, 'error.invalid.url');
    }

    $encodedPenId = rawurlencode(rawurldecode($penId));
    if ($username) {
        $encodedUsername = rawurlencode(rawurldecode($username));
        $embedUrl = 'https://codepen.io/' . $encodedUsername . '/embed/' . $encodedPenId . '?default-tab=result';
        $linkUrl = 'https://codepen.io/' . $encodedUsername . '/pen/' . $encodedPenId;
    } else {
        $embedUrl = 'https://codepen.io/pen/embed/' . $encodedPenId . '?default-tab=result';
        $linkUrl = 'https://codepen.io/pen/' . $encodedPenId;
    }

    $iframeStyle = ' style="width: 100%;';
    if ($height !== null) {
        $iframeStyle .= ' height: ' . html($height) . ';';
    }
    $iframeStyle .= '"';
    $iframe = '<iframe' . $iframeStyle . ' src="' . html($embedUrl) . '" loading="lazy"></iframe>';
    $caption = '<a href="' . html($linkUrl) . '" target="_blank" rel="noopener noreferrer">CodePen</a>';
    return render_embed_figure($iframe, $width, $caption);
}

function render_embed_map(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);
    if ($value === '') {
        return render_embed_invalid($original);
    }

    $query = rawurlencode($value);
    $embedUrl = 'https://maps.google.com/maps?q=' . $query . '&output=embed';
    $iframeStyle = $height !== null ? ' style="height: ' . html($height) . ';"' : '';
    $iframe = '<iframe' . $iframeStyle . ' src="' . html($embedUrl) . '" frameborder="0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
    return render_embed_figure($iframe, $width, html($value));
}

function render_embed_arena(string $value, string $original): string
{
    [$value, $width, $height] = parse_size_options($value);
    if ($value === '') {
        return render_embed_invalid($original);
    }

    if (preg_match('#^https?://(?:www\.)?are\.na/(.+?)(?:/embed)?/?$#', $value, $m)) {
        $path = $m[1];
    } else {
        $path = $value;
    }
    $segments = explode('/', trim($path, '/'));
    $encodedSegments = array_map(
        static fn(string $segment): string => rawurlencode(rawurldecode($segment)),
        $segments,
    );
    $embedUrl = 'https://www.are.na/' . implode('/', $encodedSegments) . '/embed';
    $iframeStyle = $height !== null ? ' style="height: ' . html($height) . ';"' : '';
    $iframe = '<iframe' . $iframeStyle . ' src="' . html($embedUrl) . '" loading="lazy"></iframe>';
    return render_embed_figure($iframe, $width);
}

function render_embed_wikipedia(string $value, string $original): string
{
    $lang = defined('LANGUAGE') ? (string) LANGUAGE : 'en';
    $term = $value;
    if (preg_match('/^(.*?) lang:\s*(\w{2,3})$/', $value, $langMatches)) {
        $term = trim($langMatches[1]);
        $lang = trim($langMatches[2]);
    }
    if (trim($term) === '') {
        return render_embed_invalid($original);
    }
    $url = 'https://' . $lang . '.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $term));
    return '<a href="' . html($url) . '" class="external wikipedia" target="_blank" rel="noopener noreferrer">' . html($term) . '</a>';
}

function render_embed_toc(array $headings): string
{
    $valid = [];
    foreach ($headings as $heading) {
        if (!is_array($heading)) {
            continue;
        }
        $level = (int) ($heading['level'] ?? 0);
        $id = trim((string) ($heading['id'] ?? ''));
        $text = trim((string) ($heading['text'] ?? ''));
        if ($level < 1 || $level > 6 || $id === '' || $text === '') {
            continue;
        }
        $valid[] = ['level' => $level, 'id' => $id, 'text' => $text];
    }

    if ($valid === []) {
        return '';
    }

    $levels = array_values(array_unique(array_map(static fn(array $h): int => $h['level'], $valid)));
    sort($levels, SORT_NUMERIC);
    $levels = array_slice($levels, 0, 2);

    $levelMap = [];
    foreach ($levels as $index => $level) {
        $levelMap[$level] = $index + 1;
    }

    $items = [];
    foreach ($valid as $h) {
        $relative = (int) ($levelMap[$h['level']] ?? 0);
        if ($relative < 1 || $relative > 2) {
            continue;
        }
        $items[] = '<li class="toc-h' . $relative . '"><a href="#' . html($h['id']) . '">' . html($h['text']) . '</a></li>';
    }

    return '<nav class="toc"><ul>' . implode('', $items) . '</ul></nav>';
}

function render_embed_list(string $type, string $value, string $original): string
{
    $limit = (int) $value;
    if ($limit <= 0) {
        return render_embed_invalid($original);
    }
    $limit = min($limit, PAGE_LIST_LIMIT);

    if ($type === 'recent') {
        $pages = page_recent_without_redirects($limit);
    } elseif ($type === 'wanted') {
        $pages = page_wanted($limit);
    } else {
        $pages = page_random($limit);
    }

    if ($pages === []) {
        return '';
    }

    $html = '<ul data-columns>';
    foreach ($pages as $item) {
        $title = (string) ($item['title'] ?? '');
        if ($title === '') {
            continue;
        }

        $titleHtml = html($title);
        if ($type === 'wanted') {
            $backlinksLabel = t('title.backlinks');
            $html .= '<li><a href="' . html(url($title)) . '" class="not-exists">' . $titleHtml . '</a> ';
            $html .= '<span aria-hidden="true">→</span> ';
            $html .= '<a href="' . html(url($title, '/backlinks')) . '">' . html($backlinksLabel) . '</a></li>';
            continue;
        }

        $html .= '<li><a href="' . html(url($title)) . '">' . $titleHtml . '</a></li>';
    }
    $html .= '</ul>';
    return $html;
}
