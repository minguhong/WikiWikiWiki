<?php

declare(strict_types=1);

use Michelf\SmartyPants;

const TRANSCLUSION_MAX_DEPTH = 2;

function markdown_line_prefix(string $content, int $offset): string
{
    if ($offset <= 0) {
        return '';
    }

    $prefix = substr($content, 0, $offset);
    $lineStart = strrpos($prefix, "\n");
    $carriageStart = strrpos($prefix, "\r");
    $breakPos = max($lineStart === false ? -1 : $lineStart, $carriageStart === false ? -1 : $carriageStart);
    return substr($prefix, $breakPos + 1);
}

function is_markdown_container_prefix(string $prefix): bool
{
    return preg_match('/^\s*(?:>\s*)+/', $prefix) === 1
        || preg_match('/^\s*(?:>\s*)*(?:[-+*]|\d+\.)\s+/', $prefix) === 1;
}

function parse_custom_tags(string $content, array &$placeholders, int &$counter, array &$tocMarkers): string
{
    $tags = [
        'video' => true,
        'iframe' => true,
        'image' => true,
        'codepen' => true,
        'map' => true,
        'arena' => true,
        'wikipedia' => false,
        'recent' => true,
        'wanted' => true,
        'random' => true,
    ];
    $listTags = [
        'recent' => true,
        'wanted' => true,
        'random' => true,
    ];

    $length = strlen($content);
    $result = '';
    $offset = 0;

    while ($offset < $length) {
        $openPos = strpos($content, '(', $offset);
        if ($openPos === false) {
            $result .= substr($content, $offset);
            break;
        }

        $result .= substr($content, $offset, $openPos - $offset);
        $cursor = $openPos + 1;
        $typeStart = $cursor;

        while ($cursor < $length && ctype_alpha($content[$cursor])) {
            $cursor++;
        }

        $type = strtolower(substr($content, $typeStart, $cursor - $typeStart));
        if ($type === 'toc') {
            if (is_markdown_container_prefix(markdown_line_prefix($content, $openPos))) {
                $result .= '(';
                $offset = $openPos + 1;
                continue;
            }

            $tocCursor = $cursor;
            while ($tocCursor < $length && ($content[$tocCursor] === ' ' || $content[$tocCursor] === "\t")) {
                $tocCursor++;
            }

            if ($tocCursor < $length && $content[$tocCursor] === ')') {
                $key = "%%HTML{$counter}%%";
                $marker = "<!--TOC_{$counter}-->";
                $placeholders[$key] = $marker;
                $tocMarkers[] = $marker;
                $counter++;
                $result .= "\n\n" . $key . "\n\n";
                $offset = $tocCursor + 1;
                continue;
            }
        }

        if ($cursor === $typeStart || $cursor >= $length || $content[$cursor] !== ':') {
            $result .= '(';
            $offset = $openPos + 1;
            continue;
        }

        if (!isset($tags[$type])) {
            $result .= '(';
            $offset = $openPos + 1;
            continue;
        }

        
        
        if ($tags[$type] && is_markdown_container_prefix(markdown_line_prefix($content, $openPos))) {
            $result .= '(';
            $offset = $openPos + 1;
            continue;
        }

        $valueStart = $cursor + 1;
        while ($valueStart < $length && ($content[$valueStart] === ' ' || $content[$valueStart] === "\t")) {
            $valueStart++;
        }

        $depth = 1;
        $endPos = -1;
        $cursor = $valueStart;

        while ($cursor < $length) {
            $ch = $content[$cursor];
            if ($ch === "\n" || $ch === "\r") {
                break;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $endPos = $cursor;
                    break;
                }
            }

            $cursor++;
        }

        if ($endPos < 0) {
            $lineEnd = $length;
            $newlinePos = strpos($content, "\n", $openPos);
            if ($newlinePos !== false) {
                $lineEnd = $newlinePos;
            }
            $carriagePos = strpos($content, "\r", $openPos);
            if ($carriagePos !== false && $carriagePos < $lineEnd) {
                $lineEnd = $carriagePos;
            }

            $malformed = substr($content, $openPos, $lineEnd - $openPos);
            if ($malformed === '') {
                $result .= '(';
                $offset = $openPos + 1;
                continue;
            }

            $key = "%%HTML{$counter}%%";
            $placeholders[$key] = html($malformed);
            $counter++;
            $result .= $key;
            $offset = $lineEnd;
            continue;
        }

        $original = substr($content, $openPos, $endPos - $openPos + 1);
        $value = trim(substr($content, $valueStart, $endPos - $valueStart));

        if ($value === '') {
            $result .= render_embed_invalid($original);
            $offset = $endPos + 1;
            continue;
        }

        if (isset($listTags[$type])) {
            $html = render_embed_list($type, $value, $original);
        } elseif ($type === 'video') {
            $html = render_embed_video($value, $original);
        } elseif ($type === 'image') {
            $html = render_embed_image($value, $original);
        } elseif ($type === 'iframe') {
            $html = render_embed_iframe($value, $original);
        } elseif ($type === 'codepen') {
            $html = render_embed_codepen($value, $original);
        } elseif ($type === 'map') {
            $html = render_embed_map($value, $original);
        } elseif ($type === 'arena') {
            $html = render_embed_arena($value, $original);
        } else {
            $html = render_embed_wikipedia($value, $original);
        }

        if ($html === '') {
            $result .= isset($listTags[$type]) ? '' : $original;
            $offset = $endPos + 1;
            continue;
        }

        $key = "%%HTML{$counter}%%";
        $placeholders[$key] = $html;
        $counter++;
        $result .= $tags[$type] ? "\n\n" . $key . "\n\n" : $key;
        $offset = $endPos + 1;
    }

    return $result;
}

