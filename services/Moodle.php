<?php
declare(strict_types=1);

namespace App;

final class Moodle {
  /**
   * Sync user access at Moodle platform level (site).
   *
   * - firstaccess: first time the user accessed the system.
   * - lastaccess: latest platform access.
   */
  public static function sync_system_access(int $moodle_userid): void {
    global $USER, $DB;
    static $syncedByUser = [];

    if ($moodle_userid <= 0 || !\isloggedin() || \isguestuser()) {
      return;
    }

    if (isset($syncedByUser[$moodle_userid])) {
      return;
    }
    $syncedByUser[$moodle_userid] = true;

    $timenow = time();
    $currentUserId = (int)($USER->id ?? 0);

    if ($currentUserId === $moodle_userid) {
      if ((int)($USER->firstaccess ?? 0) === 0) {
        $DB->set_field('user', 'firstaccess', $timenow, ['id' => $moodle_userid]);
        $USER->firstaccess = $timenow;
      }

      // Keep Moodle native behavior for site access tracking.
      \user_accesstime_log(SITEID);
      return;
    }

    $row = $DB->get_record('user', ['id' => $moodle_userid], 'id, firstaccess, lastaccess');
    if (!$row) {
      return;
    }

    $update = (object)['id' => $moodle_userid];
    $changed = false;
    if ((int)($row->firstaccess ?? 0) <= 0) {
      $update->firstaccess = $timenow;
      $changed = true;
    }
    if ((int)($row->lastaccess ?? 0) < $timenow) {
      $update->lastaccess = $timenow;
      $changed = true;
    }

    if ($changed) {
      $DB->update_record('user', $update);
    }
  }

  /**
   * Sync course access back to Moodle so reports do not show "never accessed".
   *
   * Uses Moodle native tracking for current logged user and falls back to a
   * direct user_lastaccess write when needed.
   */
  public static function sync_course_access(int $moodle_courseid, int $moodle_userid, bool $emitCourseViewedEvent = true): void {
    global $USER, $DB, $SESSION;

    if ($moodle_courseid <= 0 || $moodle_userid <= 0) {
      return;
    }

    if (!\isloggedin() || \isguestuser()) {
      return;
    }

    self::sync_system_access($moodle_userid);

    $timenow = time();
    $params = [
      'userid' => $moodle_userid,
      'courseid' => $moodle_courseid,
    ];
    $hadRecord = $DB->record_exists('user_lastaccess', $params);

    $currentUserId = (int)($USER->id ?? 0);
    if ($currentUserId === $moodle_userid) {
      // Keep Moodle native behavior for course access tracking.
      \user_accesstime_log($moodle_courseid);
    } else {
      $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', $params);
      if ($lastaccess === false) {
        $record = (object)[
          'userid' => $moodle_userid,
          'courseid' => $moodle_courseid,
          'timeaccess' => $timenow,
        ];
        $DB->insert_record('user_lastaccess', $record);
      } else if ($timenow - (int)$lastaccess >= (int)LASTACCESS_UPDATE_SECS) {
        $DB->set_field('user_lastaccess', 'timeaccess', $timenow, $params);
      }
    }

    if (!$emitCourseViewedEvent || $currentUserId !== $moodle_userid) {
      return;
    }

    if (!isset($SESSION->app_v3_last_course_view_log) || !is_array($SESSION->app_v3_last_course_view_log)) {
      $SESSION->app_v3_last_course_view_log = [];
    }

    $firstCourseAccess = !$hadRecord;
    $lastLogged = (int)($SESSION->app_v3_last_course_view_log[$moodle_courseid] ?? 0);
    $shouldLogEvent = $firstCourseAccess || ($timenow - $lastLogged >= 300);

    if (!$shouldLogEvent) {
      return;
    }

    $context = \context_course::instance($moodle_courseid, IGNORE_MISSING);
    if (!$context) {
      return;
    }

    try {
      $event = \core\event\course_viewed::create([
        'context' => $context,
      ]);
      $event->trigger();
      $SESSION->app_v3_last_course_view_log[$moodle_courseid] = $timenow;
    } catch (\Throwable $e) {
      // Nao deve interromper o fluxo do aluno por falha de telemetria.
      error_log('[app_v3] Falha ao disparar course_viewed: ' . $e->getMessage());
    }
  }

