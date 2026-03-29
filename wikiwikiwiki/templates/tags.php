<section class="section">
  <header class="section-header">
    <h1 class="title" data-label="<?= t('title.tags') ?>">
      #<?= html($tag) ?>
    </h1>
  </header>
  <div class="section-main">
    <div class="content">
      <?php if ($tagPages): ?>
        <h2><?= t('title.related') ?></h2>
        <ul data-columns>
          <?php foreach ($tagPages as $page): ?>
            <li><a href="<?= url($page) ?>"><?= html($page) ?></a></li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <p><?= t('message.empty.tag_pages') ?></p>
      <?php endif ?>
      <?php if ($allTags): ?>
        <h2><?= t('title.all_tags') ?></h2>
        <ul data-columns>
          <?php foreach ($allTags as $tagItem): ?>
            <li><?= render_tag_link($tagItem) ?></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
  </div>
</section>
