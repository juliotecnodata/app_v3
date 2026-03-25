<?php
declare(strict_types=1);

namespace App;

final class ProgressSyncService {
  public static function sync_user_course_from_moodle(int $appCourseId, int $moodleUserId, bool $force = false): array {
    global $SESSION;

    $stats = [
      'inserted' => 0,
      'updated' => 0,
      'completed' => 0,
      'in_progress' => 0,
      'mapped_nodes' => 0,
      'source_completed' => 0,
      'source_viewed' => 0,
      'throttled' => 0,
    ];

    if ($appCourseId <= 0 || $moodleUserId <= 0) {
      return $stats;
    }

    $course = Db::one(
      "SELECT id, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $appCourseId]
    );
    if (!$course) {
      return $stats;
    }

    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    if ($moodleCourseId <= 0) {
      return $stats;
    }

    $now = time();
    $interval = (int)App::cfg('moodle_progress_sync_interval_seconds', 300);
    $syncKey = $appCourseId . '|' . $moodleUserId;

    if (!isset($SESSION->app_v3_progress_sync) || !is_array($SESSION->app_v3_progress_sync)) {
      $SESSION->app_v3_progress_sync = [];
    }

    if (!$force && $interval > 0) {
      $lastSync = (int)($SESSION->app_v3_progress_sync[$syncKey] ?? 0);
      if ($lastSync > 0 && ($now - $lastSync) < $interval) {
        $stats['throttled'] = 1;
        return $stats;
      }
    }

    $cmidToNodes = self::map_nodes_by_moodle_cmid($appCourseId);
    if (empty($cmidToNodes)) {
      $SESSION->app_v3_progress_sync[$syncKey] = $now;
      return $stats;
    }

    $allNodeIds = [];
    foreach ($cmidToNodes as $nodeIds) {
      foreach ($nodeIds as $nodeId) {
        $allNodeIds[] = (int)$nodeId;
      }
    }
    $allNodeIds = array_values(array_unique($allNodeIds));
    $stats['mapped_nodes'] = count($allNodeIds);

    $cmids = array_values(array_unique(array_map('intval', array_keys($cmidToNodes))));
    $completionByCmid = self::fetch_moodle_completion_map($moodleUserId, $cmids);
    $viewedByCmid = self::fetch_moodle_viewed_map($moodleCourseId, $moodleUserId, $cmids);
    $existingByNode = self::fetch_existing_progress_by_node($appCourseId, $moodleUserId, $allNodeIds);

    foreach ($completionByCmid as $info) {
      if ((int)($info['completionstate'] ?? 0) > 0) {
        $stats['source_completed']++;
      }
    }
    $stats['source_viewed'] = count($viewedByCmid);

    Db::tx(function () use (
      $appCourseId,
      $moodleUserId,
      $cmidToNodes,
      $completionByCmid,
      $viewedByCmid,
      &$existingByNode,
      &$stats,
      $now
    ): void {
      foreach ($cmidToNodes as $cmid => $nodeIds) {
        $cmid = (int)$cmid;
        $completion = $completionByCmid[$cmid] ?? null;
        $completed = $completion && (int)($completion['completionstate'] ?? 0) > 0;

        $completionTs = $completed ? (int)($completion['timemodified'] ?? 0) : 0;
        $viewTs = (int)($viewedByCmid[$cmid] ?? 0);
        $lastTs = max($completionTs, $viewTs, $now);
        $lastSeenAt = self::fmt_ts($lastTs);

        foreach ($nodeIds as $nodeId) {
          $nodeId = (int)$nodeId;
          $existing = $existingByNode[$nodeId] ?? null;

          if ($completed) {
            if ($existing === null) {
              Db::exec(
                "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
                 VALUES (:uid, :cid, :nid, 'completed', 100, 0, 0, :last_seen_at, :completed_at)",
                [
                  'uid' => $moodleUserId,
                  'cid' => $appCourseId,
                  'nid' => $nodeId,
                  'last_seen_at' => $lastSeenAt,
                  'completed_at' => $lastSeenAt,
                ]
              );
              $stats['inserted']++;
              $stats['completed']++;
              $existingByNode[$nodeId] = [
                'id' => (int)Db::lastId(),
                'status' => 'completed',
                'percent' => 100,
              ];
              continue;
            }

            $existingStatus = (string)($existing['status'] ?? 'not_started');
            $existingPercent = (int)($existing['percent'] ?? 0);
            if ($existingStatus === 'completed' && $existingPercent >= 100) {
              continue;
            }

            Db::exec(
              "UPDATE app_progress
                  SET status = 'completed',
                      percent = GREATEST(percent, 100),
                      last_seen_at = :last_seen_at,
                      completed_at = COALESCE(completed_at, :completed_at)
                WHERE id = :id",
              [
                'last_seen_at' => $lastSeenAt,
                'completed_at' => $lastSeenAt,
                'id' => (int)$existing['id'],
              ]
            );
            $stats['updated']++;
            $stats['completed']++;
            $existingByNode[$nodeId]['status'] = 'completed';
            $existingByNode[$nodeId]['percent'] = max($existingPercent, 100);
            continue;
          }

          // Sem completude no Moodle: tenta preservar "em andamento" por visualizacao.
          if ($viewTs <= 0) {
            continue;
          }

          if ($existing === null) {
            Db::exec(
              "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
               VALUES (:uid, :cid, :nid, 'in_progress', 5, 0, 0, :last_seen_at, NULL)",
              [
                'uid' => $moodleUserId,
                'cid' => $appCourseId,
                'nid' => $nodeId,
                'last_seen_at' => $lastSeenAt,
              ]
            );
            $stats['inserted']++;
            $stats['in_progress']++;
            $existingByNode[$nodeId] = [
              'id' => (int)Db::lastId(),
              'status' => 'in_progress',
              'percent' => 5,
            ];
            continue;
          }

          $existingStatus = (string)($existing['status'] ?? 'not_started');
          if ($existingStatus === 'completed') {
            continue;
          }

          Db::exec(
            "UPDATE app_progress
                SET status = CASE WHEN status = 'not_started' THEN 'in_progress' ELSE status END,
                    percent = GREATEST(percent, 5),
                    last_seen_at = :last_seen_at
              WHERE id = :id",
            [
              'last_seen_at' => $lastSeenAt,
              'id' => (int)$existing['id'],
            ]
          );
          $stats['updated']++;
          $stats['in_progress']++;
          $existingByNode[$nodeId]['status'] = $existingStatus === 'not_started' ? 'in_progress' : $existingStatus;
          $existingByNode[$nodeId]['percent'] = max((int)($existing['percent'] ?? 0), 5);
        }
      }
    });

    $SESSION->app_v3_progress_sync[$syncKey] = $now;
    return $stats;
  }

