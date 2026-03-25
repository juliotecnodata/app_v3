$bootstrap = dirname(__DIR__) . '/bootstrap.mock.php';
if (file_exists($bootstrap)) {
	require_once $bootstrap;
} else {
	die('bootstrap.mock.php não encontrado.');
}
<?php
$viewFile = __DIR__ . '/mock_teste_inner.php';
$title = 'Mock Teste • UI';
$extra_css = ['css/admin.css', 'css/study.css'];
$extra_js = ['js/mock.js'];
?>
<?php include APP_DIR . '/views/layouts/app.php'; ?>
