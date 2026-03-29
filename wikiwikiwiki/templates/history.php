<section class="section">
  <header class="section-header">
    <h1 class="title" data-label="<?= t('title.history') ?>">
      <a href="<?= url($page) ?>"><?= html($page) ?></a>
    </h1>
  </header>
  <div class="section-main">
    <?php if (isset($versions)): ?>
      <?php if ($versions): ?>
        <div class="table">
          <table>
            <thead>
              <tr>
                <th><?= t('history.date') ?></th>
                <th><?= t('history.author') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($versions as $version): ?>
                <tr>
                  <?php if ($version['author'] === '_deleted'): ?>
                    <td><?= html($version['date']) ?></td>
                    <td><?= t('message.edit.history_deleted') ?></td>
                  <?php else: ?>
                    <td>
                      <a href="<?= url($page, '/history/' . $version['timestamp']) ?>"><?= html($version['date']) ?></a>
                    </td>
                    <td>
                      <?= $version['author'] !== '' ? html($version['author']) : t('history.unknown_author') ?>
                    </td>
                  <?php endif ?>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="content">
          <p><?= t('message.empty.history') ?></p>
        </div>
      <?php endif ?>
    <?php else: ?>
      <h2 class="table-title">
        <span><?= html($date) ?></span>
        <span aria-hidden="true">·</span>
        <span><?= $author !== '' ? html($author) : t('history.unknown_author') ?></span>
      </h2>
      <?php if ($diffTooLarge): ?>
        <div class="content">
          <p><?= t('message.edit.diff_too_large') ?></p>
        </div>
      <?php endif ?>
      <?php if (is_array($diff) && $diff !== [] && !$diffTooLarge): ?>
        <div class="table">
          <table class="diff">
            <?php foreach ($diff as $line): ?>
              <tr class="diff-<?= html((string) $line['type']) ?>">
                <td class="diff-sign"><?= $line['type'] === 'add' ? '+' : ($line['type'] === 'remove' ? '−' : '') ?></td>
                <td class="diff-line"><?= html($line['line']) ?></td>
              </tr>
            <?php endforeach ?>
          </table>
        </div>
      <?php endif ?>
    <?php endif ?>
  </div>
  <footer class="section-footer">
    <?php if (isset($versions)): ?>
      <a class="button" href="<?= url($page) ?>"><?= t('button.back') ?></a>
    <?php else: ?>
      <div class="buttons">
        <div>
          <a class="button" href="<?= url($page, '/history') ?>"><?= t('button.back') ?></a>
        </div>
        <div>
          <?php if ($olderTimestamp): ?>
            <a class="button" href="<?= url($page, '/history/' . $olderTimestamp) ?>"><?= t('button.prev') ?></a>
          <?php else: ?>
            <button class="button" type="button" disabled><?= t('button.prev') ?></button>
          <?php endif ?>
          <?php if ($newerTimestamp): ?>
            <a class="button" href="<?= url($page, '/history/' . $newerTimestamp) ?>"><?= t('button.next') ?></a>
          <?php else: ?>
            <button class="button" type="button" disabled><?= t('button.next') ?></button>
          <?php endif ?>
          <?php if (is_admin()): ?>
            <form action="<?= url($page, '/history/' . $timestamp) ?>" method="post">
              <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
              <input type="hidden" name="action" value="delete_version">
              <button class="button is-danger" type="submit" data-confirm-message="<?= html(t('message.confirm.delete_history_version')) ?>"><?= t('button.delete') ?></button>
            </form>
          <?php endif ?>
          <?php if (is_admin() && $newerTimestamp !== null): ?>
            <form action="<?= url($page, '/history/' . $timestamp) ?>" method="post">
              <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
              <input type="hidden" name="action" value="restore">
              <button class="button is-primary" type="submit" data-confirm-message="<?= html(t('message.confirm.restore')) ?>"><?= t('button.restore') ?></button>
            </form>
          <?php endif ?>
        </div>
      </div>
    <?php endif ?>
  </footer>
</section>
