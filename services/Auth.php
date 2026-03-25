<?php
declare(strict_types=1);

namespace App;

final class Auth {
  public static function require_login(): void {
    \require_login();
    global $USER;
    if (!empty($USER->id)) {
      Moodle::sync_system_access((int)$USER->id);
      try {
        self::ensure_runtime_snapshot(false);
      } catch (\Throwable $e) {
        // Fallback silencioso: nao derruba a sessao por falha no prefetch.
        error_log('[app_v3] Falha ao preparar snapshot de acesso: ' . $e->getMessage());
      }
    }
  }

  public static function user(): \stdClass {
    global $USER;
    return $USER;
  }

  public static function is_site_admin(): bool {
    global $USER;
    return \is_siteadmin($USER);
  }

  public static function is_app_admin(int $moodleUserid): bool {
    if (\is_siteadmin($moodleUserid)) return true;

    $row = Db::one(
      "SELECT role FROM app_admin_role WHERE moodle_userid = :uid AND is_active = 1 LIMIT 1",
      ['uid' => $moodleUserid]
    );

    return $row !== null;
  }

  public static function require_app_admin(): void {
    self::require_login();
    $user = self::user();

    if (!self::is_app_admin((int)$user->id)) {
      throw new \RuntimeException('Acesso negado: area administrativa.');
    }
  }

  public static function login_with_moodle(string $username, string $password): bool {
    // Compativel com versoes do Moodle em que o 4o parametro e passado por referencia.
    $failureReason = null;
    $user = \authenticate_user_login($username, $password, false, $failureReason);
    if (!$user || empty($user->id)) return false;

    \complete_user_login($user);
    return true;
  }

  public static function logout_and_redirect(string $url): void {
    \require_logout();
    Response::redirect($url);
  }

  public static function ensure_runtime_snapshot(bool $force = false): void {
    global $SESSION, $USER;

    if ((int)App::cfg('moodle_prefetch_on_login_enabled', 1) !== 1) {
      return;
    }

    $moodleUserId = (int)($USER->id ?? 0);
    if ($moodleUserId <= 0) {
      return;
    }

    $loadedFor = (int)($SESSION->app_v3_runtime_snapshot_userid ?? 0);
    if (
      !$force
      && $loadedFor === $moodleUserId
      && isset($SESSION->app_v3_runtime_snapshot)
      && is_array($SESSION->app_v3_runtime_snapshot)
    ) {
      return;
    }

    try {
      $isAdmin = self::is_app_admin($moodleUserId);
      $syncProgress = (int)App::cfg('moodle_login_prefetch_progress_enabled', 1) === 1;

      $courses = Db::all(
        "SELECT c.id, c.moodle_courseid
           FROM app_course c
           JOIN (
             SELECT course_id, MAX(only_app) AS only_app
               FROM app_course_runtime
              GROUP BY course_id
           ) r ON r.course_id = c.id
          WHERE c.status = 'published'
            AND r.only_app = 1
            AND c.moodle_courseid > 0"
      );

      $snapshot = [];
      foreach ($courses as $course) {
        $courseId = (int)($course['id'] ?? 0);
        $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
        if ($courseId <= 0 || $moodleCourseId <= 0) {
          continue;
        }

        try {
          $win = Moodle::enrol_window($moodleUserId, $moodleCourseId);
          $isEnrolled = false;
          $timestart = 0;
          $timeend = 0;

          if (is_array($win)) {
            $timestart = (int)($win['timestart'] ?? 0);
            $timeend = (int)($win['timeend'] ?? 0);
            $isEnrolled = $isAdmin ? true : Moodle::is_enrolled_in_course($moodleCourseId, $moodleUserId);
          }

          if ($syncProgress && $isEnrolled) {
            try {
              ProgressSyncService::sync_user_course_from_moodle($courseId, $moodleUserId, true);
            } catch (\Throwable $syncError) {
              error_log('[app_v3] Falha no prefetch de progresso (course ' . $courseId . '): ' . $syncError->getMessage());
            }
          }

          $snapshot[$courseId] = [
            'course_id' => $courseId,
            'moodle_courseid' => $moodleCourseId,
            'is_enrolled' => $isEnrolled ? 1 : 0,
            'has_window' => is_array($win) ? 1 : 0,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'snapshot_at' => time(),
          ];
        } catch (\Throwable $e) {
          error_log('[app_v3] Falha no prefetch de acesso (course ' . $courseId . '): ' . $e->getMessage());
        }
      }

      $SESSION->app_v3_runtime_snapshot = $snapshot;
      $SESSION->app_v3_runtime_snapshot_userid = $moodleUserId;
      $SESSION->app_v3_runtime_snapshot_at = time();
    } catch (\Throwable $e) {
      $SESSION->app_v3_runtime_snapshot = [];
      $SESSION->app_v3_runtime_snapshot_userid = $moodleUserId;
      $SESSION->app_v3_runtime_snapshot_at = time();
      error_log('[app_v3] Snapshot runtime indisponivel: ' . $e->getMessage());
    }
  }

  public static function course_access_snapshot(int $courseId): ?array {
    global $SESSION, $USER;
    if ((int)($USER->id ?? 0) <= 0) {
      return null;
    }
    if (!isset($SESSION->app_v3_runtime_snapshot) || !is_array($SESSION->app_v3_runtime_snapshot)) {
      self::ensure_runtime_snapshot(false);
    }
    $snapshot = $SESSION->app_v3_runtime_snapshot ?? null;
    if (!is_array($snapshot)) {
      return null;
    }
    $entry = $snapshot[$courseId] ?? null;
    return is_array($entry) ? $entry : null;
  }

  public static function has_any_cached_app_enrollment(): bool {
    global $SESSION, $USER;
    if ((int)($USER->id ?? 0) <= 0) {
      return false;
    }
    if (!isset($SESSION->app_v3_runtime_snapshot) || !is_array($SESSION->app_v3_runtime_snapshot)) {
      self::ensure_runtime_snapshot(false);
    }
    $snapshot = $SESSION->app_v3_runtime_snapshot ?? null;
    if (!is_array($snapshot) || empty($snapshot)) {
      return false;
    }

    $now = time();
    foreach ($snapshot as $entry) {
      if (!is_array($entry)) {
        continue;
      }
      if ((int)($entry['is_enrolled'] ?? 0) !== 1) {
        continue;
      }
      $timestart = (int)($entry['timestart'] ?? 0);
      $timeend = (int)($entry['timeend'] ?? 0);
      if ($timestart > 0 && $timestart > $now) {
        continue;
      }
      if ($timeend > 0 && $now > $timeend) {
        continue;
      }
      return true;
    }

    return false;
  }
}
