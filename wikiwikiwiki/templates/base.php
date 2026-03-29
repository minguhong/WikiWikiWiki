<!DOCTYPE html>
<html lang="<?= html($language) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="generator" content="WikiWikiWiki<?= html(wiki_version()) ?>">
  <title><?= html($metaTitle) ?></title>
  <meta name="description" content="<?= html($metaDescription) ?>">
  <meta name="author" content="<?= html($wikiTitle) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= html($wikiTitle) ?>">
  <meta property="og:title" content="<?= html($metaTitle) ?>">
  <meta property="og:description" content="<?= html($metaDescription) ?>">
  <meta property="og:url" content="<?= html($ogUrl) ?>">
  <meta property="og:image" content="<?= html($ogImageUrl) ?>">
  <meta property="og:image:type" content="image/png">
  <meta property="og:locale" content="<?= html($languageCode) ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:image" content="<?= html($ogImageUrl) ?>">
  <link rel="canonical" href="<?= html($ogUrl) ?>">
  <link rel="alternate" type="application/rss+xml" title="<?= html($wikiTitle . ' RSS') ?>" href="<?= url('/rss.xml') ?>">
  <link rel="alternate" type="application/atom+xml" title="<?= html($wikiTitle . ' Atom') ?>" href="<?= url('/atom.xml') ?>">
  <link rel="alternate" type="application/feed+json" title="<?= html($wikiTitle . ' JSON Feed') ?>" href="<?= url('/feed.json') ?>">
  <link rel="icon" type="image/svg+xml" href="<?= url('/favicon.svg') ?>">
  <?= css('css/style.css') ?>
</head>
<body id="<?= html($template) ?>">
  <a class="skip" href="#main"><?= t('nav.skip_to_main') ?></a>
  <?php if ($flashes): ?>
    <div class="flash" role="status" aria-live="polite">
      <?php foreach ($flashes as $flash): ?>
        <p class="<?= html($flash['type']) ?>">
          <?= html($flash['message']) ?>
        </p>
      <?php endforeach ?>
    </div>
  <?php endif ?>
  <div class="document">
    <header class="header" aria-label="<?= t('aria.site_header') ?>">
      <h1 class="header-title"><a href="<?= url() ?>"><?= html($wikiTitle) ?></a></h1>
      <nav class="header-nav" aria-label="<?= t('aria.main_navigation') ?>">
        <a href="<?= url('/search') ?>" data-shortcut="search" aria-label="<?= t('nav.search') ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
            <path d="M18.031 16.6168L22.3137 20.8995L20.8995 22.3137L16.6168 18.031C15.0769 19.263 13.124 20 11 20C6.032 20 2 15.968 2 11C2 6.032 6.032 2 11 2C15.968 2 20 6.032 20 11C20 13.124 19.263 15.0769 18.031 16.6168ZM16.0247 15.8748C17.2475 14.6146 18 12.8956 18 11C18 7.1325 14.8675 4 11 4C7.1325 4 4 7.1325 4 11C4 14.8675 7.1325 18 11 18C12.8956 18 14.6146 17.2475 15.8748 16.0247L16.0247 15.8748Z"></path>
          </svg></a>
        <details class="dropdown">
          <summary aria-label="<?= t('aria.open_menu') ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
              <path d="M5 10C3.9 10 3 10.9 3 12C3 13.1 3.9 14 5 14C6.1 14 7 13.1 7 12C7 10.9 6.1 10 5 10ZM19 10C17.9 10 17 10.9 17 12C17 13.1 17.9 14 19 14C20.1 14 21 13.1 21 12C21 10.9 20.1 10 19 10ZM12 10C10.9 10 10 10.9 10 12C10 13.1 10.9 14 12 14C13.1 14 14 13.1 14 12C14 10.9 13.1 10 12 10Z"></path>
            </svg></summary>
          <menu class="dropdown-content">
            <?php foreach ($menuItems as $menuItem): ?>
              <li><a href="<?= html((string) $menuItem['href']) ?>"<?= ((string) $menuItem['shortcut']) !== '' ? ' data-shortcut="' . html((string) $menuItem['shortcut']) . '"' : '' ?>><?= html((string) $menuItem['label']) ?></a></li>
            <?php endforeach ?>
          </menu>
        </details>
      </nav>
    </header>
    <main class="main" id="main" aria-label="<?= t('aria.main_content') ?>">
      <?= $section ?>
    </main>
    <footer class="footer" aria-label="<?= t('aria.site_footer') ?>">
      <div class="content">
        <p><?= t('copyright') ?></p>
      </div>
    </footer>
  </div>
  <?= js('js/script.js') ?>
</body>
</html>
