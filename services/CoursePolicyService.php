<?php
declare(strict_types=1);

namespace App;

final class CoursePolicyService {
  /** @var array<string,int> */
  private static array $biometricRequestCache = [];
  /** @var array<string,array<string,mixed>> */
  private static array $finalExamGateCache = [];

  public static function sequence_lock_enabled(array $course): bool {
    if (!array_key_exists('enable_sequence_lock', $course)) {
      return true;
    }

    if ($course['enable_sequence_lock'] === null) {
      return true;
    }

    return (int)$course['enable_sequence_lock'] === 1;
  }

  public static function biometric_required(array $course): bool {
    if (!array_key_exists('require_biometric', $course)) {
      return false;
    }

    if ($course['require_biometric'] === null) {
      return false;
    }

    return (int)$course['require_biometric'] === 1;
  }

  public static function final_exam_unlock_hours(array $course): int {
    if (!array_key_exists('final_exam_unlock_hours', $course)) {
      return 0;
    }

    if ($course['final_exam_unlock_hours'] === null) {
      return 0;
    }

    return max(0, (int)$course['final_exam_unlock_hours']);
  }

  public static function node_counts_towards_progress(array $node): bool {
    if (self::node_is_library_only($node)) {
      return false;
    }

    if (array_key_exists('count_in_progress_percent', $node) && $node['count_in_progress_percent'] !== null) {
      return (int)$node['count_in_progress_percent'] === 1;
    }

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    return !($kind === 'action' && $subtype === 'certificate');
  }

  public static function node_is_sequential(array $node): bool {
    $rules = self::node_rules($node);
    if (array_key_exists('sequential', $rules)) {
      return self::bool_from_input($rules['sequential'], true) === 1;
    }

    return true;
  }

  public static function node_is_library_only(array $node): bool {
    $rules = self::node_rules($node);
    if (array_key_exists('library_only', $rules)) {
      return self::bool_from_input($rules['library_only'], false) === 1;
    }

    return false;
  }

  public static function node_progress_sql(string $alias = ''): string {
    $prefix = trim($alias);
    if ($prefix !== '' && substr($prefix, -1) !== '.') {
      $prefix .= '.';
    }

    return "COALESCE({$prefix}count_in_progress_percent, CASE WHEN {$prefix}kind = 'action' AND COALESCE({$prefix}subtype, '') = 'certificate' THEN 0 ELSE 1 END) = 1";
  }

  private static function node_rules(array $node): array {
    $raw = $node['rules_json'] ?? null;
    if ($raw === null || $raw === '') {
      return [];
    }

    if (is_array($raw)) {
      return $raw;
    }

    $decoded = json_decode((string)$raw, true);
    return is_array($decoded) ? $decoded : [];
  }

  public static function bool_from_input($value, bool $default): int {
    if ($value === null || $value === '') {
      return $default ? 1 : 0;
    }

    if (is_bool($value)) {
      return $value ? 1 : 0;
    }

    $normalized = strtolower(trim((string)$value));
    if ($normalized === '1' || $normalized === 'true' || $normalized === 'on' || $normalized === 'sim') {
      return 1;
    }
    if ($normalized === '0' || $normalized === 'false' || $normalized === 'off' || $normalized === 'nao' || $normalized === 'não') {
      return 0;
    }

    return $default ? 1 : 0;
  }

  public static function biometric_session_key(int $courseId, int $userId): string {
    return $courseId . ':' . $userId;
  }

