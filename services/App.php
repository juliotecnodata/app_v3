<?php
declare(strict_types=1);

namespace App;

final class App {
  private static array $cfg = [];
  private static ?string $basePathResolved = null;

  public static function init(array $cfg): void {
    self::$cfg = $cfg;
  }

  public static function cfg(string $key, $default = null) {
    return self::$cfg[$key] ?? $default;
  }

  public static function base_url(string $path = ''): string {
    global $CFG;

    $basePath = self::base_path();
    $rootPath = (string)(parse_url((string)$CFG->wwwroot, PHP_URL_PATH) ?: '');
    $rootPath = '/' . trim($rootPath, '/');
    if ($rootPath === '//') $rootPath = '/';

    if ($rootPath !== '/' && (\str_starts_with($basePath, $rootPath . '/') || $basePath === $rootPath)) {
      $basePath = substr($basePath, strlen($rootPath));
      if ($basePath === '') $basePath = '/';
    }

    $base = rtrim((string)$CFG->wwwroot, '/') . $basePath;
    return $base . $path;
  }

  public static function base_path(): string {
    if (self::$basePathResolved !== null) return self::$basePathResolved;

    $requestPath = '';
    if (isset($_SERVER['REQUEST_URI'])) {
      $requestPath = (string)(parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
    }

    $basePath = '/' . trim((string)APP_BASE_PATH, '/');
    if ($basePath === '//') $basePath = '/';

    $baseDir = '/' . trim(basename(APP_DIR), '/');

    if ($requestPath !== '') {
      $pos = strpos($requestPath, $baseDir);
      if ($pos !== false) {
        $basePath = substr($requestPath, 0, $pos + strlen($baseDir));
      } else if (isset($_SERVER['SCRIPT_NAME'])) {
        $auto = rtrim(str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME'])), '/');
        if ($auto === '') $auto = '/';
        $basePath = $auto;
      }
    }

    self::$basePathResolved = $basePath;
    return $basePath;
  }

  public static function route(): string {
    $route = isset($_GET['r']) ? (string)$_GET['r'] : '';
    if ($route !== '') {
      return '/' . ltrim($route, '/');
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');

    $basePath = self::base_path();
    if ($basePath !== '/' && \str_starts_with($path, $basePath)) {
      $path = substr($path, strlen($basePath));
      if ($path === '') $path = '/';
    }

    return '/' . ltrim($path, '/');
  }

  public static function dispatch(string $route): void {
    $route = '/' . trim($route, '/');
    if ($route === '/') $route = '/dashboard';

    if (\str_starts_with($route, '/api/')) {
      \App\Controllers\ApiController::handle($route);
      return;
    }

    if ($route === '/login') {
      \App\Controllers\AuthController::login();
      return;
    }

    if ($route === '/logout') {
      \App\Controllers\AuthController::logout();
      return;
    }

    if ($route === '/admin') {
      Response::redirect(self::base_url('/admin/courses'));
      return;
    }

    if ($route === '/admin/courses') {
      \App\Controllers\AdminCoursesController::index();
      return;
    }

    if ($route === '/admin/cursos-roda-app' || $route === '/admin/cursos_roda_app' || $route === '/admin/courses-roda-app') {
      \App\Controllers\AdminCourseRuntimeController::index();
      return;
    }

    if ($route === '/admin/course-mapping' || $route === '/admin/course_mapping' || $route === '/admin/mapeamento-cursos') {
      \App\Controllers\AdminCourseMappingController::index();
      return;
    }

    if (preg_match('#^/admin/(?:material-apoio|material_apoio|support-materials)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminSupportMaterialsController::index();
      return;
    }

    if (preg_match('#^/admin/(?:alertas-aluno|alertas|user-alerts)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminUserAlertsController::index();
      return;
    }

    if (preg_match('#^/admin/(?:inscritos|enrollments)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminEnrollmentsController::index();
      return;
    }

    if (preg_match('#^/admin/(?:alunos-percentual|progresso-percentual|progress-bands)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminProgressBandsController::index();
      return;
    }

    if (preg_match('#^/admin/(?:biometrias|biometricas|biometric-audit|biometrics)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminBiometricAuditController::index();
      return;
    }

    if (preg_match('#^/admin/(?:biometria-config|biometria-configuracao|biometric-settings)(?:/.*)?$#i', $route)) {
      \App\Controllers\AdminBiometricSettingsController::index();
      return;
    }

    // Fallback defensivo para ambientes com reescrita/normalizacao de rota inconsistente.
    $routeLower = strtolower($route);
    if (\str_starts_with($routeLower, '/admin/') && (strpos($routeLower, 'inscritos') !== false || strpos($routeLower, 'enrollment') !== false)) {
      \App\Controllers\AdminEnrollmentsController::index();
      return;
    }

    if (\str_starts_with($routeLower, '/admin/') && (strpos($routeLower, 'percentual') !== false || strpos($routeLower, 'progress-band') !== false)) {
      \App\Controllers\AdminProgressBandsController::index();
      return;
    }

    if (\str_starts_with($routeLower, '/admin/') && (strpos($routeLower, 'biometr') !== false || strpos($routeLower, 'biometric') !== false)) {
      if (strpos($routeLower, 'config') !== false || strpos($routeLower, 'setting') !== false) {
        \App\Controllers\AdminBiometricSettingsController::index();
        return;
      }
      \App\Controllers\AdminBiometricAuditController::index();
      return;
    }

    if ($route === '/admin/courses/new') {
      \App\Controllers\AdminCoursesController::new();
      return;
    }

    if (preg_match('#^/admin/courses/(\d+)/builder$#', $route, $match)) {
      \App\Controllers\AdminBuilderController::builder((int)$match[1]);
      return;
    }

    if (preg_match('#^/course/(\d+)$#', $route, $match)) {
      \App\Controllers\CourseController::view((int)$match[1]);
      return;
    }

    if ($route === '/dashboard') {
      \App\Controllers\DashboardController::index();
      return;
    }

    Response::html(self::render('dashboard/index.php', [
      'error' => 'Rota nao encontrada: ' . htmlspecialchars($route, ENT_QUOTES, 'UTF-8')
    ]), 404);
  }

  public static function render(string $view, array $data = []): string {
    $viewFile = APP_DIR . '/views/' . $view;
    if (!file_exists($viewFile)) {
      throw new \RuntimeException('View nao encontrada: ' . $view);
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $viewFile;
    return (string)ob_get_clean();
  }

  public static function partial(string $file, array $data = []): void {
    $partialFile = APP_DIR . '/views/' . $file;
    extract($data, EXTR_SKIP);
    include $partialFile;
  }

  public static function flash_set(string $type, string $message): void {
    global $SESSION;
    $SESSION->app_flash = ['type' => $type, 'msg' => $message];
  }

  public static function flash_get(): ?array {
    global $SESSION;
    $flash = $SESSION->app_flash ?? null;
    unset($SESSION->app_flash);
    return $flash ?: null;
  }

  public static function render_error(\Throwable $exception): string {
    self::log_exception($exception);

    $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
    $trace = '';

    if (defined('DEBUG_DEVELOPER') && debugging('', DEBUG_DEVELOPER)) {
      $trace = '<pre class="small text-muted mt-3" style="white-space:pre-wrap;">'
        . htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8')
        . '</pre>';
    }

    return self::render('dashboard/index.php', [
      'error' => $message,
      'trace' => $trace,
    ]);
  }

  public static function log_exception(\Throwable $exception): void {
    $message = '[' . date('c') . '] ' . get_class($exception) . ': ' . $exception->getMessage()
      . ' in ' . $exception->getFile() . ':' . $exception->getLine() . "\n"
      . $exception->getTraceAsString();
    error_log($message);
  }
}
