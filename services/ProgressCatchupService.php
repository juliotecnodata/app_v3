<?php
declare(strict_types=1);

namespace App;

final class ProgressCatchupService {
  public static function backfill_course_for_completed_students(int $courseId): array {
    if ($courseId <= 0) {
      return [
        'eligible_nodes' => 0,
        'processed_nodes' => 0,
        'auto_completed_rows' => 0,
      ];
    }

    $progressNodeSql = CoursePolicyService::node_progress_sql();
    $nodes = Db::all(
      "SELECT id, course_id, kind, subtype, is_published, rules_json, count_in_progress_percent
         FROM app_node
        WHERE course_id = :cid
          AND is_published = 1
          AND kind IN ('content', 'action')
          AND {$progressNodeSql}
     ORDER BY sort ASC, id ASC",
      ['cid' => $courseId]
    );

    $eligibleNodes = count($nodes);
    $processedNodes = 0;
    $autoCompletedRows = 0;

    foreach ($nodes as $node) {
      $nodeId = (int)($node['id'] ?? 0);
      if ($nodeId <= 0) {
        continue;
      }

      $processedNodes++;
      $autoCompletedRows += self::backfill_new_counted_node_for_completed_students(
        $courseId,
        $nodeId,
        $node,
        $nodeId
      );
    }

    return [
      'eligible_nodes' => $eligibleNodes,
      'processed_nodes' => $processedNodes,
      'auto_completed_rows' => $autoCompletedRows,
    ];
  }

  public static function backfill_new_counted_node_for_completed_students(
    int $courseId,
    int $nodeId,
    ?array $node = null,
    ?int $excludeNodeId = null
  ): int {
    if ($courseId <= 0 || $nodeId <= 0) {
      return 0;
    }

    if ($node === null) {
      $node = Db::one(
        "SELECT id, course_id, kind, subtype, is_published, rules_json, count_in_progress_percent
           FROM app_node
          WHERE id = :id
            AND course_id = :cid
          LIMIT 1",
        [
          'id' => $nodeId,
          'cid' => $courseId,
        ]
      );
    }

    if (!$node) {
      return 0;
    }

    if ((int)($node['is_published'] ?? 0) !== 1) {
      return 0;
    }

    $kind = (string)($node['kind'] ?? '');
    if ($kind !== 'content' && $kind !== 'action') {
      return 0;
    }

    if (!CoursePolicyService::node_counts_towards_progress($node)) {
      return 0;
    }

    $completedUserIds = self::fully_completed_user_ids($courseId, $excludeNodeId);
    if (!$completedUserIds) {
      return 0;
    }

    $existingRows = Db::all(
      "SELECT id, moodle_userid, status
         FROM app_progress
        WHERE course_id = :cid
          AND node_id = :nid",
      [
        'cid' => $courseId,
        'nid' => $nodeId,
      ]
    );

    $existingByUser = [];
    foreach ($existingRows as $row) {
      $uid = (int)($row['moodle_userid'] ?? 0);
      if ($uid <= 0 || isset($existingByUser[$uid])) {
        continue;
      }
      $existingByUser[$uid] = [
        'id' => (int)($row['id'] ?? 0),
        'status' => (string)($row['status'] ?? ''),
      ];
    }

    $changed = 0;
    Db::tx(function() use ($completedUserIds, $existingByUser, $courseId, $nodeId, &$changed): void {
      foreach ($completedUserIds as $userId) {
        $existing = $existingByUser[$userId] ?? null;
        if ($existing && (string)($existing['status'] ?? '') === 'completed') {
          continue;
        }

        if ($existing) {
          Db::exec(
            "UPDATE app_progress
                SET status = 'completed',
                    percent = GREATEST(percent, 100),
                    last_seen_at = NOW(),
                    completed_at = NOW()
              WHERE course_id = :cid
                AND node_id = :nid
                AND moodle_userid = :uid",
            [
              'cid' => $courseId,
              'nid' => $nodeId,
              'uid' => $userId,
            ]
          );
        } else {
          Db::exec(
            "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
             VALUES (:uid, :cid, :nid, 'completed', 100, 0, 0, NOW(), NOW())",
            [
              'uid' => $userId,
              'cid' => $courseId,
              'nid' => $nodeId,
            ]
          );
        }

        $changed++;
      }
    });

    return $changed;
  }

  public static function fully_completed_user_ids(int $courseId, ?int $excludeNodeId = null): array {
    if ($courseId <= 0) {
      return [];
    }

    $params = ['cid' => $courseId];
    $excludeSql = '';
    if ($excludeNodeId !== null && $excludeNodeId > 0) {
      $excludeSql = ' AND n.id <> :exclude_nid';
      $params['exclude_nid'] = $excludeNodeId;
    }

    $progressNodeSql = CoursePolicyService::node_progress_sql('n');
    $totalRow = Db::one(
      "SELECT COUNT(*) AS total
         FROM app_node n
        WHERE n.course_id = :cid
          AND n.is_published = 1
          AND n.kind IN ('content', 'action')
          AND {$progressNodeSql}
          {$excludeSql}",
      $params
    );

    $total = (int)($totalRow['total'] ?? 0);
    if ($total <= 0) {
      return [];
    }

    $rows = Db::all(
      "SELECT p.moodle_userid
         FROM app_progress p
         JOIN app_node n
           ON n.id = p.node_id
        WHERE p.course_id = :cid
          AND n.course_id = p.course_id
          AND n.is_published = 1
          AND n.kind IN ('content', 'action')
          AND {$progressNodeSql}
          {$excludeSql}
          AND p.status = 'completed'
     GROUP BY p.moodle_userid
       HAVING COUNT(DISTINCT p.node_id) >= :required_total",
      $params + ['required_total' => $total]
    );

    $userIds = [];
    foreach ($rows as $row) {
      $userId = (int)($row['moodle_userid'] ?? 0);
      if ($userId > 0) {
        $userIds[] = $userId;
      }
    }

    return $userIds;
  }
}