  public static function register_course_access(array $course, int $userId): array {
    $courseId = (int)($course['id'] ?? 0);
    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    $hours = self::final_exam_unlock_hours($course);
    $now = time();
    $cacheKey = $courseId . ':' . $userId . ':' . $hours;

    if ($courseId <= 0 || $userId <= 0) {
      return self::build_final_exam_gate_payload($hours, 0, 0, false);
    }

    $row = Db::one(
      "SELECT id,
              UNIX_TIMESTAMP(first_access_at) AS first_ts,
              UNIX_TIMESTAMP(last_access_at) AS last_ts
         FROM app_course_user_access
        WHERE course_id = :course_id
          AND moodle_userid = :user_id
        LIMIT 1",
      [
        'course_id' => $courseId,
        'user_id' => $userId,
      ]
    );

    $firstTs = (int)($row['first_ts'] ?? 0);
    $lastTs = (int)($row['last_ts'] ?? 0);
    $resolvedFirstTs = $firstTs > 0
      ? $firstTs
      : self::resolve_initial_course_access_timestamp($courseId, $userId, $moodleCourseId, $now);

    if ($resolvedFirstTs <= 0) {
      $resolvedFirstTs = $now;
    }
    if ($resolvedFirstTs > $now) {
      $resolvedFirstTs = $now;
    }

    if ($row && isset($row['id'])) {
      if ($firstTs !== $resolvedFirstTs || $lastTs < $now) {
        Db::exec(
          "UPDATE app_course_user_access
              SET first_access_at = FROM_UNIXTIME(:first_ts),
                  last_access_at = FROM_UNIXTIME(:last_ts)
            WHERE id = :id",
          [
            'first_ts' => $resolvedFirstTs,
            'last_ts' => $now,
            'id' => (int)$row['id'],
          ]
        );
      }
    } else {
      Db::exec(
        "INSERT INTO app_course_user_access (course_id, moodle_userid, first_access_at, last_access_at)
         VALUES (:course_id, :user_id, FROM_UNIXTIME(:first_ts), FROM_UNIXTIME(:last_ts))",
        [
          'course_id' => $courseId,
          'user_id' => $userId,
          'first_ts' => $resolvedFirstTs,
          'last_ts' => $now,
        ]
      );
    }

    $payload = self::build_final_exam_gate_payload($hours, $resolvedFirstTs, $now, true);
    self::$finalExamGateCache[$cacheKey] = $payload;
    return $payload;
  }

  public static function final_exam_gate_snapshot(array $course, int $userId, bool $touchAccess = false): array {
    $courseId = (int)($course['id'] ?? 0);
    $hours = self::final_exam_unlock_hours($course);
    $cacheKey = $courseId . ':' . $userId . ':' . $hours;

    if ($touchAccess) {
      return self::register_course_access($course, $userId);
    }

    if (isset(self::$finalExamGateCache[$cacheKey])) {
      return self::$finalExamGateCache[$cacheKey];
    }

    if ($courseId <= 0 || $userId <= 0) {
      return self::build_final_exam_gate_payload($hours, 0, 0, false);
    }

    $row = Db::one(
      "SELECT UNIX_TIMESTAMP(first_access_at) AS first_ts,
              UNIX_TIMESTAMP(last_access_at) AS last_ts
         FROM app_course_user_access
        WHERE course_id = :course_id
          AND moodle_userid = :user_id
        LIMIT 1",
      [
        'course_id' => $courseId,
        'user_id' => $userId,
      ]
    );

    $firstTs = (int)($row['first_ts'] ?? 0);
    $lastTs = (int)($row['last_ts'] ?? 0);
    $payload = self::build_final_exam_gate_payload($hours, $firstTs, $lastTs, $row !== null);
    self::$finalExamGateCache[$cacheKey] = $payload;
    return $payload;
  }

  public static function final_exam_gate_preview(array $course, int $userId): array {
    $courseId = (int)($course['id'] ?? 0);
    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    $hours = self::final_exam_unlock_hours($course);
    $cacheKey = 'preview:' . $courseId . ':' . $userId . ':' . $hours;

    if (isset(self::$finalExamGateCache[$cacheKey])) {
      return self::$finalExamGateCache[$cacheKey];
    }

    if ($courseId <= 0 || $userId <= 0) {
      return self::build_final_exam_gate_payload($hours, 0, 0, false);
    }

    $row = Db::one(
      "SELECT UNIX_TIMESTAMP(first_access_at) AS first_ts,
              UNIX_TIMESTAMP(last_access_at) AS last_ts
         FROM app_course_user_access
        WHERE course_id = :course_id
          AND moodle_userid = :user_id
        LIMIT 1",
      [
        'course_id' => $courseId,
        'user_id' => $userId,
      ]
    );

    $firstTs = (int)($row['first_ts'] ?? 0);
    $lastTs = (int)($row['last_ts'] ?? 0);

    if ($firstTs <= 0) {
      $firstTs = self::resolve_initial_course_access_timestamp($courseId, $userId, $moodleCourseId, 0);
      if ($firstTs > 0 && $lastTs <= 0) {
        $lastTs = $firstTs;
      }
    }

    $payload = self::build_final_exam_gate_payload($hours, $firstTs, $lastTs, $row !== null || $firstTs > 0);
    self::$finalExamGateCache[$cacheKey] = $payload;
    return $payload;
  }

  public static function mark_biometric_verified(int $courseId, int $userId): void {
    self::store_biometric_timestamp($courseId, $userId, time());
  }

