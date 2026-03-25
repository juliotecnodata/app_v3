<?php
declare(strict_types=1);

namespace App;

final class UserAlertService {
  private const SCOPES = ['all', 'dashboard', 'study', 'biometric'];
  private const SEVERITIES = ['info', 'warning', 'danger'];

  public static function normalize_scope(string $scope): string {
    $value = strtolower(trim($scope));
    return in_array($value, self::SCOPES, true) ? $value : 'all';
  }

  public static function normalize_severity(string $severity): string {
    $value = strtolower(trim($severity));
    return in_array($value, self::SEVERITIES, true) ? $value : 'warning';
  }

  public static function get(int $alertId): ?array {
    $row = Db::one(
      "SELECT *
         FROM app_user_alert
        WHERE id = :id
        LIMIT 1",
      ['id' => $alertId]
    );

    return $row ?: null;
  }

  public static function create(array $input, int $createdByUserId): int {
    $payload = self::prepare_payload($input);

    Db::exec(
      "INSERT INTO app_user_alert (
         moodle_userid,
         course_id,
         scope,
         severity,
         title,
         message,
         is_blocking,
         require_ack,
         status,
         active_from,
         expires_at,
         created_by_moodle_userid
       ) VALUES (
         :moodle_userid,
         :course_id,
         :scope,
         :severity,
         :title,
         :message,
         :is_blocking,
         :require_ack,
         'active',
         COALESCE(:active_from, NOW()),
         :expires_at,
         :created_by_moodle_userid
       )",
      [
        'moodle_userid' => $payload['moodle_userid'],
        'course_id' => $payload['course_id'] > 0 ? $payload['course_id'] : null,
        'scope' => $payload['scope'],
        'severity' => $payload['severity'],
        'title' => $payload['title'],
        'message' => $payload['message'],
        'is_blocking' => $payload['is_blocking'],
        'require_ack' => $payload['require_ack'],
        'active_from' => $payload['active_from'],
        'expires_at' => $payload['expires_at'],
        'created_by_moodle_userid' => $createdByUserId,
      ]
    );

