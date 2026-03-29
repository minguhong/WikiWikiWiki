<section class="section">
  <header class="section-header">
    <?php if ($isNew): ?>
      <h1 class="title" data-label="<?= t('title.new') ?>">
        <?= html($page) ?>
      </h1>
    <?php else: ?>
      <h1 class="title" data-label="<?= t('title.edit') ?>">
        <?= html($page) ?>
      </h1>
    <?php endif ?>
  </header>
  <div class="section-main">
    <form class="form is-fill" id="form" action="<?= url($page) ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
      <input type="hidden" name="original_revision" value="<?= html($revision ?? '') ?>">
      <input type="hidden" name="delete_history" value="0" id="delete-history-field">
      <?php if (!$isNew && $page !== HOME_PAGE): ?>
        <div class="field">
          <label class="hidden" for="new-title"><?= t('field.label.page_title') ?></label>
          <input class="input" id="new-title" type="text" name="new_title" value="<?= html((string) ($newTitleInput ?? $page)) ?>" pattern="[^\x5C\x3C\x3E\x3A\x22\x23\x25\x7C\x3F\x2A\x5B\x5D]+" maxlength="<?= PAGE_TITLE_MAX_LENGTH ?>" placeholder="<?= t('field.placeholder.page_title') ?>">
          <p class="field-help"><?= t('field.help.page_title') ?></p>
        </div>
      <?php endif ?>
      <?php if (isset($conflict) && $conflict): ?>
        <div class="field is-fill" aria-live="polite">
          <div class="content">
            <p role="alert"><?= t($existingNoticeKey) ?></p>
          </div>
          <div class="field is-fill">
            <label class="hidden" for="conflict-content"><?= t('message.edit.existing_content') ?></label>
            <label class="label" for="conflict-content">
              <?= t('message.edit.existing_content') ?>
              <?php if ($modifiedAt !== null): ?>
                (<?= t('message.edit.conflict_modified') ?>: <?= html(date('Y-m-d H:i:s', (int) $modifiedAt)) ?>)
              <?php endif ?>
            </label>
            <textarea class="textarea" id="conflict-content" readonly><?= html($currentContent ?? '') ?></textarea>
          </div>
        </div>
      <?php endif ?>
      <div class="field is-fill">
        <?php if (isset($conflict) && $conflict): ?>
          <label class="label" for="content"><?= t('message.edit.your_content') ?></label>
        <?php else: ?>
          <label class="hidden" for="content"><?= t('field.label.content') ?></label>
        <?php endif ?>
        <textarea class="textarea" id="content" name="content" placeholder="<?= t('field.placeholder.content') ?>" spellcheck="false" autofocus><?= html($content ?? '') ?></textarea>
        <p class="field-help"><?= t('field.help.content') ?></p>
      </div>
      <div class="field is-sticky">
        <div class="buttons">
          <div>
            <a class="button" id="button-cancel" href="<?= $isNew ? url('/') : url($page) ?>"><?= t('button.cancel') ?></a>
          </div>
          <div>
            <button class="button is-primary" id="button-save" type="submit" data-skip-confirm="1" data-confirm-message="<?= html(t('message.confirm.delete_page')) ?>" data-confirm-history-message="<?= html(t('message.confirm.delete_history')) ?>" data-can-delete-history="<?= is_admin() ? '1' : '0' ?>"><?= t('button.save') ?></button>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>
