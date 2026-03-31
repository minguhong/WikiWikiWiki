<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.install') ?></h1>
  </header>
  <div class="section-main">
    <div class="content">
      <p><?= html((string) ($installStatusMessage ?? '')) ?></p>
      <?php if (($installReady ?? false) === true): ?>
        <p><?= t('message.install.intro') ?></p>
      <?php endif ?>
      <?php if ($installErrors !== []): ?>
        <ul>
          <?php foreach ($installErrors as $line): ?>
            <li><?= html($line) ?></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
      <?php if (($installWarnings ?? []) !== []): ?>
        <p role="alert"><?= t('message.install.warnings_title') ?></p>
        <ul>
          <?php foreach ($installWarnings as $line): ?>
            <li><?= html($line) ?></li>
          <?php endforeach ?>
        </ul>
      <?php endif ?>
    </div>
    <form class="form" action="<?= html(url('/install')) ?>" method="get">
      <div class="field">
        <label class="label" for="install_language"><?= t('settings.field.language') ?></label>
        <select class="input" id="install_language" name="language" onchange="this.form.submit()">
          <?php foreach (($languageOptions ?? []) as $code => $meta): ?>
            <option value="<?= html((string) $code) ?>"
              <?= ((string) ($selectedInstallLanguage ?? LANGUAGE) === (string) $code) ? 'selected' : '' ?>>
              <?= html((string) ($meta['name'] ?? $code)) ?>
            </option>
          <?php endforeach ?>
        </select>
        <noscript>
          <button class="button" type="submit"><?= t('button.save') ?></button>
        </noscript>
      </div>
    </form>
    <form class="form" action="<?= html(url('/install')) ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
      <input type="hidden" name="language" value="<?= html((string) ($selectedInstallLanguage ?? LANGUAGE)) ?>">
      <div class="field">
        <label class="label" for="wiki_title"><?= t('settings.field.wiki_title') ?></label>
        <input class="input" id="wiki_title" type="text" name="wiki_title"
          value="<?= html((string) (($installConfig['wiki_title'] ?? '') !== '' ? $installConfig['wiki_title'] : WIKI_TITLE)) ?>"
          maxlength="120"
          placeholder="<?= t('field.placeholder.wiki_title') ?>" required>
      </div>
      <div class="field">
        <label class="label" for="wiki_description"><?= t('settings.field.wiki_description') ?></label>
        <input class="input" id="wiki_description" type="text" name="wiki_description"
          value="<?= html((string) (($installConfig['wiki_description'] ?? '') !== '' ? $installConfig['wiki_description'] : WIKI_DESCRIPTION)) ?>"
          maxlength="300"
          placeholder="<?= t('field.placeholder.wiki_description') ?>" required>
      </div>
      <div class="field">
        <label class="label" for="home_page"><?= t('settings.field.home_page') ?></label>
        <input class="input" id="home_page" type="text" name="home_page"
          value="<?= html((string) (($installConfig['home_page'] ?? '') !== '' ? $installConfig['home_page'] : HOME_PAGE)) ?>"
          pattern="<?= html(page_title_input_pattern()) ?>"
          maxlength="<?= PAGE_TITLE_MAX_LENGTH ?>"
          placeholder="<?= t('field.placeholder.home_page') ?>" required>
        <p class="field-help"><?= sprintf(t('field.help.page_title'), page_title_forbidden_chars_help_html()) ?></p>
      </div>
      <div class="field">
        <label class="label" for="edit_permission"><?= t('settings.edit_permission_label') ?></label>
        <select class="input" id="edit_permission" name="edit_permission">
          <?php foreach (($installEditPermissionOptions ?? []) as $option): ?>
            <option value="<?= html((string) ($option['value'] ?? '')) ?>"<?= !empty($option['selected']) ? ' selected' : '' ?>>
              <?= html((string) ($option['label'] ?? '')) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="username"><?= t('field.label.username') ?></label>
        <input class="input" id="username" type="text" name="username"
          pattern="[a-z0-9]{<?= USERNAME_MIN_LENGTH ?>,<?= USERNAME_MAX_LENGTH ?>}"
          minlength="<?= USERNAME_MIN_LENGTH ?>" maxlength="<?= USERNAME_MAX_LENGTH ?>"
          value="<?= html((string) ($inputUsername ?? '')) ?>"
          placeholder="<?= t('field.placeholder.username') ?>" autocomplete="username" required autofocus>
        <p class="field-help"><?= t('field.help.username_pattern') ?></p>
      </div>
      <div class="field">
        <label class="label" for="password"><?= t('field.label.password') ?></label>
        <input class="input" id="password" type="password" name="password" autocomplete="new-password" placeholder="<?= t('field.placeholder.password') ?>" required>
        <p class="field-help"><?= sprintf(t('field.help.password_min_length'), PASSWORD_MIN_LENGTH) ?></p>
      </div>
      <div class="field">
        <label class="label" for="confirm_password"><?= t('field.label.confirm_password') ?></label>
        <input class="input" id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" placeholder="<?= t('field.placeholder.confirm_password') ?>" required>
      </div>
      <div class="field">
        <button class="button is-primary" type="submit"><?= t('button.install') ?></button>
      </div>
    </form>
  </div>
</section>