  public static function push_course_completion_to_moodle(int $appCourseId, int $limitUsers = 0, ?int $overrideByMoodleUserId = null): array {
    $stats = [
      'rows' => 0,
      'unique_pairs' => 0,
      'unique_users' => 0,
      'attempted' => 0,
      'synced' => 0,
      'already_synced' => 0,
      'skipped' => 0,
      'errors' => 0,
      'limit_users' => $limitUsers > 0 ? $limitUsers : 0,
      'skipped_by_limit' => 0,
      'reasons' => [],
    ];

    if ($appCourseId <= 0) {
      throw new \RuntimeException('Curso invalido.');
    }

    $course = Db::one(
      "SELECT id, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $appCourseId]
    );
    if (!$course) {
      throw new \RuntimeException('Curso nao encontrado.');
    }

    if ((int)($course['moodle_courseid'] ?? 0) <= 0) {
      throw new \RuntimeException('Curso sem mapeamento Moodle para sincronizacao.');
    }

    $rows = Db::all(
      "SELECT p.id, p.moodle_userid, p.node_id
         FROM app_progress p
         JOIN app_node n ON n.id = p.node_id
        WHERE p.course_id = :cid
          AND p.status = 'completed'
          AND n.course_id = :cid
          AND n.is_published = 1
          AND n.kind IN ('content', 'action')
     ORDER BY p.moodle_userid ASC,
              p.node_id ASC,
              COALESCE(p.last_seen_at, p.completed_at, '1970-01-01 00:00:00') DESC,
              p.id DESC",
      ['cid' => $appCourseId]
    );
    $stats['rows'] = count($rows);

    if (!$rows) {
      return $stats;
    }

    $seenPair = [];
    $allowedUsers = [];
    foreach ($rows as $row) {
      $userId = (int)($row['moodle_userid'] ?? 0);
      $nodeId = (int)($row['node_id'] ?? 0);
      if ($userId <= 0 || $nodeId <= 0) {
        continue;
      }

      $pairKey = $userId . '|' . $nodeId;
      if (isset($seenPair[$pairKey])) {
        continue;
      }
      $seenPair[$pairKey] = true;
      $stats['unique_pairs']++;

      if ($limitUsers > 0 && !isset($allowedUsers[$userId]) && count($allowedUsers) >= $limitUsers) {
        $stats['skipped_by_limit']++;
        continue;
      }
      $allowedUsers[$userId] = true;

      $stats['attempted']++;
      try {
        $sync = Moodle::sync_node_completion_from_app(
          $appCourseId,
          $nodeId,
          $userId,
          true,
          $overrideByMoodleUserId
        );

        if (!empty($sync['ok']) && !empty($sync['synced'])) {
          $stats['synced']++;
          continue;
        }

        $reason = trim((string)($sync['reason'] ?? 'unknown'));
        if ($reason === 'already_synced') {
          $stats['already_synced']++;
          continue;
        }

        $stats['skipped']++;
        if (!isset($stats['reasons'][$reason])) {
          $stats['reasons'][$reason] = 0;
        }
        $stats['reasons'][$reason]++;
      } catch (\Throwable $e) {
        $stats['errors']++;
        $reason = 'exception';
        if (!isset($stats['reasons'][$reason])) {
          $stats['reasons'][$reason] = 0;
        }
        $stats['reasons'][$reason]++;
        error_log('[app_v3] Falha no push APP -> Moodle (course ' . $appCourseId . ', user ' . $userId . ', node ' . $nodeId . '): ' . $e->getMessage());
      }
    }

    $stats['unique_users'] = count($allowedUsers);
    ksort($stats['reasons']);
    return $stats;
  }

