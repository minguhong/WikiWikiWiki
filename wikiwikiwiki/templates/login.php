<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.login') ?></h1>
  </header>
  <div class="section-main">
    <form class="form" action="<?= html((string) ($loginActionUrl ?? url('/login'))) ?>" method="post">
      <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
      <div class="field">
        <label class="label" for="username"><?= t('field.label.username') ?></label>
        <input class="input" id="username" type="text" name="username"
          pattern="[a-z0-9]{<?= USERNAME_MIN_LENGTH ?>,<?= USERNAME_MAX_LENGTH ?>}"
          minlength="<?= USERNAME_MIN_LENGTH ?>" maxlength="<?= USERNAME_MAX_LENGTH ?>"
          value="<?= html((string) ($inputUsername ?? '')) ?>"
          placeholder="<?= t('field.placeholder.username') ?>" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label class="label" for="password"><?= t('field.label.password') ?></label>
        <input class="input" id="password" type="password" name="password" autocomplete="current-password" placeholder="<?= t('field.placeholder.password') ?>" required>
      </div>
      <div class="field">
        <button class="button is-primary" id="button-login" type="submit"><?= t('button.login') ?></button>
      </div>
    </form>
  </div>
</section>
