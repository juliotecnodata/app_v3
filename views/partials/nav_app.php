<?php
use App\App;
use App\Auth;
global $CFG;

$currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/');
$basePath = App::base_path();
$routePath = $currentPath;
if ($basePath !== '/' && strpos($currentPath, $basePath) === 0) {
  $routePath = substr($currentPath, strlen($basePath));
  if ($routePath === '') {
    $routePath = '/';
  }
}

$isMyCourses = preg_match('#^/(?:dashboard|course(?:/.*)?)$#', $routePath) === 1;
$isAdminCourses = preg_match('#^/admin/courses(?:/.*)?$#', $routePath) === 1;
$isAdminCourseMapping = preg_match('#^/admin/(?:course-mapping|course_mapping|mapeamento-cursos)(?:/.*)?$#', $routePath) === 1;
$isAdminEnrollments = preg_match('#^/admin/(?:inscritos|enrollments)(?:/.*)?$#', $routePath) === 1;
$isAdminProgressBands = preg_match('#^/admin/(?:alunos-percentual|progresso-percentual|progress-bands)(?:/.*)?$#', $routePath) === 1;
$isAdminBiometrics = preg_match('#^/admin/(?:biometrias|biometricas|biometric-audit|biometrics)(?:/.*)?$#', $routePath) === 1;
$isAdminRoute = preg_match('#^/admin(?:/.*)?$#', $routePath) === 1;
$canAdmin = $user && Auth::is_app_admin((int)$user->id);
$moodleLmsUrl = rtrim((string)$CFG->wwwroot, '/') . '/my/';
?>
<nav class="admin-topbar app-topbar--student">
  <div class="container-fluid app-container">
    <div class="admin-topbar__inner">
      <a class="admin-brand" href="<?= App::base_url('/dashboard') ?>">
        <span class="admin-brand__mark"><i class="fa-solid fa-graduation-cap"></i></span>
        <span>
          <span class="admin-brand__title">Portal LMS</span>
          <span class="admin-brand__subtitle d-block">Tecnodata</span>
        </span>
      </a>

      <button
        class="btn app-student-menu-btn d-lg-none"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#studentTopbarMenu"
        aria-controls="studentTopbarMenu"
        aria-expanded="false"
        aria-label="Abrir menu"
      >
        <i class="fa-solid fa-bars"></i>
      </button>

      <div class="app-student-menu app-student-menu--<?= $canAdmin ? 'admin' : 'student' ?> collapse d-lg-flex" id="studentTopbarMenu">
        <div class="admin-nav">
          <a class="admin-nav__link <?= $isMyCourses ? 'is-active' : '' ?>" href="<?= App::base_url('/dashboard') ?>">
            <i class="fa-solid fa-house me-1"></i> Meus cursos
          </a>
          <?php if ($canAdmin): ?>
            <a class="admin-nav__link <?= $isAdminCourses ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/courses') ?>">
              <i class="fa-solid fa-layer-group me-1"></i> Cursos
            </a>
            <a class="admin-nav__link <?= $isAdminCourseMapping ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/course-mapping') ?>">
              <i class="fa-solid fa-link me-1"></i> Mapeamento LMS
            </a>
            <a class="admin-nav__link <?= $isAdminEnrollments ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/inscritos') ?>">
              <i class="fa-solid fa-users-viewfinder me-1"></i> Inscritos
            </a>
            <a class="admin-nav__link <?= $isAdminProgressBands ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/alunos-percentual') ?>">
              <i class="fa-solid fa-chart-column me-1"></i> Percentual
            </a>
            <a class="admin-nav__link <?= $isAdminBiometrics ? 'is-active' : '' ?>" href="<?= App::base_url('/admin/biometrias') ?>">
              <i class="fa-solid fa-camera-retro me-1"></i> Biometrias
            </a>
          <?php endif; ?>
        </div>

        <div class="admin-user">
        <?php if ($user && !\isguestuser()): ?>
          <div class="admin-user__name d-none d-lg-block">
            Logado como
            <strong><?= htmlspecialchars(fullname($user), ENT_QUOTES, 'UTF-8') ?></strong>
          </div>
          <?php if ($canAdmin && !$isAdminRoute): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= App::base_url('/admin/courses') ?>">
              <i class="fa-solid fa-shield-halved me-1"></i> Area admin
            </a>
          <?php endif; ?>
          <?php if ($canAdmin): ?>
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
  </div>
</nav>
