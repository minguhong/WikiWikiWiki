<section class="section">
  <header class="section-header">
    <h1 class="title">
      <a href="<?= url($page, '/backlinks') ?>"><?= html($page) ?></a>
    </h1>
  </header>
  <div class="section-main">
    <div class="content">
      <?php if (isset($redirectTarget)): ?>
        <p><?= t('message.page.redirect') ?> <a href="<?= url($redirectTarget) ?>"><?= html($redirectTarget) ?></a></p>
        <?php if ($content): ?>
          <hr>
          <?= $content ?>
        <?php endif ?>
      <?php else: ?>
        <?= $content ?>
      <?php endif ?>
      <?php if ($parent || $siblings || $children): ?>
        <?php if ($parent): ?>
          <h2><?= t('title.parent') ?></h2>
          <ul data-columns>
            <li><a class="<?= page_exists($parent) ? 'exists' : 'not-exists' ?>" href="<?= url($parent) ?>"><?= html($parent) ?></a></li>
          </ul>
        <?php endif ?>
        <?php if ($siblings): ?>
          <h2><?= t('title.siblings') ?></h2>
          <ul data-columns>
            <?php foreach ($siblings as $sibling): ?>
              <li><a href="<?= url($sibling) ?>"><?= html($sibling) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
        <?php if ($children): ?>
          <h2><?= t('title.children') ?></h2>
          <ul data-columns>
            <?php foreach ($children as $child): ?>
              <li><a href="<?= url($child) ?>"><?= html($child) ?></a></li>
            <?php endforeach ?>
          </ul>
        <?php endif ?>
      <?php endif ?>
      <?php if ($modifiedAt !== null): ?>
        <footer>
          <time datetime="<?= date('c', $modifiedAt) ?>"><?= date('Y-m-d H:i:s', $modifiedAt) ?></time>
          <span aria-hidden="true">·</span>
          <a href="<?= url($page, '.txt') ?>"><?= html($sourceFile) ?></a>
        </footer>
      <?php endif ?>
    </div>
  </div>
  <footer class="section-footer">
    <div class="buttons">
      <div>
        <a class="button" href="<?= url($page, '/history') ?>"><?= t('button.history') ?></a>
      </div>
      <?php if (can_edit_pages()): ?>
        <div>
          <a class="button is-primary" id="button-edit" data-shortcut="edit" href="<?= url($page, '/edit') ?>"><?= t('button.edit') ?></a>
        </div>
      <?php endif ?>
    </div>
  </footer>
</section>