function markdown_to_html(string $text): string
{
    $parser = new ParsedownExtra();
    $parser->setBreaksEnabled(true);
    $parser->setMarkupEscaped(true);
    $parser->setUrlsLinked(true);
    $parser->setSafeMode(true);
    $html = $parser->text($text);
    return SmartyPants::defaultTransform($html);
}

function heading_slug(string $text): string
{
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = mb_strtolower(trim($decoded), 'UTF-8');
    $decoded = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $decoded) ?? $decoded;
    $decoded = trim($decoded);
    $decoded = preg_replace('/[\s_-]+/u', '-', $decoded) ?? $decoded;
    $decoded = trim($decoded, '-');

    return $decoded !== '' ? $decoded : 'section';
}

function heading_extract_id(string $attrs): ?string
{
    if (preg_match('/\bid\s*=\s*(["\'])(.*?)\1/i', $attrs, $m) === 1) {
        return trim((string) $m[2]);
    }
    if (preg_match('/\bid\s*=\s*([^\s"\'>]+)/i', $attrs, $m) === 1) {
        return trim((string) $m[1]);
    }
    return null;
}

function heading_set_id(string $attrs, string $id): string
{
    $replacement = 'id="' . html($id) . '"';
    if (preg_match('/\bid\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i', $attrs) === 1) {
        return preg_replace('/\bid\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i', $replacement, $attrs, 1) ?? $attrs;
    }

    $trimmed = rtrim($attrs);
    if ($trimmed === '') {
        return ' ' . $replacement;
    }
    return $trimmed . ' ' . $replacement;
}

function heading_unique_id(string $baseId, array &$used): string
{
    $candidate = trim($baseId);
    if ($candidate === '') {
        $candidate = 'section';
    }

    if (!isset($used[$candidate])) {
        $used[$candidate] = 1;
        return $candidate;
    }

    $used[$candidate]++;
    return $candidate . '-' . $used[$candidate];
}

function heading_text_from_inner_html(string $innerHtml, array $inlinePlaceholders): string
{
    $resolved = $innerHtml;
    if ($inlinePlaceholders !== [] && preg_match('/%%HTML\d+%%/', $resolved) === 1) {
        $resolved = str_replace(array_keys($inlinePlaceholders), array_values($inlinePlaceholders), $resolved);
    }

    $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($resolved), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return trim((string) ($text ?? ''));
}

