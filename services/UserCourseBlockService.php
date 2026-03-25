<?php
declare(strict_types=1);

namespace App;

final class UserCourseBlockService {
  public static function default_title(): string {
    return 'Acesso bloqueado';
  }

  public static function default_message(): string {
    return 'Seu acesso a este curso foi bloqueado por descumprimento das regras de validacao. Entre em contato com a equipe administrativa para regularizacao.';
  }

  public static function get(int $courseId, int $moodleUserId): ?array {
    Schema::ensure();

    if ($courseId <= 0 || $moodleUserId <= 0) {
      return null;
    }

    $row = Db::one(
      "SELECT *
         FROM app_course_user_block
        WHERE course_id = :course_id
          AND moodle_userid = :moodle_userid
        LIMIT 1",
      [
        'course_id' => $courseId,
        'moodle_userid' => $moodleUserId,
      ]
    );

    return $row ?: null;
  }

  public static function active_for_user(int $courseId, int $moodleUserId): ?array {
    Schema::ensure();

    if ($courseId <= 0 || $moodleUserId <= 0) {
      return null;
    }

    $row = Db::one(
      "SELECT *
         FROM app_course_user_block
        WHERE course_id = :course_id
          AND moodle_userid = :moodle_userid
          AND is_blocked = 1
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1",
      [
        'course_id' => $courseId,
        'moodle_userid' => $moodleUserId,
      ]
    );

    return $row ?: null;
  }

  public static function active_map_for_user_courses(int $moodleUserId, array $courseIds): array {
    Schema::ensure();

    $courseIds = self::normalize_positive_ids($courseIds);
    if ($moodleUserId <= 0 || empty($courseIds)) {
      return [];
    }

    [$inSql, $params] = self::build_in_params('cid', $courseIds);
    $params['moodle_userid'] = $moodleUserId;

    $rows = Db::all(
      "SELECT *
         FROM app_course_user_block
        WHERE moodle_userid = :moodle_userid
          AND course_id IN ({$inSql})
          AND is_blocked = 1
          AND (expires_at IS NULL OR expires_at > NOW())",
      $params
    );

    $map = [];
    foreach ($rows as $row) {
      $map[(int)($row['course_id'] ?? 0)] = $row;
    }

    return $map;
  }

  public static function active_map_for_course_users(int $courseId, array $userIds): array {
    Schema::ensure();

    $userIds = self::normalize_positive_ids($userIds);
    if ($courseId <= 0 || empty($userIds)) {
      return [];
    }

    [$inSql, $params] = self::build_in_params('uid', $userIds);
    $params['course_id'] = $courseId;

    $rows = Db::all(
      "SELECT *
         FROM app_course_user_block
        WHERE course_id = :course_id
          AND moodle_userid IN ({$inSql})
          AND is_blocked = 1
          AND (expires_at IS NULL OR expires_at > NOW())",
      $params
    );

    $map = [];
    foreach ($rows as $row) {
      $map[(int)($row['moodle_userid'] ?? 0)] = $row;
    }

    return $map;
  }

  public static function active_lookup(array $pairs): array {
    Schema::ensure();

    $clauses = [];
    $params = [];
    $seen = [];
    $index = 0;

    foreach ($pairs as $pair) {
      $courseId = (int)($pair['course_id'] ?? 0);
      $userId = (int)($pair['moodle_userid'] ?? 0);
      if ($courseId <= 0 || $userId <= 0) {
        continue;
      }
      $key = self::key($courseId, $userId);
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = true;
      $courseParam = 'course_' . $index;
      $userParam = 'user_' . $index;
      $clauses[] = "(course_id = :{$courseParam} AND moodle_userid = :{$userParam})";
      $params[$courseParam] = $courseId;
      $params[$userParam] = $userId;
      $index++;
    }

    if (empty($clauses)) {
      return [];
    }

    $rows = Db::all(
      "SELECT *
         FROM app_course_user_block
        WHERE is_blocked = 1
          AND (expires_at IS NULL OR expires_at > NOW())
          AND (" . implode(' OR ', $clauses) . ")",
      $params
    );

    $map = [];
    foreach ($rows as $row) {
      $map[self::key((int)($row['course_id'] ?? 0), (int)($row['moodle_userid'] ?? 0))] = $row;
    }

    return $map;
  }

