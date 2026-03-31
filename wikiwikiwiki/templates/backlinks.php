<section class="section">
  <header class="section-header">
    <h1 class="title" data-label="<?= t('title.backlinks') ?>">
      <a href="<?= html(url($page)) ?>"><?= html($page) ?></a>
    </h1>
  </header>
  <div class="section-main">
    <div class="content">
      <?php if ($backlinks): ?>
        <h2><?= t('title.related') ?></h2>
        <ul data-columns>
          <?php foreach ($backlinks as $backlink): ?>
            <li>
              <a href="<?= html(url($backlink)) ?>"><?= html($backlink) ?></a>
            </li>
          <?php endforeach ?>
        </ul>
      <?php else: ?>
        <p><?= t('message.empty.backlinks') ?></p>
      <?php endif ?>
      <?php if ($parent || $siblings || $children): ?>
        <?php if ($parent): ?>
          <h2><?= t('title.parent') ?></h2>
          <ul data-columns>
            <li><a class="<?= page_exists($parent) ? 'exists' : 'not-exists' ?>" href="<?= html(url($parent)) ?>"><?= html($parent) ?></a></li>
          </ul>
        <?php endif ?>
        <?php if ($siblings): ?>
          <h2><?= t('title.siblings') ?></h2>
          <ul data-columns>
            <?php foreach ($siblings as $sibling): ?>
              <li><a href="<?= html(url($sibling)) ?>"><?= html($sibling) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
        <?php if ($children): ?>
          <h2><?= t('title.children') ?></h2>
          <ul data-columns>
            <?php foreach ($children as $child): ?>
              <li><a href="<?= html(url($child)) ?>"><?= html($child) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      <?php endif ?>
    </div>
  </div>
  <footer class="section-footer">
    <a class="button" href="<?= html(url($page)) ?>"><?= t('button.back') ?></a>
  </footer>
</section>
