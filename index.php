<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use App\Response;

$route = App\App::route();

try {
  if (preg_match('#^/admin/(?:inscritos|enrollments)(?:/.*)?$#i', $route) && class_exists('\App\Controllers\AdminEnrollmentsController')) {
    \App\Controllers\AdminEnrollmentsController::index();
  }
  App\App::dispatch($route);
} catch (Throwable $e) {
  Response::html(App\App::render_error($e), 500);
}
