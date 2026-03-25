<?php
use App\App;
use App\Auth;
?>
<nav class="app-topbar navbar navbar-expand-lg navbar-light sticky-top">
  <div class="container-fluid app-container">
    <a class="navbar-brand d-flex align-items-center gap-2 app-brand" href="<?= App::base_url('/dashboard') ?>">
      <span class="app-mark"><i class="fa-solid fa-graduation-cap"></i></span>
      <span class="app-brand-text">Portal</span>
      <span class="app-brand-sub d-none d-sm-inline">Tecnodata</span>
    </a>
    <button class="navbar-toggler app-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#topbarNav" aria-controls="topbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link app-nav-link" href="<?= App::base_url('/dashboard') ?>"><i class="fa-solid fa-house me-1"></i> Meus cursos</a>
        </li>
        <?php if ($user && Auth::is_app_admin((int)$user->id)): ?>
        <li class="nav-item d-lg-none">
          <a class="nav-link app-nav-link" href="<?= App::base_url('/admin/courses') ?>"><i class="fa-solid fa-layer-group me-1"></i> Admin</a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="ms-auto d-flex align-items-center gap-2 app-user">
      <?php if ($user && !\isguestuser()): ?>
        <div class="d-none d-lg-flex flex-column text-end me-3 app-user-meta">
          <small class="text-muted">Logado como</small>
          <strong class="fw-semibold"><?= htmlspecialchars(fullname($user), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>

        <?php if (Auth::is_app_admin((int)$user->id)): ?>
          <a class="btn btn-sm btn-outline-primary d-none d-lg-inline-block" href="<?= App::base_url('/admin/courses') ?>">
            <i class="fa-solid fa-screwdriver-wrench me-1"></i> Admin
          </a>
        <?php endif; ?>

        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-user"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
            <li><a class="dropdown-item" href="<?= App::base_url('/profile') ?>"><i class="fa-solid fa-user-circle me-2"></i> Perfil</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= App::base_url('/logout') ?>"><i class="fa-solid fa-right-from-bracket me-2"></i> Sair</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a class="btn btn-sm btn-primary" href="<?= App::base_url('/login') ?>">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