  /**
   * Check if user is enrolled in Moodle course.
   */
  public static function is_enrolled_in_course(int $moodle_courseid, int $moodle_userid): bool {
    $ctx = \context_course::instance($moodle_courseid, IGNORE_MISSING);
    if (!$ctx) return false;
    $user = \core_user::get_user($moodle_userid, '*', MUST_EXIST);
    return \is_enrolled($ctx, $user, '', true);
  }

  /**
   * Return enrol window (timestart/timeend) from Moodle enrolments.
   *
   * Rules:
   * - Only active enrol instances and active user enrolments.
   * - If any active enrolment has no end date (timeend=0), returns timeend=0.
   * - Otherwise returns the furthest active end date.
   */
  public static function enrol_window(int $userid, int $courseid): ?array {
    global $DB;

    $sql = "SELECT ue.timestart, ue.timeend
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid
               AND e.courseid = :courseid
               AND ue.status = 0
               AND e.status = 0";

    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
    if (!$rows) return null;

    $start = 0;
    $end = 0;
    $hasUnlimited = false;

    foreach ($rows as $row) {
      $tstart = (int)($row->timestart ?? 0);
      $tend = (int)($row->timeend ?? 0);

      if ($start === 0 || ($tstart > 0 && $tstart < $start)) {
        $start = $tstart;
      }

      if ($tend <= 0) {
        $hasUnlimited = true;
      } elseif ($tend > $end) {
        $end = $tend;
      }
    }

    return [
      'timestart' => $start,
      'timeend' => $hasUnlimited ? 0 : $end,
    ];
  }

  public static function course_first_access_timestamp(int $userid, int $courseid): int {
    global $DB;

    if ($userid <= 0 || $courseid <= 0) {
      return 0;
    }

    try {
      $sql = "SELECT MIN(timecreated)
                FROM {logstore_standard_log}
               WHERE courseid = :courseid
                 AND userid = :userid
                 AND anonymous = 0
                 AND target = :target
                 AND action = :action
                 AND component = :component";
      $firstSeen = (int)$DB->get_field_sql($sql, [
        'courseid' => $courseid,
        'userid' => $userid,
        'target' => 'course',
        'action' => 'viewed',
        'component' => 'core',
      ]);
      if ($firstSeen > 0) {
        return $firstSeen;
      }
    } catch (\Throwable $e) {
      // Fallback abaixo.
    }

    try {
      $lastAccess = (int)$DB->get_field('user_lastaccess', 'timeaccess', [
        'courseid' => $courseid,
        'userid' => $userid,
      ]);
      if ($lastAccess > 0) {
        return $lastAccess;
      }
    } catch (\Throwable $e) {
      // Sem fallback adicional no Moodle.
    }

    return 0;
  }

  /**
   * Sync completion state from APP node progress to Moodle activity completion.
   *
   * Returns metadata about what happened. This is best-effort and should not
   * break APP flow when a module is not mapped to a Moodle CMID.
   */
  public static function sync_node_completion_from_app(
    int $appCourseId,
    int $appNodeId,
    int $moodleUserId,
    bool $completed,
    ?int $overrideByMoodleUserId = null
  ): array {
    $completeState = defined('COMPLETION_COMPLETE') ? (int)\COMPLETION_COMPLETE : 1;
    $incompleteState = defined('COMPLETION_INCOMPLETE') ? (int)\COMPLETION_INCOMPLETE : 0;

    $result = [
      'ok' => true,
      'synced' => false,
      'cmid' => 0,
      'reason' => null,
      'state' => $completed ? $completeState : $incompleteState,
    ];

    if ($appCourseId <= 0 || $appNodeId <= 0 || $moodleUserId <= 0) {
      $result['reason'] = 'invalid_args';
      return $result;
    }

    if (!\isloggedin() || \isguestuser()) {
      $result['reason'] = 'not_logged_in';
      return $result;
    }

    $course = Db::one(
      "SELECT moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $appCourseId]
    );
    if (!$course) {
      $result['reason'] = 'app_course_not_found';
      return $result;
    }

    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    if ($moodleCourseId <= 0) {
      $result['reason'] = 'app_course_not_mapped';
      return $result;
    }

    $cmid = self::resolve_node_moodle_cmid($appCourseId, $appNodeId);
    if ($cmid <= 0) {
      $result['reason'] = 'node_without_cmid';
      return $result;
    }

    $sync = self::sync_module_completion_from_app(
      $cmid,
      $moodleUserId,
      $completed,
      $moodleCourseId,
      $overrideByMoodleUserId
    );
    return $sync;
  }

