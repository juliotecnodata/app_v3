<?php
$viewFile = __DIR__ . '/view_inner.php';
$title = (string)($course['title'] ?? 'Curso') . ' - Portal';
$page_js = 'app.js';
$full_width = true;
$include_tawk = true;
$tawk_page = 'estudo';
$alert_page = 'study';
?>
<?php include APP_DIR . '/views/layouts/study.php'; ?>
