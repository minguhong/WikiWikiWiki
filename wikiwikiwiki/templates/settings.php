<section class="section">
  <header class="section-header">
    <h1 class="title"><?= t('title.settings') ?></h1>
  </header>
  <div class="section-main">
    <div class="tabs" role="tablist" data-query-key="tab">
      <button class="tab" type="button" role="tab" data-target="settings-basic" aria-selected="<?= $activeTab === 'basic' ? 'true' : 'false' ?>"><?= t('settings.tab_basic') ?></button>
      <button class="tab" type="button" role="tab" data-target="settings-edit" aria-selected="<?= $activeTab === 'edit' ? 'true' : 'false' ?>"><?= t('settings.tab_edit') ?></button>
      <button class="tab" type="button" role="tab" data-target="settings-documents" aria-selected="<?= $activeTab === 'documents' ? 'true' : 'false' ?>"><?= t('settings.tab_documents') ?></button>
      <button class="tab" type="button" role="tab" data-target="settings-users" aria-selected="<?= $activeTab === 'users' ? 'true' : 'false' ?>"><?= t('settings.tab_users') ?></button>
      <button class="tab" type="button" role="tab" data-target="settings-system" aria-selected="<?= $activeTab === 'system' ? 'true' : 'false' ?>"><?= t('settings.tab_system') ?></button>
    </div>

    <div class="tab-content" data-id="settings-basic" <?= $activeTab === 'basic' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('settings.default') ?></h3>
      <form class="form" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="tab" value="basic">
        <?php foreach ($basicFields as $field): ?>
          <div class="field">
            <label class="label" for="<?= html((string) $field['id']) ?>"><?= html((string) $field['label']) ?></label>
            <?php if ((string) ($field['kind'] ?? 'input') === 'select'): ?>
              <select class="input" id="<?= html((string) $field['id']) ?>" name="<?= html((string) $field['id']) ?>"<?= !empty($field['required']) ? ' required' : '' ?>>
                <?php foreach (($field['options'] ?? []) as $option): ?>
                  <option value="<?= html((string) ($option['value'] ?? '')) ?>"<?= !empty($option['selected']) ? ' selected' : '' ?>><?= html((string) ($option['label'] ?? '')) ?></option>
                <?php endforeach ?>
              </select>
            <?php else: ?>
              <input class="input" type="<?= html((string) ($field['inputType'] ?? 'text')) ?>" id="<?= html((string) $field['id']) ?>" name="<?= html((string) $field['id']) ?>" value="<?= html((string) ($field['value'] ?? '')) ?>"<?= ((string) ($field['pattern'] ?? '')) !== '' ? ' pattern="' . html((string) $field['pattern']) . '"' : '' ?><?= ((int) ($field['maxlength'] ?? 0)) > 0 ? ' maxlength="' . (int) $field['maxlength'] . '"' : '' ?><?= ((string) ($field['placeholder'] ?? '')) !== '' ? ' placeholder="' . html((string) $field['placeholder']) . '"' : '' ?><?= ((string) ($field['spellcheck'] ?? '')) !== '' ? ' spellcheck="' . html((string) $field['spellcheck']) . '"' : '' ?><?= !empty($field['required']) ? ' required' : '' ?>>
            <?php endif ?>
            <?php if (((string) ($field['help'] ?? '')) !== ''): ?>
              <p class="field-help"><?= (string) $field['help'] ?></p>
            <?php endif ?>
          </div>
        <?php endforeach ?>
        <div class="field">
          <div class="buttons">
            <button class="button" type="submit" name="action" value="save_config"><?= t('button.save') ?></button>
          </div>
        </div>
      </form>
    </div>

    <div class="tab-content" data-id="settings-edit" <?= $activeTab === 'edit' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('settings.edit_permission_label') ?></h3>
      <form class="form" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="tab" value="edit">
        <div class="field">
          <label class="label" for="edit_permission"><?= t('settings.edit_permission_label') ?></label>
          <select class="input" id="edit_permission" name="edit_permission">
            <?php foreach ($editPermissionOptions as $option): ?>
              <option value="<?= html((string) ($option['value'] ?? '')) ?>"<?= !empty($option['selected']) ? ' selected' : '' ?>><?= html((string) ($option['label'] ?? '')) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field">
          <button class="button" type="submit" name="action" value="update_edit_permission"><?= t('button.save') ?></button>
        </div>
      </form>
    </div>

    <div class="tab-content" data-id="settings-documents" <?= $activeTab === 'documents' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('settings.stats') ?></h3>
      <div class="table">
        <table>
          <tbody>
            <tr>
              <td><?= t('settings.total_pages') ?></td>
              <td><?= number_format((int) $pageCount) ?></td>
            </tr>
            <tr>
              <td><?= t('settings.total_tags') ?></td>
              <td><?= number_format((int) $tagCount) ?></td>
            </tr>
            <tr>
              <td><?= t('settings.content_size') ?></td>
              <td><?= format_bytes((int) $contentSize) ?></td>
            </tr>
            <tr>
              <td><?= t('settings.history_size') ?></td>
              <td><?= format_bytes((int) $historySize) ?></td>
            </tr>
            <tr>
              <td><?= t('settings.cache_size') ?></td>
              <td><?= format_bytes((int) $cacheSize) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <h3 class="tab-title"><?= t('settings.cleanup_history') ?></h3>
      <form class="form" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="tab" value="documents">
        <div class="field">
          <div class="buttons">
            <button class="button" type="submit" name="action" value="cleanup_document_history" data-confirm-message="<?= html(t('settings.cleanup_history_confirm')) ?>"><?= t('settings.cleanup_history_button') ?></button>
          </div>
          <p class="field-help"><?= t('settings.cleanup_history_help') ?></p>
        </div>
      </form>
      <h3 class="tab-title"><?= t('settings.export_documents') ?></h3>
      <form class="form" method="post">
        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
        <input type="hidden" name="tab" value="documents">
        <div class="buttons">
          <button class="button" type="submit" name="action" value="export_documents"><?= t('settings.export_zip') ?></button>
        </div>
      </form>
    </div>

    <div class="tab-content" data-id="settings-users" <?= $activeTab === 'users' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('settings.stats') ?></h3>
      <div class="table">
        <table>
          <tbody>
            <tr>
              <td><?= t('settings.total_users') ?></td>
              <td><?= number_format((int) $userCount) ?></td>
            </tr>
            <tr>
              <td><?= t('settings.user_size') ?></td>
              <td><?= format_bytes((int) $userSize) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <?php if ($userRows !== []): ?>
        <div class="table">
          <table>
            <thead>
              <tr>
                <th><?= t('settings.username') ?></th>
                <th><?= t('settings.role_label') ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($userRows as $userRow): ?>
                <tr>
                  <td><?= html((string) ($userRow['username'] ?? '')) ?></td>
                  <td><?= html((string) ($userRow['roleLabel'] ?? '')) ?></td>
                  <td>
                    <?php if (!empty($userRow['canDelete'])): ?>
                      <form class="form" method="post">
                        <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
                        <input type="hidden" name="tab" value="users">
                        <input type="hidden" name="username" value="<?= html((string) ($userRow['username'] ?? '')) ?>">
                        <button class="button is-danger" type="submit" name="action" value="delete_user" data-confirm-message="<?= html(t('settings.confirm_delete_user')) ?>"><?= t('settings.delete_user') ?></button>
                      </form>
                    <?php endif ?>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p><?= t('message.empty.users') ?></p>
      <?php endif ?>

      <?php if ($showUserCreateForm): ?>
        <h3 class="tab-title"><?= t('settings.add_user_title') ?></h3>
        <form class="form" method="post">
          <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
          <input type="hidden" name="tab" value="users">
          <div class="fields">
            <div class="field">
              <label class="label" for="username"><?= t('field.label.username') ?></label>
              <input class="input" type="text" id="username" name="username" pattern="[a-z0-9]{<?= USERNAME_MIN_LENGTH ?>,<?= USERNAME_MAX_LENGTH ?>}" minlength="<?= USERNAME_MIN_LENGTH ?>" maxlength="<?= USERNAME_MAX_LENGTH ?>" autocomplete="off" value="<?= html($inputNewUsername) ?>" placeholder="<?= t('field.placeholder.username') ?>" required>
              <p class="field-help"><?= t('field.help.username_pattern') ?></p>
            </div>
            <div class="field">
              <label class="label" for="password"><?= t('field.label.password') ?></label>
              <input class="input" type="password" id="password" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN_LENGTH ?>" placeholder="<?= t('field.placeholder.password') ?>" required>
              <p class="field-help"><?= sprintf(t('field.help.password_min_length'), PASSWORD_MIN_LENGTH) ?></p>
            </div>
          </div>
          <div class="field">
            <button class="button" type="submit" name="action" value="create_user"><?= t('settings.add_user') ?></button>
          </div>
        </form>
      <?php endif ?>
    </div>

    <div class="tab-content" data-id="settings-system" <?= $activeTab === 'system' ? '' : ' hidden' ?>>
      <h3 class="tab-title"><?= t('settings.system') ?></h3>
      <div class="table">
        <table>
          <thead>
            <tr>
              <th><?= t('settings.system.col_check') ?></th>
              <th><?= t('settings.system.col_status') ?></th>
              <th><?= t('settings.system.col_result') ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= t('settings.system.version') ?></td>
              <td><?= t('settings.system.status_ok') ?></td>
              <td><?= html(wiki_version()) ?></td>
            </tr>
            <?php foreach ($systemRows as $systemRow): ?>
              <tr>
                <td><?= settings_system_message_html((string) ($systemRow['label'] ?? '')) ?></td>
                <td><?= html((string) ($systemRow['statusText'] ?? '')) ?></td>
                <td><?= settings_system_message_html((string) ($systemRow['message'] ?? '')) ?></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php if ($showSessionCleanupAction): ?>
        <form class="form" method="post">
          <input type="hidden" name="csrf_token" value="<?= html($csrfToken) ?>">
          <input type="hidden" name="tab" value="system">
          <div class="field">
            <div class="buttons">
              <button class="button" type="submit" name="action" value="cleanup_expired_sessions" data-confirm-message="<?= html(t('settings.system.cleanup_confirm')) ?>"><?= t('settings.system.cleanup_button') ?></button>
            </div>
            <p class="field-help"><?= t('settings.system.cleanup_help') ?></p>
          </div>
        </form>
      <?php endif ?>
    </div>
  </div>
</section>
