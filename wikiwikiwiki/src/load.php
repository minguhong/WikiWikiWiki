<?php

declare(strict_types=1);

function load_wiki_core(string $wikiDir): void
{
    $files = [
        '/src/helpers.php',
        '/src/helpers/security.php',
        '/src/helpers/view.php',
        '/src/helpers/storage.php',
        '/src/helpers/web.php',
        '/src/helpers/i18n.php',
        '/src/helpers/config.php',
        '/src/helpers/export.php',
        '/src/helpers/stats.php',
        '/src/user.php',
        '/src/auth.php',
        '/src/page.php',
        '/src/page/discover.php',
        '/src/page/index.php',
        '/src/page/search.php',
        '/src/page/related.php',
        '/src/page/history.php',
        '/src/page/crud.php',
        '/src/page/diff.php',
        '/src/render.php',
    ];

    foreach ($files as $file) {
        require_once $wikiDir . $file;
    }
}
