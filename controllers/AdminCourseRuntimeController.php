<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CourseRuntimeService;
use App\Csrf;
use App\Response;

final class AdminCourseRuntimeController {
  public static function index(): void {
    Auth::require_app_admin();
    $user = Auth::user();

    $courses = CourseRuntimeService::list_courses_runtime();

    Response::html(App::render('admin/cursos_roda_app/index.php', [
      'user' => $user,
      'courses' => $courses,
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
    ]));
  }
}

