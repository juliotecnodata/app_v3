<?php
$login_error = $error ?? null;
$error = null;

$viewFile = __DIR__ . '/login_inner.php';
$title = 'Login - Portal do Aluno';
$include_tawk = true;
$tawk_page = 'login';
?>
<?php include APP_DIR . '/views/layouts/auth.php'; ?>
