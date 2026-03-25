<?php
declare(strict_types=1);

namespace App;

final class SettingsService {
  private static bool $ensured = false;

  public static function get(string $key, $default = null) {
    self::ensure_table();

    $row = Db::one(
      "SELECT setting_value
         FROM app_settings
        WHERE setting_key = :key
        LIMIT 1",
      ['key' => $key]
    );

    if (!$row) {
      return $default;
    }

    return (string)($row['setting_value'] ?? $default);
  }

  public static function set(string $key, string $value, ?int $updatedByUserId = null): void {
    self::ensure_table();

    Db::exec(
      "INSERT INTO app_settings (setting_key, setting_value, updated_by_moodle_userid, updated_at)
       VALUES (:key, :value, :updated_by, NOW())
       ON DUPLICATE KEY UPDATE
         setting_value = VALUES(setting_value),
         updated_by_moodle_userid = VALUES(updated_by_moodle_userid),
         updated_at = NOW()",
      [
        'key' => $key,
        'value' => $value,
        'updated_by' => $updatedByUserId,
      ]
    );
  }

  public static function biometric_mode(): string {
    $value = strtolower(trim((string)self::get('biometric_provider_mode', '')));
    if ($value === 'gryfo') {
      return 'gryfo';
    }
    return 'simple';
  }

  public static function biometric_provider(): string {
    return self::biometric_mode() === 'gryfo' ? 'gryfo' : '';
  }

  private static function ensure_table(): void {
    if (self::$ensured) {
      return;
    }

    self::$ensured = true;
    try {
      Schema::ensure();
    } catch (\Throwable $exception) {
      // Mantem fallback sem quebrar a request.
    }
  }
}
