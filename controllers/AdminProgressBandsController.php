<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CoursePolicyService;
use App\Db;
use App\Response;
use App\UserCourseBlockService;

final class AdminProgressBandsController {

  public static function index(): void {
    global $DB;

    Auth::require_app_admin();
    $user = Auth::user();
    $selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

    $appCoursesRaw = Db::all(
      "SELECT id, title, slug, status, moodle_courseid, final_exam_unlock_hours
         FROM app_course
        WHERE moodle_courseid IS NOT NULL
          AND moodle_courseid > 0
        ORDER BY title ASC, id DESC"
    );

    $selectedCourse = null;
    foreach ($appCoursesRaw as $course) {
      if ((int)($course['id'] ?? 0) === $selectedCourseId) {
        $selectedCourse = $course;
        break;
      }
    }
    if ($selectedCourse === null && !empty($appCoursesRaw)) {
      $selectedCourse = $appCoursesRaw[0];
      $selectedCourseId = (int)($selectedCourse['id'] ?? 0);
    }
    if ($selectedCourse === null) {
      $selectedCourseId = 0;
    }

    $rows = [];
    $summary = [
      'total' => 0,
      'lt25' => 0,
      '25to50' => 0,
      'gt50' => 0,
    ];

    if ($selectedCourse !== null) {
      $moodleCourseId = (int)($selectedCourse['moodle_courseid'] ?? 0);
      $appCourseId = (int)($selectedCourse['id'] ?? 0);

      if ($moodleCourseId > 0 && $appCourseId > 0) {
        $enrolmentsSql = "SELECT
                            ue.userid,
                            e.courseid,
                            u.username,
                            u.firstname,
                            u.lastname,
                            u.email
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                          JOIN {user} u ON u.id = ue.userid
                         WHERE e.status = 0
                           AND e.courseid = :courseid
                           AND ue.status = 0
                           AND u.deleted = 0
                      ORDER BY u.firstname ASC, u.lastname ASC";

        $enrolments = [];
        $enrolmentsRs = $DB->get_recordset_sql($enrolmentsSql, ['courseid' => $moodleCourseId]);
        foreach ($enrolmentsRs as $record) {
          $enrolments[] = $record;
        }
        $enrolmentsRs->close();

        $courseLastAccessMap = [];
        $lastAccessRs = $DB->get_recordset_sql(
          "SELECT userid, timeaccess
             FROM {user_lastaccess}
            WHERE courseid = :courseid",
          ['courseid' => $moodleCourseId]
        );
        foreach ($lastAccessRs as $record) {
          $courseLastAccessMap[(int)$record->userid] = (int)($record->timeaccess ?? 0);
        }
        $lastAccessRs->close();

        $courseLogMap = [];
        $logsRs = $DB->get_recordset_sql(
          "SELECT
             userid,
             MIN(timecreated) AS first_seen,
             MAX(timecreated) AS last_seen
           FROM {logstore_standard_log}
          WHERE courseid = :courseid
            AND userid > 0
            AND anonymous = 0
            AND target = :target
            AND action = :action
            AND component = :component
       GROUP BY userid",
          [
            'courseid' => $moodleCourseId,
            'target' => 'course',
            'action' => 'viewed',
            'component' => 'core',
          ]
        );
        foreach ($logsRs as $record) {
          $courseLogMap[(int)$record->userid] = [
            'first' => (int)($record->first_seen ?? 0),
            'last' => (int)($record->last_seen ?? 0),
          ];
        }
        $logsRs->close();

        $progressNodeSql = CoursePolicyService::node_progress_sql();
        $progressNodeJoinSql = CoursePolicyService::node_progress_sql('n');

        $totalsRows = Db::all(
          "SELECT course_id, COUNT(*) AS total_items
             FROM app_node
            WHERE course_id = :course_id
              AND is_published = 1
              AND kind IN ('content', 'action')
              AND {$progressNodeSql}
         GROUP BY course_id",
          ['course_id' => $appCourseId]
        );

        $progressTotal = 0;
        foreach ($totalsRows as $row) {
          $progressTotal = (int)($row['total_items'] ?? 0);
          break;
        }

        $progressByUser = [];
        $progressRows = Db::all(
          "SELECT
             p.moodle_userid,
             SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS done_items
           FROM app_progress p
           JOIN app_node n ON n.id = p.node_id
          WHERE p.course_id = :course_id
            AND n.course_id = p.course_id
            AND n.is_published = 1
            AND n.kind IN ('content', 'action')
            AND {$progressNodeJoinSql}
       GROUP BY p.moodle_userid",
          ['course_id' => $appCourseId]
        );
        foreach ($progressRows as $row) {
          $progressByUser[(int)$row['moodle_userid']] = (int)($row['done_items'] ?? 0);
        }

        foreach ($enrolments as $enrolment) {
          $userId = (int)($enrolment->userid ?? 0);
          if ($userId <= 0) {
            continue;
          }

          $logInfo = $courseLogMap[$userId] ?? ['first' => 0, 'last' => 0];
          $courseFirstAccess = (int)($logInfo['first'] ?? 0);
          $courseLastAccess = (int)($courseLastAccessMap[$userId] ?? 0);
          if ($courseFirstAccess <= 0 && $courseLastAccess > 0) {
            $courseFirstAccess = $courseLastAccess;
          }
          if ($courseLastAccess <= 0) {
            $courseLastAccess = (int)($logInfo['last'] ?? 0);
          }

          $progressDone = (int)($progressByUser[$userId] ?? 0);
          if ($progressDone < 0) {
            $progressDone = 0;
          }
          if ($progressTotal > 0 && $progressDone > $progressTotal) {
            $progressDone = $progressTotal;
          }

          $progressPercent = $progressTotal > 0
            ? (int)floor(($progressDone * 100) / $progressTotal)
            : 0;

          $fullName = trim(fullname((object)[
            'firstname' => (string)($enrolment->firstname ?? ''),
            'lastname' => (string)($enrolment->lastname ?? ''),
          ]));
          if ($fullName === '') {
            $fullName = (string)($enrolment->username ?? 'Sem nome');
          }

          $band = self::progress_band($progressPercent);

          $rows[] = [
            'app_course_id' => $appCourseId,
            'app_course_title' => (string)($selectedCourse['title'] ?? ''),
            'moodle_courseid' => $moodleCourseId,
            'moodle_userid' => $userId,
            'username' => (string)($enrolment->username ?? ''),
            'fullname' => $fullName,
            'email' => (string)($enrolment->email ?? ''),
            'course_first_access' => $courseFirstAccess,
            'course_last_access' => $courseLastAccess,
            'progress_done' => $progressDone,
            'progress_total' => $progressTotal,
            'progress_percent' => $progressPercent,
            'progress_band' => $band,
          ];

          $summary['total']++;
          $summary[$band]++;
        }

        if (!empty($rows)) {
          $userIds = [];
          foreach ($rows as $row) {
            $userId = (int)($row['moodle_userid'] ?? 0);
            if ($userId > 0) {
              $userIds[] = $userId;
            }
          }

          if (!empty($userIds)) {
            $blockMap = UserCourseBlockService::active_map_for_course_users($appCourseId, $userIds);
            foreach ($rows as &$row) {
              $userId = (int)($row['moodle_userid'] ?? 0);
              $block = $blockMap[$userId] ?? null;
              $row['is_blocked'] = $block !== null;
              $row['block_title'] = UserCourseBlockService::title_for_row($block);
              $row['block_message'] = UserCourseBlockService::message_for_row($block);
            }
            unset($row);
          }
        }
      }
    }

    usort($rows, static function(array $a, array $b): int {
      return ((int)$b['moodle_userid']) <=> ((int)$a['moodle_userid']);
    });

    $courseFilters = [];
    foreach ($appCoursesRaw as $course) {
      $courseFilters[] = [
        'id' => (int)$course['id'],
        'title' => (string)($course['title'] ?? ''),
        'status' => (string)($course['status'] ?? 'draft'),
        'moodle_courseid' => (int)($course['moodle_courseid'] ?? 0),
        'final_exam_unlock_hours' => (int)($course['final_exam_unlock_hours'] ?? 0),
      ];
    }

    Response::html(App::render('admin/progress_bands/index.php', [
      'user' => $user,
      'rows' => $rows,
      'summary' => $summary,
      'courseFilters' => $courseFilters,
      'selectedCourseId' => $selectedCourseId,
      'selectedCourse' => $selectedCourse,
      'flash' => App::flash_get(),
    ]));
  }

  private static function progress_band(int $percent): string {
    if ($percent < 25) {
      return 'lt25';
    }
    if ($percent <= 50) {
      return '25to50';
    }
    return 'gt50';
  }
}
