<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.discover') ?></h1>
  </header>
  <div class="section-main">
    <div class="content">
      <h2><?= t('title.recent') ?></h2>
      <?php if ($recentPages): ?>
        <ul data-columns>
          <?php foreach ($recentPages as $page): ?>
            <li>
              <a href="<?= html(url($page['title'])) ?>"><?= html($page['title']) ?></a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <p><?= t('message.empty.pages') ?></p>
      <?php endif ?>
      <h2><?= t('title.random') ?></h2>
      <?php if ($randomPages): ?>
        <ul data-columns>
          <?php foreach ($randomPages as $page): ?>
            <li>
              <a href="<?= html(url($page['title'])) ?>"><?= html($page['title']) ?></a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if ($stubPages): ?>
        <h2><?= t('title.stub') ?></h2>
        <ul data-columns>
          <?php foreach ($stubPages as $item): ?>
            <li><a href="<?= html(url($item['title'])) ?>"><?= html($item['title']) ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if ($orphanedPages): ?>
        <h2><?= t('title.orphaned_documents') ?></h2>
        <ul data-columns>
          <?php foreach ($orphanedPages as $orphanedPage): ?>
            <li><a href="<?= html(url($orphanedPage)) ?>"><?= html($orphanedPage) ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if ($redirectPages): ?>
        <h2><?= t('title.redirect_documents') ?></h2>
        <ul data-columns>
          <?php foreach ($redirectPages as $redirectPage): ?>
            <li>
              <a href="<?= html(url((string) ($redirectPage['title'] ?? ''))) ?>"><?= html((string) ($redirectPage['title'] ?? '')) ?></a>
              <span aria-hidden="true">→</span>
              <a href="<?= html(url((string) ($redirectPage['target'] ?? ''))) ?>"><?= html((string) ($redirectPage['target'] ?? '')) ?></a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if ($wantedPages): ?>
        <h2><?= t('title.wanted') ?></h2>
        <ul data-columns>
          <?php foreach ($wantedPages as $wantedPage): ?>
            <li>
              <a class="not-exists" href="<?= html(url($wantedPage['title'])) ?>"><?= html($wantedPage['title']) ?></a>
              <span aria-hidden="true">→</span>
              <a href="<?= html(url($wantedPage['title'], '/backlinks')) ?>"><?= t('title.backlinks') ?></a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <h2><?= t('title.all_documents') ?></h2>
      <?php if ($allPages): ?>
        <ul data-columns>
          <?php foreach ($allPages as $page): ?>
            <li>
              <a href="<?= html(url($page)) ?>"><?= html($page) ?></a>
            </li>
          <?php endforeach ?>
          <li><a href="<?= html(url('/all')) ?>" aria-label="<?= t('title.all_documents') ?>">⋯</a></li>
        </ul>
      <?php endif ?>
      <?php if ($allTags): ?>
        <h2><?= t('title.all_tags') ?></h2>
        <ul data-columns>
          <?php foreach ($allTags as $tag): ?>
            <li><?= render_tag_link($tag) ?></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <h2><?= t('title.subscribe') ?></h2>
      <ul>
        <li><a href="<?= html(url('/rss.xml')) ?>"><?= t('nav.rss') ?></a></li>
        <li><a href="<?= html(url('/atom.xml')) ?>"><?= t('nav.atom') ?></a></li>
        <li><a href="<?= html(url('/feed.json')) ?>"><?= t('nav.json_feed') ?></a></li>
      </ul>
    </div>
  </div>
</section>
