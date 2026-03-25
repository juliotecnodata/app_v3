<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Csrf;
use App\Db;
use App\Response;

final class AdminCourseMappingController {
  public static function index(): void {
    global $DB;

    Auth::require_app_admin();
    $user = Auth::user();

    $appCourses = Db::all(
      "SELECT
          c.id,
          c.title,
          c.slug,
          c.status,
          c.moodle_courseid,
          c.updated_at,
          COALESCE(r.only_app, 0) AS only_app
         FROM app_course c
         LEFT JOIN (
           SELECT course_id, MAX(only_app) AS only_app
             FROM app_course_runtime
            GROUP BY course_id
         ) r ON r.course_id = c.id
        ORDER BY c.id DESC"
    );

    $moodleCourses = [];
    $rs = $DB->get_recordset_sql(
      "SELECT
          c.id,
          c.fullname,
          c.shortname,
          c.visible,
          c.category,
          cc.name AS category_name
         FROM {course} c
         LEFT JOIN {course_categories} cc ON cc.id = c.category
        WHERE c.id <> :siteid
        ORDER BY c.id DESC",
      ['siteid' => SITEID]
    );
    foreach ($rs as $row) {
      $moodleCourses[] = [
        'id' => (int)($row->id ?? 0),
        'fullname' => trim((string)($row->fullname ?? '')),
        'shortname' => trim((string)($row->shortname ?? '')),
        'visible' => (int)($row->visible ?? 0),
        'category' => (int)($row->category ?? 0),
        'category_name' => trim((string)($row->category_name ?? '')),
      ];
    }
    $rs->close();

    $moodleMap = [];
    foreach ($moodleCourses as $course) {
      $moodleMap[(int)$course['id']] = $course;
    }

    $mappedCount = 0;
    foreach ($appCourses as $course) {
      if ((int)($course['moodle_courseid'] ?? 0) > 0) {
        $mappedCount++;
      }
    }

    Response::html(App::render('admin/course_mapping/index.php', [
      'user' => $user,
      'appCourses' => $appCourses,
      'moodleCourses' => $moodleCourses,
      'moodleMap' => $moodleMap,
      'stats' => [
        'total' => count($appCourses),
        'mapped' => $mappedCount,
        'unmapped' => max(0, count($appCourses) - $mappedCount),
      ],
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
    ]));
  }
}