  /**
   * Sync completion state directly to a Moodle CMID.
   */
  public static function sync_module_completion_from_app(
    int $cmid,
    int $moodleUserId,
    bool $completed,
    int $expectedMoodleCourseId = 0,
    ?int $overrideByMoodleUserId = null
  ): array {
    global $DB, $CFG;

    $completeState = defined('COMPLETION_COMPLETE') ? (int)\COMPLETION_COMPLETE : 1;
    $incompleteState = defined('COMPLETION_INCOMPLETE') ? (int)\COMPLETION_INCOMPLETE : 0;

    $result = [
      'ok' => true,
      'synced' => false,
      'cmid' => $cmid,
      'reason' => null,
      'state' => $completed ? $completeState : $incompleteState,
    ];

    if ($cmid <= 0 || $moodleUserId <= 0) {
      $result['reason'] = 'invalid_args';
      return $result;
    }

    if (!\isloggedin() || \isguestuser()) {
      $result['reason'] = 'not_logged_in';
      return $result;
    }

    require_once($CFG->libdir . '/completionlib.php');

    $cm = $DB->get_record(
      'course_modules',
      ['id' => $cmid],
      'id,course,module,instance,completion,completionview',
      IGNORE_MISSING
    );
    if (!$cm) {
      $result['reason'] = 'cm_not_found';
      return $result;
    }

    if ($expectedMoodleCourseId > 0 && (int)$cm->course !== $expectedMoodleCourseId) {
      $result['reason'] = 'cm_course_mismatch';
      return $result;
    }

    $course = $DB->get_record('course', ['id' => (int)$cm->course], '*', IGNORE_MISSING);
    if (!$course) {
      $result['reason'] = 'moodle_course_not_found';
      return $result;
    }

    $completionInfo = new \completion_info($course);
    if (!$completionInfo->is_enabled($cm)) {
      $result['reason'] = 'completion_disabled';
      return $result;
    }

    $data = $completionInfo->get_data($cm, false, $moodleUserId);
    $currentState = (int)($data->completionstate ?? $incompleteState);
    $currentViewed = (int)($data->viewed ?? $incompleteState);

    if ($completed) {
      $targetState = $currentState;
      if (!in_array($targetState, [\COMPLETION_COMPLETE_PASS, \COMPLETION_COMPLETE_FAIL], true)) {
        $targetState = $completeState;
      }
    } else {
      $targetState = $incompleteState;
    }

    $viewRequired = (int)($cm->completionview ?? 0) === (defined('COMPLETION_VIEW_REQUIRED') ? (int)\COMPLETION_VIEW_REQUIRED : 1);
    if ($viewRequired) {
      $targetViewed = $completed ? $completeState : $incompleteState;
    } else if ($completed) {
      $targetViewed = $currentViewed;
    } else {
      $targetViewed = $incompleteState;
    }

    if ($currentState === $targetState && $currentViewed === $targetViewed) {
      $result['reason'] = 'already_synced';
      $result['state'] = $targetState;
      return $result;
    }

    $data->completionstate = $targetState;
    $data->viewed = $targetViewed;
    $data->timemodified = time();
    if ($overrideByMoodleUserId !== null && $overrideByMoodleUserId > 0 && $overrideByMoodleUserId !== $moodleUserId) {
      $data->overrideby = (int)$overrideByMoodleUserId;
    } else {
      $data->overrideby = null;
    }

    $completionInfo->internal_set_data($cm, $data);

    $result['synced'] = true;
    $result['state'] = $targetState;
    return $result;
  }

