<section class="section">
  <header class="section-header">
    <?php if ($hasQuery): ?>
      <h1 class="title" data-label="<?= t('title.search') ?>">
        <?= html($query) ?>
        <?php if ($hasResults): ?>
          <small class="title-count"><?= number_format((int) $resultCount) ?></small>
        <?php endif ?>
      </h1>
    <?php else: ?>
      <h1 class="title">
        <?= t('title.search') ?>
      </h1>
    <?php endif ?>
  </header>
  <div class="section-main">
    <form class="form" action="<?= html(url('/search')) ?>" method="get" role="search">
      <div class="fields">
        <div class="field">
          <label class="hidden" for="search"><?= t('field.label.search') ?></label>
          <input class="input" id="search" type="text" name="q" value="<?= html($query) ?>" placeholder="<?= t('field.placeholder.search') ?>" autofocus required>
        </div>
        <div class="field">
          <label class="hidden" for="scope"><?= t('field.label.search_scope') ?></label>
          <select class="input" id="scope" name="scope">
            <?php foreach ($scopeOptions as $scopeValue => $scopeLabel): ?>
              <option value="<?= html((string) $scopeValue) ?>" <?= $scope === $scopeValue ? 'selected' : '' ?>><?= html((string) $scopeLabel) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="field">
        <button class="button" id="search-submit" type="submit"><?= t('button.search') ?></button>
      </div>
    </form>
    <?php if (!$hasQuery): ?>
      <?php if ($recentPages !== []): ?>
        <div class="content">
          <h2><?= t('title.recent') ?></h2>
          <ul data-columns>
            <?php foreach ($recentPages as $page): ?>
              <li>
                <a href="<?= html(url($page['title'])) ?>"><?= html($page['title']) ?></a>
              </li>
            <?php endforeach ?>
          </ul>
        </div>
      <?php endif ?>
      <?php if ($allPages !== []): ?>
        <div class="content">
          <h2><?= t('title.all_documents') ?></h2>
          <ul data-columns>
            <?php foreach ($allPages as $page): ?>
              <li>
                <a href="<?= html(url($page)) ?>"><?= html($page) ?></a>
              </li>
            <?php endforeach ?>
            <li><a href="<?= html(url('/all')) ?>" aria-label="<?= t('title.all_documents') ?>">⋯</a></li>
          </ul>
        </div>
      <?php endif ?>
    <?php endif ?>
    <?php if ($hasQuery): ?>
      <div class="content" aria-live="polite">
        <?php if ($hasResults): ?>
          <ul>
            <?php foreach ($results as $result): ?>
              <li>
                <div class="content">
                  <?php if (((string) ($result['parentPath'] ?? '')) !== ''): ?>
                    <a href="<?= html(url((string) $result['parentPath'])) ?>"><?= html((string) $result['parentPath']) ?></a>
                    <span aria-hidden="true">/</span>
                  <?php endif ?>
                  <a href="<?= html(url($result['title'])) ?>"><?= html($result['title']) ?></a>
                  <?php if (((string) ($result['redirectTarget'] ?? '')) !== ''): ?>
                    <span aria-hidden="true">→</span>
                    <a href="<?= html(url((string) $result['redirectTarget'])) ?>"><?= html((string) $result['redirectTarget']) ?></a>
                  <?php endif ?>
                </div>
                <?php if ($result['snippet']): ?>
                  <div class="content">
                    <p><?= $result['snippet'] ?></p>
                  </div>
                <?php endif ?>
              </li>
            <?php endforeach ?>
          </ul>
        <?php else: ?>
          <p><?= t('message.empty.search_results') ?></p>
          <?php if ($randomPages !== []): ?>
            <h2><?= t('title.random') ?></h2>
            <ul data-columns>
              <?php foreach ($randomPages as $page): ?>
                <li>
                  <a href="<?= html(url($page['title'])) ?>"><?= html($page['title']) ?></a>
                </li>
              <?php endforeach ?>
            </ul>
          <?php endif ?>
        <?php endif ?>
      </div>
    <?php endif ?>
  </div>
</section>
