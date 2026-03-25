<?php
$viewFile = __DIR__ . '/builder_inner.php';
$title = 'Builder - ' . ($course['title'] ?? 'Curso');
$admin_page_title = 'Builder do curso';
$admin_page_subtitle = (string)($course['title'] ?? 'Estruture modulos, itens e regras do curso.');
$builderJsVersion = @filemtime(APP_DIR . '/assets/js/builder.js');
if (!$builderJsVersion) {
  $builderJsVersion = time();
}
$extra_js = ['js/builder.js?v=' . $builderJsVersion];
$full_width = true;
?>
<?php include APP_DIR . '/views/layouts/admin.php'; ?>
