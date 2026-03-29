<section class="section">
  <header class="section-header">
    <h1 class="title"><?= html($title ?? t('error.invalid.title_label')) ?></h1>
  </header>
  <div class="section-main">
    <div class="content">
      <p role="alert"><?= html($message ?? t('error.invalid.title_message')) ?></p>
    </div>
  </div>
</section>