    return (int)Db::lastId();
  }

  public static function update(int $alertId, array $input): void {
    $payload = self::prepare_payload($input);

    Db::exec(
      "UPDATE app_user_alert
          SET moodle_userid = :moodle_userid,
              course_id = :course_id,
              scope = :scope,
              severity = :severity,
              title = :title,
              message = :message,
              is_blocking = :is_blocking,
              require_ack = :require_ack,
              active_from = COALESCE(:active_from, NOW()),
              expires_at = :expires_at
        WHERE id = :id",
      [
        'id' => $alertId,
        'moodle_userid' => $payload['moodle_userid'],
        'course_id' => $payload['course_id'] > 0 ? $payload['course_id'] : null,
        'scope' => $payload['scope'],
        'severity' => $payload['severity'],
        'title' => $payload['title'],
        'message' => $payload['message'],
        'is_blocking' => $payload['is_blocking'],
        'require_ack' => $payload['require_ack'],
        'active_from' => $payload['active_from'],
        'expires_at' => $payload['expires_at'],
      ]
    );
  }

  public static function dismiss(int $alertId, int $closedByUserId): void {
    Db::exec(
      "UPDATE app_user_alert
          SET status = 'closed',
              closed_at = NOW(),
              closed_by_moodle_userid = :closed_by
        WHERE id = :id",
      [
        'id' => $alertId,
        'closed_by' => $closedByUserId,
      ]
    );
  }

  public static function delete(int $alertId): void {
    Db::exec(
      "DELETE FROM app_user_alert
        WHERE id = :id",
      ['id' => $alertId]
    );
  }

  public static function list_recent(?int $courseId = null, int $limit = 80): array {
    global $DB;

    $sql = "SELECT a.*,
                   c.title AS course_title
              FROM app_user_alert a
         LEFT JOIN app_course c
                ON c.id = a.course_id";

    $params = [];
    if (($courseId ?? 0) > 0) {
      $sql .= " WHERE a.course_id = :course_id";
      $params['course_id'] = (int)$courseId;
    }

    $limit = max(1, min(200, $limit));
    $sql .= " ORDER BY
                CASE
                  WHEN a.status = 'active' AND (a.expires_at IS NULL OR a.expires_at > NOW()) THEN 0
                  WHEN a.status = 'active' THEN 1
                  ELSE 2
                END ASC,
                a.created_at DESC
              LIMIT {$limit}";

    $rows = Db::all($sql, $params);
    $userIds = [];
    foreach ($rows as $row) {
      $uid = (int)($row['moodle_userid'] ?? 0);
      $creatorId = (int)($row['created_by_moodle_userid'] ?? 0);
      if ($uid > 0) {
        $userIds[$uid] = $uid;
      }
      if ($creatorId > 0) {
        $userIds[$creatorId] = $creatorId;
      }
    }

    $usersById = [];
    if (!empty($userIds)) {
      [$inSql, $inParams] = $DB->get_in_or_equal(array_values($userIds), \SQL_PARAMS_NAMED, 'alert_uid');
      $records = $DB->get_records_sql(
        "SELECT id, username, firstname, lastname, email
           FROM {user}
          WHERE id $inSql",
        $inParams
      );

      foreach ($records as $record) {
        $usersById[(int)$record->id] = $record;
      }
    }

    foreach ($rows as &$row) {
      $targetUser = $usersById[(int)($row['moodle_userid'] ?? 0)] ?? null;
      $creatorUser = $usersById[(int)($row['created_by_moodle_userid'] ?? 0)] ?? null;

      $row['full_name'] = $targetUser ? trim((string)\fullname($targetUser)) : '';
      $row['username'] = $targetUser ? (string)($targetUser->username ?? '') : '';
      $row['email'] = $targetUser ? (string)($targetUser->email ?? '') : '';
      $row['created_by_name'] = $creatorUser ? trim((string)\fullname($creatorUser)) : '';
      $row['created_by_username'] = $creatorUser ? (string)($creatorUser->username ?? '') : '';
    }
    unset($row);

    return $rows;
  }

  public static function summary(?int $courseId = null): array {
    $rows = self::list_recent($courseId, 200);
    $summary = [
      'total' => 0,
      'active' => 0,
      'blocking' => 0,
      'read' => 0,
      'expired' => 0,
    ];

    foreach ($rows as $row) {
      $summary['total']++;
      $status = self::status_snapshot($row);
      if ($status === 'active') {
        $summary['active']++;
      }
      if ($status === 'expired') {
        $summary['expired']++;
      }
      if ((int)($row['is_blocking'] ?? 0) === 1 && $status === 'active') {
        $summary['blocking']++;
      }
      if (!empty($row['acknowledged_at'])) {
        $summary['read']++;
      }
    }

    return $summary;
  }

  public static function current_for_user(int $moodleUserId, string $page, int $courseId = 0): ?array {
    $page = self::normalize_scope($page);

    $row = Db::one(
      "SELECT a.*
         FROM app_user_alert a
        WHERE a.moodle_userid = :moodle_userid
          AND a.status = 'active'
          AND a.active_from <= NOW()
          AND (a.expires_at IS NULL OR a.expires_at > NOW())
          AND (a.scope = 'all' OR a.scope = :scope)
          AND (
                a.course_id IS NULL
             OR a.course_id = 0
             OR a.course_id = :course_id
          )
          AND (
                a.require_ack = 0
             OR a.acknowledged_at IS NULL
          )
        ORDER BY a.is_blocking DESC, a.created_at DESC, a.id DESC
        LIMIT 1",
      [
        'moodle_userid' => $moodleUserId,
        'scope' => $page,
        'course_id' => max(0, $courseId),
      ]
    );

    if (!$row) {
      return null;
    }

    return [
      'id' => (int)($row['id'] ?? 0),
      'title' => (string)($row['title'] ?? ''),
      'message' => (string)($row['message'] ?? ''),
      'severity' => self::normalize_severity((string)($row['severity'] ?? 'warning')),
      'scope' => self::normalize_scope((string)($row['scope'] ?? 'all')),
      'course_id' => (int)($row['course_id'] ?? 0),
      'is_blocking' => (int)($row['is_blocking'] ?? 0) === 1,
      'require_ack' => (int)($row['require_ack'] ?? 1) === 1,
      'created_at_label' => self::format_datetime((string)($row['created_at'] ?? '')),
      'expires_at_label' => self::format_datetime((string)($row['expires_at'] ?? '')),
    ];
  }

  public static function acknowledge(int $alertId, int $moodleUserId, string $page): void {
    Db::exec(
      "UPDATE app_user_alert
          SET acknowledged_at = NOW(),
              acknowledged_page = :ack_page,
              acknowledged_by_moodle_userid = :ack_user
        WHERE id = :id
          AND moodle_userid = :moodle_userid
          AND status = 'active'
          AND acknowledged_at IS NULL",
      [
        'id' => $alertId,
        'moodle_userid' => $moodleUserId,
        'ack_page' => self::normalize_scope($page),
        'ack_user' => $moodleUserId,
      ]
    );
  }

  public static function status_snapshot(array $row): string {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($status !== 'active') {
      return 'closed';
    }
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time()) {
      return 'expired';
    }
    return 'active';
  }

  public static function format_datetime(string $value): string {
    $value = trim($value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
      return '';
    }

    try {
      $timezone = new \DateTimeZone((string)App::cfg('timezone', 'America/Sao_Paulo'));
      $date = new \DateTimeImmutable($value, $timezone);
      return $date->format('d/m/Y H:i');
    } catch (\Throwable $exception) {
      return $value;
    }
  }

  private static function prepare_payload(array $input): array {
    $moodleUserId = (int)($input['moodle_userid'] ?? 0);
    $courseId = (int)($input['course_id'] ?? 0);
    $scope = self::normalize_scope((string)($input['scope'] ?? 'all'));
    $severity = self::normalize_severity((string)($input['severity'] ?? 'warning'));
    $title = trim((string)($input['title'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    $isBlocking = !empty($input['is_blocking']) ? 1 : 0;
    $requireAck = array_key_exists('require_ack', $input) ? (!empty($input['require_ack']) ? 1 : 0) : 1;
    $activeFrom = trim((string)($input['active_from'] ?? ''));
    $expiresAt = trim((string)($input['expires_at'] ?? ''));

    if ($moodleUserId <= 0) {
      throw new \RuntimeException('Informe o UID do aluno.');
    }
    if ($title === '') {
      throw new \RuntimeException('Informe o titulo do alerta.');
    }
    if ($message === '') {
      throw new \RuntimeException('Informe a mensagem do alerta.');
    }

    $activeFromSql = self::normalize_datetime($activeFrom);
    $expiresAtSql = self::normalize_datetime($expiresAt);

    if ($activeFrom !== '' && $activeFromSql === null) {
      throw new \RuntimeException('Data de ativacao invalida.');
    }
    if ($expiresAt !== '' && $expiresAtSql === null) {
      throw new \RuntimeException('Data de expiracao invalida.');
    }
    if ($expiresAtSql !== null && $activeFromSql !== null && strtotime($expiresAtSql) <= strtotime($activeFromSql)) {
      throw new \RuntimeException('A expiracao precisa ser maior que a ativacao.');
    }

    return [
      'moodle_userid' => $moodleUserId,
      'course_id' => $courseId,
      'scope' => $scope,
      'severity' => $severity,
      'title' => $title,
      'message' => $message,
      'is_blocking' => $isBlocking,
      'require_ack' => $requireAck,
      'active_from' => $activeFromSql,
      'expires_at' => $expiresAtSql,
    ];
  }

  private static function normalize_datetime(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
      return null;
    }

    $timezone = new \DateTimeZone((string)App::cfg('timezone', 'America/Sao_Paulo'));
    $formats = ['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
      $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
      if ($date instanceof \DateTimeImmutable) {
        return $date->format('Y-m-d H:i:s');
      }
    }

    try {
      $date = new \DateTimeImmutable($value, $timezone);
      return $date->format('Y-m-d H:i:s');
    } catch (\Throwable $exception) {
      return null;
    }
  }
}