  private static function resolve_node_moodle_cmid(int $appCourseId, int $appNodeId): int {
    $node = Db::one(
      "SELECT subtype, moodle_cmid, moodle_url, content_json
         FROM app_node
        WHERE id = :id
          AND course_id = :cid
        LIMIT 1",
      [
        'id' => $appNodeId,
        'cid' => $appCourseId,
      ]
    );
    if (!$node) {
      return 0;
    }

    $mappedCmid = (int)($node['moodle_cmid'] ?? 0);
    if ($mappedCmid > 0) {
      return $mappedCmid;
    }

    $content = json_decode((string)($node['content_json'] ?? ''), true);
    if (is_array($content)) {
      $candidates = [
        (int)($content['moodle']['cmid'] ?? 0),
        (int)($content['cmid'] ?? 0),
        (int)($content['moodle_cmid'] ?? 0),
        (int)($content['quiz_cmid'] ?? 0),
      ];
      foreach ($candidates as $candidate) {
        if ($candidate > 0) {
          return $candidate;
        }
      }

      $urlCandidates = [
        (string)($node['moodle_url'] ?? ''),
        (string)($content['moodle']['url'] ?? ''),
        (string)($content['moodle_url'] ?? ''),
        (string)($content['url'] ?? ''),
        (string)($content['source_url'] ?? ''),
      ];
      foreach ($urlCandidates as $urlCandidate) {
        $urlCmid = self::extract_cmid_from_url($urlCandidate);
        if ($urlCmid > 0) {
          return $urlCmid;
        }
      }
    } else {
      $nodeUrlCmid = self::extract_cmid_from_url((string)($node['moodle_url'] ?? ''));
      if ($nodeUrlCmid > 0) {
        return $nodeUrlCmid;
      }
    }

    if ((string)($node['subtype'] ?? '') !== 'final_exam') {
      return 0;
    }

    $exam = Db::one(
      "SELECT quiz_cmid
         FROM app_course_exam
        WHERE course_id = :cid
        LIMIT 1",
      ['cid' => $appCourseId]
    );
    $quizCmid = (int)($exam['quiz_cmid'] ?? 0);
    return $quizCmid > 0 ? $quizCmid : 0;
  }

  private static function extract_cmid_from_url(string $url): int {
    $source = trim(str_replace('&amp;', '&', (string)$url));
    if ($source === '') {
      return 0;
    }

    $parts = @parse_url($source);
    if (!is_array($parts)) {
      return 0;
    }

    $query = (string)($parts['query'] ?? '');
    if ($query !== '') {
      parse_str($query, $params);
      if (!empty($params['id']) && (int)$params['id'] > 0) {
        return (int)$params['id'];
      }
      if (!empty($params['cmid']) && (int)$params['cmid'] > 0) {
        return (int)$params['cmid'];
      }
    }

    return 0;
  }

  /**
   * From CMID to quiz instance id.
   */
  public static function quiz_instance_from_cmid(int $cmid): ?int {
    global $DB;
    $sql = "SELECT cm.instance
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.id = :cmid AND m.name = 'quiz'";
    $instance = $DB->get_field_sql($sql, ['cmid' => $cmid]);
    return $instance ? (int)$instance : null;
  }

  /**
   * From CMID to lesson instance id.
   */
  public static function lesson_instance_from_cmid(int $cmid): ?int {
    global $DB;
    if ($cmid <= 0) {
      return null;
    }
    $sql = "SELECT cm.instance
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.id = :cmid
               AND m.name = 'lesson'";
    $instance = $DB->get_field_sql($sql, ['cmid' => $cmid], IGNORE_MULTIPLE);
    return $instance ? (int)$instance : null;
  }

