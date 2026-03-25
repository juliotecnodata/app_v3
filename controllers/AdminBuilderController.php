<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CourseSyncService;
use App\Db;
use App\Response;
use App\Tree;

final class AdminBuilderController {

  public static function builder(int $courseId): void {
    Auth::require_app_admin();
    $u = Auth::user();

    $course = Db::one("SELECT * FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      throw new \RuntimeException('Curso nao encontrado.');
    }

    // Backfill defensivo para cursos importados antes do mapeamento em colunas.
    try {
      CourseSyncService::syncNodeMappingColumnsForCourse($courseId);
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha ao sincronizar colunas moodle_* no builder: ' . $e->getMessage());
    }

    $nodes = Tree::nodes_for_course($courseId);
    $tree = Tree::build($nodes);

    $exam = Db::one("SELECT * FROM app_course_exam WHERE course_id = :cid LIMIT 1", ['cid' => $courseId]);

    Response::html(App::render('admin/builder/index.php', [
      'user' => $u,
      'course' => $course,
      'tree' => $tree,
      'exam' => $exam,
      'csrf' => \App\Csrf::token(),
    ]));
  }
}
