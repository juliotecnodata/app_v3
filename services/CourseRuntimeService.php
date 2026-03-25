<?php
declare(strict_types=1);

namespace App;

final class CourseRuntimeService {
  public static function list_courses_runtime(): array {
    return Db::all(
      "SELECT
          c.id,
          c.title,
          c.slug,
          c.status,
          c.moodle_courseid,
          COALESCE(r.only_app, 0) AS only_app,
          r.updated_at,
          r.updated_by_moodle_userid
         FROM app_course c
         LEFT JOIN (
           SELECT
             course_id,
             MAX(only_app) AS only_app,
             MAX(updated_at) AS updated_at,
             MAX(updated_by_moodle_userid) AS updated_by_moodle_userid
           FROM app_course_runtime
           GROUP BY course_id
         ) r ON r.course_id = c.id
        ORDER BY c.id DESC"
    );
  }

  public static function set_only_app(int $courseId, bool $onlyApp, int $updatedByMoodleUserid = 0): void {
    if ($courseId <= 0) {
      throw new \RuntimeException('Curso invalido.');
    }

    $exists = Db::one("SELECT id FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$exists) {
      throw new \RuntimeException('Curso nao encontrado.');
    }

    $runtimeRow = Db::one(
      "SELECT course_id
         FROM app_course_runtime
        WHERE course_id = :cid
        LIMIT 1",
      ['cid' => $courseId]
    );

    $params = [
      'cid' => $courseId,
      'only_app' => $onlyApp ? 1 : 0,
      'uid' => $updatedByMoodleUserid > 0 ? $updatedByMoodleUserid : null,
    ];

    if ($runtimeRow) {
      try {
        Db::exec(
          "UPDATE app_course_runtime
              SET only_app = :only_app,
                  updated_by_moodle_userid = :uid,
                  updated_at = NOW()
            WHERE course_id = :cid",
          $params
        );
      } catch (\PDOException $e) {
        // Compatibilidade com schema legado sem colunas de auditoria.
        Db::exec(
          "UPDATE app_course_runtime
              SET only_app = :only_app
            WHERE course_id = :cid",
          [
            'only_app' => $params['only_app'],
            'cid' => $params['cid'],
          ]
        );
      }
      return;
    }

    try {
      Db::exec(
        "INSERT INTO app_course_runtime (course_id, only_app, updated_by_moodle_userid, updated_at)
         VALUES (:cid, :only_app, :uid, NOW())",
        $params
      );
    } catch (\PDOException $e) {
      // Compatibilidade com schema legado sem colunas de auditoria.
      Db::exec(
        "INSERT INTO app_course_runtime (course_id, only_app)
         VALUES (:cid, :only_app)",
        [
          'cid' => $params['cid'],
          'only_app' => $params['only_app'],
        ]
      );
    }
  }

  public static function is_only_app_course(int $courseId): bool {
    if ($courseId <= 0) {
      return false;
    }

    $row = Db::one(
      "SELECT MAX(only_app) AS only_app
         FROM app_course_runtime
        WHERE course_id = :cid
        GROUP BY course_id
        LIMIT 1",
      ['cid' => $courseId]
    );

    return ((int)($row['only_app'] ?? 0) === 1);
  }

  public static function user_has_app_enrollment(int $moodleUserId): bool {
    if ($moodleUserId <= 0) {
      return false;
    }

    $courses = Db::all(
      "SELECT c.moodle_courseid
         FROM app_course c
         JOIN (
           SELECT course_id, MAX(only_app) AS only_app
             FROM app_course_runtime
            GROUP BY course_id
         ) r ON r.course_id = c.id
        WHERE c.status = 'published'
          AND c.moodle_courseid > 0
          AND r.only_app = 1"
    );

    if (!$courses) {
      return false;
    }

    foreach ($courses as $row) {
      $moodleCourseId = (int)($row['moodle_courseid'] ?? 0);
      if ($moodleCourseId <= 0) {
        continue;
      }
      if (Moodle::is_enrolled_in_course($moodleCourseId, $moodleUserId)) {
        return true;
      }
    }

    return false;
  }
}
