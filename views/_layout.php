<?php
/** @var string $viewFile */
use App\App;

$flash = $flash ?? null;
$csrf  = $csrf ?? \App\Csrf::token();
$full_width = $full_width ?? false;

$title = $title ?? 'Tecnodata - Portal do Aluno';
$base  = App::base_url('');
$asset = function(string $p): string {
  $clean = ltrim($p, '/');
  $url = App::base_url('/assets/' . $clean);
  $full = APP_DIR . '/assets/' . str_replace(['\\', '..'], ['/', ''], $clean);
  if (is_file($full)) {
    $url .= '?v=' . (string)filemtime($full);
  }
  return $url;
};
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="Portal do Aluno Tecnodata - Plataforma de Cursos">
  <meta name="theme-color" content="#0f6bdc">
  <meta name="app-base" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- FontAwesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet">
  <!-- DataTables -->
  <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <!-- Toastify -->
  <link href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css" rel="stylesheet">
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <!-- Custom CSS -->
  <link href="<?= $asset('app.css') ?>" rel="stylesheet">
  <!-- Plyr (video player) -->
  <link href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css" rel="stylesheet">

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="<?= $asset('favicon.png') ?>">
</head>
<body class="app-body">

<div class="app-shell">
  <?php App::partial('_partials/nav.php', ['user'=>$user ?? null]); ?>
  <main class="app-main">
    <div class="<?= $full_width ? 'app-container-full' : 'app-container' ?> py-3 py-lg-4">
      <?php App::partial('_partials/flash.php', ['flash'=>$flash]); ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2">
          <i class="fa-solid fa-triangle-exclamation mt-1"></i>
          <div>
            <div class="fw-semibold">Ops...</div>
            <div><?= $error ?></div>
            <?= $trace ?? '' ?>
          </div>
        </div>
      <?php endif; ?>
      <?php include $viewFile; ?>
    </div>
  </main>
</div>

<!-- JS: Bootstrap, jQuery, DataTables, SweetAlert2, Toastify, Select2 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.0/dist/hls.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

<!-- Custom JS -->
<script src="<?= $asset('app.js') ?>"></script>
<?php if (!empty($page_js)): ?>
<script src="<?= $asset($page_js) ?>"></script>
<?php endif; ?>

<script>
// Toastify helper
window.showToast = function(msg, type = 'info') {
  Toastify({
    text: msg,
    duration: 4000,
    gravity: 'top',
    position: 'right',
    backgroundColor: type === 'success' ? '#198754' : (type === 'error' ? '#dc3545' : '#0d6efd'),
    stopOnFocus: true,
    close: true
  }).showToast();
};
</script>
</body>
</html>
