<?php
use App\App;
use App\Auth;
global $CFG;

$currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/');
$basePath = App::base_path();
$routePath = $currentPath;
if ($basePath !== '/' && strpos($currentPath, $basePath) === 0) {
  $routePath = substr($currentPath, strlen($basePath));
  if ($routePath === '') $routePath = '/';
}

$isCourses = preg_match('#^/admin/courses(?:/.*)?$#', $routePath) === 1;
$isRuntimeCourses = preg_match('#^/admin/(?:cursos-roda-app|cursos_roda_app|courses-roda-app)(?:/.*)?$#', $routePath) === 1;
$isCourseMapping = preg_match('#^/admin/(?:course-mapping|course_mapping|mapeamento-cursos)(?:/.*)?$#', $routePath) === 1;
$isSupportMaterials = preg_match('#^/admin/(?:material-apoio|material_apoio|support-materials)(?:/.*)?$#', $routePath) === 1;
$isUserAlerts = preg_match('#^/admin/(?:alertas-aluno|alertas|user-alerts)(?:/.*)?$#', $routePath) === 1;
$isEnrollments = preg_match('#^/admin/(?:inscritos|enrollments)(?:/.*)?$#', $routePath) === 1;
$isProgressBands = preg_match('#^/admin/(?:alunos-percentual|progresso-percentual|progress-bands)(?:/.*)?$#', $routePath) === 1;
$isBiometricAudit = preg_match('#^/admin/(?:biometrias|biometricas|biometric-audit|biometrics)(?:/.*)?$#', $routePath) === 1;
$isBiometricSettings = preg_match('#^/admin/(?:biometria-config|biometria-configuracao|biometric-settings)(?:/.*)?$#', $routePath) === 1;
$isDashboard = $routePath === '/dashboard';
$moodleLmsUrl = rtrim((string)$CFG->wwwroot, '/') . '/my/';
$isAdminUser = $user && Auth::is_app_admin((int)$user->id);

