<?php

declare(strict_types=1);

function markdown_to_preview_text(string $text): string
{
    $text = preg_replace('/!\[\[[^\[\]\r\n]+\]\]/u', '', $text) ?? $text;

    $text = preg_replace_callback(WIKI_LINK_PATTERN, function ($m) {
        return trim($m[2] ?? $m[1]);
    }, $text) ?? $text;

    $text = preg_replace('/(?:^|(?<=\s))(#[\p{L}\p{N}_-]+)/u', '', $text) ?? $text;
    $text = strip_tags($text);

    return trim($text);
}

function extract_description(string $content, int $maxLength = 150): string
{
    $customTagPattern = '/\((?:video|iframe|image|codepen|map|arena|wikipedia|recent|wanted|random):[^\r\n]*\)/iu';
    $redirectPattern = '/^\(redirect:\s*.*?\)\s*$/mi';
    $text = preg_replace('/^#{1,6} .*/m', '', $content) ?? $content;
    $text = preg_replace('/^[\s]*[-*+] .*/m', '', $text) ?? $text;
    $text = preg_replace('/^> .*/m', '', $text) ?? $text;
    $text = preg_replace('/^[-*_]{3,}\s*$/m', '', $text) ?? $text;
    $text = preg_replace($redirectPattern, '', $text) ?? $text;
    $text = preg_replace('/<!--[\s\S]*?-->/', '', $text) ?? $text;
    $text = preg_replace($customTagPattern, '', $text) ?? $text;
    $text = preg_replace('/!\[\[[^\[\]\r\n]+\]\]/u', '', $text) ?? $text;
    $text = markdown_to_preview_text($text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
    $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '', $text) ?? $text;
    $text = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $text) ?? $text;
    $text = preg_replace('/[*_]{1,3}([^*_]+)[*_]{1,3}/', '$1', $text) ?? $text;
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength) . '...';
    }

    return $text;
}