function add_heading_ids_and_collect(string $html, array $inlinePlaceholders = []): array
{
    $used = [];
    $headings = [];

    $updatedHtml = preg_replace_callback('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', static function (array $m) use (&$used, &$headings, $inlinePlaceholders): string {
        $level = (int) $m[1];
        $attrs = (string) $m[2];
        $innerHtml = (string) $m[3];

        $text = heading_text_from_inner_html($innerHtml, $inlinePlaceholders);
        if ($text === '') {
            return $m[0];
        }

        $existingId = heading_extract_id($attrs);
        $baseId = $existingId !== null && $existingId !== '' ? $existingId : heading_slug($text);
        $uniqueId = heading_unique_id($baseId, $used);

        if ($existingId === null || $existingId !== $uniqueId) {
            $attrs = heading_set_id($attrs, $uniqueId);
        }

        $headings[] = [
            'level' => $level,
            'id' => $uniqueId,
            'text' => $text,
        ];

        return '<h' . $level . $attrs . '>' . $innerHtml . '</h' . $level . '>';
    }, $html) ?? $html;

    return [$updatedHtml, $headings];
}

function wrap_markdown_tables(string $html): string
{
    return preg_replace_callback('/<table\b[^>]*>.*?<\/table>/is', static function (array $matches): string {
        return '<div class="table">' . $matches[0] . '</div>';
    }, $html) ?? $html;
}

function wrap_markdown_images(string $html): string
{
    return preg_replace_callback('/<p>\s*(<img\b[^>]*>)\s*<\/p>/i', static function (array $matches): string {
        $img = $matches[1];
        if (preg_match('/\sloading\s*=/i', $img) !== 1) {
            $img = preg_replace('/<img\b/i', '<img loading="lazy"', $img, 1) ?? $img;
        }
        return '<figure>' . $img . '</figure>';
    }, $html) ?? $html;
}

function append_html_class_attribute(string $attrs, string $class): string
{
    if (preg_match('/\bclass=(["\'])(.*?)\1/i', $attrs, $matches, PREG_OFFSET_CAPTURE) === 1) {
        $fullMatch = (string) $matches[0][0];
        $offset = (int) $matches[0][1];
        $quote = (string) $matches[1][0];
        $existing = trim((string) $matches[2][0]);
        if ($existing !== '' && preg_match('/(?:^|\s)' . preg_quote($class, '/') . '(?:\s|$)/', $existing) === 1) {
            return $attrs;
        }
        $nextClass = $existing === '' ? $class : $existing . ' ' . $class;
        $replacement = 'class=' . $quote . $nextClass . $quote;
        return substr($attrs, 0, $offset) . $replacement . substr($attrs, $offset + strlen($fullMatch));
    }

    return $attrs . ' class="' . $class . '"';
}

function render_markdown_task_lists(string $html): string
{
    $withCheckboxes = preg_replace_callback('/<li\b([^>]*)>\s*\[( |x|X)\]\s+(.*?)<\/li>/is', static function (array $matches): string {
        $attrs = (string) $matches[1];
        $checked = strtolower((string) $matches[2]) === 'x';
        $body = (string) $matches[3];
        $checkbox = '<input type="checkbox" disabled' . ($checked ? ' checked' : '') . '>';
        return '<li' . $attrs . '>' . $checkbox . ' ' . $body . '</li>';
    }, $html) ?? $html;

    return preg_replace_callback('/<ul\b([^>]*)>(.*?)<\/ul>/is', static function (array $matches): string {
        $attrs = (string) $matches[1];
        $body = (string) $matches[2];
        if (!str_contains($body, '<input type="checkbox" disabled')) {
            return $matches[0];
        }

        $attrs = append_html_class_attribute($attrs, 'task-list');
        return '<ul' . $attrs . '>' . $body . '</ul>';
    }, $withCheckboxes) ?? $withCheckboxes;
}

function decorate_external_links(string $html): string
{
    return preg_replace_callback('/<a href="https?:\/\/[^"]+"/', static function (array $m): string {
        if (!str_contains($m[0], 'target=')) {
            return str_replace('<a ', '<a class="external" ', $m[0]) . ' target="_blank" rel="noopener noreferrer"';
        }
        return $m[0];
    }, $html) ?? $html;
}

