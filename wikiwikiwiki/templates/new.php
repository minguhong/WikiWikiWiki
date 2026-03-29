<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.new') ?></h1>
  </header>
  <div class="section-main">
    <form class="form is-fill" id="form" method="post">
      <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
      <div class="field">
        <label class="hidden" for="title"><?= t('field.label.page_title') ?></label>
        <input class="input" id="title" type="text" name="title" pattern="[^\x5C\x3C\x3E\x3A\x22\x23\x25\x7C\x3F\x2A\x5B\x5D]+" maxlength="<?= PAGE_TITLE_MAX_LENGTH ?>" value="<?= html($inputTitle ?? '') ?>" placeholder="<?= t('field.placeholder.page_title') ?>" spellcheck="false" required autofocus>
        <p class="field-help"><?= t('field.help.page_title') ?></p>
      </div>
      <div class="field is-fill">
        <label class="hidden" for="content"><?= t('field.label.content') ?></label>
        <textarea class="textarea" id="content" name="content" placeholder="<?= t('field.placeholder.content') ?>" spellcheck="false" required><?= html($inputContent ?? '') ?></textarea>
        <p class="field-help"><?= t('field.help.content') ?></p>
      </div>
      <div class="field is-sticky">
        <div class="buttons">
          <div>
            <a class="button" id="button-cancel" href="<?= url('/') ?>"><?= t('button.cancel') ?></a>
          </div>
          <div>
            <button class="button is-primary" id="button-save" type="submit"><?= t('button.save') ?></button>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>
