<section class="section">
  <header class="section-header">
    <h1 class="title">
      <?= t('title.all_documents') ?>
      <small class="title-count"><?= number_format((int) $totalDocumentCount) ?></small>
    </h1>
  </header>
  <div class="section-main">
    <div class="content">
      <?php if ($letters === []): ?>
        <p><?= t('message.empty.pages') ?></p>
      <?php else: ?>
        <?php foreach ($letters as $letter => $pages): ?>
          <h2><?= html($letter) ?></h2>
          <ul data-columns>
            <?php foreach ($pages as $page): ?>
              <li>
                <a href="<?= html(url($page)) ?>"><?= html($page) ?></a>
                <?php if (isset($redirects[$page])): ?>
                  <span aria-hidden="true">→</span>
                  <a href="<?= html(url($redirects[$page])) ?>"><?= html($redirects[$page]) ?></a>
                <?php endif ?>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>
  <footer class="section-footer">
    <div class="buttons">
      <div>
        <p><?= html(sprintf(t('message.search.pagination_status'), (int) $currentPage, (int) $totalPaginationPageCount)) ?></p>
      </div>
      <?php if ((int) $totalPaginationPageCount > 1): ?>
        <div>
          <?php if ($prevPageUrl !== null): ?>
            <a class="button" href="<?= html($prevPageUrl) ?>"><?= t('button.prev') ?></a>
          <?php else: ?>
            <button class="button" type="button" disabled><?= t('button.prev') ?></button>
          <?php endif ?>
          <?php if ($nextPageUrl !== null): ?>
            <a class="button" href="<?= html($nextPageUrl) ?>"><?= t('button.next') ?></a>
          <?php else: ?>
            <button class="button" type="button" disabled><?= t('button.next') ?></button>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>
  </footer>
</section>