  private static function map_nodes_by_moodle_cmid(int $appCourseId): array {
    $rows = Db::all(
      "SELECT id, moodle_cmid, content_json
         FROM app_node
        WHERE course_id = :cid
          AND is_published = 1
          AND kind IN ('content', 'action')",
      ['cid' => $appCourseId]
    );

    $map = [];
    foreach ($rows as $row) {
      $nodeId = (int)($row['id'] ?? 0);
      if ($nodeId <= 0) {
        continue;
      }

      $cmid = (int)($row['moodle_cmid'] ?? 0);
      if ($cmid <= 0) {
        $contentRaw = (string)($row['content_json'] ?? '');
        if ($contentRaw !== '') {
          $content = json_decode($contentRaw, true);
          if (is_array($content)) {
            $cmid = (int)($content['moodle']['cmid'] ?? 0);
            if ($cmid <= 0) {
              $cmid = (int)($content['cmid'] ?? 0);
            }
            if ($cmid <= 0) {
              $cmid = (int)($content['moodle_cmid'] ?? 0);
            }
          }
        }
      }
      if ($cmid <= 0) {
        continue;
      }

      if (!isset($map[$cmid])) {
        $map[$cmid] = [];
      }
      $map[$cmid][] = $nodeId;
    }

    return $map;
  }

  private static function fetch_existing_progress_by_node(int $appCourseId, int $moodleUserId, array $nodeIds): array {
    if (empty($nodeIds)) {
      return [];
    }

    [$inSql, $inParams] = self::build_in_params('nid', $nodeIds);
    $params = array_merge($inParams, [
      'uid' => $moodleUserId,
      'cid' => $appCourseId,
    ]);

    $rows = Db::all(
      "SELECT id, node_id, status, percent
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
          AND node_id IN ($inSql)
        ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC",
      $params
    );

    $map = [];
    foreach ($rows as $row) {
      $nodeId = (int)($row['node_id'] ?? 0);
      if ($nodeId <= 0 || isset($map[$nodeId])) {
        continue;
      }
      $map[$nodeId] = $row;
    }

    return $map;
  }

  private static function fetch_moodle_completion_map(int $moodleUserId, array $cmids): array {
    global $DB;
    if (empty($cmids)) {
      return [];
    }

    [$inSql, $inParams] = self::build_in_params('cm', $cmids);
    $params = array_merge($inParams, ['uid' => $moodleUserId]);
    $sql = "SELECT
              coursemoduleid AS cmid,
              completionstate,
              timemodified
            FROM {course_modules_completion}
           WHERE userid = :uid
             AND coursemoduleid IN ($inSql)";

    $rows = $DB->get_records_sql($sql, $params);
    $map = [];
    foreach ($rows as $row) {
      $cmid = (int)($row->cmid ?? 0);
      if ($cmid <= 0) {
        continue;
      }
      $map[$cmid] = [
        'completionstate' => (int)($row->completionstate ?? 0),
        'timemodified' => (int)($row->timemodified ?? 0),
      ];
    }
    return $map;
  }

  private static function fetch_moodle_viewed_map(int $moodleCourseId, int $moodleUserId, array $cmids): array {
    global $DB;
    if (empty($cmids) || $moodleCourseId <= 0 || $moodleUserId <= 0) {
      return [];
    }

    [$inSql, $inParams] = self::build_in_params('lg', $cmids);
    $params = array_merge($inParams, [
      'uid' => $moodleUserId,
      'cid' => $moodleCourseId,
      'ctx' => defined('CONTEXT_MODULE') ? (int)CONTEXT_MODULE : 70,
    ]);

    $sql = "SELECT
              contextinstanceid AS cmid,
              MAX(timecreated) AS lastview
            FROM {logstore_standard_log}
           WHERE userid = :uid
             AND courseid = :cid
             AND contextlevel = :ctx
             AND target = 'course_module'
             AND action = 'viewed'
             AND contextinstanceid IN ($inSql)
        GROUP BY contextinstanceid";

    try {
      $rows = $DB->get_records_sql($sql, $params);
    } catch (\Throwable $e) {
      return [];
    }

    $map = [];
    foreach ($rows as $row) {
      $cmid = (int)($row->cmid ?? 0);
      if ($cmid <= 0) {
        continue;
      }
      $map[$cmid] = (int)($row->lastview ?? 0);
    }
    return $map;
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

  private static function fmt_ts(int $timestamp): string {
    if ($timestamp <= 0) {
      $timestamp = time();
    }
    return date('Y-m-d H:i:s', $timestamp);
  }
}