function preserve_markdown_links_and_images(string $content, array &$placeholders, int &$counter): string
{
    $length = strlen($content);
    $result = '';
    $offset = 0;

    while ($offset < $length) {
        $openPos = strpos($content, '[', $offset);
        if ($openPos === false) {
            $result .= substr($content, $offset);
            break;
        }

        $start = $openPos;
        if ($start > $offset && $content[$start - 1] === '!') {
            $start--;
        }

        $result .= substr($content, $offset, $start - $offset);
        $cursor = $start;
        if ($content[$start] === '!') {
            if ($start + 1 >= $length || $content[$start + 1] !== '[') {
                $result .= $content[$start];
                $offset = $start + 1;
                continue;
            }
            $cursor = $start + 1;
        } elseif ($content[$start] !== '[') {
            $result .= $content[$start];
            $offset = $start + 1;
            continue;
        }

        
        
        if ($content[$start] === '[' && $start + 1 < $length && $content[$start + 1] === '[') {
            $result .= $content[$start];
            $offset = $start + 1;
            continue;
        }

        $depth = 0;
        while ($cursor < $length) {
            $ch = $content[$cursor];
            if ($ch === '\\' && $cursor + 1 < $length) {
                $cursor += 2;
                continue;
            }
            if ($ch === "\n" || $ch === "\r") {
                break;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $cursor++;
                    break;
                }
            }
            $cursor++;
        }

        if ($depth !== 0) {
            $result .= $content[$start];
            $offset = $start + 1;
            continue;
        }

        while ($cursor < $length && ($content[$cursor] === ' ' || $content[$cursor] === "\t")) {
            $cursor++;
        }

        $end = -1;
        if ($cursor < $length && $content[$cursor] === '(') {
            $parenDepth = 0;
            while ($cursor < $length) {
                $ch = $content[$cursor];
                if ($ch === '\\' && $cursor + 1 < $length) {
                    $cursor += 2;
                    continue;
                }
                if ($ch === "\n" || $ch === "\r") {
                    break;
                }
                if ($ch === '(') {
                    $parenDepth++;
                } elseif ($ch === ')') {
                    $parenDepth--;
                    if ($parenDepth === 0) {
                        $end = $cursor;
                        break;
                    }
                }
                $cursor++;
            }
        } elseif ($cursor < $length && $content[$cursor] === '[' && !($cursor + 1 < $length && $content[$cursor + 1] === '[')) {
            $bracketDepth = 0;
            while ($cursor < $length) {
                $ch = $content[$cursor];
                if ($ch === '\\' && $cursor + 1 < $length) {
                    $cursor += 2;
                    continue;
                }
                if ($ch === "\n" || $ch === "\r") {
                    break;
                }
                if ($ch === '[') {
                    $bracketDepth++;
                } elseif ($ch === ']') {
                    $bracketDepth--;
                    if ($bracketDepth === 0) {
                        $end = $cursor;
                        break;
                    }
                }
                $cursor++;
            }
        }

        if ($end < 0) {
            $result .= $content[$start];
            $offset = $start + 1;
            continue;
        }

        $token = substr($content, $start, $end - $start + 1);
        $key = "%%CODE{$counter}%%";
        $placeholders[$key] = $token;
        $counter++;
        $result .= $key;
        $offset = $end + 1;
    }

    return $result;
}

function preserve_inline_code_spans(string $content, array &$placeholders, int &$counter): string
{
    $length = strlen($content);
    $result = '';
    $offset = 0;

    while ($offset < $length) {
        $tickPos = strpos($content, '`', $offset);
        if ($tickPos === false) {
            $result .= substr($content, $offset);
            break;
        }

        $result .= substr($content, $offset, $tickPos - $offset);

        $delimiterLength = 1;
        while ($tickPos + $delimiterLength < $length && $content[$tickPos + $delimiterLength] === '`') {
            $delimiterLength++;
        }
        $delimiter = str_repeat('`', $delimiterLength);

        $lineEnd = $length;
        $newlinePos = strpos($content, "\n", $tickPos);
        if ($newlinePos !== false) {
            $lineEnd = $newlinePos;
        }
        $carriagePos = strpos($content, "\r", $tickPos);
        if ($carriagePos !== false && $carriagePos < $lineEnd) {
            $lineEnd = $carriagePos;
        }

        $closePos = strpos($content, $delimiter, $tickPos + $delimiterLength);
        if ($closePos === false || $closePos >= $lineEnd) {
            $result .= $content[$tickPos];
            $offset = $tickPos + 1;
            continue;
        }

        $token = substr($content, $tickPos, $closePos - $tickPos + $delimiterLength);
        $key = "%%CODE{$counter}%%";
        $placeholders[$key] = $token;
        $counter++;
        $result .= $key;
        $offset = $closePos + $delimiterLength;
    }

    return $result;
}

