<?php
$viewFile = __DIR__ . '/biometric_gate_inner.php';
$title = (string)($course['title'] ?? 'Curso') . ' - Validacao biometrica';
$full_width = true;
$alert_page = 'biometric';
?>
<?php include APP_DIR . '/views/layouts/study.php'; ?>
