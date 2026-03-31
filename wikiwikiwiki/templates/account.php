<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.account') ?></h1>
  </header>
  <div class="section-main">
    <div class="tabs" role="tablist" data-query-key="tab">
      <button class="tab" type="button" role="tab" data-target="account-info" aria-selected="<?= $activeTab === 'account-info' ? 'true' : 'false' ?>"><?= t('account.info') ?></button>
      <button class="tab" type="button" role="tab" data-target="account-change-password" aria-selected="<?= $activeTab === 'account-change-password' ? 'true' : 'false' ?>"><?= t('account.change_password') ?></button>
      <button class="tab" type="button" role="tab" data-target="account-delete-account" aria-selected="<?= $activeTab === 'account-delete-account' ? 'true' : 'false' ?>"><?= t('account.delete_account') ?></button>
    </div>

    <div class="tab-content" data-id="account-info"<?= $activeTab === 'account-info' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('account.info') ?></h3>
      <form class="form">
        <div class="field">
          <label class="label" for="username"><?= t('field.label.username') ?></label>
          <input class="input" id="username" type="text" name="username" value="<?= html($username) ?>" placeholder="<?= t('field.placeholder.username') ?>" disabled>
        </div>
        <div class="field">
          <label class="label" for="role"><?= t('field.label.role') ?></label>
          <input class="input" id="role" type="text" name="role" value="<?= $role === 'admin' ? t('account.role_admin') : t('account.role_editor') ?>" placeholder="<?= t('field.placeholder.role') ?>" disabled>
        </div>
      </form>
    </div>

    <div class="tab-content" data-id="account-change-password"<?= $activeTab === 'account-change-password' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('account.change_password') ?></h3>
      <form class="form" action="<?= html(url('/account')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="tab" value="account-change-password">
        <input class="hidden" type="text" name="username_hint" value="<?= html($username) ?>" placeholder="<?= t('field.placeholder.username') ?>" autocomplete="username" tabindex="-1" aria-hidden="true">
        <div class="field">
          <label class="label" for="current_password"><?= t('field.label.current_password') ?></label>
          <input class="input" id="current_password" type="password" name="current_password" autocomplete="current-password" placeholder="<?= t('field.placeholder.password') ?>" required>
        </div>
        <div class="field">
          <label class="label" for="new_password"><?= t('field.label.new_password') ?></label>
          <input class="input" id="new_password" type="password" name="new_password" autocomplete="new-password" placeholder="<?= t('field.placeholder.password') ?>" required>
          <p class="field-help"><?= sprintf(t('field.help.password_min_length'), PASSWORD_MIN_LENGTH) ?></p>
        </div>
        <div class="field">
          <label class="label" for="confirm_password"><?= t('field.label.confirm_password') ?></label>
          <input class="input" id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" placeholder="<?= t('field.placeholder.confirm_password') ?>" required>
        </div>
        <div class="field">
          <button class="button" type="submit"><?= t('account.change_password') ?></button>
        </div>
      </form>
    </div>

    <div class="tab-content" data-id="account-delete-account"<?= $activeTab === 'account-delete-account' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('account.delete_account') ?></h3>
      <form class="form" action="<?= html(url('/account')) ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="action" value="delete_account">
        <input type="hidden" name="tab" value="account-delete-account">
        <div class="field">
          <label class="label" for="delete_password"><?= t('field.label.current_password') ?></label>
          <input class="input" id="delete_password" type="password" name="delete_password" autocomplete="current-password" placeholder="<?= t('field.placeholder.password') ?>" required>
        </div>
        <div class="field">
          <button class="button is-danger" type="submit"
            data-confirm-message="<?= html(t('message.confirm.delete_account')) ?>"><?= t('account.delete_account') ?></button>
        </div>
      </form>
    </div>
  </div>
</section>
