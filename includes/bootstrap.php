<?php
declare(strict_types=1);

/**
 * APP Portal (estrutura nova)
 * - Usa login e sessao do Moodle (SSO real)
 * - Mantem DB proprio (separado) para cursos, conteudos e progresso
 */

// Polyfills para PHP < 8
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return strncmp($haystack, $needle, strlen($needle)) === 0;
  }
}
if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    return strpos($haystack, $needle) !== false;
  }
}
if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    $length = strlen($needle);
    return substr($haystack, -$length) === $needle;
  }
}

// Resolve config.php do Moodle
$candidates = [
  __DIR__ . '/../../config.php',
  __DIR__ . '/../../moodle/config.php',
  __DIR__ . '/../../../moodle/config.php'
];

$moodleConfig = null;
foreach ($candidates as $candidate) {
  if (file_exists($candidate)) {
    $moodleConfig = $candidate;
    break;
  }
}

if (!$moodleConfig) {
  die('config.php do Moodle nao encontrado.');
}

require_once $moodleConfig;

global $CFG;

require_once $CFG->libdir . '/moodlelib.php';
require_once $CFG->dirroot . '/login/lib.php';
require_once $CFG->libdir . '/filelib.php';
require_once $CFG->libdir . '/accesslib.php';
require_once $CFG->libdir . '/weblib.php';

$appConfigFile = __DIR__ . '/config.php';
if (!file_exists($appConfigFile)) {
  $appConfigFile = __DIR__ . '/config.sample.php';
}
$appConfig = require $appConfigFile;

$appTimezone = trim((string)($appConfig['timezone'] ?? 'America/Sao_Paulo'));
if ($appTimezone === '') {
  $appTimezone = 'America/Sao_Paulo';
}
@date_default_timezone_set($appTimezone);

define('APP_BASE_PATH', (string)($appConfig['base_path'] ?? '/app_v3'));
define('APP_DIR', dirname(__DIR__));

// Error logging local do app
$logDir = APP_DIR . '/storage/logs';
if (!is_dir($logDir)) {
  @mkdir($logDir, 0775, true);
}
$cacheDir = APP_DIR . '/storage/cache';
if (!is_dir($cacheDir)) {
  @mkdir($cacheDir, 0775, true);
}

@ini_set('log_errors', '1');
@ini_set('error_log', $logDir . '/app-error.log');

set_error_handler(function (int $severity, string $message, string $file, int $line) {
  if (!(error_reporting() & $severity)) return false;
  $text = '[' . date('c') . '] PHP ERROR ' . $severity . ': ' . $message . ' in ' . $file . ':' . $line;
  error_log($text);
  return false;
});

set_exception_handler(function (\Throwable $exception) {
  $text = '[' . date('c') . '] UNCAUGHT ' . get_class($exception) . ': ' . $exception->getMessage()
    . ' in ' . $exception->getFile() . ':' . $exception->getLine() . "\n"
    . $exception->getTraceAsString();
  error_log($text);
});

register_shutdown_function(function () {
  $error = error_get_last();
  if (!$error) return;

  $text = '[' . date('c') . '] FATAL ' . ($error['type'] ?? 0) . ': ' . ($error['message'] ?? '')
    . ' in ' . ($error['file'] ?? '') . ':' . ($error['line'] ?? 0);
  error_log($text);
});

require_once APP_DIR . '/services/Db.php';
require_once APP_DIR . '/services/Response.php';
require_once APP_DIR . '/services/Csrf.php';
require_once APP_DIR . '/services/Auth.php';
require_once APP_DIR . '/services/Moodle.php';
require_once APP_DIR . '/services/SettingsService.php';
require_once APP_DIR . '/services/BiometricProviderService.php';
require_once APP_DIR . '/services/CoursePolicyService.php';
require_once APP_DIR . '/services/UserAlertService.php';
require_once APP_DIR . '/services/UserCourseBlockService.php';
require_once APP_DIR . '/services/Schema.php';
require_once APP_DIR . '/services/Tree.php';
require_once APP_DIR . '/services/ProgressSyncService.php';
require_once APP_DIR . '/services/ProgressCatchupService.php';
require_once APP_DIR . '/services/CourseBlueprintService.php';
require_once APP_DIR . '/services/CourseSyncService.php';
require_once APP_DIR . '/services/CourseRuntimeService.php';
require_once APP_DIR . '/services/App.php';

require_once APP_DIR . '/models/Course.php';
require_once APP_DIR . '/models/Node.php';
require_once APP_DIR . '/models/Progress.php';

require_once APP_DIR . '/controllers/AuthController.php';
require_once APP_DIR . '/controllers/DashboardController.php';
require_once APP_DIR . '/controllers/CourseController.php';
require_once APP_DIR . '/controllers/AdminCoursesController.php';
require_once APP_DIR . '/controllers/AdminCourseRuntimeController.php';
require_once APP_DIR . '/controllers/AdminCourseMappingController.php';
require_once APP_DIR . '/controllers/AdminSupportMaterialsController.php';
require_once APP_DIR . '/controllers/AdminUserAlertsController.php';
require_once APP_DIR . '/controllers/AdminEnrollmentsController.php';
require_once APP_DIR . '/controllers/AdminProgressBandsController.php';
require_once APP_DIR . '/controllers/AdminBiometricAuditController.php';
require_once APP_DIR . '/controllers/AdminBiometricSettingsController.php';
require_once APP_DIR . '/controllers/AdminBuilderController.php';
require_once APP_DIR . '/controllers/ApiController.php';

\App\Db::init($appConfig['db'] ?? []);
try {
  $schemaEnsureInterval = max(0, (int)($appConfig['schema_ensure_interval_seconds'] ?? 21600));
  $schemaStampFile = $cacheDir . '/schema_ensure.timestamp';
  $shouldEnsureSchema = true;

  if ($schemaEnsureInterval > 0 && is_file($schemaStampFile)) {
    $lastEnsure = (int)@file_get_contents($schemaStampFile);
    if ($lastEnsure > 0 && (time() - $lastEnsure) < $schemaEnsureInterval) {
      $shouldEnsureSchema = false;
    }
  }

  if ($shouldEnsureSchema) {
    \App\Schema::ensure();
    @file_put_contents($schemaStampFile, (string)time(), LOCK_EX);
  }
} catch (\Throwable $e) {
  error_log('[app_v3] Falha ao validar schema do APP: ' . $e->getMessage());
}
\App\App::init($appConfig);

