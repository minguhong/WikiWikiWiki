<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('message.page.not_found') ?></h1>
  </header>
  <div class="section-main">
    <div class="content">
      <p><?= html(sprintf(t('message.page.not_exists_404'), (string) ($requestedPage ?? ''))) ?></p>
      <?php if ($randomPages !== []): ?>
        <h2><?= t('title.random') ?></h2>
        <ul data-columns>
          <?php foreach ($randomPages as $randomPage): ?>
            <li><a href="<?= html(url($randomPage['title'])) ?>"><?= html($randomPage['title']) ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
  </div>
</section>
