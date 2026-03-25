<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Db;
use App\Response;

final class AdminBiometricAuditController {

  public static function index(): void {
    global $DB;

    Auth::require_app_admin();
    $user = Auth::user();
    $selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

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
    if ($selectedCourse === null) {
      $selectedCourseId = 0;
    }

    $params = [];
    $where = '';
    if ($selectedCourseId > 0) {
      $where = 'WHERE b.course_id = :course_id';
      $params['course_id'] = $selectedCourseId;
    }

    $rows = Db::all(
      "SELECT
         b.id,
         b.course_id,
         b.moodle_userid,
         b.photo_size_bytes,
         b.status,
         UNIX_TIMESTAMP(b.captured_at) AS captured_ts,
         b.ip_address,
         b.user_agent,
         CASE WHEN b.photo_b64 IS NULL OR b.photo_b64 = '' THEN 0 ELSE 1 END AS has_photo,
         c.title AS course_title,
         c.moodle_courseid
       FROM app_course_biometric_audit b
       JOIN app_course c ON c.id = b.course_id
       $where
       ORDER BY b.captured_at DESC, b.id DESC",
      $params
    );

    $userIds = [];
    foreach ($rows as $row) {
      $uid = (int)($row['moodle_userid'] ?? 0);
      if ($uid > 0) {
        $userIds[$uid] = $uid;
      }
    }

    $usersById = [];
    if (!empty($userIds)) {
      [$userInSql, $userParams] = $DB->get_in_or_equal(array_values($userIds), \SQL_PARAMS_NAMED, 'bio_uid');
      $userRecords = $DB->get_records_sql(
        "SELECT id, username, firstname, lastname, email
           FROM {user}
          WHERE id $userInSql",
        $userParams
      );

      foreach ($userRecords as $record) {
        $usersById[(int)$record->id] = $record;
      }
    }

    $summary = [
      'total' => 0,
      'today' => 0,
      'users' => 0,
      'courses' => 0,
    ];

    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $uniqueUsers = [];
    $uniqueCourses = [];

    foreach ($rows as &$row) {
      $userId = (int)($row['moodle_userid'] ?? 0);
      $courseId = (int)($row['course_id'] ?? 0);
      $capturedTs = (int)($row['captured_ts'] ?? 0);
      $userRecord = $usersById[$userId] ?? null;

      if ($userRecord) {
        $fullName = trim((string)\fullname($userRecord));
        $username = (string)($userRecord->username ?? '');
        $email = (string)($userRecord->email ?? '');
      } else {
        $fullName = 'Usuario #' . $userId;
        $username = '';
        $email = '';
      }

      $row['fullname'] = $fullName;
      $row['username'] = $username;
      $row['email'] = $email;

      $summary['total']++;
      if ($capturedTs >= $todayStart) {
        $summary['today']++;
      }
      if ($userId > 0) {
        $uniqueUsers[$userId] = true;
      }
      if ($courseId > 0) {
        $uniqueCourses[$courseId] = true;
      }
    }
    unset($row);

    $summary['users'] = count($uniqueUsers);
    $summary['courses'] = count($uniqueCourses);

    Response::html(App::render('admin/biometric_audit/index.php', [
      'user' => $user,
      'rows' => $rows,
      'summary' => $summary,
      'courseFilters' => $courseFilters,
      'selectedCourseId' => $selectedCourseId,
      'selectedCourse' => $selectedCourse,
      'flash' => App::flash_get(),
    ]));
  }
}
