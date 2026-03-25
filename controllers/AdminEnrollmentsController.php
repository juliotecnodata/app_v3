<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CoursePolicyService;
use App\Db;
use App\Response;
use App\UserCourseBlockService;

final class AdminEnrollmentsController {

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
        ORDER BY id DESC"
    );

    $selectedCourse = null;
    foreach ($appCoursesRaw as $course) {
      if ((int)($course['id'] ?? 0) === $selectedCourseId) {
        $selectedCourse = $course;
        break;
      }
    }
    if ($selectedCourse === null) {
      $selectedCourseId = 0;
    }

    $moodleCourseIds = [];
    $coursesScopeByMoodle = [];
    if ($selectedCourse !== null) {
      $selectedMoodleCourseId = (int)($selectedCourse['moodle_courseid'] ?? 0);
      if ($selectedMoodleCourseId > 0) {
        $moodleCourseIds[] = $selectedMoodleCourseId;
        $coursesScopeByMoodle[$selectedMoodleCourseId] = $selectedCourse;
      }
    }

    $rows = [];
    $summary = [
      'total' => 0,
      'active' => 0,
      'expiring' => 0,
      'expired' => 0,
      'inactive' => 0,
      'never' => 0,
      'released' => 0,
      'completed' => 0,
      'studied' => 0,
    ];

    if (!empty($moodleCourseIds)) {
      [$moodleInSql, $moodleParams] = self::build_in_params('mc', $moodleCourseIds);

      $enrolmentsSql = "SELECT
                          ue.userid,
                          e.courseid,
                          u.username,
                          u.firstname,
                          u.lastname,
                          u.email,
                          u.firstaccess,
                          u.lastaccess,
                          u.suspended,
                          MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE NULL END) AS enrol_timestart,
                          MAX(CASE WHEN ue.timeend > 0 THEN ue.timeend ELSE 0 END) AS enrol_timeend,
                          MAX(CASE WHEN ue.timeend = 0 THEN 1 ELSE 0 END) AS enrol_unlimited,
                          MAX(CASE WHEN ue.status = 0 THEN 1 ELSE 0 END) AS enrol_active
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {user} u ON u.id = ue.userid
                       WHERE e.status = 0
                         AND e.courseid IN ($moodleInSql)
                         AND u.deleted = 0
                    GROUP BY
                          ue.userid,
                          e.courseid,
                          u.username,
                          u.firstname,
                          u.lastname,
                          u.email,
                          u.firstaccess,
                          u.lastaccess,
                          u.suspended
                    ORDER BY e.courseid ASC, u.firstname ASC, u.lastname ASC";

      $enrolments = [];
      $enrolmentsRs = $DB->get_recordset_sql($enrolmentsSql, $moodleParams);
      foreach ($enrolmentsRs as $record) {
        $enrolments[] = $record;
      }
      $enrolmentsRs->close();

      $courseLastAccessMap = [];
      $lastAccessSql = "SELECT userid, courseid, timeaccess
                          FROM {user_lastaccess}
                         WHERE courseid IN ($moodleInSql)";
      $lastAccessRs = $DB->get_recordset_sql($lastAccessSql, $moodleParams);
      foreach ($lastAccessRs as $record) {
        $key = (int)$record->userid . '|' . (int)$record->courseid;
        $courseLastAccessMap[$key] = (int)($record->timeaccess ?? 0);
      }
      $lastAccessRs->close();

      $logParams = $moodleParams;
      $logParams['target'] = 'course';
      $logParams['action'] = 'viewed';
      $logParams['component'] = 'core';
      $logsSql = "SELECT
                    userid,
                    courseid,
                    MIN(timecreated) AS first_seen,
                    MAX(timecreated) AS last_seen
                  FROM {logstore_standard_log}
                 WHERE courseid IN ($moodleInSql)
                   AND userid > 0
                   AND anonymous = 0
                   AND target = :target
                   AND action = :action
                   AND component = :component
              GROUP BY userid, courseid";

      $courseLogMap = [];
      $logsRs = $DB->get_recordset_sql($logsSql, $logParams);
      foreach ($logsRs as $record) {
        $key = (int)$record->userid . '|' . (int)$record->courseid;
        $courseLogMap[$key] = [
          'first' => (int)($record->first_seen ?? 0),
          'last' => (int)($record->last_seen ?? 0),
        ];
      }
      $logsRs->close();

      $appCourseIds = [];
      if ($selectedCourse !== null) {
        $appCourseIds[] = (int)$selectedCourse['id'];
      }

      $nodeTotalsByCourse = [];
      $progressByCourseUser = [];
      if (!empty($appCourseIds)) {
        [$appInSql, $appParams] = self::build_in_params('ac', $appCourseIds);
        $progressNodeSql = CoursePolicyService::node_progress_sql();
        $progressNodeJoinSql = CoursePolicyService::node_progress_sql('n');

        $totalsRows = Db::all(
          "SELECT course_id, COUNT(*) AS total_items
             FROM app_node
            WHERE course_id IN ($appInSql)
              AND is_published = 1
              AND kind IN ('content', 'action')
              AND {$progressNodeSql}
         GROUP BY course_id",
          $appParams
        );
        foreach ($totalsRows as $row) {
          $nodeTotalsByCourse[(int)$row['course_id']] = (int)$row['total_items'];
        }

        $progressRows = Db::all(
          "SELECT
             p.course_id,
             p.moodle_userid,
             SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS done_items,
             MAX(COALESCE(p.last_seen_at, p.completed_at)) AS app_last_activity
           FROM app_progress p
           JOIN app_node n ON n.id = p.node_id
          WHERE p.course_id IN ($appInSql)
            AND n.course_id = p.course_id
            AND n.is_published = 1
            AND n.kind IN ('content', 'action')
            AND {$progressNodeJoinSql}
       GROUP BY p.course_id, p.moodle_userid",
          $appParams
        );

        foreach ($progressRows as $row) {
          $key = (int)$row['course_id'] . '|' . (int)$row['moodle_userid'];
          $progressByCourseUser[$key] = [
            'done' => (int)$row['done_items'],
            'last' => (string)($row['app_last_activity'] ?? ''),
          ];
        }
      }

      $now = time();
      foreach ($enrolments as $enrolment) {
        $moodleCourseId = (int)($enrolment->courseid ?? 0);
        if ($moodleCourseId <= 0 || !isset($coursesScopeByMoodle[$moodleCourseId])) {
          continue;
        }

        $course = $coursesScopeByMoodle[$moodleCourseId];
        $appCourseId = (int)$course['id'];
        $userId = (int)($enrolment->userid ?? 0);
        if ($userId <= 0) {
          continue;
        }

        $isActiveEnrol = (int)($enrolment->enrol_active ?? 0) === 1;
        $isSuspended = (int)($enrolment->suspended ?? 0) === 1;
        $startTs = (int)($enrolment->enrol_timestart ?? 0);
        $endTs = (int)($enrolment->enrol_timeend ?? 0);
        $unlimited = (int)($enrolment->enrol_unlimited ?? 0) === 1 || $endTs <= 0;

        $accessState = 'active';
        $accessLabel = 'Ativo';
        $daysLeft = null;

        if ($isSuspended || !$isActiveEnrol) {
          $accessState = 'inactive';
          $accessLabel = 'Matricula inativa';
        } else if ($startTs > 0 && $startTs > $now) {
          $accessState = 'scheduled';
          $accessLabel = 'Acesso agendado';
        } else if (!$unlimited && $endTs > 0 && $now > $endTs) {
          $accessState = 'expired';
          $accessLabel = 'Acesso expirado';
        } else if (!$unlimited && $endTs > 0) {
          $daysLeft = (int)floor(($endTs - $now) / 86400);
          if ($daysLeft <= 7) {
            $accessState = 'expiring';
            $accessLabel = 'Expira em breve';
          }
        }

        $courseKey = $userId . '|' . $moodleCourseId;
        $courseLastAccess = (int)($courseLastAccessMap[$courseKey] ?? 0);
        $logInfo = $courseLogMap[$courseKey] ?? ['first' => 0, 'last' => 0];
        $courseFirstAccess = (int)($logInfo['first'] ?? 0);
        if ($courseFirstAccess <= 0 && $courseLastAccess > 0) {
          $courseFirstAccess = $courseLastAccess;
        }
        if ($courseLastAccess <= 0) {
          $courseLastAccess = (int)($logInfo['last'] ?? 0);
        }

        $progressKey = $appCourseId . '|' . $userId;
        $progressData = $progressByCourseUser[$progressKey] ?? ['done' => 0, 'last' => ''];
        $progressDone = (int)($progressData['done'] ?? 0);
        $progressTotal = (int)($nodeTotalsByCourse[$appCourseId] ?? 0);
        if ($progressDone < 0) {
          $progressDone = 0;
        }
        if ($progressTotal < 0) {
          $progressTotal = 0;
        }
        if ($progressTotal > 0 && $progressDone > $progressTotal) {
          $progressDone = $progressTotal;
        }
        $progressPercent = $progressTotal > 0
          ? (int)floor(($progressDone * 100) / $progressTotal)
          : 0;

        $appLastTs = 0;
        $appLastRaw = trim((string)($progressData['last'] ?? ''));
        if ($appLastRaw !== '') {
          $parsed = strtotime($appLastRaw);
          if ($parsed !== false) {
            $appLastTs = (int)$parsed;
          }
        }

        $isReleased = in_array($accessState, ['active', 'expiring'], true);
        $isCompleted = $progressTotal > 0 && $progressDone >= $progressTotal;
        $isStudied = !$isCompleted && ($progressDone > 0 || $appLastTs > 0);

        $fullName = trim(fullname((object)[
          'firstname' => (string)($enrolment->firstname ?? ''),
          'lastname' => (string)($enrolment->lastname ?? ''),
        ]));
        if ($fullName === '') {
          $fullName = (string)($enrolment->username ?? 'Sem nome');
        }

        $neverAccessed = $courseLastAccess <= 0;

        $rows[] = [
          'app_course_id' => $appCourseId,
          'app_course_title' => (string)($course['title'] ?? ''),
          'app_course_slug' => (string)($course['slug'] ?? ''),
          'app_course_status' => (string)($course['status'] ?? 'draft'),
          'moodle_courseid' => $moodleCourseId,
          'moodle_userid' => $userId,
          'username' => (string)($enrolment->username ?? ''),
          'fullname' => $fullName,
          'email' => (string)($enrolment->email ?? ''),
          'system_first_access' => (int)($enrolment->firstaccess ?? 0),
          'system_last_access' => (int)($enrolment->lastaccess ?? 0),
          'course_first_access' => $courseFirstAccess,
          'course_last_access' => $courseLastAccess,
          'app_last_activity' => $appLastTs,
          'enrol_start' => $startTs,
          'enrol_end' => $endTs,
          'enrol_unlimited' => $unlimited,
          'days_left' => $daysLeft,
          'access_state' => $accessState,
          'access_label' => $accessLabel,
          'never_accessed' => $neverAccessed,
          'is_released' => $isReleased,
          'is_completed' => $isCompleted,
          'is_studied' => $isStudied,
          'progress_done' => $progressDone,
          'progress_total' => $progressTotal,
          'progress_percent' => $progressPercent,
        ];

        $summary['total']++;
        if ($accessState === 'active') {
          $summary['active']++;
        } else if ($accessState === 'expiring') {
          $summary['expiring']++;
        } else if ($accessState === 'expired') {
          $summary['expired']++;
        } else if ($accessState === 'inactive') {
          $summary['inactive']++;
        }
        if ($neverAccessed) {
          $summary['never']++;
        }
        if ($isReleased) {
          $summary['released']++;
        }
        if ($isCompleted) {
          $summary['completed']++;
        }
        if ($isStudied) {
          $summary['studied']++;
        }
      }

      if ($selectedCourseId > 0 && !empty($rows)) {
        $userIds = [];
        foreach ($rows as $row) {
          $userId = (int)($row['moodle_userid'] ?? 0);
          if ($userId > 0) {
            $userIds[] = $userId;
          }
        }

        if (!empty($userIds)) {
          $blockMap = UserCourseBlockService::active_map_for_course_users($selectedCourseId, $userIds);
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
    usort($courseFilters, static function(array $a, array $b): int {
      return strcmp((string)$a['title'], (string)$b['title']);
    });

    Response::html(App::render('admin/enrollments/index.php', [
      'user' => $user,
      'rows' => $rows,
      'summary' => $summary,
      'courseFilters' => $courseFilters,
      'selectedCourseId' => $selectedCourseId,
      'flash' => App::flash_get(),
    ]));
  }

  private static function build_in_params(string $prefix, array $values): array {
    $placeholders = [];
    $params = [];
    $index = 0;

    foreach ($values as $value) {
      $key = $prefix . $index;
      $placeholders[] = ':' . $key;
      $params[$key] = (int)$value;
      $index++;
    }

    if (empty($placeholders)) {
      $key = $prefix . 'empty';
      $placeholders[] = ':' . $key;
      $params[$key] = -1;
    }

    return [implode(', ', $placeholders), $params];
  }
}
