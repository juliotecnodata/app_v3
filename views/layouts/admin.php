<?php
$css = ['css/shared.css', 'css/admin.css'];
$js = ['js/shared.js', 'js/admin.js'];
$body_class = $body_class ?? 'app-body admin-shell';
$content_padding = false;
$nav_partial = 'partials/nav_admin.php';
$admin_inner_view = $viewFile;
$admin_page_title = $admin_page_title ?? preg_replace('/^(Admin|Builder)\s*-\s*/u', '', (string)($title ?? 'Admin'));
$admin_page_subtitle = $admin_page_subtitle ?? 'Controle operacional do app integrado ao Moodle.';
$admin_page_kicker = $admin_page_kicker ?? 'Painel administrativo';
$admin_page_actions = $admin_page_actions ?? '';
$viewFile = APP_DIR . '/views/layouts/admin_frame.php';
include __DIR__ . '/_base.php';