  /**
   * From lesson instance id to CMID.
   */
  public static function lesson_cmid_from_instance(int $lessonid): ?int {
    global $DB;
    if ($lessonid <= 0) {
      return null;
    }

    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.instance = :lessonid
               AND m.name = 'lesson'";
    $cmid = $DB->get_field_sql($sql, ['lessonid' => $lessonid], IGNORE_MULTIPLE);
    return $cmid ? (int)$cmid : null;
  }

  /**
   * From quiz instance id to CMID.
   */
  public static function quiz_cmid_from_quiz_instance(int $quizid): ?int {
    global $DB;
    if ($quizid <= 0) {
      return null;
    }

    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.instance = :quizid
               AND m.name = 'quiz'";
    $cmid = $DB->get_field_sql($sql, ['quizid' => $quizid], IGNORE_MULTIPLE);
    return $cmid ? (int)$cmid : null;
  }

  /**
   * Resolve Moodle activity view URL from CMID.
   */
  public static function cm_view_url(int $cmid): string {
    global $DB;

    if ($cmid <= 0) {
      return '';
    }

    $courseId = (int)$DB->get_field('course_modules', 'course', ['id' => $cmid]);
    if ($courseId <= 0) {
      return '';
    }

    try {
      $modinfo = \get_fast_modinfo($courseId);
      $cm = $modinfo->get_cm($cmid);
      if (!$cm || !$cm->url) {
        return '';
      }
      return (string)$cm->url->out(false);
    } catch (\Throwable $e) {
      return '';
    }
  }

  /**
   * Resolve Moodle module name from CMID (e.g. quiz, lesson, page).
   */
  public static function cm_modname(int $cmid): string {
    global $DB;
    if ($cmid <= 0) {
      return '';
    }

    $sql = "SELECT m.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE cm.id = :cmid";
    $name = $DB->get_field_sql($sql, ['cmid' => $cmid], IGNORE_MULTIPLE);
    return $name ? strtolower(trim((string)$name)) : '';
  }

  /**
   * Completion snapshot for a Moodle activity (CMID) and user.
   */
  public static function cm_completion_state(int $cmid, int $userid): array {
    global $DB;

    $out = [
      'completionstate' => 0,
      'timemodified' => 0,
      'viewed' => 0,
    ];

    if ($cmid <= 0 || $userid <= 0) {
      return $out;
    }

    $row = null;
    $hasViewedColumn = true;
    try {
      $row = $DB->get_record(
        'course_modules_completion',
        ['coursemoduleid' => $cmid, 'userid' => $userid],
        'completionstate,timemodified,viewed',
        IGNORE_MISSING
      );
    } catch (\Throwable $e) {
      $hasViewedColumn = false;
      $row = $DB->get_record(
        'course_modules_completion',
        ['coursemoduleid' => $cmid, 'userid' => $userid],
        'completionstate,timemodified',
        IGNORE_MISSING
      );
    }
    if ($row) {
      $out['completionstate'] = (int)($row->completionstate ?? 0);
      $out['timemodified'] = (int)($row->timemodified ?? 0);
      if ($hasViewedColumn && property_exists($row, 'viewed')) {
        $out['viewed'] = (int)($row->viewed ?? 0);
      }
      if ($out['completionstate'] > 0) {
        $out['viewed'] = 1;
        return $out;
      }
      if ($hasViewedColumn) {
        return $out;
      }
    }

    $params = [
      'uid' => $userid,
      'cmid' => $cmid,
      'ctx' => defined('CONTEXT_MODULE') ? (int)CONTEXT_MODULE : 70,
    ];
    $sql = "SELECT MAX(timecreated)
              FROM {logstore_standard_log}
             WHERE userid = :uid
               AND contextlevel = :ctx
               AND contextinstanceid = :cmid
               AND target = 'course_module'
               AND action = 'viewed'";
    try {
      $lastView = (int)$DB->get_field_sql($sql, $params);
      if ($lastView > 0) {
        $out['viewed'] = 1;
        $out['timemodified'] = $lastView;
      }
    } catch (\Throwable $e) {
      // Log table may be unavailable/disabled in some environments.
    }

    return $out;
  }

