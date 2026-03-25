<?php
declare(strict_types=1);

namespace App;

final class Tree {

  public static function nodes_for_course(int $courseId): array {
    return Db::all("SELECT * FROM app_node WHERE course_id = :cid ORDER BY parent_id ASC, sort ASC, id ASC", [
      'cid' => $courseId
    ]);
  }

  public static function build(array $nodes): array {
    $byId = [];
    foreach ($nodes as $n) {
      $n['children'] = [];
      $byId[(int)$n['id']] = $n;
    }

    $root = [];
    foreach ($byId as $id => &$node) {
      $pid = $node['parent_id'] ? (int)$node['parent_id'] : 0;
      if ($pid && isset($byId[$pid])) {
        $byId[$pid]['children'][] = &$node;
      } else {
        $root[] = &$node;
      }
    }
    unset($node);

    return ['root' => $root, 'byId' => $byId];
  }

  public static function linearize_content(array $treeRoot): array {
    $out = [];
    $walk = function(array $nodes) use (&$walk, &$out) {
      foreach ($nodes as $n) {
        if ($n['kind'] === 'content' || $n['kind'] === 'action') {
          $out[] = $n;
        }
        if (!empty($n['children'])) $walk($n['children']);
      }
    };
    $walk($treeRoot);
    return $out;
  }

  public static function find_first_content(array $linear): ?array {
    return $linear[0] ?? null;
  }
}
