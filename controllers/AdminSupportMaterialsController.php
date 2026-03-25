<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Csrf;
use App\Db;
use App\Response;
use App\Schema;

final class AdminSupportMaterialsController {

  public static function index(): void {
    Auth::require_app_admin();
    Schema::ensure();

    $selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
      self::handle_post();
      return;
    }

    $courseFilters = Db::all(
      "SELECT id, title, moodle_courseid
         FROM app_course
        ORDER BY title ASC, id DESC"
    );

    $selectedCourse = null;
    foreach ($courseFilters as $course) {
      if ((int)($course['id'] ?? 0) === $selectedCourseId) {
        $selectedCourse = $course;
        break;
      }
    }

    if ($selectedCourseId > 0 && $selectedCourse === null) {
      $selectedCourseId = 0;
    }

    $params = [];
    $where = '';
    if ($selectedCourseId > 0) {
      $where = 'WHERE m.course_id = :course_id';
      $params['course_id'] = $selectedCourseId;
    }

    $rows = Db::all(
      "SELECT m.*,
              c.title AS course_title,
              c.moodle_courseid
         FROM app_support_material m
         JOIN app_course c
           ON c.id = m.course_id
         $where
        ORDER BY c.title ASC, m.sort_order ASC, m.id DESC",
      $params
    );

    $summary = [
      'total' => 0,
      'published' => 0,
      'hidden' => 0,
      'courses' => 0,
    ];
    $courseCounter = [];
    foreach ($rows as $row) {
      $summary['total']++;
      if ((int)($row['is_published'] ?? 0) === 1) {
        $summary['published']++;
      } else {
        $summary['hidden']++;
      }
      $courseId = (int)($row['course_id'] ?? 0);
      if ($courseId > 0) {
        $courseCounter[$courseId] = true;
      }
    }
    $summary['courses'] = count($courseCounter);

    $editMaterial = null;
    if ($editId > 0) {
      $editMaterial = Db::one(
        "SELECT *
           FROM app_support_material
          WHERE id = :id
          LIMIT 1",
        ['id' => $editId]
      );
      if ($editMaterial) {
        $selectedCourseId = (int)($editMaterial['course_id'] ?? $selectedCourseId);
        foreach ($courseFilters as $course) {
          if ((int)($course['id'] ?? 0) === $selectedCourseId) {
            $selectedCourse = $course;
            break;
          }
        }
      }
    }

    Response::html(App::render('admin/support_materials/index.php', [
      'user' => Auth::user(),
      'rows' => $rows,
      'summary' => $summary,
      'courseFilters' => $courseFilters,
      'selectedCourseId' => $selectedCourseId,
      'selectedCourse' => $selectedCourse,
      'editMaterial' => $editMaterial,
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
    ]));
  }

  private static function handle_post(): void {
    Csrf::check((string)($_POST['csrf'] ?? ''));

    $action = trim((string)($_POST['action'] ?? 'save'));
    $courseId = (int)($_POST['course_id'] ?? 0);
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
      if ($id <= 0) {
        App::flash_set('error', 'Material de apoio nao informado.');
        Response::redirect(App::base_url('/admin/material-apoio'));
      }

      Db::exec("DELETE FROM app_support_material WHERE id = :id", ['id' => $id]);
      App::flash_set('success', 'Material de apoio excluido.');
      Response::redirect(App::base_url('/admin/material-apoio' . ($courseId > 0 ? '?course_id=' . $courseId : '')));
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $url = trim((string)($_POST['url'] ?? ''));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isPublished = (int)($_POST['is_published'] ?? 1) === 1 ? 1 : 0;

    if ($courseId <= 0) {
      App::flash_set('error', 'Selecione o curso do material de apoio.');
      Response::redirect(App::base_url('/admin/material-apoio'));
    }

    if ($title === '' || $url === '') {
      $suffix = '?course_id=' . $courseId . ($id > 0 ? '&edit=' . $id : '');
      App::flash_set('error', 'Informe titulo e URL do material de apoio.');
      Response::redirect(App::base_url('/admin/material-apoio' . $suffix));
    }

    if ($id > 0) {
      Db::exec(
        "UPDATE app_support_material
            SET course_id = :course_id,
                title = :title,
                url = :url,
                sort_order = :sort_order,
                is_published = :is_published
          WHERE id = :id",
        [
          'id' => $id,
          'course_id' => $courseId,
          'title' => $title,
          'url' => $url,
          'sort_order' => $sortOrder,
          'is_published' => $isPublished,
        ]
      );
      App::flash_set('success', 'Material de apoio atualizado.');
    } else {
      Db::exec(
        "INSERT INTO app_support_material (course_id, title, url, sort_order, is_published)
              VALUES (:course_id, :title, :url, :sort_order, :is_published)",
        [
          'course_id' => $courseId,
          'title' => $title,
          'url' => $url,
          'sort_order' => $sortOrder,
          'is_published' => $isPublished,
        ]
      );
      App::flash_set('success', 'Material de apoio criado.');
    }

    Response::redirect(App::base_url('/admin/material-apoio?course_id=' . $courseId));
  }
}
