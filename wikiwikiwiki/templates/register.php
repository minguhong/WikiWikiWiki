<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.register') ?></h1>
  </header>
  <div class="section-main">
    <form class="form" action="<?= html(url('/register')) ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
      <?= honeypot_field() ?>
      <div class="field">
        <label class="label" for="username"><?= t('field.label.username') ?></label>
        <input class="input" id="username" type="text" name="username"
          pattern="[a-z0-9]{<?= USERNAME_MIN_LENGTH ?>,<?= USERNAME_MAX_LENGTH ?>}"
          minlength="<?= USERNAME_MIN_LENGTH ?>" maxlength="<?= USERNAME_MAX_LENGTH ?>"
          value="<?= html((string) ($inputUsername ?? '')) ?>"
          placeholder="<?= t('field.placeholder.username') ?>" autocomplete="username" required autofocus>
        <p class="field-help"><?= t('field.help.username_pattern') ?></p>
      </div>
      <div class="fields">
        <div class="field">
          <label class="label" for="password"><?= t('field.label.password') ?></label>
          <input class="input" id="password" type="password" name="password" autocomplete="new-password" placeholder="<?= t('field.placeholder.password') ?>" required>
          <p class="field-help"><?= sprintf(t('field.help.password_min_length'), PASSWORD_MIN_LENGTH) ?></p>
        </div>
        <div class="field">
          <label class="label" for="confirm_password"><?= t('field.label.confirm_password') ?></label>
          <input class="input" id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" placeholder="<?= t('field.placeholder.confirm_password') ?>" required>
        </div>
      </div>
      <div class="field">
        <button class="button is-primary" type="submit"><?= t('button.register') ?></button>
      </div>
    </form>
  </div>
</section>
