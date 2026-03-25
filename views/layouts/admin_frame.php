<?php
$headerTitle = htmlspecialchars((string)($admin_page_title ?? 'Admin'), ENT_QUOTES, 'UTF-8');
$headerSubtitle = trim((string)($admin_page_subtitle ?? ''));
$headerKicker = trim((string)($admin_page_kicker ?? ''));
$headerActions = (string)($admin_page_actions ?? '');
$hideHeader = !empty($admin_hide_header);
?>
<div class="admin-page-shell">
  <?php if (!$hideHeader): ?>
    <header class="admin-page-header">
      <div class="admin-page-header__copy">
        <?php if ($headerKicker !== ''): ?>
          <span class="admin-page-header__kicker"><?= htmlspecialchars($headerKicker, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
        <h1 class="admin-page-header__title"><?= $headerTitle ?></h1>
        <?php if ($headerSubtitle !== ''): ?>
          <p class="admin-page-header__subtitle"><?= htmlspecialchars($headerSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </div>
      <?php if ($headerActions !== ''): ?>
        <div class="admin-page-header__actions"><?= $headerActions ?></div>
      <?php endif; ?>
    </header>
  <?php endif; ?>

  <div class="admin-page-content">
    <?php include $admin_inner_view; ?>
  </div>
</div>