  /**
   * Last quiz grade for the user.
   */
  public static function quiz_last_grade(int $quizid, int $userid): ?float {
    global $DB;
    $sql = "SELECT grade
              FROM {quiz_grades}
             WHERE quiz = :quizid
               AND userid = :userid";
    $grade = $DB->get_field_sql($sql, ['quizid' => $quizid, 'userid' => $userid], IGNORE_MISSING);
    return $grade === false || $grade === null ? null : (float)$grade;
  }

  /**
   * Grade to pass configured in Moodle gradebook for this quiz.
   * Returns null when no minimum grade rule is configured.
   */
  public static function quiz_grade_to_pass(int $quizid): ?float {
    global $DB;
    if ($quizid <= 0) {
      return null;
    }

    $sql = "SELECT gi.gradepass
              FROM {grade_items} gi
             WHERE gi.itemtype = 'mod'
               AND gi.itemmodule = 'quiz'
               AND gi.iteminstance = :quizid
          ORDER BY gi.id ASC";
    $gradePass = $DB->get_field_sql($sql, ['quizid' => $quizid], IGNORE_MULTIPLE);
    if ($gradePass === false || $gradePass === null) {
      return null;
    }

    $value = (float)$gradePass;
    return $value > 0 ? $value : null;
  }

  /**
   * Whether user already has at least one finished attempt for this quiz.
   */
  public static function quiz_has_finished_attempt(int $quizid, int $userid): bool {
    global $DB;
    if ($quizid <= 0 || $userid <= 0) {
      return false;
    }

    return $DB->record_exists('quiz_attempts', [
      'quiz' => $quizid,
      'userid' => $userid,
      'state' => 'finished',
    ]);
  }

  public static function quiz_has_open_attempt(int $quizid, int $userid): bool {
    global $DB;
    if ($quizid <= 0 || $userid <= 0) {
      return false;
    }

    $sql = "SELECT 1
              FROM {quiz_attempts}
             WHERE quiz = :quizid
               AND userid = :userid
               AND state IN ('inprogress', 'overdue')";
    return (bool)$DB->record_exists_sql($sql, [
      'quizid' => $quizid,
      'userid' => $userid,
    ]);
  }

  /**
   * Snapshot of lesson completion signals for a user.
   *
   * This combines:
   * - course_modules_completion (when activity completion is enabled),
   * - lesson_timer.completed,
   * - lesson_grades.completed.
   */
  public static function lesson_completion_snapshot(int $lessonid, int $userid, int $cmid = 0): array {
    global $DB;

    $snapshot = [
      'cmid' => $cmid > 0 ? $cmid : 0,
      'completion_state' => 0,
      'viewed' => 0,
      'timemodified' => 0,
      'timer_completed' => false,
      'grade_completed' => false,
      'grade' => null,
      'completion_met' => false,
      'completion_source' => 'none',
    ];

    if ($userid <= 0) {
      return $snapshot;
    }

    $resolvedCmid = $cmid > 0 ? $cmid : (int)(self::lesson_cmid_from_instance($lessonid) ?? 0);
    if ($resolvedCmid > 0) {
      $cmState = self::cm_completion_state($resolvedCmid, $userid);
      $snapshot['cmid'] = $resolvedCmid;
      $snapshot['completion_state'] = (int)($cmState['completionstate'] ?? 0);
      $snapshot['viewed'] = (int)($cmState['viewed'] ?? 0);
      $snapshot['timemodified'] = (int)($cmState['timemodified'] ?? 0);
    }

    if ($lessonid <= 0) {
      $snapshot['completion_met'] = ((int)$snapshot['completion_state'] > 0);
      $snapshot['completion_source'] = $snapshot['completion_met'] ? 'cm_completion' : 'none';
      return $snapshot;
    }

    $timer = $DB->get_record_sql(
      "SELECT MAX(completed) AS completedat
         FROM {lesson_timer}
        WHERE lessonid = :lessonid
          AND userid = :userid",
      [
        'lessonid' => $lessonid,
        'userid' => $userid,
      ]
    );
    $timerCompletedAt = (int)($timer->completedat ?? 0);
    $snapshot['timer_completed'] = $timerCompletedAt > 0;

    $gradeRow = $DB->get_record_sql(
      "SELECT MAX(completed) AS completedat, MAX(grade) AS grade
         FROM {lesson_grades}
        WHERE lessonid = :lessonid
          AND userid = :userid",
      [
        'lessonid' => $lessonid,
        'userid' => $userid,
      ]
    );
    $gradeCompletedAt = (int)($gradeRow->completedat ?? 0);
    $snapshot['grade_completed'] = $gradeCompletedAt > 0;
    if (isset($gradeRow->grade) && $gradeRow->grade !== null) {
      $snapshot['grade'] = (float)$gradeRow->grade;
    }

    $hasCmCompletion = $snapshot['completion_state'] > 0;
    $met = $hasCmCompletion || $snapshot['timer_completed'] || $snapshot['grade_completed'];
    $snapshot['completion_met'] = $met;

    if ($hasCmCompletion) {
      $snapshot['completion_source'] = 'cm_completion';
    } else if ($snapshot['timer_completed']) {
      $snapshot['completion_source'] = 'timer';
    } else if ($snapshot['grade_completed']) {
      $snapshot['completion_source'] = 'grade';
    }

    return $snapshot;
  }

