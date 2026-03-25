<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CourseBlueprintService;
use App\CoursePolicyService;
use App\Csrf;
use App\Db;
use App\Response;

final class AdminCoursesController {

  public static function index(): void {
    Auth::require_app_admin();
    $user = Auth::user();

    $courses = Db::all("SELECT * FROM app_course ORDER BY id DESC");

    Response::html(App::render('admin/courses/index.php', [
      'user' => $user,
      'courses' => $courses,
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
    ]));
  }

  public static function new(): void {
    Auth::require_app_admin();
    $user = Auth::user();

    $error = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      Csrf::check((string)($_POST['csrf'] ?? ''));

      $title = trim((string)($_POST['title'] ?? ''));
      $slug = trim((string)($_POST['slug'] ?? ''));

      if ($title === '' || $slug === '') {
        $error = 'Titulo e slug sao obrigatorios.';
      } else {
        try {
          $courseId = CourseBlueprintService::createDraftCourse([
            'title' => $title,
            'slug' => $slug,
            'description' => (string)($_POST['description'] ?? ''),
            'structure' => (string)($_POST['structure'] ?? 'simple'),
            'moodle_courseid' => (int)($_POST['moodle_courseid'] ?? 0),
            'access_days' => (int)($_POST['access_days'] ?? 0),
            'enable_sequence_lock' => CoursePolicyService::bool_from_input($_POST['enable_sequence_lock'] ?? null, true) === 1,
            'require_biometric' => CoursePolicyService::bool_from_input($_POST['require_biometric'] ?? null, false) === 1,
            'final_exam_unlock_hours' => max(0, (int)($_POST['final_exam_unlock_hours'] ?? 0)),
            'wizard_enabled' => (int)($_POST['wizard_enabled'] ?? 0) === 1,
            'wizard_modules' => (int)($_POST['wizard_modules'] ?? 3),
            'wizard_units' => (int)($_POST['wizard_units'] ?? 4),
            'wizard_welcome' => (int)($_POST['wizard_welcome'] ?? 0) === 1,
            'wizard_materials' => (int)($_POST['wizard_materials'] ?? 0) === 1,
            'wizard_exercises' => (int)($_POST['wizard_exercises'] ?? 0) === 1,
            'wizard_final_exam' => (int)($_POST['wizard_final_exam'] ?? 0) === 1,
            'wizard_default_type' => (string)($_POST['wizard_default_type'] ?? 'video'),
          ], (int)$user->id);

          App::flash_set('success', 'Curso criado em rascunho. Continue no Builder.');
          Response::redirect(App::base_url('/admin/courses/' . $courseId . '/builder'));
        } catch (\Throwable $exception) {
          $error = $exception->getMessage();
        }
      }
    }

    Response::html(App::render('admin/courses/new.php', [
      'user' => $user,
      'csrf' => Csrf::token(),
      'error' => $error,
    ]));
  }
}