function preserve_indented_code_blocks(string $content, array &$placeholders, int &$counter): string
{
    $pattern = '/^(?: {4}|\t)(?![ \t]*(?:[-+*]|\d+\.)\s).*(?:\R(?: {4}|\t)(?![ \t]*(?:[-+*]|\d+\.)\s).*)*/m';

    return preg_replace_callback(
        $pattern,
        static function (array $m) use (&$placeholders, &$counter, $content): string {
            $block = $m[0][0];
            $offset = $m[0][1];
            $firstLine = (string) (preg_split('/\R/', $block, 2)[0] ?? '');
            if (!markdown_should_preserve_indented_code_block($content, $offset, $firstLine)) {
                return $block;
            }

            $key = "%%CODE{$counter}%%";
            $placeholders[$key] = $block;
            $counter++;
            return $key;
        },
        $content,
        -1,
        $count,
        PREG_OFFSET_CAPTURE,
    ) ?? $content;
}

function preserve_parser_tokens(string $content): array
{
    $placeholders = [];
    $counter = 0;

    $replaceWithPlaceholder = static function (array $m) use (&$placeholders, &$counter): string {
        $key = "%%CODE{$counter}%%";
        $placeholders[$key] = $m[0];
        $counter++;
        return $key;
    };

    $patterns = [
        '/```[\s\S]*?```/',
        '/~~~[\s\S]*?~~~/',
    ];
    foreach ($patterns as $pattern) {
        $content = preg_replace_callback($pattern, $replaceWithPlaceholder, $content) ?? $content;
    }

    $content = preserve_indented_code_blocks($content, $placeholders, $counter);
    $content = preserve_markdown_links_and_images($content, $placeholders, $counter);
    $content = preserve_inline_code_spans($content, $placeholders, $counter);

    return [$content, $placeholders];
}

function preserve_literal_placeholder_tokens(string $content): array
{
    $placeholders = [];
    $counter = 0;
    $namespace = 'LITERAL_' . safe_random_hex(6);
    while (str_contains($content, "%%{$namespace}_")) {
        $namespace = 'LITERAL_' . safe_random_hex(6);
    }

    $content = preg_replace_callback('/%%(?:CODE|HTML)\d+%%/', static function (array $m) use (&$placeholders, &$counter, $namespace): string {
        $key = "%%{$namespace}_{$counter}%%";
        $placeholders[$key] = $m[0];
        $counter++;
        return $key;
    }, $content) ?? $content;

    return [$content, $placeholders];
}

function parse_wiki_links(string $content, array &$placeholders, int &$counter): string
{
    return preg_replace_callback(WIKI_LINK_PATTERN, static function (array $m) use (&$placeholders, &$counter): string {
        $page = trim($m[1]);
        $alias = isset($m[2]) ? trim($m[2]) : $page;

        $sanitized = sanitize_page_title($page);

        if ($sanitized === '' || (isset($m[2]) && $alias === '')) {
            $key = "%%HTML{$counter}%%";
            $placeholders[$key] = '<span class="invalid" title="' . html(t('error.invalid.wiki_link')) . '">'
                 . html($m[0]) . '</span>';
            $counter++;
            return $key;
        }

        $key = "%%HTML{$counter}%%";
        $placeholders[$key] = render_wiki_link($sanitized, $alias);
        $counter++;
        return $key;
    }, $content) ?? $content;
}