  public static function is_biometric_verified(int $courseId, int $userId): bool {
    $ttl = (int)App::cfg('biometric_ttl_seconds', 4 * 3600);
    $now = time();
    $dbTimestamp = self::fetch_last_biometric_timestamp($courseId, $userId);
    if ($dbTimestamp > 0 && ($ttl <= 0 || ($dbTimestamp + $ttl) >= $now)) {
      self::store_biometric_timestamp($courseId, $userId, $dbTimestamp);
      return true;
    }

    self::clear_biometric_timestamp($courseId, $userId);
    return false;
  }

  private static function read_biometric_session_timestamp(int $courseId, int $userId): int {
    global $SESSION;

    if (!isset($SESSION->app_v3_biometric_verified) || !is_array($SESSION->app_v3_biometric_verified)) {
      return 0;
    }

    return (int)($SESSION->app_v3_biometric_verified[self::biometric_session_key($courseId, $userId)] ?? 0);
  }

  private static function store_biometric_timestamp(int $courseId, int $userId, int $timestamp): void {
    global $SESSION;

    if (!isset($SESSION->app_v3_biometric_verified) || !is_array($SESSION->app_v3_biometric_verified)) {
      $SESSION->app_v3_biometric_verified = [];
    }

    $key = self::biometric_session_key($courseId, $userId);
    $SESSION->app_v3_biometric_verified[$key] = max(0, $timestamp);
    self::$biometricRequestCache[$key] = max(0, $timestamp);
  }

  private static function clear_biometric_timestamp(int $courseId, int $userId): void {
    global $SESSION;

    $key = self::biometric_session_key($courseId, $userId);
    unset(self::$biometricRequestCache[$key]);
    if (isset($SESSION->app_v3_biometric_verified) && is_array($SESSION->app_v3_biometric_verified)) {
      unset($SESSION->app_v3_biometric_verified[$key]);
    }
  }

  private static function fetch_last_biometric_timestamp(int $courseId, int $userId): int {
    $key = self::biometric_session_key($courseId, $userId);
    if (array_key_exists($key, self::$biometricRequestCache)) {
      return (int)self::$biometricRequestCache[$key];
    }

    $row = Db::one(
      "SELECT UNIX_TIMESTAMP(captured_at) AS captured_ts
         FROM app_course_biometric_audit
        WHERE course_id = :course_id
          AND moodle_userid = :user_id
          AND status = 'approved'
        ORDER BY captured_at DESC, id DESC
        LIMIT 1",
      [
        'course_id' => $courseId,
        'user_id' => $userId,
      ]
    );

    $timestamp = (int)($row['captured_ts'] ?? 0);
    self::$biometricRequestCache[$key] = $timestamp;
    return $timestamp;
  }

  private static function resolve_initial_course_access_timestamp(int $courseId, int $userId, int $moodleCourseId, int $fallbackTs): int {
    $moodleFirstAccess = Moodle::course_first_access_timestamp($userId, $moodleCourseId);
    if ($moodleFirstAccess > 0) {
      return $moodleFirstAccess;
    }

    $progressRow = Db::one(
      "SELECT UNIX_TIMESTAMP(MIN(COALESCE(last_seen_at, completed_at))) AS first_ts
         FROM app_progress
        WHERE course_id = :course_id
          AND moodle_userid = :user_id",
      [
        'course_id' => $courseId,
        'user_id' => $userId,
      ]
    );
    $progressFirstAccess = (int)($progressRow['first_ts'] ?? 0);
    if ($progressFirstAccess > 0) {
      return $progressFirstAccess;
    }

    return max(0, $fallbackTs);
  }

  private static function build_final_exam_gate_payload(int $hours, int $firstAccessTs, int $lastAccessTs, bool $hasRecord): array {
    $enabled = $hours > 0;
    $unlockTs = ($enabled && $firstAccessTs > 0)
      ? ($firstAccessTs + ($hours * 3600))
      : 0;
    $now = time();
    $remainingSeconds = ($enabled && $unlockTs > 0)
      ? max(0, $unlockTs - $now)
      : 0;
    $unlocked = !$enabled || ($unlockTs > 0 && $remainingSeconds <= 0);

    return [
      'enabled' => $enabled,
      'has_record' => $hasRecord,
      'hours' => $hours,
      'first_access_ts' => $firstAccessTs,
      'last_access_ts' => $lastAccessTs,
      'unlock_ts' => $unlockTs,
      'remaining_seconds' => $remainingSeconds,
      'unlocked' => $unlocked,
      'blocked' => $enabled && !$unlocked,
    ];
  }
}