  public static function block(int $courseId, int $moodleUserId, array $input, int $adminUserId): array {
    Schema::ensure();

    if ($courseId <= 0 || $moodleUserId <= 0) {
      throw new \InvalidArgumentException('Curso ou usuario invalido para bloqueio.');
    }

    $title = self::sanitize_title((string)($input['title'] ?? ''));
    $message = self::sanitize_message((string)($input['message'] ?? ''));
    $reasonCode = self::sanitize_reason_code((string)($input['reason_code'] ?? 'manual'));
    $expiresAt = self::normalize_datetime($input['expires_at'] ?? null);

    Db::exec(
      "INSERT INTO app_course_user_block (
         course_id,
         moodle_userid,
         reason_code,
         title,
         message,
         is_blocked,
         blocked_at,
         blocked_by_moodle_userid,
         expires_at,
         unblocked_at,
         unblocked_by_moodle_userid
       ) VALUES (
         :course_id,
         :moodle_userid,
         :reason_code,
         :title,
         :message,
         1,
         NOW(),
         :blocked_by,
         :expires_at,
         NULL,
         NULL
       )
       ON DUPLICATE KEY UPDATE
         reason_code = VALUES(reason_code),
         title = VALUES(title),
         message = VALUES(message),
         is_blocked = 1,
         blocked_at = NOW(),
         blocked_by_moodle_userid = VALUES(blocked_by_moodle_userid),
         expires_at = VALUES(expires_at),
         unblocked_at = NULL,
         unblocked_by_moodle_userid = NULL",
      [
        'course_id' => $courseId,
        'moodle_userid' => $moodleUserId,
        'reason_code' => $reasonCode,
        'title' => $title,
        'message' => $message,
        'blocked_by' => $adminUserId > 0 ? $adminUserId : null,
        'expires_at' => $expiresAt,
      ]
    );

    return self::active_for_user($courseId, $moodleUserId) ?? self::get($courseId, $moodleUserId) ?? [];
  }

  public static function unblock(int $courseId, int $moodleUserId, int $adminUserId): void {
    Schema::ensure();

    if ($courseId <= 0 || $moodleUserId <= 0) {
      throw new \InvalidArgumentException('Curso ou usuario invalido para desbloqueio.');
    }

    Db::exec(
      "UPDATE app_course_user_block
          SET is_blocked = 0,
              unblocked_at = NOW(),
              unblocked_by_moodle_userid = :unblocked_by
        WHERE course_id = :course_id
          AND moodle_userid = :moodle_userid",
      [
        'course_id' => $courseId,
        'moodle_userid' => $moodleUserId,
        'unblocked_by' => $adminUserId > 0 ? $adminUserId : null,
      ]
    );
  }

  public static function title_for_row(?array $row): string {
    $title = trim((string)($row['title'] ?? ''));
    return $title !== '' ? $title : self::default_title();
  }

  public static function message_for_row(?array $row): string {
    $message = trim((string)($row['message'] ?? ''));
    return $message !== '' ? $message : self::default_message();
  }

  public static function key(int $courseId, int $moodleUserId): string {
    return $courseId . ':' . $moodleUserId;
  }

  private static function sanitize_title(string $title): string {
    $value = trim($title);
    if ($value === '') {
      $value = self::default_title();
    }

    if (function_exists('mb_substr')) {
      return mb_substr($value, 0, 180, 'UTF-8');
    }

    return substr($value, 0, 180);
  }

  private static function sanitize_message(string $message): string {
    $value = trim($message);
    if ($value === '') {
      $value = self::default_message();
    }

    if (function_exists('mb_substr')) {
      return mb_substr($value, 0, 4000, 'UTF-8');
    }

    return substr($value, 0, 4000);
  }

  private static function sanitize_reason_code(string $reasonCode): string {
    $value = strtolower(trim($reasonCode));
    $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value) ?: 'manual';
    return substr($value, 0, 40);
  }

  private static function normalize_datetime($value): ?string {
    if ($value === null) {
      return null;
    }

    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
      return null;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
      return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
  }

  private static function normalize_positive_ids(array $values): array {
    $normalized = [];
    foreach ($values as $value) {
      $id = (int)$value;
      if ($id > 0) {
        $normalized[$id] = $id;
      }
    }
    return array_values($normalized);
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
