<?php
if (!defined('APP_DIR')) {
  $bootstrap = __DIR__ . '/../bootstrap.mock.php';
  if (file_exists($bootstrap)) require_once $bootstrap;
}
$viewFile = __DIR__ . '/mock_estudo_inner.php';
$title = 'Mock Estudo • UI';
?>
<?php include APP_DIR . '/views/layouts/study.php'; ?>
