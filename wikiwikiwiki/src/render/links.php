<?php

declare(strict_types=1);

function render_wiki_link(string $page, string $alias): string
{
    $class = page_exists($page) ? 'exists' : 'not-exists';
    return '<a href="' . url($page) . '" class="' . $class . '">' . html($alias) . '</a>';
}

function render_tag_link(string $tag): string
{
    return '<a href="' . url('/tags/' . rawurlencode($tag)) . '" class="tag">#' . html($tag) . '</a>';
}