function parse_hashtags(string $content, array &$placeholders, int &$counter): string
{
    return preg_replace_callback('/(?:^|(?<=\s))(#[\p{L}\p{N}_-]+)/u', static function (array $m) use (&$placeholders, &$counter): string {
        $tag = ltrim($m[1], '#');
        if ($tag === '') {
            return $m[0];
        }
        $key = "%%HTML{$counter}%%";
        $placeholders[$key] = render_tag_link($tag);
        $counter++;
        return $key;
    }, $content) ?? $content;
}

function transclusion_stack_key(string $title): string
{
    return mb_strtolower($title, 'UTF-8');
}

function parse_transclusions(
    string $content,
    array &$placeholders,
    int &$counter,
    int $depth = 0,
    array $stack = [],
): string {
    $pushPlaceholder = static function (string $html, bool $block = false) use (&$placeholders, &$counter): string {
        $key = "%%HTML{$counter}%%";
        $placeholders[$key] = $html;
        $counter++;
        return $block ? ("\n\n" . $key . "\n\n") : $key;
    };

    $invalidSpan = static fn(string $original, string $title): string => '<span class="invalid" title="'
        . html($title) . '">' . html($original) . '</span>';

    return preg_replace_callback('/!\[\[([^\[\]\r\n]+)\]\]/u', static function (array $m) use ($pushPlaceholder, $invalidSpan, $depth, $stack): string {
        $original = (string) $m[0];
        $rawTitle = trim((string) $m[1]);
        $title = sanitize_page_title($rawTitle);
        if ($title === '') {
            return $pushPlaceholder($invalidSpan($original, t('error.invalid.title_label')));
        }

        $titleKey = transclusion_stack_key($title);
        if (isset($stack[$titleKey])) {
            return $pushPlaceholder($invalidSpan($original, t('error.invalid.transclusion')));
        }

        if ($depth >= TRANSCLUSION_MAX_DEPTH) {
            return $pushPlaceholder($invalidSpan($original, t('error.invalid.transclusion')));
        }

        $includedContent = page_get($title);
        if ($includedContent === null) {
            return $pushPlaceholder($invalidSpan($original, t('message.page.not_exists')));
        }

        $nextStack = $stack;
        $nextStack[$titleKey] = true;

        $parsed = parse_content($includedContent, [
            'source_title' => $title,
            'depth' => $depth + 1,
            'stack' => $nextStack,
        ]);

        $htmlContent = '';
        if (($parsed['type'] ?? '') === 'html') {
            $htmlContent = (string) ($parsed['content'] ?? '');
        } elseif (($parsed['type'] ?? '') === 'redirect') {
            $redirectTargetRaw = trim((string) ($parsed['target'] ?? ''));
            if ($redirectTargetRaw !== '' && preg_match('/^https?:\/\//i', $redirectTargetRaw) !== 1) {
                $redirectTarget = normalize_internal_redirect_title($redirectTargetRaw);
                if ($redirectTarget !== null) {
                    $htmlContent = '<p>' . render_wiki_link($redirectTarget, $redirectTarget) . '</p>';
                }
            }
            $htmlContent .= (string) ($parsed['content'] ?? '');
        }

        if ($htmlContent === '') {
            return '';
        }

        return $pushPlaceholder(
            "\n<section class=\"transclusion content\">\n"
            . "<header class=\"transclusion-header\">"
            . "<a class=\"transclusion-title\" href=\"" . html(page_url($title)) . "\">" . html($title) . "</a>"
            . "</header>\n"
            . $htmlContent . "\n</section>\n",
            true,
        );
    }, $content) ?? $content;
}

function restore_placeholders(string $content, array $placeholders): string
{
    if ($placeholders === []) {
        return $content;
    }
    return str_replace(array_keys($placeholders), array_values($placeholders), $content);
}

function restore_placeholders_recursive(string $content, array $placeholders): string
{
    if ($placeholders === [] || $content === '') {
        return $content;
    }

    $result = $content;
    $maxPasses = count($placeholders) + 1;
    for ($i = 0; $i < $maxPasses; $i++) {
        $next = restore_placeholders($result, $placeholders);
        if ($next === $result) {
            break;
        }
        $result = $next;
    }

    return $result;
}

