<?php if (!empty($flash) && is_array($flash)): ?>
  <?php
    $type = htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
    $msg  = htmlspecialchars($flash['msg'] ?? '', ENT_QUOTES, 'UTF-8');
    $bg = $type === 'success' ? 'success' : ($type === 'error' ? 'danger' : 'info');
  ?>
  <noscript>
    <div class="alert alert-<?= $bg ?> d-flex align-items-start gap-2">
      <i class="fa-solid fa-circle-info mt-1"></i>
      <div><?= $msg ?></div>
    </div>
  </noscript>
  <script>
    window.addEventListener('load', () => {
      if (typeof showToast === 'function') {
        showToast(<?= json_encode($flash['msg'] ?? '') ?>, <?= json_encode($flash['type'] ?? 'info') ?>);
      } else if (typeof Toastify !== 'undefined') {
        Toastify({ text: <?= json_encode($flash['msg'] ?? '') ?>, duration:4000, gravity:'top', position:'right', close:true }).showToast();
      } else {
        console.log('Flash: ', <?= json_encode($flash['msg'] ?? '') ?>);
      }
    });
  </script>
<?php endif; ?>
