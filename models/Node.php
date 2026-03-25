<?php
declare(strict_types=1);

namespace App\Models;

use App\Db;

final class Node {
  public static function forCourse(int $courseId): array {
    return Db::all("SELECT * FROM app_node WHERE course_id = :cid ORDER BY parent_id ASC, sort ASC, id ASC", ['cid'=>$courseId]);
  }
}