function restore_placeholder_values_recursive(array $values, array $placeholders): array
{
    if ($values === [] || $placeholders === []) {
        return $values;
    }

    foreach ($values as $key => $value) {
        if (!is_string($value) || $value === '') {
            continue;
        }
        $values[$key] = restore_placeholders_recursive($value, $placeholders);
    }

    return $values;
}

function parse_content(string $content, array $context = []): array
{
    $depth = isset($context['depth']) ? max(0, (int) $context['depth']) : 0;
    $stack = [];
    if (isset($context['stack']) && is_array($context['stack'])) {
        foreach ($context['stack'] as $stackKey => $value) {
            if (is_string($stackKey) && $stackKey !== '' && (bool) $value) {
                $stack[$stackKey] = true;
            } elseif (is_string($value) && $value !== '') {
                $stack[transclusion_stack_key($value)] = true;
            }
        }
    }
    $sourceTitle = sanitize_page_title((string) ($context['source_title'] ?? ''));
    if ($sourceTitle !== '') {
        $stack[transclusion_stack_key($sourceTitle)] = true;
    }

    if (preg_match('/^\(redirect:\s*([^\n\r]*)\)\s*\n?(.*)$/si', $content, $m)) {
        $target = trim($m[1]);
        $additionalContent = trim($m[2]);

        $result = [
            'type' => 'redirect',
            'target' => $target,
        ];

        if ($additionalContent !== '') {
            $parsed = parse_content($additionalContent, [
                'source_title' => $sourceTitle,
                'depth' => $depth,
                'stack' => $stack,
            ]);
            $result['content'] = $parsed['content'];
        }

        return $result;
    }

    [$text, $literalPlaceholders] = preserve_literal_placeholder_tokens($content);
    [$text, $codePlaceholders] = preserve_parser_tokens($text);

    $transclusionPlaceholders = [];
    $inlineHtmlPlaceholders = [];
    $tocMarkers = [];
    $counter = 0;
    $text = parse_transclusions($text, $transclusionPlaceholders, $counter, $depth, $stack);
    $text = parse_wiki_links($text, $inlineHtmlPlaceholders, $counter);
    $text = parse_hashtags($text, $inlineHtmlPlaceholders, $counter);
    $text = parse_custom_tags($text, $inlineHtmlPlaceholders, $counter, $tocMarkers);

    $text = restore_placeholders_recursive($text, $codePlaceholders);
    $inlineHtmlPlaceholders = restore_placeholder_values_recursive($inlineHtmlPlaceholders, $codePlaceholders);
    $html = markdown_to_html($text);
    [$html, $headings] = add_heading_ids_and_collect($html, $inlineHtmlPlaceholders);
    $html = wrap_markdown_tables($html);
    $html = wrap_markdown_images($html);
    $html = render_markdown_task_lists($html);
    $html = decorate_external_links($html);
    $html = restore_placeholders($html, $inlineHtmlPlaceholders);
    if ($tocMarkers !== []) {
        $tocHtml = render_embed_toc($headings);
        $html = str_replace($tocMarkers, $tocHtml, $html);
    }
    $html = restore_placeholders($html, $transclusionPlaceholders);
    $html = restore_placeholders($html, $literalPlaceholders);
    $html = preg_replace('/<p>(<figure>.*?<\/figure>)<\/p>/s', '$1', $html) ?? $html;
    $html = preg_replace('/<p>(<ul data-columns>.*?<\/ul>)<\/p>/s', '$1', $html) ?? $html;
    $html = preg_replace('/<p>\s*(<section class="[^"]*\btransclusion\b[^"]*"[^>]*>.*?<\/section>)\s*<\/p>/s', '$1', $html) ?? $html;
    $html = preg_replace('/<p>\s*(<nav class="toc">.*?<\/nav>)\s*<\/p>/s', '$1', $html) ?? $html;
    $html = preg_replace('/<p>\s*<\/p>/', '', $html) ?? $html;

    return [
        'type' => 'html',
        'content' => $html,
    ];
}
