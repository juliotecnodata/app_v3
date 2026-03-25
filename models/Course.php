<?php
declare(strict_types=1);

namespace App\Models;

use App\Db;

final class Course {
  public static function all(): array {
    return Db::all("SELECT * FROM app_course ORDER BY id DESC");
  }

  public static function find(int $id): ?array {
    return Db::one("SELECT * FROM app_course WHERE id = :id LIMIT 1", ['id'=>$id]);
  }

  public static function findByMoodle(int $mcid): ?array {
    return Db::one("SELECT * FROM app_course WHERE moodle_courseid = :mcid LIMIT 1", ['mcid'=>$mcid]);
  }
}
