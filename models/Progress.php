<?php
declare(strict_types=1);

namespace App\Models;

use App\Db;

final class Progress {
  public static function forUserNode(int $userId, int $nodeId): ?array {
    return Db::one("SELECT * FROM app_progress WHERE moodle_userid = :uid AND node_id = :nid LIMIT 1", [
      'uid' => $userId,
      'nid' => $nodeId,
    ]);
  }
}