  /**
   * Snapshot of completion criteria for a quiz according to Moodle grade rules.
   */
  public static function quiz_completion_snapshot(int $quizid, int $userid, int $cmid = 0): array {
    $grade = self::quiz_last_grade($quizid, $userid);
    $resolvedCmid = $cmid > 0 ? $cmid : (int)(self::quiz_cmid_from_quiz_instance($quizid) ?? 0);
    $moodlePassGrade = self::quiz_grade_to_pass($quizid);
    $appPassGrade = self::quiz_app_min_grade($resolvedCmid);
    $passGrade = null;
    if ($moodlePassGrade !== null && $moodlePassGrade > 0) {
      $passGrade = (float)$moodlePassGrade;
    }
    if ($appPassGrade !== null && $appPassGrade > 0) {
      $passGrade = $passGrade === null
        ? (float)$appPassGrade
        : max((float)$passGrade, (float)$appPassGrade);
    }

    $hasFinishedAttempt = self::quiz_has_finished_attempt($quizid, $userid);
    $passRequired = $passGrade !== null && $passGrade > 0;
    $metByRules = $passRequired
      ? ($grade !== null && ((float)$grade + 0.00001) >= (float)$passGrade)
      : $hasFinishedAttempt;
    $cmState = self::cm_completion_state($resolvedCmid, $userid);
    $completionState = (int)($cmState['completionstate'] ?? 0);
    $completePassState = defined('COMPLETION_COMPLETE_PASS') ? (int)COMPLETION_COMPLETE_PASS : 2;
    $metByCm = $passRequired
      ? $completionState === $completePassState
      : $completionState > 0;
    $met = $passRequired
      ? $metByRules
      : ($metByCm || $metByRules);

    return [
      'cmid' => $resolvedCmid,
      'completion_state' => $completionState,
      'viewed' => (int)($cmState['viewed'] ?? 0),
      'timemodified' => (int)($cmState['timemodified'] ?? 0),
      'grade' => $grade,
      'pass_grade' => $passGrade,
      'pass_required' => $passRequired,
      'has_finished_attempt' => $hasFinishedAttempt,
      'completion_met_by_cm' => $metByCm,
      'completion_met_by_rules' => $metByRules,
      'completion_met' => $met,
    ];
  }

  private static function quiz_app_min_grade(int $cmid): ?float {
    if ($cmid <= 0) {
      return null;
    }

    try {
      $exam = Db::one(
        "SELECT min_grade
           FROM app_course_exam
          WHERE quiz_cmid = :cmid
            AND min_grade IS NOT NULL
          LIMIT 1",
        ['cmid' => $cmid]
      );
    } catch (\Throwable $e) {
      return null;
    }

    if (!$exam || !isset($exam['min_grade']) || $exam['min_grade'] === null || $exam['min_grade'] === '') {
      return null;
    }

    $value = (float)$exam['min_grade'];
    return $value > 0 ? $value : null;
  }
}