$sectionTitle = 'Controle administrativo';
if ($isCourses) {
  $sectionTitle = 'Cursos';
} elseif ($isRuntimeCourses) {
  $sectionTitle = 'Cursos no app';
} elseif ($isCourseMapping) {
  $sectionTitle = 'Mapeamento LMS';
} elseif ($isSupportMaterials) {
  $sectionTitle = 'Material de apoio';
} elseif ($isUserAlerts) {
  $sectionTitle = 'Alertas ao aluno';
} elseif ($isEnrollments) {
  $sectionTitle = 'Inscritos';
} elseif ($isProgressBands) {
  $sectionTitle = 'Alunos por percentual';
} elseif ($isBiometricAudit) {
  $sectionTitle = 'Biometrias';
} elseif ($isBiometricSettings) {
  $sectionTitle = 'Configuracao de biometria';
}
?>
<nav class="admin-frame-topbar">
  <div class="container-fluid app-container">
    <div class="admin-frame-topbar__inner">
      <div class="admin-frame-topbar__lead">
        <button type="button" class="admin-frame-toggle" id="adminSidebarToggle" aria-label="Abrir menu administrativo" aria-expanded="false" aria-controls="adminSidebar">
          <i class="fa-solid fa-bars"></i>
        </button>

        <a class="admin-brand" href="<?= App::base_url('/admin/courses') ?>">
          <span class="admin-brand__mark"><i class="fa-solid fa-layer-group"></i></span>
          <span>
            <span class="admin-brand__title">Admin LMS</span>
            <span class="admin-brand__subtitle d-block">Tecnodata</span>
          </span>
        </a>

        <div class="admin-frame-topbar__context d-none d-xl-grid">
          <span class="admin-frame-topbar__context-label">Area administrativa</span>
          <strong><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>

      <div class="admin-user">
        <?php if ($user && !\isguestuser()): ?>
          <div class="admin-user__name d-none d-lg-block">
            Logado como
            <strong><?= htmlspecialchars(fullname($user), ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <?php if ($isAdminUser): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($moodleLmsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
              <i class="fa-solid fa-up-right-from-square me-1"></i> LMS Moodle
            </a>
          <?php endif; ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= App::base_url('/logout') ?>">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
          </a>
        <?php else: ?>
          <a class="btn btn-sm btn-primary" href="<?= App::base_url('/login') ?>">Entrar</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Menu administrativo">
  <div class="admin-sidebar__inner">
    <div class="admin-sidebar__head">
      <span class="admin-sidebar__eyebrow">Controle central</span>
      <strong class="admin-sidebar__title">Operacao do app</strong>
      <button type="button" class="admin-sidebar__close" id="adminSidebarClose" aria-label="Fechar menu administrativo">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="admin-sidebar__group">
      <span class="admin-sidebar__group-label">Operacao</span>
      <nav class="admin-sidebar__nav">
        <a class="admin-sidebar__link <?= $isCourses ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/courses') ?>">
          <i class="fa-solid fa-layer-group"></i>
          <span>Cursos</span>
        </a>
        <a class="admin-sidebar__link <?= $isRuntimeCourses ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/cursos-roda-app') ?>">
          <i class="fa-solid fa-route"></i>
          <span>Cursos no app</span>
        </a>
        <a class="admin-sidebar__link <?= $isCourseMapping ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/course-mapping') ?>">
          <i class="fa-solid fa-link"></i>
          <span>Mapeamento LMS</span>
        </a>
        <a class="admin-sidebar__link <?= $isSupportMaterials ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/material-apoio') ?>">
          <i class="fa-solid fa-book-open"></i>
          <span>Material de apoio</span>
        </a>
        <a class="admin-sidebar__link <?= $isUserAlerts ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/alertas-aluno') ?>">
          <i class="fa-solid fa-bell"></i>
          <span>Alertas ao aluno</span>
        </a>
      </nav>
    </div>

    <div class="admin-sidebar__group">
      <span class="admin-sidebar__group-label">Acompanhamento</span>
      <nav class="admin-sidebar__nav">
        <a class="admin-sidebar__link <?= $isEnrollments ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/inscritos') ?>">
          <i class="fa-solid fa-users-viewfinder"></i>
          <span>Inscritos</span>
        </a>
        <a class="admin-sidebar__link <?= $isProgressBands ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/alunos-percentual') ?>">
          <i class="fa-solid fa-chart-column"></i>
          <span>Alunos por percentual</span>
        </a>
        <a class="admin-sidebar__link <?= $isBiometricAudit ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/biometrias') ?>">
          <i class="fa-solid fa-camera-retro"></i>
          <span>Biometrias</span>
        </a>
        <a class="admin-sidebar__link <?= $isBiometricSettings ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/biometria-config') ?>">
          <i class="fa-solid fa-sliders"></i>
          <span>Config. biometria</span>
        </a>
      </nav>
    </div>

    <div class="admin-sidebar__footer">
      <a class="admin-sidebar__link admin-sidebar__link--soft <?= $isDashboard ? 'is-active' : '' ?>" href="<?= App::base_url('/dashboard') ?>">
        <i class="fa-solid fa-house"></i>
        <span>Area do aluno</span>
      </a>
      <?php if ($isAdminUser): ?>
        <a class="admin-sidebar__link admin-sidebar__link--soft" href="<?= htmlspecialchars($moodleLmsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-up-right-from-square"></i>
          <span>LMS Moodle</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</aside>

<script>
(() => {
  const body = document.body;
  const toggleButton = document.getElementById('adminSidebarToggle');
  const closeButton = document.getElementById('adminSidebarClose');
  const backdrop = document.getElementById('adminSidebarBackdrop');
  const storageKey = 'app_v3_admin_sidebar_collapsed';

  if (!body || !toggleButton || !backdrop) {
    return;
  }

  const isDesktop = () => window.innerWidth >= 1200;

  const syncToggleState = () => {
    if (isDesktop()) {
      toggleButton.setAttribute('aria-expanded', body.classList.contains('admin-sidebar-collapsed') ? 'false' : 'true');
      return;
    }
    toggleButton.setAttribute('aria-expanded', body.classList.contains('admin-sidebar-open') ? 'true' : 'false');
  };

  const closeSidebar = () => {
    body.classList.remove('admin-sidebar-open');
    syncToggleState();
  };

  const openSidebar = () => {
    body.classList.add('admin-sidebar-open');
    syncToggleState();
  };

  const setDesktopCollapsed = (collapsed) => {
    body.classList.toggle('admin-sidebar-collapsed', !!collapsed);
    try {
      window.localStorage.setItem(storageKey, collapsed ? '1' : '0');
    } catch (_error) {
      // Sem bloqueio para navegadores com storage restrito.
    }
    syncToggleState();
  };

  const restoreDesktopState = () => {
    if (!isDesktop()) {
      body.classList.remove('admin-sidebar-collapsed');
      return;
    }
    let collapsed = false;
    try {
      collapsed = window.localStorage.getItem(storageKey) === '1';
    } catch (_error) {
      collapsed = false;
    }
    body.classList.toggle('admin-sidebar-collapsed', collapsed);
    syncToggleState();
  };

  toggleButton.addEventListener('click', () => {
    if (isDesktop()) {
      setDesktopCollapsed(!body.classList.contains('admin-sidebar-collapsed'));
      return;
    }

    if (body.classList.contains('admin-sidebar-open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });

  closeButton?.addEventListener('click', closeSidebar);
  backdrop.addEventListener('click', closeSidebar);

  window.addEventListener('resize', () => {
    if (isDesktop()) {
      closeSidebar();
      restoreDesktopState();
    } else {
      body.classList.remove('admin-sidebar-collapsed');
      syncToggleState();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
    }
  });

  restoreDesktopState();
})();
</script>
