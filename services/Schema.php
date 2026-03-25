<?php
declare(strict_types=1);

namespace App;

final class Schema {
  private static bool $ensured = false;

  public static function ensure(): void {
    if (self::$ensured) {
      return;
    }
    self::$ensured = true;

    self::ensure_course_policy_columns();
    self::ensure_course_display_columns();
    self::ensure_course_media_columns();
    self::ensure_settings_table();
    self::ensure_course_user_block_table();
    self::ensure_user_alert_table();
    self::ensure_course_runtime_table();
    self::ensure_support_material_table();
    self::ensure_biometric_audit_table();
    self::ensure_course_user_access_table();
    self::ensure_node_moodle_mapping_columns();
    self::ensure_node_progress_columns();
    self::ensure_progress_indexes();
  }

  public static function ensure_biometric_provider_columns(): void {
    self::ensure_biometric_audit_table();
  }

  private static function ensure_settings_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_settings (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         setting_key VARCHAR(120) NOT NULL,
         setting_value TEXT NULL,
         updated_by_moodle_userid INT NULL,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         UNIQUE KEY uq_app_settings_key (setting_key)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_index('app_settings', 'uq_app_settings_key')) {
      Db::exec("ALTER TABLE app_settings ADD UNIQUE KEY uq_app_settings_key (setting_key)");
    }
  }

  private static function ensure_course_user_block_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_course_user_block (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         course_id BIGINT UNSIGNED NOT NULL,
         moodle_userid INT NOT NULL,
         reason_code VARCHAR(40) NOT NULL DEFAULT 'manual',
         title VARCHAR(180) NOT NULL,
         message TEXT NOT NULL,
         is_blocked TINYINT(1) NOT NULL DEFAULT 1,
         blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         blocked_by_moodle_userid INT NULL,
         expires_at DATETIME NULL,
         unblocked_at DATETIME NULL,
         unblocked_by_moodle_userid INT NULL,
         created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         UNIQUE KEY uq_course_user_block (course_id, moodle_userid),
         KEY ix_course_user_block_user (moodle_userid, is_blocked, expires_at),
         KEY ix_course_user_block_state (course_id, is_blocked, expires_at)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_index('app_course_user_block', 'uq_course_user_block')) {
      Db::exec("ALTER TABLE app_course_user_block ADD UNIQUE KEY uq_course_user_block (course_id, moodle_userid)");
    }

    if (!self::has_index('app_course_user_block', 'ix_course_user_block_user')) {
      Db::exec("ALTER TABLE app_course_user_block ADD INDEX ix_course_user_block_user (moodle_userid, is_blocked, expires_at)");
    }

    if (!self::has_index('app_course_user_block', 'ix_course_user_block_state')) {
      Db::exec("ALTER TABLE app_course_user_block ADD INDEX ix_course_user_block_state (course_id, is_blocked, expires_at)");
    }
  }
  private static function ensure_user_alert_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_user_alert (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         moodle_userid INT NOT NULL,
         course_id BIGINT UNSIGNED NULL,
         scope VARCHAR(20) NOT NULL DEFAULT 'all',
         severity VARCHAR(20) NOT NULL DEFAULT 'warning',
         title VARCHAR(255) NOT NULL,
         message TEXT NOT NULL,
         is_blocking TINYINT(1) NOT NULL DEFAULT 0,
         require_ack TINYINT(1) NOT NULL DEFAULT 1,
         status VARCHAR(20) NOT NULL DEFAULT 'active',
         active_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         expires_at DATETIME NULL,
         acknowledged_at DATETIME NULL,
         acknowledged_page VARCHAR(20) NULL,
         acknowledged_by_moodle_userid INT NULL,
         closed_at DATETIME NULL,
         closed_by_moodle_userid INT NULL,
         created_by_moodle_userid INT NOT NULL,
         created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         KEY ix_user_alert_lookup (moodle_userid, status, active_from, expires_at),
         KEY ix_user_alert_course (course_id, status, created_at),
         KEY ix_user_alert_state (status, acknowledged_at, created_at)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_index('app_user_alert', 'ix_user_alert_lookup')) {
      Db::exec("ALTER TABLE app_user_alert ADD INDEX ix_user_alert_lookup (moodle_userid, status, active_from, expires_at)");
    }

    if (!self::has_index('app_user_alert', 'ix_user_alert_course')) {
      Db::exec("ALTER TABLE app_user_alert ADD INDEX ix_user_alert_course (course_id, status, created_at)");
    }

    if (!self::has_index('app_user_alert', 'ix_user_alert_state')) {
      Db::exec("ALTER TABLE app_user_alert ADD INDEX ix_user_alert_state (status, acknowledged_at, created_at)");
    }
  }

  private static function ensure_course_policy_columns(): void {
    if (!self::has_column('app_course', 'enable_sequence_lock')) {
      Db::exec("ALTER TABLE app_course ADD COLUMN enable_sequence_lock TINYINT(1) NOT NULL DEFAULT 1 AFTER access_days");
    }

    if (!self::has_column('app_course', 'require_biometric')) {
      Db::exec("ALTER TABLE app_course ADD COLUMN require_biometric TINYINT(1) NOT NULL DEFAULT 0 AFTER enable_sequence_lock");
    }

    if (!self::has_column('app_course', 'final_exam_unlock_hours')) {
      Db::exec("ALTER TABLE app_course ADD COLUMN final_exam_unlock_hours INT NOT NULL DEFAULT 0 AFTER require_biometric");
    }
  }

  private static function ensure_course_media_columns(): void {
    if (!self::has_column('app_course', 'image')) {
      Db::exec("ALTER TABLE app_course ADD COLUMN image VARCHAR(1024) NULL AFTER description");
    }
  }

  private static function ensure_course_display_columns(): void {
    if (!self::has_column('app_course', 'show_numbering')) {
      $anchor = self::has_column('app_course', 'final_exam_unlock_hours')
        ? 'final_exam_unlock_hours'
        : 'require_biometric';
      Db::exec("ALTER TABLE app_course ADD COLUMN show_numbering TINYINT(1) NOT NULL DEFAULT 0 AFTER {$anchor}");
    }
  }

  private static function ensure_course_user_access_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_course_user_access (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         course_id BIGINT UNSIGNED NOT NULL,
         moodle_userid INT NOT NULL,
         first_access_at DATETIME NOT NULL,
         last_access_at DATETIME NOT NULL,
         created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         UNIQUE KEY uq_course_user_access (course_id, moodle_userid),
         KEY ix_course_user_access_last (course_id, last_access_at),
         KEY ix_course_user_access_user (moodle_userid, course_id)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_index('app_course_user_access', 'uq_course_user_access')) {
      Db::exec("ALTER TABLE app_course_user_access ADD UNIQUE KEY uq_course_user_access (course_id, moodle_userid)");
    }

    if (!self::has_index('app_course_user_access', 'ix_course_user_access_last')) {
      Db::exec("ALTER TABLE app_course_user_access ADD INDEX ix_course_user_access_last (course_id, last_access_at)");
    }

    if (!self::has_index('app_course_user_access', 'ix_course_user_access_user')) {
      Db::exec("ALTER TABLE app_course_user_access ADD INDEX ix_course_user_access_user (moodle_userid, course_id)");
    }
  }

  private static function ensure_course_runtime_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_course_runtime (
         course_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
         only_app TINYINT(1) NOT NULL DEFAULT 0,
         updated_by_moodle_userid INT NULL,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         KEY ix_course_runtime_only_app (only_app)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_column('app_course_runtime', 'only_app')) {
      Db::exec("ALTER TABLE app_course_runtime ADD COLUMN only_app TINYINT(1) NOT NULL DEFAULT 0 AFTER course_id");
    }

    if (!self::has_column('app_course_runtime', 'updated_by_moodle_userid')) {
      Db::exec("ALTER TABLE app_course_runtime ADD COLUMN updated_by_moodle_userid INT NULL AFTER only_app");
    }

    if (!self::has_column('app_course_runtime', 'updated_at')) {
      Db::exec("ALTER TABLE app_course_runtime ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by_moodle_userid");
    }

    if (!self::has_index('app_course_runtime', 'ix_course_runtime_only_app')) {
      Db::exec("ALTER TABLE app_course_runtime ADD INDEX ix_course_runtime_only_app (only_app)");
    }
  }

  private static function ensure_support_material_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_support_material (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         course_id BIGINT UNSIGNED NOT NULL,
         title VARCHAR(255) NOT NULL,
         url VARCHAR(2048) NOT NULL,
         sort_order INT NOT NULL DEFAULT 0,
         is_published TINYINT(1) NOT NULL DEFAULT 1,
         created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         KEY ix_support_material_course (course_id, is_published, sort_order, id),
         KEY ix_support_material_published (is_published, updated_at)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_index('app_support_material', 'ix_support_material_course')) {
      Db::exec("ALTER TABLE app_support_material ADD INDEX ix_support_material_course (course_id, is_published, sort_order, id)");
    }

    if (!self::has_index('app_support_material', 'ix_support_material_published')) {
      Db::exec("ALTER TABLE app_support_material ADD INDEX ix_support_material_published (is_published, updated_at)");
    }
  }

  private static function ensure_biometric_audit_table(): void {
    Db::exec(
      "CREATE TABLE IF NOT EXISTS app_course_biometric_audit (
         id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
         course_id BIGINT UNSIGNED NOT NULL,
         moodle_userid INT NOT NULL,
         photo_path VARCHAR(255) NULL,
         photo_b64 MEDIUMTEXT NULL,
         photo_size_bytes INT UNSIGNED NULL,
         provider_name VARCHAR(30) NULL,
         provider_operation VARCHAR(20) NULL,
         provider_external_id VARCHAR(120) NULL,
         provider_score DECIMAL(10,4) NULL,
         provider_http_status SMALLINT UNSIGNED NULL,
         provider_message VARCHAR(255) NULL,
         provider_response_json TEXT NULL,
         status VARCHAR(20) NOT NULL DEFAULT 'approved',
         captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
         ip_address VARCHAR(64) NULL,
         user_agent VARCHAR(255) NULL,
         KEY ix_bio_course_user_time (course_id, moodle_userid, captured_at)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!self::has_column('app_course_biometric_audit', 'photo_b64')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN photo_b64 MEDIUMTEXT NULL AFTER photo_path");
    }

    if (!self::has_column('app_course_biometric_audit', 'photo_size_bytes')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN photo_size_bytes INT UNSIGNED NULL AFTER photo_b64");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_name')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_name VARCHAR(30) NULL AFTER photo_size_bytes");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_operation')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_operation VARCHAR(20) NULL AFTER provider_name");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_external_id')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_external_id VARCHAR(120) NULL AFTER provider_operation");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_score')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_score DECIMAL(10,4) NULL AFTER provider_external_id");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_http_status')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_http_status SMALLINT UNSIGNED NULL AFTER provider_score");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_message')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_message VARCHAR(255) NULL AFTER provider_http_status");
    }

    if (!self::has_column('app_course_biometric_audit', 'provider_response_json')) {
      Db::exec("ALTER TABLE app_course_biometric_audit ADD COLUMN provider_response_json TEXT NULL AFTER provider_message");
    }
  }

  private static function ensure_node_moodle_mapping_columns(): void {
    if (!self::has_column('app_node', 'moodle_cmid')) {
      Db::exec("ALTER TABLE app_node ADD COLUMN moodle_cmid BIGINT UNSIGNED NULL AFTER path");
    }

    if (!self::has_column('app_node', 'moodle_modname')) {
      Db::exec("ALTER TABLE app_node ADD COLUMN moodle_modname VARCHAR(50) NULL AFTER moodle_cmid");
    }

    if (!self::has_column('app_node', 'moodle_url')) {
      Db::exec("ALTER TABLE app_node ADD COLUMN moodle_url VARCHAR(2048) NULL AFTER moodle_modname");
    }

    if (!self::has_index('app_node', 'ix_node_course_moodle_cmid')) {
      Db::exec("ALTER TABLE app_node ADD INDEX ix_node_course_moodle_cmid (course_id, moodle_cmid)");
    }

    self::backfill_node_moodle_mapping_columns();
  }

  private static function ensure_node_progress_columns(): void {
    $added = false;

    if (!self::has_column('app_node', 'count_in_progress_percent')) {
      Db::exec("ALTER TABLE app_node ADD COLUMN count_in_progress_percent TINYINT(1) NOT NULL DEFAULT 1 AFTER is_published");
      $added = true;
    }

    if ($added) {
      // Preserva o comportamento atual: certificado nao entra no percentual.
      Db::exec(
        "UPDATE app_node
            SET count_in_progress_percent = 0
          WHERE kind = 'action'
            AND COALESCE(subtype, '') = 'certificate'"
      );
    }
  }

  private static function ensure_progress_indexes(): void {
    if (!self::has_index('app_progress', 'ix_progress_user_course_node')) {
      Db::exec("ALTER TABLE app_progress ADD INDEX ix_progress_user_course_node (moodle_userid, course_id, node_id)");
    }

    if (!self::has_index('app_progress', 'ix_progress_course_status_user')) {
      Db::exec("ALTER TABLE app_progress ADD INDEX ix_progress_course_status_user (course_id, status, moodle_userid, node_id)");
    }
  }

  private static function has_column(string $table, string $column): bool {
    $row = Db::one(
      "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
        LIMIT 1",
      [
        'table' => $table,
        'column' => $column,
      ]
    );

    return $row !== null;
  }

  private static function has_index(string $table, string $index): bool {
    $row = Db::one(
      "SELECT INDEX_NAME
         FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND INDEX_NAME = :index
        LIMIT 1",
      [
        'table' => $table,
        'index' => $index,
      ]
    );

    return $row !== null;
  }

  private static function backfill_node_moodle_mapping_columns(): void {
    $rows = Db::all(
      "SELECT id, content_json, moodle_cmid, moodle_modname, moodle_url
         FROM app_node
        WHERE content_json IS NOT NULL
          AND content_json <> ''
          AND (
            moodle_cmid IS NULL OR moodle_cmid = 0 OR
            moodle_modname IS NULL OR moodle_modname = '' OR
            moodle_url IS NULL OR moodle_url = ''
          )"
    );

    foreach ($rows as $row) {
      $id = (int)($row['id'] ?? 0);
      if ($id <= 0) {
        continue;
      }

      $contentRaw = (string)($row['content_json'] ?? '');
      $content = json_decode($contentRaw, true);
      if (!is_array($content)) {
        continue;
      }

      $cmid = (int)($row['moodle_cmid'] ?? 0);
      if ($cmid <= 0) {
        $cmid = (int)($content['moodle']['cmid'] ?? 0);
      }
      if ($cmid <= 0) {
        $cmid = (int)($content['cmid'] ?? 0);
      }
      if ($cmid <= 0) {
        $cmid = (int)($content['moodle_cmid'] ?? 0);
      }
      if ($cmid <= 0) {
        $urlForCmid = trim((string)($row['moodle_url'] ?? ''));
        if ($urlForCmid === '') {
          $urlForCmid = trim((string)($content['moodle']['url'] ?? $content['moodle_url'] ?? ''));
        }
        $cmid = self::extract_cmid_from_url($urlForCmid);
      }

      $modname = trim((string)($row['moodle_modname'] ?? ''));
      if ($modname === '') {
        $modname = trim((string)($content['moodle']['modname'] ?? $content['moodle_modname'] ?? ''));
      }
      if ($modname !== '') {
        $modname = function_exists('mb_substr') ? mb_substr($modname, 0, 50, 'UTF-8') : substr($modname, 0, 50);
      }

      $url = trim((string)($row['moodle_url'] ?? ''));
      if ($url === '') {
        $url = trim((string)($content['moodle']['url'] ?? $content['moodle_url'] ?? ''));
      }
      if ($url === '' && $cmid <= 0) {
        $url = trim((string)($content['url'] ?? $content['file_path'] ?? $content['source_url'] ?? ''));
      }
      if ($url === '' && $cmid > 0) {
        $url = trim((string)Moodle::cm_view_url($cmid));
      }
      if ($url !== '') {
        $url = function_exists('mb_substr') ? mb_substr($url, 0, 2048, 'UTF-8') : substr($url, 0, 2048);
      }

      $currentCmid = (int)($row['moodle_cmid'] ?? 0);
      $currentMod = trim((string)($row['moodle_modname'] ?? ''));
      $currentUrl = trim((string)($row['moodle_url'] ?? ''));
      $changed = ($currentCmid !== $cmid) || ($currentMod !== $modname) || ($currentUrl !== $url);
      if (!$changed) {
        continue;
      }

      Db::exec(
        "UPDATE app_node
            SET moodle_cmid = :cmid,
                moodle_modname = :modname,
                moodle_url = :url
          WHERE id = :id",
        [
          'cmid' => $cmid > 0 ? $cmid : null,
          'modname' => $modname !== '' ? $modname : null,
          'url' => $url !== '' ? $url : null,
          'id' => $id,
        ]
      );
    }
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
    if ($query === '') {
      return 0;
    }

    parse_str($query, $params);
    if (!empty($params['id']) && (int)$params['id'] > 0) {
      return (int)$params['id'];
    }
    if (!empty($params['cmid']) && (int)$params['cmid'] > 0) {
      return (int)$params['cmid'];
    }

    return 0;
  }
}

