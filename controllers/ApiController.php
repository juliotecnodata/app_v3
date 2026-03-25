<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\BiometricProviderService;
use App\CourseBlueprintService;
use App\CoursePolicyService;
use App\CourseRuntimeService;
use App\CourseSyncService;
use App\Csrf;
use App\Db;
use App\Moodle;
use App\ProgressCatchupService;
use App\ProgressSyncService;
use App\Response;
use App\Tree;
use App\UserAlertService;
use App\UserCourseBlockService;

final class ApiController {
  private static ?bool $biometricProviderColumnsAvailable = null;

  public static function handle(string $route): void {
    // normalize
    $route = '/' . trim($route, '/');

    // All API requires login (and CSRF for POST)
    Auth::require_login();

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST') {
      Csrf::check((string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    }
    if (!\str_starts_with($route, '/api/admin/')) {
      self::guard_course_access_block($route, $method);
    }


    // progress update
    if ($route === '/api/progress/update' && $method === 'POST') {
      self::progress_update();
      return;
    }

    if ($route === '/api/course/biometric/verify' && $method === 'POST') {
      self::course_biometric_verify();
      return;
    }

    if ($route === '/api/quiz/session' && $method === 'POST') {
      self::quiz_session();
      return;
    }

    if ($route === '/api/quiz/page' && $method === 'POST') {
      self::quiz_page();
      return;
    }

    if ($route === '/api/quiz/finish' && $method === 'POST') {
      self::quiz_finish();
      return;
    }

    if ($route === '/api/quiz/review' && $method === 'POST') {
      self::quiz_review();
      return;
    }

    if ($route === '/api/lesson/session' && $method === 'POST') {
      self::lesson_session();
      return;
    }

    if ($route === '/api/lesson/page' && $method === 'POST') {
      self::lesson_page();
      return;
    }

    if ($route === '/api/lesson/page/view' && $method === 'POST') {
      self::lesson_page_view();
      return;
    }

    if ($route === '/api/lesson/finish' && $method === 'POST') {
      self::lesson_finish();
      return;
    }

    if ($route === '/api/lesson/status' && $method === 'POST') {
      self::lesson_status();
      return;
    }

    if ($route === '/api/chat/context' && $method === 'GET') {
      self::chat_context();
      return;
    }

    if ($route === '/api/user-alert/current' && $method === 'GET') {
      self::user_alert_current();
      return;
    }

    if ($route === '/api/user-alert/ack' && $method === 'POST') {
      self::user_alert_ack();
      return;
    }

    // Admin endpoints
    if (\str_starts_with($route, '/api/admin/')) {
      Auth::require_app_admin();

      if ($route === '/api/admin/node/read' && $method === 'GET') { self::node_read(); return; }
      if ($route === '/api/admin/enrollment/tracker' && $method === 'GET') { self::enrollment_tracker(); return; }
      if ($route === '/api/admin/enrollment/progress/sync' && $method === 'POST') { self::enrollment_progress_sync(); return; }
      if ($route === '/api/admin/enrollment/progress/sync/bulk' && $method === 'POST') { self::enrollment_progress_sync_bulk(); return; }
      if ($route === '/api/admin/course-access/block' && $method === 'POST') { self::course_access_block(); return; }
      if ($route === '/api/admin/course-access/unblock' && $method === 'POST') { self::course_access_unblock(); return; }
      if ($route === '/api/admin/enrollment/progress/set' && $method === 'POST') { self::enrollment_progress_set(); return; }
      if ($route === '/api/admin/enrollment/progress/bulk' && $method === 'POST') { self::enrollment_progress_bulk(); return; }
      if ($route === '/api/admin/biometric/read' && $method === 'GET') { self::admin_biometric_read(); return; }
      if ($route === '/api/admin/biometric/delete' && $method === 'POST') { self::admin_biometric_delete(); return; }

      if ($route === '/api/admin/course/update' && $method === 'POST') { self::course_update(); return; }
      if ($route === '/api/admin/course/mapping/save' && $method === 'POST') { self::course_mapping_save(); return; }
      if ($route === '/api/admin/course/runtime/update' && $method === 'POST') { self::course_runtime_update(); return; }
      if ($route === '/api/admin/course/numbering/toggle' && $method === 'POST') { self::course_numbering_toggle(); return; }
      if ($route === '/api/admin/course/delete' && $method === 'POST') { self::course_delete(); return; }
      if ($route === '/api/admin/course/create' && $method === 'POST') { self::course_create(); return; }
      if ($route === '/api/admin/course/import' && $method === 'POST') { self::course_import(); return; }
      if ($route === '/api/admin/course/sync' && $method === 'POST') { self::course_sync(); return; }
      if ($route === '/api/admin/course/sync-cover' && $method === 'POST') { self::course_sync_cover(); return; }
      if ($route === '/api/admin/course/progress/push' && $method === 'POST') { self::course_progress_push(); return; }
      if ($route === '/api/admin/course/progress/catchup' && $method === 'POST') { self::course_progress_catchup(); return; }
      if ($route === '/api/admin/course/sync/preview' && $method === 'POST') { self::course_sync_preview(); return; }
      if ($route === '/api/admin/course/template/apply' && $method === 'POST') { self::course_template_apply(); return; }
      if ($route === '/api/admin/course/template/new' && $method === 'POST') { self::course_template_new(); return; }
      if ($route === '/api/admin/course/publish' && $method === 'POST') { self::course_publish(); return; }
      if ($route === '/api/admin/exam/save' && $method === 'POST') { self::exam_save(); return; }

      if ($route === '/api/admin/node/create' && $method === 'POST') { self::node_create(); return; }
      if ($route === '/api/admin/node/update' && $method === 'POST') { self::node_update(); return; }
      if ($route === '/api/admin/node/complete-for-enrolled' && $method === 'POST') { self::node_complete_for_enrolled(); return; }
      if ($route === '/api/admin/node/cleanup-never-started' && $method === 'POST') { self::node_cleanup_never_started(); return; }
      if ($route === '/api/admin/node/delete' && $method === 'POST') { self::node_delete(); return; }
      if ($route === '/api/admin/node/reorder' && $method === 'POST') { self::node_reorder(); return; }
      if ($route === '/api/admin/upload' && $method === 'POST') { self::upload(); return; }
    }

    Response::json(['ok' => false, 'error' => 'Endpoint nao encontrado.'], 404);
  }

  private static function guard_course_access_block(string $route, string $method): void {
    $user = Auth::user();
    $userId = (int)($user->id ?? 0);
    if ($userId <= 0 || Auth::is_app_admin($userId)) {
      return;
    }

    $courseId = self::resolve_blockable_course_id($route, $method);
    if ($courseId <= 0) {
      return;
    }

    $block = UserCourseBlockService::active_for_user($courseId, $userId);
    if (!$block) {
      return;
    }

    $title = UserCourseBlockService::title_for_row($block);
    $message = UserCourseBlockService::message_for_row($block);
    Response::json([
      'ok' => false,
      'code' => 'course_access_blocked',
      'error' => trim($title . ': ' . $message),
      'redirect' => App::base_url('/dashboard'),
      'block' => [
        'course_id' => (int)($block['course_id'] ?? $courseId),
        'moodle_userid' => (int)($block['moodle_userid'] ?? $userId),
        'title' => $title,
        'message' => $message,
        'reason_code' => (string)($block['reason_code'] ?? ''),
        'expires_at' => (string)($block['expires_at'] ?? ''),
      ],
    ], 423);
  }

  private static function resolve_blockable_course_id(string $route, string $method): int {
    if ($method !== 'POST') {
      return 0;
    }

    switch ($route) {
      case '/api/progress/update':
      case '/api/course/biometric/verify':
      case '/api/quiz/session':
      case '/api/quiz/finish':
      case '/api/lesson/session':
      case '/api/lesson/finish':
        return (int)($_POST['course_id'] ?? 0);
      default:
        return 0;
    }
  }
  private static function chat_context(): void {
    $user = Auth::user();
    $userId = (int)($user->id ?? 0);
    if ($userId <= 0 || (\function_exists('isguestuser') && \isguestuser($user))) {
      Response::json(['ok' => false, 'error' => 'Usuario nao autenticado.'], 401);
    }

    $page = strtolower(trim((string)($_GET['page'] ?? '')));
    $courseId = (int)($_GET['course_id'] ?? 0);
    $courseTitle = trim((string)($_GET['course'] ?? ''));

    if ($courseTitle === '' && $courseId > 0) {
      $course = Db::one(
        "SELECT title
           FROM app_course
          WHERE id = :id
          LIMIT 1",
        ['id' => $courseId]
      );
      if ($course) {
        $courseTitle = trim((string)($course['title'] ?? ''));
      }
    }

    if ($courseTitle === '') {
      if ($page === 'dashboard') {
        $courseTitle = 'Dashboard';
      } elseif ($page === 'estudo') {
        $courseTitle = 'Estudo';
      } elseif ($page === 'login') {
        $courseTitle = 'Login';
      }
    }

    $firstname = trim((string)($user->firstname ?? ''));
    $lastname = trim((string)($user->lastname ?? ''));
    $username = trim((string)($user->username ?? ''));
    $email = trim((string)($user->email ?? ''));

    $fullname = trim($firstname . ' ' . $lastname);
    if (\function_exists('fullname')) {
      try {
        $fullFromMoodle = trim((string)\fullname($user));
        if ($fullFromMoodle !== '') {
          $fullname = $fullFromMoodle;
        }
      } catch (\Throwable $_ignore) {
        // fallback para o nome montado acima.
      }
    }
    if ($fullname === '') {
      $fullname = $username !== '' ? $username : 'Usuario';
    }

    $coursesList = [];
    try {
      global $DB;
      $rows = $DB->get_records_sql(
        "SELECT DISTINCT c.fullname
           FROM {course} c
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
          WHERE ue.userid = :userid
       ORDER BY c.fullname ASC",
        ['userid' => $userId]
      );
      if ($rows) {
        foreach ($rows as $row) {
          $name = trim((string)($row->fullname ?? ''));
          if ($name !== '') {
            $coursesList[] = $name;
          }
        }
      }
    } catch (\Throwable $e) {
      error_log('[app_v3][chat_context] Falha ao listar cursos Moodle: ' . $e->getMessage());
    }
    $coursesText = implode(', ', $coursesList);
    if (strlen($coursesText) > 1200) {
      $coursesText = substr($coursesText, 0, 1197) . '...';
    }

    Response::json([
      'ok' => true,
      'context' => [
        'page' => $page,
        'course' => $courseTitle,
        'course_id' => $courseId,
        'user_id' => $userId,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'fullname' => $fullname,
        'username' => $username,
        'email' => $email,
        // Campos legados no padrao do plugin do Moodle.
        'matricula' => (string)$userId,
        'usuario' => $username,
        'nome' => $fullname,
        'cursos' => $coursesText,
        'cursos_list' => $coursesList,
      ],
    ]);
  }

  private static function user_alert_current(): void {
    $user = Auth::user();
    $userId = (int)($user->id ?? 0);
    if ($userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Usuario nao autenticado.'], 401);
    }

    $page = trim((string)($_GET['page'] ?? ''));
    $courseId = (int)($_GET['course_id'] ?? 0);
    $alert = UserAlertService::current_for_user($userId, $page, $courseId);

    Response::json([
      'ok' => true,
      'alert' => $alert,
    ]);
  }

  private static function user_alert_ack(): void {
    $user = Auth::user();
    $userId = (int)($user->id ?? 0);
    if ($userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Usuario nao autenticado.'], 401);
    }

    $alertId = (int)($_POST['alert_id'] ?? 0);
    if ($alertId <= 0) {
      Response::json(['ok' => false, 'error' => 'Alerta invalido.'], 400);
    }

    $page = trim((string)($_POST['page'] ?? ''));
    UserAlertService::acknowledge($alertId, $userId, $page);

    Response::json(['ok' => true]);
  }

  private static function progress_update(): void {
    $logFile = __DIR__.'/../storage/progress_debug.log';
    $progressDebugEnabled = (int)App::cfg('progress_debug_log_enabled', 0) === 1;
    $writeProgressDebug = static function (string $line) use ($progressDebugEnabled, $logFile): void {
      if (!$progressDebugEnabled) {
        return;
      }
      @file_put_contents($logFile, $line, FILE_APPEND);
    };

    // Coleta e validacao dos parametros
    $u = Auth::user();
    $nodeId   = isset($_POST['node_id'])   ? (int)$_POST['node_id']   : 0;
    $courseId = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $status   = isset($_POST['status'])    ? (string)$_POST['status'] : '';
    $percent  = isset($_POST['percent'])   ? (int)$_POST['percent']   : 0;
    $seconds  = isset($_POST['seconds'])   ? (int)$_POST['seconds']   : 0;
    $pos      = isset($_POST['position'])  ? (int)$_POST['position']  : 0;

    // Validacao basica
    if ($u === null || $nodeId <= 0 || $courseId <= 0) {
      Response::json(['ok'=>false,'error'=>'Parametros invalidos.'], 400);
    }

    // Protecao de entrada para reduzir carga em horarios de pico:
    // pings muito proximos de "in_progress" retornam OK sem gravar no banco.
    $normalizedStatus = strtolower(trim($status));
    $completionAttempt = ($normalizedStatus === 'completed' || $percent >= 100);
    if ($normalizedStatus !== 'completed') {
      global $SESSION;
      $throttleSeconds = max(0, (int)App::cfg('progress_ingress_throttle_seconds', 45));
      $percentStep = max(0, (int)App::cfg('progress_ingress_percent_step', 5));
      if ($throttleSeconds > 0) {
        if (!isset($SESSION->app_v3_progress_ingress) || !is_array($SESSION->app_v3_progress_ingress)) {
          $SESSION->app_v3_progress_ingress = [];
        }
        $key = (int)$u->id . '|' . $courseId . '|' . $nodeId;
        $nowTs = time();
        $last = $SESSION->app_v3_progress_ingress[$key] ?? null;
        if (is_array($last)) {
          $lastTs = (int)($last['ts'] ?? 0);
          $lastPercent = (int)($last['percent'] ?? 0);
          $withinWindow = $lastTs > 0 && ($nowTs - $lastTs) < $throttleSeconds;
          $tinyDelta = $percent <= ($lastPercent + $percentStep);
          if ($withinWindow && $tinyDelta) {
            Response::json(['ok' => true, 'throttled' => true]);
          }
        }
        $SESSION->app_v3_progress_ingress[$key] = [
          'ts' => $nowTs,
          'percent' => max(0, min(100, (int)$percent)),
        ];
      }
    }

    try {
      $moodleCourseId = 0;
      Db::tx(function() use ($u, $nodeId, $courseId, $status, $percent, $seconds, $pos, $writeProgressDebug, &$moodleCourseId) {
        // Busca min_video_percent do node
        $node = Db::one("SELECT id, course_id, kind, subtype, is_published, moodle_cmid, moodle_modname, content_json, rules_json FROM app_node WHERE id=:id", ['id'=>$nodeId]);
        if (!$node) {
          throw new \RuntimeException('Conteudo nao encontrado.');
        }
        if ((int)($node['course_id'] ?? 0) !== $courseId) {
          throw new \RuntimeException('Conteudo nao pertence ao curso informado.');
        }
        if ((int)($node['is_published'] ?? 0) !== 1) {
          throw new \RuntimeException('Conteudo indisponivel.');
        }
        $kind = (string)($node['kind'] ?? '');
        if ($kind !== 'content' && $kind !== 'action') {
          throw new \RuntimeException('Progresso invalido para este item.');
        }

        $course = Db::one("SELECT id, moodle_courseid, enable_sequence_lock FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
        if (!$course) {
          throw new \RuntimeException('Curso nao encontrado.');
        }
        $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
        $sequenceLockEnabled = CoursePolicyService::sequence_lock_enabled($course);

        if ($sequenceLockEnabled) {
          $tree = Tree::build(Tree::nodes_for_course($courseId));
          $linear = Tree::linearize_content($tree['root']);
          $linearIds = [];
          foreach ($linear as $item) {
            if ((int)($item['is_published'] ?? 0) !== 1) continue;
            $linearIds[] = (int)$item['id'];
          }

          $linearPos = array_search($nodeId, $linearIds, true);
          if ($linearPos === false) {
            throw new \RuntimeException('Conteudo fora da trilha ativa.');
          }
          if ((int)$linearPos > 0) {
            $prevNodeId = (int)$linearIds[(int)$linearPos - 1];
            $prevProgress = Db::one(
              "SELECT status
                 FROM app_progress
                WHERE moodle_userid = :uid
                  AND course_id = :cid
                  AND node_id = :nid
                ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC
                LIMIT 1",
              [
                'uid' => (int)$u->id,
                'cid' => $courseId,
                'nid' => $prevNodeId,
              ]
            );
            if (!$prevProgress || (string)($prevProgress['status'] ?? '') !== 'completed') {
              throw new \RuntimeException('Conclua o conteudo anterior para continuar.');
            }
          }
        }

        $cjArr = [];
        $rjArr = [];
        $minPercent = 100;
        if ($node) {
          $cj = $node['content_json'] ?? '';
          $rj = $node['rules_json'] ?? '';
          $minp = null;
          if ($cj) {
            $decodedContent = json_decode($cj, true);
            if (is_array($decodedContent)) {
              $cjArr = $decodedContent;
              if (isset($cjArr['min_video_percent'])) $minp = (int)$cjArr['min_video_percent'];
            }
          }
          if ($rj) {
            $decodedRules = json_decode($rj, true);
            if (is_array($decodedRules)) {
              $rjArr = $decodedRules;
              if ($minp === null && isset($rjArr['min_video_percent'])) $minp = (int)$rjArr['min_video_percent'];
            }
          }
          if ($minp !== null && $minp > 0 && $minp <= 100) $minPercent = $minp;
        }
        $percentClamped = max(0, min(100, (int)$percent));

        $exists = Db::one("SELECT id, status, percent, last_position, completed_at
                             FROM app_progress
                            WHERE moodle_userid = :uid
                              AND course_id = :cid
                              AND node_id = :nid
                            ORDER BY id DESC
                            LIMIT 1", [
          'uid' => (int)$u->id,
          'cid' => (int)$courseId,
          'nid' => $nodeId,
        ]);
        $wasCompletedBefore = $exists && (string)($exists['status'] ?? '') === 'completed';

        $effectivePercent = $percentClamped;
        if ($exists && isset($exists['percent'])) {
          $effectivePercent = max((int)$exists['percent'], $percentClamped);
        }
        $effectivePosition = max(0, (int)$pos);
        if ($exists && isset($exists['last_position'])) {
          $effectivePosition = max((int)$exists['last_position'], (int)$pos);
        }

        $nodeSubtype = (string)($node['subtype'] ?? '');
        $isVideoNode = $kind === 'content' && $nodeSubtype === 'video';
        $requiresPdfTouch = $kind === 'content'
          && $nodeSubtype === 'pdf'
          && !empty($rjArr['require_pdf']);
        $isCertificateNode = $kind === 'action' && $nodeSubtype === 'certificate';

        $finalStatus = $status;
        if ($normalizedStatus === 'completed') {
          $finalStatus = 'completed';
        } else if (!$isVideoNode && $effectivePercent >= $minPercent) {
          $finalStatus = 'completed';
        }
        if ($wasCompletedBefore) {
          $finalStatus = 'completed';
        }

        if ($finalStatus === 'completed' && !$wasCompletedBefore && $isVideoNode) {
          if ($effectivePercent < $minPercent) {
            throw new \RuntimeException('Atenda ao criterio minimo do video para liberar a conclusao.');
          }
        }

        if ($finalStatus === 'completed' && !$wasCompletedBefore && $requiresPdfTouch) {
          $hasPdfTouch = (
            $effectivePercent > 0
            || $effectivePosition > 0
            || ($exists && (string)($exists['status'] ?? '') === 'in_progress')
          );
          if (!$hasPdfTouch) {
            throw new \RuntimeException('Abra ou baixe o PDF antes de concluir este item.');
          }
        }

        if ($finalStatus === 'completed' && !$wasCompletedBefore && self::node_is_quiz_item($node)) {
          $quizCmid = self::quiz_resolve_cmid((int)($node['moodle_cmid'] ?? 0), $courseId, $nodeId);
          if ($quizCmid <= 0) {
            throw new \RuntimeException('Questionario sem mapeamento valido no Moodle. Configure o CMID para concluir este item.');
          }

          $quizId = (int)(Moodle::quiz_instance_from_cmid($quizCmid) ?? 0);
          if ($quizId <= 0) {
            throw new \RuntimeException('Questionario sem mapeamento valido no Moodle. Verifique o CMID informado.');
          }

          $snapshot = Moodle::quiz_completion_snapshot($quizId, (int)$u->id, $quizCmid);
          if (empty($snapshot['completion_met'])) {
            if (!empty($snapshot['pass_required'])) {
              $passGrade = (float)($snapshot['pass_grade'] ?? 0);
              $currentGrade = isset($snapshot['grade']) && $snapshot['grade'] !== null
                ? (float)$snapshot['grade']
                : 0.0;
              throw new \RuntimeException(
                'Voce precisa atingir a nota minima no questionario para concluir. '
                . 'Nota atual: ' . number_format($currentGrade, 2, ',', '.')
                . ' / Minima: ' . number_format($passGrade, 2, ',', '.')
              );
            }

            throw new \RuntimeException('Finalize a tentativa do questionario para concluir este item.');
          }
        }

        if ($finalStatus === 'completed' && !$wasCompletedBefore && self::node_is_lesson_item($node)) {
          $lessonCmid = (int)($node['moodle_cmid'] ?? 0);
          if ($lessonCmid <= 0) {
            $content = json_decode((string)($node['content_json'] ?? ''), true);
            if (is_array($content)) {
              $lessonCmid = self::extract_cmid_from_content($content);
            }
          }

          if ($lessonCmid <= 0) {
            throw new \RuntimeException('Licao sem mapeamento valido no Moodle. Configure o CMID para concluir este item.');
          }

          $lessonId = (int)(Moodle::lesson_instance_from_cmid($lessonCmid) ?? 0);
          $snapshot = Moodle::lesson_completion_snapshot($lessonId, (int)$u->id, $lessonCmid);
          if (empty($snapshot['completion_met'])) {
            throw new \RuntimeException('Conclua os exercicios da licao no Moodle para liberar este item.');
          }
        }

        if ($finalStatus === 'completed' && !$wasCompletedBefore && !$isCertificateNode) {
          $syncMeta = Moodle::sync_node_completion_from_app($courseId, $nodeId, (int)$u->id, true);
          if (!self::is_successful_moodle_completion_sync($syncMeta)) {
            $reason = trim((string)($syncMeta['reason'] ?? ''));
            $writeProgressDebug(
              date('Y-m-d H:i:s') . ' COMPLETION SYNC BLOCKED(pre-write): '
              . json_encode($syncMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );
            throw new \RuntimeException(self::moodle_completion_sync_error_message($reason));
          }
        }

        $completedAt = null;
        if ($finalStatus === 'completed') {
          $existingCompletedAt = trim((string)($exists['completed_at'] ?? ''));
          $completedAt = $existingCompletedAt !== '' ? $existingCompletedAt : date('Y-m-d H:i:s');
        }

        if ($exists && isset($exists['id'])) {
          $params = [
            'status' => (string)$finalStatus,
            'percent' => $effectivePercent,
            'sec' => (int)$seconds,
            'pos' => (int)$pos,
            'completed_at' => $completedAt,
            'id' => (int)$exists['id'],
          ];
          // Log antes do UPDATE
          $writeProgressDebug(
            date('Y-m-d H:i:s') . ' UPDATE QUERY: '
            . 'UPDATE app_progress SET status = :status, percent = GREATEST(percent, :percent), seconds_spent = seconds_spent + :sec, last_position = :pos, last_seen_at = NOW(), completed_at = :completed_at WHERE id = :id | PARAMS: '
            . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
          );
          Db::exec("UPDATE app_progress
                       SET status = :status,
                           percent = GREATEST(percent, :percent),
                           seconds_spent = seconds_spent + :sec,
                           last_position = :pos,
                           last_seen_at = NOW(),
                           completed_at = :completed_at
                     WHERE id = :id", $params);
        } else {
          $insertParams = [
            'uid' => (int)$u->id,
            'cid' => (int)$courseId,
            'nid' => (int)$nodeId,
            'status' => (string)$finalStatus,
            'percent' => $effectivePercent,
            'sec' => (int)$seconds,
            'pos' => (int)$pos,
            'completed_at' => $completedAt,
          ];
          // Log antes do INSERT
          $writeProgressDebug(
            date('Y-m-d H:i:s') . ' INSERT QUERY: '
            . 'INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at) VALUES (:uid, :cid, :nid, :status, :percent, :sec, :pos, NOW(), :completed_at) | PARAMS: '
            . json_encode($insertParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
          );
          Db::exec("INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
                    VALUES (:uid, :cid, :nid, :status, :percent, :sec, :pos, NOW(), :completed_at)", $insertParams);
        }
      });

      $trackCourseAccessOnProgress = (int)App::cfg('moodle_track_course_access_on_progress', 0) === 1;
      $courseAccessSyncInterval = max(0, (int)App::cfg('moodle_course_access_sync_interval_seconds', 120));
      $shouldSyncCourseAccess = $trackCourseAccessOnProgress && $moodleCourseId > 0;
      if ($shouldSyncCourseAccess && $courseAccessSyncInterval > 0) {
        global $SESSION;
        if (!isset($SESSION->app_v3_course_access_sync) || !is_array($SESSION->app_v3_course_access_sync)) {
          $SESSION->app_v3_course_access_sync = [];
        }
        $syncKey = $moodleCourseId . '|' . (int)$u->id;
        $now = time();
        $lastSync = (int)($SESSION->app_v3_course_access_sync[$syncKey] ?? 0);
        if ($lastSync > 0 && ($now - $lastSync) < $courseAccessSyncInterval) {
          $shouldSyncCourseAccess = false;
        } else {
          $SESSION->app_v3_course_access_sync[$syncKey] = $now;
        }
      }

      if ($shouldSyncCourseAccess) {
        try {
          // During progress pings, sync timestamps but do not flood course_viewed logs.
          Moodle::sync_course_access($moodleCourseId, (int)$u->id, false);
        } catch (\Throwable $e) {
          $writeProgressDebug(date('Y-m-d H:i:s') . ' ACCESS SYNC ERROR: ' . $e->getMessage() . "\n");
        }
      }

      Response::json(['ok' => true]);
    } catch (\RuntimeException $e) {
      if (self::is_db_connection_quota_error($e)) {
        $writeProgressDebug(date('Y-m-d H:i:s') . ' DB QUOTA DEFERRED: ' . $e->getMessage() . "\n");
        if ($completionAttempt) {
          Response::json([
            'ok' => false,
            'error' => 'Nao foi possivel confirmar a conclusao agora (limite do banco atingido). Tente novamente em alguns minutos.',
            'reason' => 'db_quota_completion',
          ], 503);
        }
        Response::json(['ok' => true, 'deferred' => true, 'reason' => 'db_quota']);
      }
      $writeProgressDebug(date('Y-m-d H:i:s') . ' RULE ERROR: ' . $e->getMessage() . "\n");
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 403);
    } catch (\PDOException $e) {
      if (self::is_db_connection_quota_error($e)) {
        $writeProgressDebug(date('Y-m-d H:i:s') . ' DB QUOTA DEFERRED(PDO): ' . $e->getMessage() . "\n");
        if ($completionAttempt) {
          Response::json([
            'ok' => false,
            'error' => 'Nao foi possivel confirmar a conclusao agora (limite do banco atingido). Tente novamente em alguns minutos.',
            'reason' => 'db_quota_completion',
          ], 503);
        }
        Response::json(['ok' => true, 'deferred' => true, 'reason' => 'db_quota']);
      }
      $writeProgressDebug(date('Y-m-d H:i:s') . ' PDO ERROR: ' . $e->getMessage() . "\n");
      Response::json(['ok'=>false,'error'=>'Erro SQL: '.$e->getMessage()], 500);
    } catch (\Throwable $e) {
      if (self::is_db_connection_quota_error($e)) {
        $writeProgressDebug(date('Y-m-d H:i:s') . ' DB QUOTA DEFERRED(THROWABLE): ' . $e->getMessage() . "\n");
        if ($completionAttempt) {
          Response::json([
            'ok' => false,
            'error' => 'Nao foi possivel confirmar a conclusao agora (limite do banco atingido). Tente novamente em alguns minutos.',
            'reason' => 'db_quota_completion',
          ], 503);
        }
        Response::json(['ok' => true, 'deferred' => true, 'reason' => 'db_quota']);
      }
      $writeProgressDebug(date('Y-m-d H:i:s') . ' ERROR: ' . $e->getMessage() . "\n");
      Response::json(['ok'=>false,'error'=>'Falha ao atualizar progresso.'], 500);
    }
  }

  private static function is_db_connection_quota_error(\Throwable $e): bool {
    $message = strtolower(trim((string)$e->getMessage()));
    if ($message === '') {
      return false;
    }

    if (strpos($message, 'sqlstate[hy000] [1226]') !== false) {
      return true;
    }
    if (strpos($message, 'max_connections_per_hour') !== false) {
      return true;
    }
    if (strpos($message, 'has exceeded the') !== false && strpos($message, 'resource') !== false) {
      return true;
    }

    return false;
  }

  private static function final_exam_gate_message(array $gate): string {
    $hours = max(0, (int)($gate['hours'] ?? 0));
    $firstAccessTs = (int)($gate['first_access_ts'] ?? 0);
    $unlockTs = (int)($gate['unlock_ts'] ?? 0);

    $parts = [];
    if ($hours > 0) {
      $parts[] = 'A prova final so sera liberada apos ' . $hours . ' hora' . ($hours === 1 ? '' : 's') . ' do primeiro acesso no curso.';
    }
    if ($firstAccessTs > 0) {
      $parts[] = 'Primeiro acesso: ' . date('d/m/Y H:i', $firstAccessTs) . '.';
    }
    if ($unlockTs > 0) {
      $parts[] = 'Liberacao prevista: ' . date('d/m/Y H:i', $unlockTs) . '.';
    }

    $message = trim(implode(' ', $parts));
    return $message !== '' ? $message : 'A prova final ainda nao esta liberada para este aluno.';
  }

  private static function is_successful_moodle_completion_sync($syncMeta): bool {
    if (!is_array($syncMeta) || empty($syncMeta['ok'])) {
      return false;
    }
    if (!empty($syncMeta['synced'])) {
      return true;
    }
    $reason = trim((string)($syncMeta['reason'] ?? ''));
    return $reason === 'already_synced';
  }

  private static function moodle_completion_sync_error_message(string $reason): string {
    $normalized = strtolower(trim($reason));
    if ($normalized === '') {
      return 'Nao foi possivel sincronizar conclusao no Moodle neste momento.';
    }
    if ($normalized === 'node_without_cmid') {
      return 'Este item nao possui CMID mapeado. Configure o mapeamento para concluir no Moodle.';
    }
    if ($normalized === 'cm_not_found' || $normalized === 'cm_course_mismatch') {
      return 'Mapeamento Moodle invalido para este item. Revise o CMID.';
    }
    if ($normalized === 'completion_disabled') {
      return 'A conclusao desta atividade esta desativada no Moodle.';
    }
    if ($normalized === 'not_logged_in') {
      return 'Sessao Moodle indisponivel para sincronizar a conclusao agora.';
    }
    return 'Nao foi possivel sincronizar conclusao no Moodle agora (' . $normalized . ').';
  }

  private static function course_update(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $desc  = (string)($_POST['description'] ?? '');
    $mcid  = (int)($_POST['moodle_courseid'] ?? 0);
    $days  = (int)($_POST['access_days'] ?? 0);
    $sequenceLock = CoursePolicyService::bool_from_input($_POST['enable_sequence_lock'] ?? null, true);
    $requireBiometric = CoursePolicyService::bool_from_input($_POST['require_biometric'] ?? null, false);
    $showNumbering = CoursePolicyService::bool_from_input($_POST['show_numbering'] ?? null, false);
    $finalExamUnlockHours = max(0, (int)($_POST['final_exam_unlock_hours'] ?? 0));

    if ($courseId <= 0 || $title === '') {
      Response::json([
        'ok'=>false,
        'error'=>'Preencha todos os campos obrigatorios para salvar o curso.',
        'fields'=>[
          'course_id'=>($courseId > 0),
          'title'=>($title !== ''),
        ]
      ], 400);
    }

    try {
      Db::exec(
        "UPDATE app_course
            SET title = :t,
                description = :d,
                moodle_courseid = :mcid,
                access_days = :days,
                enable_sequence_lock = :sequence_lock,
                require_biometric = :require_biometric,
                final_exam_unlock_hours = :final_exam_unlock_hours,
                show_numbering = :show_numbering
          WHERE id = :id",
        [
          't' => $title,
          'd' => $desc,
          'mcid' => $mcid > 0 ? $mcid : 0,
          'days' => $days > 0 ? $days : 0,
          'sequence_lock' => $sequenceLock,
          'require_biometric' => $requireBiometric,
          'final_exam_unlock_hours' => $finalExamUnlockHours,
          'show_numbering' => $showNumbering,
          'id' => $courseId,
        ]
      );
    } catch (\PDOException $e) {
      $message = (string)$e->getMessage();
      $missingPolicyCols = strpos($message, 'enable_sequence_lock') !== false
        || strpos($message, 'require_biometric') !== false
        || strpos($message, 'final_exam_unlock_hours') !== false
        || strpos($message, 'show_numbering') !== false;
      if (!$missingPolicyCols) {
        Response::json(['ok' => false, 'error' => 'Falha ao salvar configuracoes do curso.'], 500);
      }

      Db::exec(
        "UPDATE app_course
            SET title = :t,
                description = :d,
                moodle_courseid = :mcid,
                access_days = :days
          WHERE id = :id",
        [
          't' => $title,
          'd' => $desc,
          'mcid' => $mcid > 0 ? $mcid : 0,
          'days' => $days > 0 ? $days : 0,
          'id' => $courseId,
        ]
      );
    }

    Response::json(['ok'=>true]);
  }

  private static function course_mapping_save(): void {
    global $DB;

    $courseId = (int)($_POST['course_id'] ?? 0);
    $moodleCourseId = (int)($_POST['moodle_courseid'] ?? 0);
    $forceRemap = ((int)($_POST['force_remap'] ?? 0) === 1);

    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso APP invalido.'], 400);
    }
    if ($moodleCourseId < 0) {
      Response::json(['ok' => false, 'error' => 'Curso LMS invalido.'], 400);
    }

    $appCourse = Db::one(
      "SELECT id, title, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$appCourse) {
      Response::json(['ok' => false, 'error' => 'Curso APP nao encontrado.'], 404);
    }

    $moodleCourse = null;
    $conflictCourse = null;
    if ($moodleCourseId > 0) {
      $moodleCourse = $DB->get_record(
        'course',
        ['id' => $moodleCourseId],
        'id, fullname, shortname, visible',
        IGNORE_MISSING
      );
      if (!$moodleCourse || (int)$moodleCourse->id === SITEID) {
        Response::json(['ok' => false, 'error' => 'Curso LMS nao encontrado.'], 404);
      }

      $conflictCourse = Db::one(
        "SELECT id, title
           FROM app_course
          WHERE moodle_courseid = :mcid
            AND id <> :id
          LIMIT 1",
        [
          'mcid' => $moodleCourseId,
          'id' => $courseId,
        ]
      );

      if ($conflictCourse && !$forceRemap) {
        Response::json([
          'ok' => false,
          'code' => 'mapping_conflict',
          'error' => 'Este curso do LMS ja esta mapeado em outro curso APP.',
          'conflict_course' => [
            'id' => (int)($conflictCourse['id'] ?? 0),
            'title' => (string)($conflictCourse['title'] ?? ''),
          ],
        ], 409);
      }
    }

    Db::tx(function () use ($courseId, $moodleCourseId, $conflictCourse): void {
      if ($moodleCourseId > 0 && $conflictCourse) {
        Db::exec(
          "UPDATE app_course
              SET moodle_courseid = 0
            WHERE id = :id",
          ['id' => (int)$conflictCourse['id']]
        );
      }

      Db::exec(
        "UPDATE app_course
            SET moodle_courseid = :mcid
          WHERE id = :id",
        [
          'mcid' => $moodleCourseId > 0 ? $moodleCourseId : 0,
          'id' => $courseId,
        ]
      );
    });

    Response::json([
      'ok' => true,
      'course' => [
        'id' => $courseId,
        'title' => (string)($appCourse['title'] ?? ''),
        'moodle_courseid' => $moodleCourseId > 0 ? $moodleCourseId : 0,
      ],
      'moodle_course' => $moodleCourse ? [
        'id' => (int)($moodleCourse->id ?? 0),
        'fullname' => (string)($moodleCourse->fullname ?? ''),
        'shortname' => (string)($moodleCourse->shortname ?? ''),
        'visible' => (int)($moodleCourse->visible ?? 0),
      ] : null,
      'forced_remap' => ($moodleCourseId > 0 && $forceRemap && $conflictCourse) ? 1 : 0,
      'unmapped' => $moodleCourseId <= 0 ? 1 : 0,
    ]);
  }

  private static function course_runtime_update(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $onlyApp = CoursePolicyService::bool_from_input($_POST['only_app'] ?? null, false) === 1;

    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido.'], 400);
    }

    $admin = Auth::user();

    try {
      CourseRuntimeService::set_only_app($courseId, $onlyApp, (int)($admin->id ?? 0));
      Response::json([
        'ok' => true,
        'course_id' => $courseId,
        'only_app' => $onlyApp ? 1 : 0,
      ]);
    } catch (\RuntimeException $e) {
      error_log('[app_v3] Falha de regra ao atualizar runtime do curso: ' . $e->getMessage());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha tecnica ao atualizar runtime do curso: ' . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Falha ao atualizar configuracao de runtime do curso.'], 500);
    }
  }

  private static function course_delete(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido.'], 400);
    }

    $course = Db::one("SELECT id, title FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    try {
      Db::tx(function () use ($courseId): void {
        Db::exec("DELETE FROM app_progress WHERE course_id = :cid", ['cid' => $courseId]);
        Db::exec("DELETE FROM app_course_exam WHERE course_id = :cid", ['cid' => $courseId]);
        Db::exec("DELETE FROM app_course_biometric_audit WHERE course_id = :cid", ['cid' => $courseId]);
        Db::exec("DELETE FROM app_node WHERE course_id = :cid", ['cid' => $courseId]);
        Db::exec("DELETE FROM app_course WHERE id = :cid", ['cid' => $courseId]);
      });

      self::delete_course_storage_dir($courseId);

      Response::json([
        'ok' => true,
        'course_id' => $courseId,
        'message' => 'Curso e dados vinculados removidos do app.',
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha ao excluir curso no app: ' . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Falha ao excluir curso no app.'], 500);
    }
  }

  private static function admin_biometric_read(): void {
    global $DB;

    $auditId = (int)($_GET['id'] ?? 0);
    if ($auditId <= 0) {
      Response::json(['ok' => false, 'error' => 'Registro biometrico invalido.'], 400);
    }

    $selectProvider = self::biometric_provider_columns_available()
      ? ",
         b.provider_name,
         b.provider_operation,
         b.provider_external_id,
         b.provider_score,
         b.provider_http_status,
         b.provider_message"
      : ",
         NULL AS provider_name,
         NULL AS provider_operation,
         NULL AS provider_external_id,
         NULL AS provider_score,
         NULL AS provider_http_status,
         NULL AS provider_message";

    $row = Db::one(
      "SELECT
         b.id,
         b.course_id,
         b.moodle_userid,
         b.photo_b64,
         b.photo_size_bytes"
         . $selectProvider .
         ",
         b.status,
         b.ip_address,
         b.user_agent,
         UNIX_TIMESTAMP(b.captured_at) AS captured_ts,
         c.title AS course_title
       FROM app_course_biometric_audit b
       JOIN app_course c ON c.id = b.course_id
      WHERE b.id = :id
      LIMIT 1",
      ['id' => $auditId]
    );

    if (!$row) {
      Response::json(['ok' => false, 'error' => 'Biometria nao encontrada.'], 404);
    }

    $userId = (int)($row['moodle_userid'] ?? 0);
    $fullName = 'Usuario #' . $userId;
    $username = '';
    $email = '';

    if ($userId > 0) {
      $userRecord = $DB->get_record('user', ['id' => $userId, 'deleted' => 0], 'id, username, firstname, lastname, email', IGNORE_MISSING);
      if ($userRecord) {
        $resolvedFullName = trim((string)\fullname($userRecord));
        if ($resolvedFullName !== '') {
          $fullName = $resolvedFullName;
        }
        $username = trim((string)($userRecord->username ?? ''));
        $email = trim((string)($userRecord->email ?? ''));
      }
    }

    $capturedTs = (int)($row['captured_ts'] ?? 0);
    $photoB64 = trim((string)($row['photo_b64'] ?? ''));
    if ($photoB64 === '') {
      Response::json(['ok' => false, 'error' => 'Esta biometria nao possui foto armazenada.'], 404);
    }

    Response::json([
      'ok' => true,
      'audit' => [
        'id' => (int)$row['id'],
        'course_id' => (int)($row['course_id'] ?? 0),
        'course_title' => (string)($row['course_title'] ?? ''),
        'moodle_userid' => $userId,
        'fullname' => $fullName,
        'username' => $username,
        'email' => $email,
        'captured_at' => $capturedTs,
        'captured_at_label' => $capturedTs > 0 ? date('d/m/Y H:i', $capturedTs) : '-',
        'photo_size_bytes' => (int)($row['photo_size_bytes'] ?? 0),
        'photo_size_label' => self::format_bytes((int)($row['photo_size_bytes'] ?? 0)),
        'provider_name' => (string)($row['provider_name'] ?? ''),
        'provider_operation' => (string)($row['provider_operation'] ?? ''),
        'provider_external_id' => (string)($row['provider_external_id'] ?? ''),
        'provider_score' => $row['provider_score'] !== null ? (float)$row['provider_score'] : null,
        'provider_http_status' => (int)($row['provider_http_status'] ?? 0),
        'provider_message' => (string)($row['provider_message'] ?? ''),
        'status' => (string)($row['status'] ?? 'approved'),
        'ip_address' => (string)($row['ip_address'] ?? ''),
        'user_agent' => (string)($row['user_agent'] ?? ''),
        'photo_src' => 'data:image/jpeg;base64,' . $photoB64,
      ],
    ]);
  }

  private static function course_access_block(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($courseId <= 0 || $userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso ou aluno invalido para bloqueio.'], 400);
    }

    $course = Db::one("SELECT id, title FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $admin = Auth::user();
    $block = UserCourseBlockService::block(
      $courseId,
      $userId,
      [
        'title' => (string)($_POST['title'] ?? ''),
        'message' => (string)($_POST['message'] ?? ''),
        'reason_code' => (string)($_POST['reason_code'] ?? 'manual'),
        'expires_at' => (string)($_POST['expires_at'] ?? ''),
      ],
      (int)($admin->id ?? 0)
    );

    Response::json([
      'ok' => true,
      'course_id' => $courseId,
      'user_id' => $userId,
      'title' => UserCourseBlockService::title_for_row($block),
      'message' => UserCourseBlockService::message_for_row($block),
      'block' => $block,
    ]);
  }

  private static function course_access_unblock(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($courseId <= 0 || $userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso ou aluno invalido para desbloqueio.'], 400);
    }

    $course = Db::one("SELECT id FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $admin = Auth::user();
    UserCourseBlockService::unblock($courseId, $userId, (int)($admin->id ?? 0));

    Response::json([
      'ok' => true,
      'course_id' => $courseId,
      'user_id' => $userId,
    ]);
  }
  private static function admin_biometric_delete(): void {
    $auditId = (int)($_POST['audit_id'] ?? 0);
    if ($auditId <= 0) {
      Response::json(['ok' => false, 'error' => 'Registro biometrico invalido.'], 400);
    }

    $row = Db::one(
      "SELECT id, course_id, moodle_userid
         FROM app_course_biometric_audit
        WHERE id = :id
        LIMIT 1",
      ['id' => $auditId]
    );

    if (!$row) {
      Response::json(['ok' => false, 'error' => 'Biometria nao encontrada.'], 404);
    }

    try {
      Db::exec("DELETE FROM app_course_biometric_audit WHERE id = :id", ['id' => $auditId]);
      Response::json([
        'ok' => true,
        'audit_id' => $auditId,
        'course_id' => (int)($row['course_id'] ?? 0),
        'moodle_userid' => (int)($row['moodle_userid'] ?? 0),
        'message' => 'Biometria removida do app.',
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha ao excluir biometria: ' . $e->getMessage());
      Response::json(['ok' => false, 'error' => 'Falha ao excluir biometria do app.'], 500);
    }
  }

  private static function course_numbering_toggle(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido.'], 400);
    }

    $course = Db::one("SELECT id, show_numbering FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $current = ((int)($course['show_numbering'] ?? 0) === 1);
    $next = array_key_exists('show_numbering', $_POST)
      ? (CoursePolicyService::bool_from_input($_POST['show_numbering'] ?? null, $current) === 1)
      : !$current;

    try {
      Db::exec(
        "UPDATE app_course
            SET show_numbering = :show_numbering
          WHERE id = :id",
        [
          'show_numbering' => $next ? 1 : 0,
          'id' => $courseId,
        ]
      );
    } catch (\PDOException $e) {
      $message = (string)$e->getMessage();
      if (strpos($message, 'show_numbering') !== false) {
        Response::json(['ok' => false, 'error' => 'Campo show_numbering nao existe no banco. Recarregue para aplicar o schema.'], 500);
      }
      Response::json(['ok' => false, 'error' => 'Falha ao atualizar numeracao do menu.'], 500);
    }

    Response::json([
      'ok' => true,
      'course_id' => $courseId,
      'show_numbering' => $next ? 1 : 0,
      'label' => $next ? 'Com numeracao' : 'Sem numeracao',
    ]);
  }

  private static function course_create(): void {
    $title = trim((string)($_POST['title'] ?? ''));
    $slug  = trim((string)($_POST['slug'] ?? ''));
    $u = Auth::user();

    if ($title === '') Response::json(['ok'=>false,'error'=>'Titulo e obrigatorio.'], 400);

    try {
      $courseId = CourseBlueprintService::createDraftCourse([
        'title' => $title,
        'slug' => $slug,
        'description' => (string)($_POST['description'] ?? ''),
        'structure' => (string)($_POST['structure'] ?? 'simple'),
        'moodle_courseid' => (int)($_POST['moodle_courseid'] ?? 0),
        'access_days' => (int)($_POST['access_days'] ?? 0),
        'enable_sequence_lock' => CoursePolicyService::bool_from_input($_POST['enable_sequence_lock'] ?? null, true) === 1,
        'require_biometric' => CoursePolicyService::bool_from_input($_POST['require_biometric'] ?? null, false) === 1,
        'final_exam_unlock_hours' => max(0, (int)($_POST['final_exam_unlock_hours'] ?? 0)),
      ], (int)$u->id);

      $course = Db::one("SELECT * FROM app_course WHERE id=:id LIMIT 1", ['id'=>$courseId]);
      Response::json(['ok'=>true, 'course'=>$course]);
    } catch (\RuntimeException $e) {
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 400);
    } catch (\Throwable $e) {
      Response::json(['ok'=>false,'error'=>'Falha ao criar curso.'], 500);
    }
  }

  private static function course_import(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $moodleCourseId = (int)($_POST['moodle_courseid'] ?? 0);
    $mode = ((string)($_POST['mode'] ?? 'merge') === 'replace') ? 'replace' : 'merge';
    $forceReplace = ((int)($_POST['force_replace'] ?? 0) === 1);
    $syncOverridesRaw = (string)($_POST['sync_overrides'] ?? '');

    $syncOverrides = [];
    if ($syncOverridesRaw !== '') {
      $decoded = json_decode($syncOverridesRaw, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $syncOverrides = $decoded;
      }
    }

    if ($courseId <= 0 && $moodleCourseId <= 0) {
      Response::json(['ok'=>false,'error'=>'Informe o curso local ou o Moodle Course ID.'], 400);
    }

    $course = null;
    if ($courseId > 0) {
      $course = Db::one("SELECT * FROM app_course WHERE id=:id LIMIT 1", ['id'=>$courseId]);
    } else if ($moodleCourseId > 0) {
      $course = Db::one("SELECT * FROM app_course WHERE moodle_courseid=:mcid LIMIT 1", ['mcid'=>$moodleCourseId]);
      if ($course) $courseId = (int)$course['id'];
    }
    if (!$course) Response::json(['ok'=>false,'error'=>'Curso local nao encontrado.'], 404);

    if ($moodleCourseId <= 0) {
      $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    }
    if ($moodleCourseId <= 0) {
      Response::json(['ok'=>false,'error'=>'Este curso nao tem Moodle Course ID.'], 400);
    }

    if ($mode === 'replace' && !$forceReplace) {
      $existing = Db::one(
        "SELECT COUNT(*) AS total
           FROM app_node
          WHERE course_id = :cid
            AND parent_id IS NOT NULL",
        ['cid' => $courseId]
      );
      if ((int)($existing['total'] ?? 0) > 0) {
        Response::json([
          'ok' => false,
          'error' => 'Sincronizacao total bloqueada por seguranca. Use merge para importar somente diferencas.'
        ], 409);
      }
    }

    if ((int)($course['moodle_courseid'] ?? 0) !== $moodleCourseId) {
      Db::exec("UPDATE app_course SET moodle_courseid=:mcid WHERE id=:id", [
        'mcid' => $moodleCourseId,
        'id' => $courseId,
      ]);
    }

    try {
      $stats = CourseSyncService::importFromMoodle($courseId, $moodleCourseId, $mode, $syncOverrides);
      $mappedPost = CourseSyncService::syncNodeMappingColumnsForCourse($courseId);
      $rowCounts = Db::one(
        "SELECT
            COUNT(*) AS total_nodes,
            SUM(CASE WHEN moodle_cmid IS NOT NULL AND moodle_cmid > 0 THEN 1 ELSE 0 END) AS mapped_nodes
           FROM app_node
          WHERE course_id = :cid",
        ['cid' => $courseId]
      );
      $stats['mapping_columns_synced_post'] = (int)$mappedPost;
      $stats['total_nodes'] = (int)($rowCounts['total_nodes'] ?? 0);
      $stats['mapped_nodes'] = (int)($rowCounts['mapped_nodes'] ?? 0);

      Response::json(['ok'=>true, 'stats'=>$stats, 'mode'=>$mode]);
    } catch (\Throwable $e) {
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
  }

  private static function course_sync(): void {
    self::course_import();
  }

  private static function course_progress_push(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $limitUsers = (int)($_POST['limit_users'] ?? 0);
    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido para sincronizacao de progresso.'], 400);
    }

    $admin = Auth::user();

    try {
      $sync = ProgressSyncService::push_course_completion_to_moodle(
        $courseId,
        $limitUsers,
        isset($admin->id) ? (int)$admin->id : null
      );

      error_log(
        '[' . date('c') . '] ADMIN_PROGRESS_PUSH_APP_TO_MOODLE '
        . 'admin=' . (int)($admin->id ?? 0)
        . ' course=' . $courseId
        . ' users=' . (int)($sync['unique_users'] ?? 0)
        . ' attempted=' . (int)($sync['attempted'] ?? 0)
        . ' synced=' . (int)($sync['synced'] ?? 0)
        . ' already=' . (int)($sync['already_synced'] ?? 0)
        . ' skipped=' . (int)($sync['skipped'] ?? 0)
        . ' errors=' . (int)($sync['errors'] ?? 0)
      );

      Response::json([
        'ok' => true,
        'sync' => $sync,
      ]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
      Response::json(['ok' => false, 'error' => 'Falha ao sincronizar progresso APP -> Moodle.'], 500);
    }
  }

  private static function course_progress_catchup(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido para rodar o catchup de progresso.'], 400);
    }

    $course = Db::one(
      "SELECT id, title
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    try {
      $summary = ProgressCatchupService::backfill_course_for_completed_students($courseId);
      $admin = Auth::user();
      error_log(
        '[' . date('c') . '] ADMIN_PROGRESS_CATCHUP '
        . 'admin=' . (int)($admin->id ?? 0)
        . ' course=' . $courseId
        . ' eligible_nodes=' . (int)($summary['eligible_nodes'] ?? 0)
        . ' processed_nodes=' . (int)($summary['processed_nodes'] ?? 0)
        . ' auto_completed_rows=' . (int)($summary['auto_completed_rows'] ?? 0)
      );

      Response::json([
        'ok' => true,
        'summary' => $summary,
      ]);
    } catch (\Throwable $e) {
      Response::json(['ok' => false, 'error' => 'Falha ao rodar o catchup de progresso.'], 500);
    }
  }

  private static function course_sync_cover(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $moodleCourseId = (int)($_POST['moodle_courseid'] ?? 0);

    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido.'], 400);
    }

    try {
      $result = CourseSyncService::syncCoverOnly($courseId, $moodleCourseId);
      Response::json(['ok' => true, 'result' => $result]);
    } catch (\RuntimeException $e) {
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
      Response::json(['ok' => false, 'error' => 'Falha ao sincronizar imagem do curso.'], 500);
    }
  }

  private static function course_sync_preview(): void {
    $moodleCourseId = (int)($_POST['moodle_courseid'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($moodleCourseId <= 0) {
      Response::json(['ok'=>false,'error'=>'Informe um Moodle Course ID valido.'], 400);
    }

    try {
      $preview = CourseSyncService::previewFromMoodle($moodleCourseId, $courseId);
      Response::json(['ok'=>true, 'preview'=>$preview]);
    } catch (\RuntimeException $e) {
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 400);
    } catch (\Throwable $e) {
      Response::json(['ok'=>false,'error'=>'Falha ao carregar pre-visualizacao.'], 500);
    }
  }

  private static function course_template_apply(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) {
      Response::json(['ok'=>false,'error'=>'Curso invalido.'], 400);
    }

    try {
      CourseSyncService::applyTemplate($courseId);
      Response::json(['ok'=>true]);
    } catch (\Throwable $e) {
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
  }

  private static function course_template_new(): void {
    $title = trim((string)($_POST['title'] ?? 'Curso Modelo'));
    if ($title === '') {
      $title = 'Curso Modelo';
    }

    $user = Auth::user();

    try {
      $courseId = CourseSyncService::createTemplateCourse($title, (int)$user->id);
      $course = Db::one("SELECT * FROM app_course WHERE id=:id LIMIT 1", ['id'=>$courseId]);

      Response::json([
        'ok'=>true,
        'course'=>$course,
        'course_id'=>$courseId,
        'builder_url'=>App::base_url('/admin/courses/' . $courseId . '/builder'),
      ]);
    } catch (\Throwable $e) {
      Response::json(['ok'=>false,'error'=>$e->getMessage()], 500);
    }
  }

  private static function course_publish(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'draft'); // draft|published|archived
    if (!in_array($status, ['draft','published','archived'], true)) $status = 'draft';

    if ($courseId <= 0) Response::json(['ok'=>false,'error'=>'Curso invalido.'], 400);

    // regra: para published, exige moodle_courseid OU admin assume risco
    if ($status === 'published') {
      $c = Db::one("SELECT moodle_courseid FROM app_course WHERE id=:id", ['id'=>$courseId]);
      if (!$c) Response::json(['ok'=>false,'error'=>'Curso nao encontrado.'], 404);
      if (empty($c['moodle_courseid'])) {
        Response::json(['ok'=>false,'error'=>'Para publicar, defina o moodle_courseid (matricula controla acesso).'], 400);
      }
      Db::exec("UPDATE app_course SET status='published', published_at=NOW() WHERE id=:id", ['id'=>$courseId]);
    } else {
      Db::exec("UPDATE app_course SET status=:s WHERE id=:id", ['s'=>$status,'id'=>$courseId]);
    }

    Response::json(['ok'=>true]);
  }

  private static function exam_save(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $cmid = (int)($_POST['quiz_cmid'] ?? 0);
    $minGrade = trim((string)($_POST['min_grade'] ?? ''));
    $minGrade = ($minGrade === '') ? null : (float)str_replace(',', '.', $minGrade);
    $title = trim((string)($_POST['exam_title'] ?? ''));

    if ($courseId <= 0 || $cmid <= 0) Response::json(['ok'=>false,'error'=>'Informe quiz CMID.'], 400);

    Db::tx(function() use ($courseId, $cmid, $minGrade, $title) {
      $exists = Db::one("SELECT course_id FROM app_course_exam WHERE course_id=:cid", ['cid'=>$courseId]);
      if ($exists) {
        Db::exec("UPDATE app_course_exam SET quiz_cmid=:cmid, min_grade=:mg, exam_title=:t WHERE course_id=:cid", [
          'cmid'=>$cmid,'mg'=>$minGrade,'t'=>$title,'cid'=>$courseId
        ]);
      } else {
        Db::exec("INSERT INTO app_course_exam (course_id, quiz_cmid, min_grade, exam_title) VALUES (:cid,:cmid,:mg,:t)", [
          'cid'=>$courseId,'cmid'=>$cmid,'mg'=>$minGrade,'t'=>$title
        ]);
      }
    });

    Response::json(['ok'=>true]);
  }

  private static function node_create(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
    $kind     = (string)($_POST['kind'] ?? 'content'); // container|content|action
    $subtype  = trim((string)($_POST['subtype'] ?? ''));
    $title    = trim((string)($_POST['title'] ?? ''));
    $countInProgressPercent = CoursePolicyService::bool_from_input(
      $_POST['count_in_progress_percent'] ?? null,
      !($kind === 'action' && $subtype === 'certificate')
    );
    $content  = $_POST['content'] ?? null; // JSON string
    $rules    = $_POST['rules'] ?? null;

    // Validacao obrigatorios
    $erros = [];
    if ($courseId<=0) $erros[] = 'Curso invalido';
    if ($title==='') $erros[] = 'Titulo obrigatorio';
    if ($subtype==='') $erros[] = 'Subtype obrigatorio';
    if (!in_array($kind, ['container','content','action'], true)) $kind='content';
    if (!in_array($subtype, self::allowed_subtypes_for_kind($kind), true)) $erros[] = 'Subtype invalido para o kind informado';
    if ($erros) Response::json(['ok'=>false,'error'=>'Preencha todos os campos obrigatorios.','fields'=>$erros], 400);

    // validate JSON
    $contentDecoded = null;
    $contentJson = null;
    if ($content !== null && $content !== '') {
      $decoded = json_decode((string)$content, true);
      if (json_last_error() !== JSON_ERROR_NONE) Response::json(['ok'=>false,'error'=>'content JSON invalido.'], 400);
      $contentDecoded = is_array($decoded) ? $decoded : [];
    }
    $nodeMap = self::extract_moodle_mapping_values(is_array($contentDecoded) ? $contentDecoded : []);
    $nodeMap = self::enrich_moodle_mapping_values($nodeMap);
    if ($kind === 'container') {
      $nodeMap = ['cmid' => 0, 'modname' => '', 'url' => ''];
      if (is_array($contentDecoded)) {
        $contentDecoded = self::strip_moodle_mapping_from_content($contentDecoded);
      }
    }
    if (is_array($contentDecoded)) {
      $contentDecoded = self::inject_moodle_mapping_in_content($contentDecoded, $nodeMap);
      $contentJson = json_encode($contentDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $rulesJson = null;
    if ($rules !== null && $rules !== '') {
      $decoded = json_decode((string)$rules, true);
      if (json_last_error() !== JSON_ERROR_NONE) Response::json(['ok'=>false,'error'=>'rules JSON invalido.'], 400);
      $rulesJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $progressNodeSnapshot = [
      'kind' => $kind,
      'subtype' => $subtype,
      'is_published' => 1,
      'rules_json' => $rulesJson,
      'count_in_progress_percent' => $countInProgressPercent,
    ];
    $shouldBackfillCompletedUsers = CoursePolicyService::node_counts_towards_progress($progressNodeSnapshot);

    $new = Db::tx(function() use ($courseId, $parentId, $kind, $subtype, $title, $contentJson, $rulesJson, $nodeMap, $countInProgressPercent) {
      $params = ['cid'=>$courseId];
      $whereParent = '';
      if ($parentId > 0) {
        $whereParent = 'parent_id=:pid';
        $params['pid'] = $parentId;
      } else {
        $whereParent = 'parent_id IS NULL';
      }
      $maxSort = Db::one("SELECT COALESCE(MAX(sort),0) AS ms FROM app_node WHERE course_id=:cid AND $whereParent", $params);
      $sort = ((int)($maxSort['ms'] ?? 0)) + 1;

      $parent = null;
      $depth = 0;
      $path = '/';
      if ($parentId > 0) {
        $parent = Db::one("SELECT id, depth, path FROM app_node WHERE id=:id AND course_id=:cid", ['id'=>$parentId,'cid'=>$courseId]);
        if (!$parent) throw new \RuntimeException('Parent invalido.');
        $depth = (int)$parent['depth'] + 1;
        $path = (string)$parent['path'];
      }

      $insertFields = "(course_id, parent_id, kind, subtype, title, sort, depth, path, moodle_cmid, moodle_modname, moodle_url, content_json, rules_json, is_published, count_in_progress_percent)";
      $insertValues = '';
      $insertParams = [];
      if ($parentId > 0) {
        $insertValues = "(:cid, :pid, :k, :st, :t, :s, :d, :p, :mcmid, :mmod, :murl, :cj, :rj, 1, :cpp)";
        $insertParams = [
          'cid'=>$courseId,
          'pid'=>$parentId,
          'k'=>$kind,
          'st'=>$subtype,
          't'=>$title,
          's'=>$sort,
          'd'=>$depth,
          'p'=>$path,
          'mcmid'=>$nodeMap['cmid'] > 0 ? $nodeMap['cmid'] : null,
          'mmod'=>$nodeMap['modname'] !== '' ? $nodeMap['modname'] : null,
          'murl'=>$nodeMap['url'] !== '' ? $nodeMap['url'] : null,
          'cj'=>$contentJson,
          'rj'=>$rulesJson,
          'cpp'=>$countInProgressPercent,
        ];
      } else {
        $insertValues = "(:cid, :pid, :k, :st, :t, :s, :d, :p, :mcmid, :mmod, :murl, :cj, :rj, 1, :cpp)";
        $insertParams = [
          'cid'=>$courseId,
          'pid'=>null,
          'k'=>$kind,
          'st'=>$subtype,
          't'=>$title,
          's'=>$sort,
          'd'=>$depth,
          'p'=>$path,
          'mcmid'=>$nodeMap['cmid'] > 0 ? $nodeMap['cmid'] : null,
          'mmod'=>$nodeMap['modname'] !== '' ? $nodeMap['modname'] : null,
          'murl'=>$nodeMap['url'] !== '' ? $nodeMap['url'] : null,
          'cj'=>$contentJson,
          'rj'=>$rulesJson,
          'cpp'=>$countInProgressPercent,
        ];
      }
      Db::exec("INSERT INTO app_node $insertFields VALUES $insertValues", $insertParams);
      $id = (int)Db::lastId();
      $newPath = rtrim($path, '/') . '/' . $id . '/';
      Db::exec("UPDATE app_node SET path=:p WHERE id=:id", ['p'=>$newPath,'id'=>$id]);

      return Db::one("SELECT * FROM app_node WHERE id=:id", ['id'=>$id]);
    });

    $autoCompletedUsers = 0;
    if ($shouldBackfillCompletedUsers && $new) {
      $autoCompletedUsers = ProgressCatchupService::backfill_new_counted_node_for_completed_students(
        $courseId,
        (int)($new['id'] ?? 0),
        $new,
        (int)($new['id'] ?? 0)
      );
    }

    Response::json(['ok'=>true,'node'=>$new, 'auto_completed_users' => $autoCompletedUsers]);
  }

  private static function node_update(): void {
    // Remove debug, permite salvar normalmente

    try {
      $nodeId = (int)($_POST['node_id'] ?? 0);
      $title  = trim((string)($_POST['title'] ?? ''));
      $kind   = trim((string)($_POST['kind'] ?? ''));
      $subtype= trim((string)($_POST['subtype'] ?? ''));
      $isPub  = (int)($_POST['is_published'] ?? 1);
      $countInProgressPercentRaw = $_POST['count_in_progress_percent'] ?? null;
      $content = $_POST['content'] ?? null; // JSON string
      $rules   = $_POST['rules'] ?? null;

      // Validacao obrigatorios e tipos conforme schema
      $erros = [];
      if ($nodeId<=0) $erros['node_id'] = 'ID do node invalido';
      if ($title==='') $erros['title'] = 'Titulo obrigatorio';
      if ($subtype==='') $erros['subtype'] = 'Subtype obrigatorio';
      if (mb_strlen($title) > 255) $erros['title'] = 'Titulo muito longo (max 255)';
      if (mb_strlen($subtype) > 40) $erros['subtype'] = 'Subtype muito longo (max 40)';
      if (!in_array($isPub, [0,1], true)) $erros['is_published'] = 'Valor invalido para publicado';
      if ($erros) Response::json(['ok'=>false,'error'=>'Preencha os campos corretamente.','fields'=>$erros], 400);

      $currentNode = Db::one(
        "SELECT id, course_id, parent_id, kind, subtype, is_published, content_json, rules_json, moodle_cmid, moodle_modname, moodle_url, count_in_progress_percent
           FROM app_node
          WHERE id = :id
          LIMIT 1",
        ['id' => $nodeId]
      );
      if (!$currentNode) {
        Response::json(['ok' => false, 'error' => 'Item nao encontrado.'], 404);
      }
      $currentContent = [];
      if (!empty($currentNode['content_json'])) {
        $parsedCurrentContent = json_decode((string)$currentNode['content_json'], true);
        if (is_array($parsedCurrentContent)) {
          $currentContent = $parsedCurrentContent;
        }
      }

      $currentCountsTowardProgress = ((int)($currentNode['is_published'] ?? 0) === 1)
        && CoursePolicyService::node_counts_towards_progress($currentNode);

      $currentKind = (string)($currentNode['kind'] ?? 'content');
      $currentSubtype = (string)($currentNode['subtype'] ?? '');
      $countInProgressPercent = CoursePolicyService::bool_from_input(
        $countInProgressPercentRaw,
        array_key_exists('count_in_progress_percent', $currentNode)
          ? ((int)($currentNode['count_in_progress_percent'] ?? 1) === 1)
          : !($currentKind === 'action' && $currentSubtype === 'certificate')
      );
      if ($kind === '') {
        $kind = $currentKind;
      }

      $allowedKinds = ['container', 'content', 'action'];
      if (!in_array($kind, $allowedKinds, true)) {
        Response::json(['ok' => false, 'error' => 'Kind invalido.'], 400);
      }

      $isRootNode = ((int)($currentNode['parent_id'] ?? 0) === 0) && $currentSubtype === 'root';
      if ($isRootNode) {
        $kind = 'container';
        $subtype = 'root';
        $countInProgressPercent = 1;
      } else {
        $allowedSubtypes = self::allowed_subtypes_for_kind($kind);
        if (!in_array($subtype, $allowedSubtypes, true)) {
          Response::json(['ok' => false, 'error' => 'Subtype invalido para o kind informado.'], 400);
        }
        if ($countInProgressPercentRaw === null || $countInProgressPercentRaw === '') {
          $countInProgressPercent = CoursePolicyService::bool_from_input(
            null,
            array_key_exists('count_in_progress_percent', $currentNode)
              ? ((int)($currentNode['count_in_progress_percent'] ?? 1) === 1)
              : !($kind === 'action' && $subtype === 'certificate')
          );
        }
      }

      if ($kind !== 'container') {
        $childrenCountRow = Db::one("SELECT COUNT(*) AS total FROM app_node WHERE parent_id = :id", ['id' => $nodeId]);
        $childrenCount = (int)($childrenCountRow['total'] ?? 0);
        if ($childrenCount > 0) {
          Response::json(['ok' => false, 'error' => 'Este item possui filhos. Para virar conteudo/acao, mova ou exclua os filhos antes.'], 400);
        }
      }

      // validate JSON
      $contentDecoded = null;
      $contentJson = null;
      if ($content !== null && $content !== '') {
        $decoded = json_decode((string)$content, true);
        if (json_last_error() !== JSON_ERROR_NONE) Response::json(['ok'=>false,'error'=>'content JSON invalido.','field'=>'content'], 400);
        $decoded = self::preserve_moodle_mapping_in_content($decoded, $currentContent);
        $contentDecoded = is_array($decoded) ? $decoded : [];
      }
      $rulesJson = null;
      if ($rules !== null && $rules !== '') {
        $decoded = json_decode((string)$rules, true);
        if (json_last_error() !== JSON_ERROR_NONE) Response::json(['ok'=>false,'error'=>'rules JSON invalido.','field'=>'rules'], 400);
        $rulesJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      $mappingSource = is_array($contentDecoded) ? $contentDecoded : $currentContent;
      $nodeMap = self::extract_moodle_mapping_values($mappingSource);
      if ($nodeMap['cmid'] <= 0) {
        $nodeMap['cmid'] = (int)($currentNode['moodle_cmid'] ?? 0);
      }
      if ($nodeMap['modname'] === '') {
        $nodeMap['modname'] = trim((string)($currentNode['moodle_modname'] ?? ''));
      }
      if ($nodeMap['url'] === '') {
        $nodeMap['url'] = trim((string)($currentNode['moodle_url'] ?? ''));
      }
      $nodeMap = self::enrich_moodle_mapping_values($nodeMap);
      if ($kind === 'container') {
        $nodeMap = ['cmid' => 0, 'modname' => '', 'url' => ''];
        if (is_array($contentDecoded)) {
          $contentDecoded = self::strip_moodle_mapping_from_content($contentDecoded);
        }
      }

      if (is_array($contentDecoded)) {
        $contentDecoded = self::inject_moodle_mapping_in_content($contentDecoded, $nodeMap);
        $contentJson = json_encode($contentDecoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      $nextProgressNode = [
        'kind' => $kind,
        'subtype' => $subtype,
        'is_published' => $isPub ? 1 : 0,
        'rules_json' => $rulesJson !== null ? $rulesJson : (string)($currentNode['rules_json'] ?? ''),
        'count_in_progress_percent' => $countInProgressPercent,
      ];
      $nextCountsTowardProgress = ($isPub === 1)
        && CoursePolicyService::node_counts_towards_progress($nextProgressNode);

      $params = [
        't'=>$title,
        'k'=>$kind,
        'st'=>$subtype,
        'p'=> $isPub ? 1 : 0,
        'mcmid'=> $nodeMap['cmid'] > 0 ? $nodeMap['cmid'] : null,
        'mmod'=> $nodeMap['modname'] !== '' ? $nodeMap['modname'] : null,
        'murl'=> $nodeMap['url'] !== '' ? $nodeMap['url'] : null,
        'cj'=> $contentJson,
        'rj'=> $rulesJson,
        'cpp'=> $countInProgressPercent,
        'id'=>$nodeId,
      ];
      foreach(['cj','rj'] as $k) {
        if (!isset($params[$k]) || $params[$k] === null) $params[$k] = null;
      }
        try {
          Db::exec("UPDATE app_node
                       SET title=:t,
                           kind=:k,
                           subtype=:st,
                           is_published=:p,
                           moodle_cmid=:mcmid,
                           moodle_modname=:mmod,
                           moodle_url=:murl,
                           content_json=:cj,
                           rules_json=:rj,
                           count_in_progress_percent=:cpp
                     WHERE id=:id", $params);
      } catch (\PDOException $e) {
        // Tenta extrair o campo do erro
        $msg = $e->getMessage();
        $campo = null;
          if (preg_match("/Column '([^']+)'/i", $msg, $m)) {
            $campo = $m[1];
          } elseif (preg_match("/Unknown column '([^']+)'/i", $msg, $m)) {
            $campo = $m[1];
          } elseif (preg_match("/Data too long for column '([^']+)'/i", $msg, $m)) {
            $campo = $m[1];
          }
        Response::json([
          'ok'=>false,
          'error'=>'Erro SQL: '.$msg.'\nParams: '.json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
          'field'=>$campo,
        ], 400);
      }

      $courseId = (int)($currentNode['course_id'] ?? 0);
      $wasFinalExam = ($currentKind === 'action' && $currentSubtype === 'final_exam');
      $isFinalExam = ($kind === 'action' && $subtype === 'final_exam');
      if ($courseId > 0) {
        if ($isFinalExam && (int)$nodeMap['cmid'] > 0) {
          $existingExam = Db::one("SELECT course_id FROM app_course_exam WHERE course_id=:cid", ['cid'=>$courseId]);
          if ($existingExam) {
            Db::exec("UPDATE app_course_exam SET quiz_cmid=:cmid, exam_title=:t WHERE course_id=:cid", [
              'cmid' => (int)$nodeMap['cmid'],
              't' => $title,
              'cid' => $courseId,
            ]);
          } else {
            Db::exec("INSERT INTO app_course_exam (course_id, quiz_cmid, min_grade, exam_title) VALUES (:cid,:cmid,NULL,:t)", [
              'cid' => $courseId,
              'cmid' => (int)$nodeMap['cmid'],
              't' => $title,
            ]);
          }
        } else if ($wasFinalExam && !$isFinalExam) {
          $otherFinalExam = Db::one(
            "SELECT id
               FROM app_node
              WHERE course_id = :cid
                AND id <> :id
                AND kind = 'action'
                AND subtype = 'final_exam'
              LIMIT 1",
            [
              'cid' => $courseId,
              'id' => $nodeId,
            ]
          );
          if (!$otherFinalExam) {
            Db::exec("DELETE FROM app_course_exam WHERE course_id = :cid", ['cid' => $courseId]);
          }
        }
      }

      $node = Db::one("SELECT * FROM app_node WHERE id=:id", ['id'=>$nodeId]);
      $autoCompletedUsers = 0;
      if (!$currentCountsTowardProgress && $nextCountsTowardProgress) {
        $autoCompletedUsers = ProgressCatchupService::backfill_new_counted_node_for_completed_students(
          $courseId,
          $nodeId,
          $node,
          $nodeId
        );
      }
      Response::json(['ok'=>true,'node'=>$node, 'auto_completed_users' => $autoCompletedUsers]);
    } catch (\Throwable $e) {
      Response::json([
        'ok'=>false,
        'error'=>'Erro interno: ' . $e->getMessage(),
        'trace'=>$e->getTraceAsString(),
      ], 500);
    }
  }

  private static function node_complete_for_enrolled(): void {
    global $DB;

    $nodeId = (int)($_POST['node_id'] ?? 0);
    if ($nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Item invalido para conclusao em massa.'], 400);
    }

    $node = Db::one(
      "SELECT id, course_id, kind, subtype, title, path, is_published
         FROM app_node
        WHERE id = :id
        LIMIT 1",
      ['id' => $nodeId]
    );
    if (!$node) {
      Response::json(['ok' => false, 'error' => 'Item nao encontrado.'], 404);
    }
    if ((int)($node['is_published'] ?? 0) !== 1) {
      Response::json(['ok' => false, 'error' => 'Publique o item antes de concluir em massa.'], 400);
    }

    $courseId = (int)($node['course_id'] ?? 0);
    $course = Db::one(
      "SELECT id, title, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    if ($kind === 'container' && $subtype === 'root') {
      Response::json(['ok' => false, 'error' => 'Use um modulo, topico, secao ou item especifico.'], 400);
    }

    $targetNodeIds = [];
    if ($kind === 'container') {
      $path = trim((string)($node['path'] ?? ''));
      if ($path === '') {
        Response::json(['ok' => false, 'error' => 'Container sem trilha valida.'], 400);
      }

      $rows = Db::all(
        "SELECT id
           FROM app_node
          WHERE course_id = :cid
            AND is_published = 1
            AND kind IN ('content', 'action')
            AND path LIKE :path
            AND id <> :nid
       ORDER BY sort ASC, id ASC",
        [
          'cid' => $courseId,
          'path' => $path . '%',
          'nid' => $nodeId,
        ]
      );
      foreach ($rows as $row) {
        $nid = (int)($row['id'] ?? 0);
        if ($nid > 0) {
          $targetNodeIds[] = $nid;
        }
      }
      if (!$targetNodeIds) {
        Response::json(['ok' => false, 'error' => 'Nao ha conteudos publicados dentro deste bloco.'], 400);
      }
    } else if ($kind === 'content' || $kind === 'action') {
      $targetNodeIds[] = $nodeId;
    } else {
      Response::json(['ok' => false, 'error' => 'Somente conteudos, acoes e containers podem ser usados nesta rotina.'], 400);
    }

    $enrolledUserIds = [];
    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    if ($moodleCourseId > 0) {
      $rows = $DB->get_records_sql(
        "SELECT DISTINCT ue.userid
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {user} u ON u.id = ue.userid
          WHERE e.courseid = :mcid
            AND e.status = 0
            AND ue.status = 0
            AND u.deleted = 0
       ORDER BY ue.userid ASC",
        ['mcid' => $moodleCourseId]
      );
      foreach ($rows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $enrolledUserIds[] = $userId;
        }
      }
    }

    $startedUserIds = [];
    $startedRows = Db::all(
      "SELECT DISTINCT moodle_userid
         FROM app_course_user_access
        WHERE course_id = :cid_access",
      ['cid_access' => $courseId]
    );
    foreach ($startedRows as $row) {
      $userId = (int)($row['moodle_userid'] ?? 0);
      if ($userId > 0) {
        $startedUserIds[] = $userId;
      }
    }

    if ($moodleCourseId > 0) {
      $lastAccessRows = $DB->get_records_sql(
        "SELECT DISTINCT userid
           FROM {user_lastaccess}
          WHERE courseid = :courseid
            AND timeaccess > 0",
        ['courseid' => $moodleCourseId]
      );
      foreach ($lastAccessRows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $startedUserIds[] = $userId;
        }
      }

      $logRows = $DB->get_records_sql(
        "SELECT DISTINCT userid
           FROM {logstore_standard_log}
          WHERE courseid = :courseid
            AND userid > 0
            AND anonymous = 0
            AND target = :target
            AND action = :action
            AND component = :component",
        [
          'courseid' => $moodleCourseId,
          'target' => 'course',
          'action' => 'viewed',
          'component' => 'core',
        ]
      );
      foreach ($logRows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $startedUserIds[] = $userId;
        }
      }
    }

    $startedUserIds = array_values(array_unique(array_map('intval', $startedUserIds)));
    $enrolledUserIds = array_values(array_unique(array_map('intval', $enrolledUserIds)));

    $userIds = $startedUserIds;
    if ($enrolledUserIds) {
      $userIds = array_values(array_intersect($startedUserIds, $enrolledUserIds));
    }

    if (!$userIds) {
      Response::json([
        'ok' => true,
        'summary' => [
          'enrolled_users' => count($enrolledUserIds),
          'started_users' => count($startedUserIds),
          'eligible_users' => 0,
          'target_nodes' => count($targetNodeIds),
          'changed_rows' => 0,
        ],
      ]);
    }

    @set_time_limit(120);

    $changedRows = 0;
    Db::tx(function() use ($courseId, $targetNodeIds, $userIds, &$changedRows): void {
      foreach ($userIds as $userId) {
        foreach ($targetNodeIds as $targetNodeId) {
          $updated = Db::exec(
            "UPDATE app_progress
                SET status = 'completed',
                    percent = GREATEST(percent, 100),
                    last_seen_at = NOW(),
                    completed_at = COALESCE(completed_at, NOW())
              WHERE moodle_userid = :uid
                AND course_id = :cid
                AND node_id = :nid",
            [
              'uid' => $userId,
              'cid' => $courseId,
              'nid' => $targetNodeId,
            ]
          );

          if ($updated > 0) {
            $changedRows++;
            continue;
          }

          Db::exec(
            "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
             VALUES (:uid, :cid, :nid, 'completed', 100, 0, 0, NOW(), NOW())",
            [
              'uid' => $userId,
              'cid' => $courseId,
              'nid' => $targetNodeId,
            ]
          );
          $changedRows++;
        }
      }
    });

    $admin = Auth::user();
    error_log(
      '[' . date('c') . '] ADMIN_NODE_COMPLETE_FOR_ENROLLED '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' course=' . $courseId
      . ' node=' . $nodeId
      . ' scope_kind=' . $kind
      . ' enrolled_users=' . count($enrolledUserIds)
      . ' started_users=' . count($startedUserIds)
      . ' eligible_users=' . count($userIds)
      . ' target_nodes=' . count($targetNodeIds)
      . ' changed_rows=' . $changedRows
    );

    Response::json([
      'ok' => true,
      'summary' => [
        'enrolled_users' => count($enrolledUserIds),
        'started_users' => count($startedUserIds),
        'eligible_users' => count($userIds),
        'target_nodes' => count($targetNodeIds),
        'changed_rows' => $changedRows,
      ],
    ]);
  }

  private static function node_cleanup_never_started(): void {
    global $DB;

    $nodeId = (int)($_POST['node_id'] ?? 0);
    if ($nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Item invalido para correcao.'], 400);
    }

    $node = Db::one(
      "SELECT id, course_id, kind, subtype, title, path, is_published
         FROM app_node
        WHERE id = :id
        LIMIT 1",
      ['id' => $nodeId]
    );
    if (!$node) {
      Response::json(['ok' => false, 'error' => 'Item nao encontrado.'], 404);
    }

    $courseId = (int)($node['course_id'] ?? 0);
    $course = Db::one(
      "SELECT id, title, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    if ($kind === 'container' && $subtype === 'root') {
      Response::json(['ok' => false, 'error' => 'Use um modulo, topico, secao ou item especifico.'], 400);
    }

    $targetNodeIds = [];
    if ($kind === 'container') {
      $path = trim((string)($node['path'] ?? ''));
      if ($path === '') {
        Response::json(['ok' => false, 'error' => 'Container sem trilha valida.'], 400);
      }

      $rows = Db::all(
        "SELECT id
           FROM app_node
          WHERE course_id = :cid
            AND is_published = 1
            AND kind IN ('content', 'action')
            AND path LIKE :path
            AND id <> :nid
       ORDER BY sort ASC, id ASC",
        [
          'cid' => $courseId,
          'path' => $path . '%',
          'nid' => $nodeId,
        ]
      );
      foreach ($rows as $row) {
        $nid = (int)($row['id'] ?? 0);
        if ($nid > 0) {
          $targetNodeIds[] = $nid;
        }
      }
      if (!$targetNodeIds) {
        Response::json(['ok' => false, 'error' => 'Nao ha conteudos publicados dentro deste bloco.'], 400);
      }
    } else if ($kind === 'content' || $kind === 'action') {
      $targetNodeIds[] = $nodeId;
    } else {
      Response::json(['ok' => false, 'error' => 'Somente conteudos, acoes e containers podem ser usados nesta rotina.'], 400);
    }

    $enrolledUserIds = [];
    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    if ($moodleCourseId > 0) {
      $rows = $DB->get_records_sql(
        "SELECT DISTINCT ue.userid
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {user} u ON u.id = ue.userid
          WHERE e.courseid = :mcid
            AND e.status = 0
            AND ue.status = 0
            AND u.deleted = 0
       ORDER BY ue.userid ASC",
        ['mcid' => $moodleCourseId]
      );
      foreach ($rows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $enrolledUserIds[] = $userId;
        }
      }
    }

    $startedUserIds = [];
    $startedRows = Db::all(
      "SELECT DISTINCT moodle_userid
         FROM app_course_user_access
        WHERE course_id = :cid_access",
      ['cid_access' => $courseId]
    );
    foreach ($startedRows as $row) {
      $userId = (int)($row['moodle_userid'] ?? 0);
      if ($userId > 0) {
        $startedUserIds[] = $userId;
      }
    }

    if ($moodleCourseId > 0) {
      $lastAccessRows = $DB->get_records_sql(
        "SELECT DISTINCT userid
           FROM {user_lastaccess}
          WHERE courseid = :courseid
            AND timeaccess > 0",
        ['courseid' => $moodleCourseId]
      );
      foreach ($lastAccessRows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $startedUserIds[] = $userId;
        }
      }

      $logRows = $DB->get_records_sql(
        "SELECT DISTINCT userid
           FROM {logstore_standard_log}
          WHERE courseid = :courseid
            AND userid > 0
            AND anonymous = 0
            AND target = :target
            AND action = :action
            AND component = :component",
        [
          'courseid' => $moodleCourseId,
          'target' => 'course',
          'action' => 'viewed',
          'component' => 'core',
        ]
      );
      foreach ($logRows as $row) {
        $userId = (int)($row->userid ?? 0);
        if ($userId > 0) {
          $startedUserIds[] = $userId;
        }
      }
    }

    $startedUserIds = array_values(array_unique(array_map('intval', $startedUserIds)));
    $enrolledUserIds = array_values(array_unique(array_map('intval', $enrolledUserIds)));
    $neverStartedUserIds = $enrolledUserIds ? array_values(array_diff($enrolledUserIds, $startedUserIds)) : [];

    if (!$neverStartedUserIds) {
      Response::json([
        'ok' => true,
        'summary' => [
          'enrolled_users' => count($enrolledUserIds),
          'never_started_users' => 0,
          'target_nodes' => count($targetNodeIds),
          'removed_rows' => 0,
        ],
      ]);
    }

    $removedRows = 0;
    Db::tx(function() use ($courseId, $targetNodeIds, $neverStartedUserIds, &$removedRows): void {
      foreach ($neverStartedUserIds as $userId) {
        foreach ($targetNodeIds as $targetNodeId) {
          $removedRows += Db::exec(
            "DELETE FROM app_progress
              WHERE moodle_userid = :uid
                AND course_id = :cid
                AND node_id = :nid",
            [
              'uid' => $userId,
              'cid' => $courseId,
              'nid' => $targetNodeId,
            ]
          );
        }
      }
    });

    $admin = Auth::user();
    error_log(
      '[' . date('c') . '] ADMIN_NODE_CLEANUP_NEVER_STARTED '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' course=' . $courseId
      . ' node=' . $nodeId
      . ' scope_kind=' . $kind
      . ' enrolled_users=' . count($enrolledUserIds)
      . ' never_started_users=' . count($neverStartedUserIds)
      . ' target_nodes=' . count($targetNodeIds)
      . ' removed_rows=' . $removedRows
    );

    Response::json([
      'ok' => true,
      'summary' => [
        'enrolled_users' => count($enrolledUserIds),
        'never_started_users' => count($neverStartedUserIds),
        'target_nodes' => count($targetNodeIds),
        'removed_rows' => $removedRows,
      ],
    ]);
  }

  private static function preserve_moodle_mapping_in_content($content, array $currentContent): array {
    if (!is_array($content)) {
      return [];
    }
    if (!is_array($currentContent)) {
      return $content;
    }

    $currentMap = self::extract_moodle_mapping_values($currentContent);
    $currentCmid = (int)($currentMap['cmid'] ?? 0);
    $newCmid = self::extract_cmid_from_content($content);
    if ($currentCmid > 0 && $newCmid <= 0) {
      $content['cmid'] = $currentCmid;
      $content['moodle_cmid'] = $currentCmid;

      $currentMoodle = $currentContent['moodle'] ?? null;
      if (is_array($currentMoodle)) {
        if (!isset($currentMoodle['cmid']) || (int)$currentMoodle['cmid'] <= 0) {
          $currentMoodle['cmid'] = $currentCmid;
        }
        $content['moodle'] = $currentMoodle;
      } else {
        $content['moodle'] = ['cmid' => $currentCmid];
      }
    }

    if (empty($content['moodle_modname']) && $currentMap['modname'] !== '') {
      $content['moodle_modname'] = (string)$currentMap['modname'];
    }
    if (empty($content['moodle']['modname']) && $currentMap['modname'] !== '') {
      if (!isset($content['moodle']) || !is_array($content['moodle'])) {
        $content['moodle'] = [];
      }
      $content['moodle']['modname'] = (string)$currentMap['modname'];
    }
    if (empty($content['moodle']['url']) && $currentMap['url'] !== '') {
      if (!isset($content['moodle']) || !is_array($content['moodle'])) {
        $content['moodle'] = [];
      }
      $content['moodle']['url'] = (string)$currentMap['url'];
    }

    return $content;
  }

  private static function strip_moodle_mapping_from_content(array $content): array {
    unset($content['cmid'], $content['moodle_cmid'], $content['moodle_modname'], $content['moodle_url']);
    if (isset($content['moodle']) && is_array($content['moodle'])) {
      unset($content['moodle']['cmid'], $content['moodle']['modname'], $content['moodle']['url']);
      if (empty($content['moodle'])) {
        unset($content['moodle']);
      }
    }
    return $content;
  }

  private static function allowed_subtypes_for_kind(string $kind, bool $allowRoot = false): array {
    if ($kind === 'container') {
      return $allowRoot
        ? ['root', 'module', 'topic', 'section', 'subsection']
        : ['module', 'topic', 'section', 'subsection'];
    }
    if ($kind === 'action') {
      return ['final_exam', 'certificate'];
    }
    return ['video', 'pdf', 'text', 'download', 'link'];
  }

  private static function extract_moodle_mapping_values(array $content): array {
    $cmid = self::extract_cmid_from_content($content);

    $modname = trim((string)($content['moodle']['modname'] ?? $content['moodle_modname'] ?? ''));
    if ($modname !== '') {
      $modname = function_exists('mb_substr') ? mb_substr($modname, 0, 50, 'UTF-8') : substr($modname, 0, 50);
    }

    $url = trim((string)($content['moodle']['url'] ?? $content['moodle_url'] ?? ''));
    if ($url === '' && $cmid <= 0) {
      $url = trim((string)($content['url'] ?? $content['file_path'] ?? $content['source_url'] ?? ''));
    }
    if ($url !== '') {
      $url = function_exists('mb_substr') ? mb_substr($url, 0, 2048, 'UTF-8') : substr($url, 0, 2048);
    }

    return [
      'cmid' => $cmid,
      'modname' => $modname,
      'url' => $url,
    ];
  }

  private static function extract_cmid_from_content(array $content): int {
    $cmid = (int)($content['moodle']['cmid'] ?? 0);
    if ($cmid > 0) {
      return $cmid;
    }
    $cmid = (int)($content['cmid'] ?? 0);
    if ($cmid > 0) {
      return $cmid;
    }
    $cmid = (int)($content['moodle_cmid'] ?? 0);
    return $cmid > 0 ? $cmid : 0;
  }

  private static function node_is_quiz_item(array $node): bool {
    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    if ($kind === 'action' && $subtype === 'final_exam') {
      return true;
    }

    $modname = strtolower(trim((string)($node['moodle_modname'] ?? '')));
    if ($modname === 'quiz') {
      return true;
    }

    $content = json_decode((string)($node['content_json'] ?? ''), true);
    if (!is_array($content)) {
      return false;
    }

    $contentModname = strtolower(trim((string)($content['moodle']['modname'] ?? $content['moodle_modname'] ?? '')));
    return $contentModname === 'quiz';
  }

  private static function node_is_lesson_item(array $node): bool {
    $modname = strtolower(trim((string)($node['moodle_modname'] ?? '')));
    if ($modname === 'lesson') {
      return true;
    }

    $content = json_decode((string)($node['content_json'] ?? ''), true);
    if (!is_array($content)) {
      return false;
    }

    $contentModname = strtolower(trim((string)($content['moodle']['modname'] ?? $content['moodle_modname'] ?? '')));
    if ($contentModname === 'lesson') {
      return true;
    }

    $cmid = self::extract_cmid_from_content($content);
    if ($cmid <= 0) {
      $cmid = (int)($node['moodle_cmid'] ?? 0);
    }
    if ($cmid <= 0) {
      return false;
    }

    return strtolower(trim((string)Moodle::cm_modname($cmid))) === 'lesson';
  }

  private static function enrich_moodle_mapping_values(array $map): array {
    $cmid = (int)($map['cmid'] ?? 0);
    if ($cmid <= 0) {
      return [
        'cmid' => 0,
        'modname' => trim((string)($map['modname'] ?? '')),
        'url' => trim((string)($map['url'] ?? '')),
      ];
    }

    $url = trim((string)($map['url'] ?? ''));
    if ($url === '') {
      $resolvedUrl = trim((string)Moodle::cm_view_url($cmid));
      if ($resolvedUrl !== '') {
        $url = function_exists('mb_substr') ? mb_substr($resolvedUrl, 0, 2048, 'UTF-8') : substr($resolvedUrl, 0, 2048);
      }
    }

    $modname = trim((string)($map['modname'] ?? ''));
    if ($modname === '') {
      $resolvedModname = trim((string)Moodle::cm_modname($cmid));
      if ($resolvedModname !== '') {
        $modname = $resolvedModname;
      }
    }
    if ($modname !== '') {
      $modname = function_exists('mb_substr') ? mb_substr($modname, 0, 50, 'UTF-8') : substr($modname, 0, 50);
    }

    return [
      'cmid' => $cmid,
      'modname' => $modname,
      'url' => $url,
    ];
  }

  private static function inject_moodle_mapping_in_content(array $content, array $map): array {
    $cmid = (int)($map['cmid'] ?? 0);
    $modname = trim((string)($map['modname'] ?? ''));
    $url = trim((string)($map['url'] ?? ''));

    if ($cmid > 0) {
      $content['cmid'] = $cmid;
      $content['moodle_cmid'] = $cmid;
      if (!isset($content['moodle']) || !is_array($content['moodle'])) {
        $content['moodle'] = [];
      }
      $content['moodle']['cmid'] = $cmid;
    }

    if ($modname !== '') {
      if (!isset($content['moodle']) || !is_array($content['moodle'])) {
        $content['moodle'] = [];
      }
      if (empty($content['moodle_modname'])) {
        $content['moodle_modname'] = $modname;
      }
      if (empty($content['moodle']['modname'])) {
        $content['moodle']['modname'] = $modname;
      }
    }

    if ($url !== '') {
      if (!isset($content['moodle']) || !is_array($content['moodle'])) {
        $content['moodle'] = [];
      }
      if (empty($content['moodle_url'])) {
        $content['moodle_url'] = $url;
      }
      if (empty($content['moodle']['url'])) {
        $content['moodle']['url'] = $url;
      }
    }

    return $content;
  }

  private static function node_delete(): void {
    $nodeId = (int)($_POST['node_id'] ?? 0);
    if ($nodeId<=0) Response::json(['ok'=>false,'error'=>'node invalido.'], 400);

    // cascades via FK parent -> children
    Db::exec("DELETE FROM app_node WHERE id=:id", ['id'=>$nodeId]);
    Response::json(['ok'=>true]);
  }

  private static function node_reorder(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $payload = (string)($_POST['tree'] ?? '');
    if ($courseId<=0 || $payload==='') Response::json(['ok'=>false,'error'=>'Dados invalidos.'], 400);

    $tree = json_decode($payload, true);
    if (!is_array($tree)) Response::json(['ok'=>false,'error'=>'Tree invalida.'], 400);

    // tree format: [{id, parent_id, sort, depth}, ...]
    Db::tx(function() use ($courseId, $tree) {
      foreach ($tree as $n) {
        $id = (int)($n['id'] ?? 0);
        $pid = isset($n['parent_id']) && $n['parent_id'] !== null ? (int)$n['parent_id'] : null;
        $sort = (int)($n['sort'] ?? 0);
        $depth = (int)($n['depth'] ?? 0);
        if ($id<=0) continue;

        Db::exec("UPDATE app_node SET parent_id=:pid, sort=:s, depth=:d WHERE id=:id AND course_id=:cid", [
          'pid'=>$pid,
          's'=>$sort,
          'd'=>$depth,
          'id'=>$id,
          'cid'=>$courseId,
        ]);
      }

      // rebuild path for all nodes based on parent relations (id path)
      $nodes = Db::all("SELECT id, parent_id FROM app_node WHERE course_id=:cid", ['cid'=>$courseId]);
      $byId = [];
      foreach ($nodes as $n) $byId[(int)$n['id']] = ['id'=>(int)$n['id'], 'parent_id'=>$n['parent_id']? (int)$n['parent_id'] : null];

      $buildPath = function(int $id) use (&$buildPath, &$byId): string {
        $p = $byId[$id]['parent_id'];
        if (!$p) return '/' . $id . '/';
        return rtrim($buildPath($p), '/') . '/' . $id . '/';
      };

      foreach ($byId as $id => $n) {
        $path = $buildPath($id);
        Db::exec("UPDATE app_node SET path=:p WHERE id=:id AND course_id=:cid", ['p'=>$path,'id'=>$id,'cid'=>$courseId]);
      }
    });

    Response::json(['ok'=>true]);
  }


  private static function node_read(): void {
    $nodeId = (int)($_GET['node_id'] ?? 0);
    if ($nodeId <= 0) Response::json(['ok'=>false,'error'=>'node_id invalido.'], 400);

    $node = Db::one("SELECT * FROM app_node WHERE id=:id LIMIT 1", ['id'=>$nodeId]);
    if (!$node) Response::json(['ok'=>false,'error'=>'Item nao encontrado.'], 404);

    Response::json(['ok'=>true,'node'=>$node]);
  }

  private static function enrollment_tracker(): void {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $userId = (int)($_GET['user_id'] ?? 0);

    if ($courseId <= 0 || $userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }

    $course = Db::one("SELECT * FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }
    $sequenceLockEnabled = CoursePolicyService::sequence_lock_enabled($course);

    $moodleUser = \core_user::get_user($userId, 'id,username,firstname,lastname,email,deleted', IGNORE_MISSING);
    if (!$moodleUser || !empty($moodleUser->deleted)) {
      Response::json(['ok' => false, 'error' => 'Aluno nao encontrado.'], 404);
    }

    $nodes = Tree::nodes_for_course($courseId);
    $tree = Tree::build($nodes);
    $linear = Tree::linearize_content($tree['root']);

    $progressRows = Db::all(
      "SELECT id, node_id, status, percent, last_position, seconds_spent, last_seen_at, completed_at
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
        ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC",
      ['uid' => $userId, 'cid' => $courseId]
    );

    $progressByNode = [];
    foreach ($progressRows as $row) {
      $nid = (int)($row['node_id'] ?? 0);
      if ($nid <= 0 || isset($progressByNode[$nid])) {
        continue;
      }
      $progressByNode[$nid] = $row;
    }

    $byId = is_array($tree['byId'] ?? null) ? $tree['byId'] : [];
    $ancestorCache = [];
    $resolveAncestors = static function(int $nodeId) use (&$resolveAncestors, &$ancestorCache, $byId): array {
      if (isset($ancestorCache[$nodeId])) {
        return $ancestorCache[$nodeId];
      }
      $node = $byId[$nodeId] ?? null;
      if (!is_array($node)) {
        $ancestorCache[$nodeId] = [];
        return [];
      }

      $parentId = (int)($node['parent_id'] ?? 0);
      if ($parentId <= 0 || !isset($byId[$parentId])) {
        $ancestorCache[$nodeId] = [];
        return [];
      }

      $ancestors = $resolveAncestors($parentId);
      $ancestors[] = $byId[$parentId];
      $ancestorCache[$nodeId] = $ancestors;
      return $ancestors;
    };

    $moduleStats = [];
    $moduleOrder = [];
    $moduleItemCounters = [];

    $items = [];
    foreach ($linear as $item) {
      if ((int)($item['is_published'] ?? 0) !== 1) {
        continue;
      }

      $nid = (int)($item['id'] ?? 0);
      if ($nid <= 0) {
        continue;
      }

      $progress = $progressByNode[$nid] ?? null;
      $status = (string)($progress['status'] ?? 'not_started');
      if ($status === '') {
        $status = 'not_started';
      }
      $percent = (int)($progress['percent'] ?? 0);
      if ($percent < 0) {
        $percent = 0;
      }
      if ($percent > 100) {
        $percent = 100;
      }
      $completed = $status === 'completed';

      $completedAtRaw = trim((string)($progress['completed_at'] ?? ''));
      $lastSeenRaw = trim((string)($progress['last_seen_at'] ?? ''));
      $completedAtTs = $completedAtRaw !== '' ? (int)(strtotime($completedAtRaw) ?: 0) : 0;
      $lastSeenTs = $lastSeenRaw !== '' ? (int)(strtotime($lastSeenRaw) ?: 0) : 0;
      $countsTowardProgress = CoursePolicyService::node_counts_towards_progress($item);
      $ancestors = $resolveAncestors($nid);
      $trailParts = [];
      $moduleId = 0;
      $moduleTitle = 'Sem modulo';

      foreach ($ancestors as $ancestor) {
        if ((string)($ancestor['kind'] ?? '') !== 'container') {
          continue;
        }

        $ancestorSubtype = (string)($ancestor['subtype'] ?? '');
        if ($ancestorSubtype === 'root') {
          continue;
        }

        $ancestorTitle = trim((string)($ancestor['title'] ?? ''));
        if ($ancestorTitle !== '') {
          $trailParts[] = $ancestorTitle;
        }

        if ($moduleId <= 0 && $ancestorSubtype === 'module') {
          $moduleId = (int)($ancestor['id'] ?? 0);
          if ($ancestorTitle !== '') {
            $moduleTitle = $ancestorTitle;
          }
        }
      }

      if ($moduleId <= 0 && !empty($trailParts)) {
        $moduleTitle = (string)$trailParts[0];
      }

      $moduleKey = $moduleId > 0
        ? ('module:' . $moduleId)
        : ('module:free:' . md5($moduleTitle));

      if (!isset($moduleStats[$moduleKey])) {
        $moduleStats[$moduleKey] = [
          'key' => $moduleKey,
          'id' => $moduleId,
          'title' => $moduleTitle,
          'order' => count($moduleOrder) + 1,
          'total' => 0,
          'completed' => 0,
          'in_progress' => 0,
          'not_started' => 0,
        ];
        $moduleItemCounters[$moduleKey] = 0;
        $moduleOrder[] = $moduleKey;
      }

      $moduleItemCounters[$moduleKey]++;
      if ($countsTowardProgress) {
        $moduleStats[$moduleKey]['total']++;
        if ($completed) {
          $moduleStats[$moduleKey]['completed']++;
        } else if ($status === 'in_progress') {
          $moduleStats[$moduleKey]['in_progress']++;
        } else {
          $moduleStats[$moduleKey]['not_started']++;
        }
      }

      $sequenceLabel = $moduleId > 0
        ? ((string)$moduleStats[$moduleKey]['order'] . '.' . (string)$moduleItemCounters[$moduleKey])
        : (string)(count($items) + 1);

      $trailLabel = implode(' > ', $trailParts);

      $items[] = [
        'id' => $nid,
        'index' => count($items) + 1,
        'sequence_label' => $sequenceLabel,
        'title' => (string)($item['title'] ?? 'Sem titulo'),
        'subtype' => (string)($item['subtype'] ?? ''),
        'module_key' => $moduleKey,
        'module_id' => $moduleId,
        'module_title' => $moduleTitle,
        'trail' => $trailLabel,
        'counts_toward_progress' => $countsTowardProgress,
        'status' => $status,
        'percent' => $percent,
        'completed' => $completed,
        'last_position' => (int)($progress['last_position'] ?? 0),
        'seconds_spent' => (int)($progress['seconds_spent'] ?? 0),
        'completed_at' => $completedAtRaw,
        'completed_at_ts' => $completedAtTs,
        'last_seen_at' => $lastSeenRaw,
        'last_seen_at_ts' => $lastSeenTs,
      ];
    }

    if ($sequenceLockEnabled) {
      $firstIncompleteIndex = null;
      foreach ($items as $idx => $item) {
        if (!CoursePolicyService::node_is_sequential($item)) {
          continue;
        }
        if (!empty($item['completed'])) {
          continue;
        }
        $firstIncompleteIndex = (int)$idx;
        break;
      }
      $maxAllowedIndex = $firstIncompleteIndex === null ? (count($items) - 1) : $firstIncompleteIndex;
      foreach ($items as $idx => &$item) {
        $item['locked_for_student'] = CoursePolicyService::node_is_sequential($item)
          ? ((int)$idx > (int)$maxAllowedIndex)
          : false;
      }
      unset($item);
    } else {
      foreach ($items as &$item) {
        $item['locked_for_student'] = false;
      }
      unset($item);
    }

    $summary = [
      'total' => 0,
      'completed' => 0,
      'in_progress' => 0,
      'not_started' => 0,
      'percent' => 0,
    ];
    foreach ($items as $item) {
      if (empty($item['counts_toward_progress'])) {
        continue;
      }
      $summary['total']++;
      if (!empty($item['completed'])) {
        $summary['completed']++;
      } else if ((string)($item['status'] ?? '') === 'in_progress') {
        $summary['in_progress']++;
      } else {
        $summary['not_started']++;
      }
    }
    if ($summary['total'] > 0) {
      $summary['percent'] = (int)floor(($summary['completed'] * 100) / $summary['total']);
    }

    $finalExamGate = CoursePolicyService::final_exam_gate_preview($course, $userId);
    $finalExamNode = null;
    foreach ($linear as $candidate) {
      if ((int)($candidate['is_published'] ?? 0) !== 1) {
        continue;
      }
      if ((string)($candidate['kind'] ?? '') !== 'action' || (string)($candidate['subtype'] ?? '') !== 'final_exam') {
        continue;
      }
      $finalExamNode = $candidate;
      break;
    }

    $finalExamStatus = [
      'exists' => $finalExamNode !== null,
      'node_id' => (int)($finalExamNode['id'] ?? 0),
      'title' => (string)($finalExamNode['title'] ?? 'Prova final'),
      'app_completed' => false,
      'has_open_attempt' => false,
      'moodle_completed' => false,
      'state_label' => $finalExamGate['blocked'] ? 'Bloqueada' : 'Liberada',
    ];

    if ($finalExamNode !== null) {
      $finalExamNodeId = (int)($finalExamNode['id'] ?? 0);
      $finalExamProgress = $progressByNode[$finalExamNodeId] ?? null;
      $finalExamStatus['app_completed'] = ((string)($finalExamProgress['status'] ?? '') === 'completed');

      $finalExamContent = [];
      if (!empty($finalExamNode['content_json'])) {
        $decodedFinalExamContent = json_decode((string)$finalExamNode['content_json'], true);
        if (is_array($decodedFinalExamContent)) {
          $finalExamContent = $decodedFinalExamContent;
        }
      }
      $finalExamMoodle = is_array($finalExamContent['moodle'] ?? null) ? $finalExamContent['moodle'] : [];
      $finalExamCmid = (int)($finalExamNode['moodle_cmid']
        ?? $finalExamMoodle['cmid']
        ?? $finalExamContent['cmid']
        ?? $finalExamContent['moodle_cmid']
        ?? 0);
      $finalExamQuizId = $finalExamCmid > 0 ? (int)(Moodle::quiz_instance_from_cmid($finalExamCmid) ?? 0) : 0;
      if ($finalExamQuizId > 0) {
        $finalExamStatus['has_open_attempt'] = Moodle::quiz_has_open_attempt($finalExamQuizId, $userId);
        $quizSnapshot = Moodle::quiz_completion_snapshot($finalExamQuizId, $userId, $finalExamCmid);
        $finalExamStatus['moodle_completed'] = !empty($quizSnapshot['completion_met']);
      }

      if ($finalExamStatus['app_completed'] || $finalExamStatus['moodle_completed']) {
        $finalExamStatus['state_label'] = 'Concluida';
      } else if ($finalExamStatus['has_open_attempt']) {
        $finalExamStatus['state_label'] = 'Tentativa em andamento';
      } else if (!empty($finalExamGate['blocked'])) {
        $finalExamStatus['state_label'] = 'Bloqueada';
      }
    }

    $modules = [];
    foreach ($moduleOrder as $moduleKey) {
      if (!isset($moduleStats[$moduleKey])) {
        continue;
      }
      $module = $moduleStats[$moduleKey];
      $moduleTotal = (int)($module['total'] ?? 0);
      $moduleCompleted = (int)($module['completed'] ?? 0);
      $modulePercent = $moduleTotal > 0
        ? (int)floor(($moduleCompleted * 100) / $moduleTotal)
        : 0;
      $modules[] = [
        'key' => (string)$module['key'],
        'id' => (int)$module['id'],
        'title' => (string)$module['title'],
        'order' => (int)$module['order'],
        'total' => $moduleTotal,
        'completed' => $moduleCompleted,
        'in_progress' => (int)($module['in_progress'] ?? 0),
        'not_started' => (int)($module['not_started'] ?? 0),
        'percent' => $modulePercent,
      ];
    }

    Response::json([
      'ok' => true,
      'tracker' => [
        'course' => [
          'id' => (int)$course['id'],
          'title' => (string)$course['title'],
          'status' => (string)$course['status'],
          'enable_sequence_lock' => $sequenceLockEnabled ? 1 : 0,
        ],
        'user' => [
          'id' => (int)$moodleUser->id,
          'name' => fullname($moodleUser),
          'username' => (string)($moodleUser->username ?? ''),
          'email' => (string)($moodleUser->email ?? ''),
        ],
        'summary' => $summary,
        'final_exam_gate' => $finalExamGate,
        'final_exam' => $finalExamStatus,
        'modules' => $modules,
        'items' => $items,
      ],
    ]);
  }

  private static function enrollment_progress_sync(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($courseId <= 0 || $userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }

    $course = Db::one(
      "SELECT id, title, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }
    if ((int)($course['moodle_courseid'] ?? 0) <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso sem mapeamento Moodle para sincronizar.'], 400);
    }

    $moodleUser = \core_user::get_user($userId, 'id,deleted', IGNORE_MISSING);
    if (!$moodleUser || !empty($moodleUser->deleted)) {
      Response::json(['ok' => false, 'error' => 'Aluno nao encontrado.'], 404);
    }

    $syncStats = ProgressSyncService::sync_user_course_from_moodle($courseId, $userId, true);

    $admin = Auth::user();
    error_log(
      '[' . date('c') . '] ADMIN_PROGRESS_SYNC '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' user=' . $userId
      . ' course=' . $courseId
      . ' inserted=' . (int)($syncStats['inserted'] ?? 0)
      . ' updated=' . (int)($syncStats['updated'] ?? 0)
      . ' completed=' . (int)($syncStats['completed'] ?? 0)
      . ' in_progress=' . (int)($syncStats['in_progress'] ?? 0)
    );

    $progressNodeSql = CoursePolicyService::node_progress_sql();
    $progressNodeJoinSql = CoursePolicyService::node_progress_sql('n');

    $totalRow = Db::one(
      "SELECT COUNT(*) AS total
         FROM app_node
        WHERE course_id = :cid
          AND is_published = 1
          AND kind IN ('content', 'action')
          AND {$progressNodeSql}",
      ['cid' => $courseId]
    );

    $progressRow = Db::one(
      "SELECT
         COUNT(DISTINCT CASE WHEN p.status = 'completed' THEN p.node_id ELSE NULL END) AS done,
         MAX(COALESCE(p.last_seen_at, p.completed_at)) AS last_activity
       FROM app_progress p
       JOIN app_node n ON n.id = p.node_id
       WHERE p.course_id = :cid
        AND p.moodle_userid = :uid
        AND n.course_id = p.course_id
        AND n.is_published = 1
        AND n.kind IN ('content', 'action')
        AND {$progressNodeJoinSql}",
      [
        'cid' => $courseId,
        'uid' => $userId,
      ]
    );

    $total = (int)($totalRow['total'] ?? 0);
    $done = (int)($progressRow['done'] ?? 0);
    if ($done > $total && $total > 0) {
      $done = $total;
    }
    $percent = $total > 0 ? (int)floor(($done * 100) / $total) : 0;

    $lastActivityRaw = trim((string)($progressRow['last_activity'] ?? ''));
    $lastActivityTs = $lastActivityRaw !== '' ? (int)(strtotime($lastActivityRaw) ?: 0) : 0;

    Response::json([
      'ok' => true,
      'sync' => $syncStats,
      'progress' => [
        'summary' => [
          'total' => $total,
          'completed' => $done,
          'percent' => $percent,
        ],
        'last_activity' => $lastActivityRaw,
        'last_activity_ts' => $lastActivityTs,
      ],
    ]);
  }

  private static function enrollment_progress_sync_bulk(): void {
    global $DB;

    $courseId = (int)($_POST['course_id'] ?? 0);
    $limit = (int)($_POST['limit'] ?? App::cfg('moodle_progress_sync_bulk_limit', 200));
    if ($courseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso invalido para sincronizacao em lote.'], 400);
    }
    if ($limit <= 0) {
      $limit = 200;
    }
    $limit = max(1, min(1000, $limit));

    $course = Db::one(
      "SELECT id, title, moodle_courseid
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
    if ($moodleCourseId <= 0) {
      Response::json(['ok' => false, 'error' => 'Curso sem mapeamento Moodle para sincronizacao em lote.'], 400);
    }

    $countSql = "SELECT COUNT(DISTINCT ue.userid)
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {user} u ON u.id = ue.userid
                  WHERE e.courseid = :mcid
                    AND e.status = 0
                    AND ue.status = 0
                    AND u.deleted = 0";
    $totalEnrolled = (int)$DB->count_records_sql($countSql, ['mcid' => $moodleCourseId]);

    if ($totalEnrolled <= 0) {
      Response::json([
        'ok' => true,
        'course_id' => $courseId,
        'total_enrolled' => 0,
        'processed_users' => 0,
        'remaining_users' => 0,
        'failed_users' => 0,
        'sync' => [
          'inserted' => 0,
          'updated' => 0,
          'completed' => 0,
          'in_progress' => 0,
          'mapped_nodes' => 0,
          'source_completed' => 0,
          'source_viewed' => 0,
          'throttled' => 0,
        ],
      ]);
    }

    $listSql = "SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid = :mcid
                   AND e.status = 0
                   AND ue.status = 0
                   AND u.deleted = 0
              ORDER BY ue.userid ASC";
    $rows = $DB->get_records_sql($listSql, ['mcid' => $moodleCourseId], 0, $limit);

    $aggregate = [
      'inserted' => 0,
      'updated' => 0,
      'completed' => 0,
      'in_progress' => 0,
      'mapped_nodes' => 0,
      'source_completed' => 0,
      'source_viewed' => 0,
      'throttled' => 0,
    ];
    $processedUsers = 0;
    $failedUsers = 0;

    @set_time_limit(120);

    foreach ($rows as $row) {
      $userId = (int)($row->userid ?? 0);
      if ($userId <= 0) {
        continue;
      }

      try {
        $stats = ProgressSyncService::sync_user_course_from_moodle($courseId, $userId, true);
        $processedUsers++;
      } catch (\Throwable $e) {
        $failedUsers++;
        error_log('[app_v3] Falha em sync bulk Moodle -> APP (course ' . $courseId . ', user ' . $userId . '): ' . $e->getMessage());
        continue;
      }

      foreach ($aggregate as $key => $_) {
        $aggregate[$key] += (int)($stats[$key] ?? 0);
      }
    }

    $remainingUsers = max(0, $totalEnrolled - $processedUsers);

    $admin = Auth::user();
    error_log(
      '[' . date('c') . '] ADMIN_PROGRESS_SYNC_BULK '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' course=' . $courseId
      . ' moodle_course=' . $moodleCourseId
      . ' processed=' . $processedUsers
      . ' failed=' . $failedUsers
      . ' total=' . $totalEnrolled
      . ' limit=' . $limit
      . ' inserted=' . (int)$aggregate['inserted']
      . ' updated=' . (int)$aggregate['updated']
      . ' completed=' . (int)$aggregate['completed']
      . ' in_progress=' . (int)$aggregate['in_progress']
    );

    Response::json([
      'ok' => true,
      'course_id' => $courseId,
      'total_enrolled' => $totalEnrolled,
      'processed_users' => $processedUsers,
      'remaining_users' => $remainingUsers,
      'failed_users' => $failedUsers,
      'limit' => $limit,
      'sync' => $aggregate,
    ]);
  }

  private static function enrollment_progress_set(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $mark = strtolower(trim((string)($_POST['mark'] ?? '')));

    if ($courseId <= 0 || $userId <= 0 || $nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }
    if (!in_array($mark, ['completed', 'pending'], true)) {
      Response::json(['ok' => false, 'error' => 'Acao de marcacao invalida.'], 400);
    }

    $course = Db::one("SELECT id, moodle_courseid FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $node = Db::one(
      "SELECT id, kind, is_published
         FROM app_node
        WHERE id = :id
          AND course_id = :cid
        LIMIT 1",
      ['id' => $nodeId, 'cid' => $courseId]
    );
    if (!$node) {
      Response::json(['ok' => false, 'error' => 'Item nao encontrado no curso.'], 404);
    }
    if ((int)($node['is_published'] ?? 0) !== 1) {
      Response::json(['ok' => false, 'error' => 'Item nao publicado.'], 400);
    }
    $kind = (string)($node['kind'] ?? '');
    if ($kind !== 'content' && $kind !== 'action') {
      Response::json(['ok' => false, 'error' => 'Somente conteudos e acoes podem ser marcados.'], 400);
    }

    $admin = Auth::user();
    $moodleSyncMeta = null;
    try {
      $moodleSyncMeta = Moodle::sync_node_completion_from_app(
        $courseId,
        $nodeId,
        $userId,
        $mark === 'completed',
        isset($admin->id) ? (int)$admin->id : null
      );
    } catch (\Throwable $e) {
      $moodleSyncMeta = [
        'ok' => false,
        'synced' => false,
        'cmid' => 0,
        'reason' => 'exception',
        'message' => $e->getMessage(),
      ];
      error_log('[app_v3] Falha ao sincronizar conclusao APP -> Moodle (set): ' . $e->getMessage());
    }

    if (!self::is_successful_moodle_completion_sync($moodleSyncMeta)) {
      $reason = trim((string)($moodleSyncMeta['reason'] ?? ''));
      Response::json([
        'ok' => false,
        'error' => self::moodle_completion_sync_error_message($reason),
        'moodle_sync' => $moodleSyncMeta,
      ], 409);
    }

    Db::tx(function() use ($courseId, $userId, $nodeId, $mark) {
      $existing = Db::one(
        "SELECT id
           FROM app_progress
          WHERE moodle_userid = :uid
            AND course_id = :cid
            AND node_id = :nid
          ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC
          LIMIT 1",
        [
          'uid' => $userId,
          'cid' => $courseId,
          'nid' => $nodeId,
        ]
      );

      if ($mark === 'completed') {
        if ($existing) {
          Db::exec(
            "UPDATE app_progress
                SET status = 'completed',
                    percent = GREATEST(percent, 100),
                    last_seen_at = NOW(),
                    completed_at = NOW()
              WHERE id = :id",
            ['id' => (int)$existing['id']]
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
        return;
      }

      // pending => remove qualquer vestigio local de progresso deste item
      // (inclusive linhas antigas duplicadas), evitando "em andamento" residual.
      Db::exec(
        "DELETE FROM app_progress
          WHERE moodle_userid = :uid
            AND course_id = :cid
            AND node_id = :nid",
        [
          'uid' => $userId,
          'cid' => $courseId,
          'nid' => $nodeId,
        ]
      );
    });

    $logText = '[' . date('c') . '] ADMIN_PROGRESS_OVERRIDE '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' user=' . $userId
      . ' course=' . $courseId
      . ' node=' . $nodeId
      . ' mark=' . $mark;
    if (is_array($moodleSyncMeta)) {
      $logText .= ' moodle_sync=' . (!empty($moodleSyncMeta['synced']) ? '1' : '0')
        . ' moodle_reason=' . (string)($moodleSyncMeta['reason'] ?? '');
    }
    error_log($logText);

    Response::json([
      'ok' => true,
      'moodle_sync' => $moodleSyncMeta,
    ]);
  }

  private static function enrollment_progress_bulk(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId = (int)($_POST['user_id'] ?? 0);
    $mark = strtolower(trim((string)($_POST['mark'] ?? '')));

    if ($courseId <= 0 || $userId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }
    if (!in_array($mark, ['completed', 'pending'], true)) {
      Response::json(['ok' => false, 'error' => 'Acao de marcacao invalida.'], 400);
    }

    $course = Db::one("SELECT id FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course) {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado.'], 404);
    }

    $nodes = Db::all(
      "SELECT id
         FROM app_node
        WHERE course_id = :cid
          AND is_published = 1
          AND kind IN ('content', 'action')
     ORDER BY sort ASC, id ASC",
      ['cid' => $courseId]
    );
    if (!$nodes) {
      Response::json(['ok' => true, 'changed' => 0]);
    }

    $nodeIds = [];
    foreach ($nodes as $row) {
      $nid = (int)($row['id'] ?? 0);
      if ($nid > 0) {
        $nodeIds[] = $nid;
      }
    }
    if (!$nodeIds) {
      Response::json(['ok' => true, 'changed' => 0]);
    }

    $existingRows = Db::all(
      "SELECT id, node_id
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
     ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC",
      [
        'uid' => $userId,
        'cid' => $courseId,
      ]
    );

    $existingByNode = [];
    foreach ($existingRows as $row) {
      $nid = (int)($row['node_id'] ?? 0);
      if ($nid <= 0 || isset($existingByNode[$nid])) {
        continue;
      }
      $existingByNode[$nid] = (int)($row['id'] ?? 0);
    }

    $admin = Auth::user();
    $moodleSync = [
      'attempted' => 0,
      'synced' => 0,
      'skipped' => 0,
      'errors' => 0,
      'failed_nodes' => [],
      'first_reason' => '',
    ];

    // Sincroniza primeiro no Moodle.
    // Se qualquer item falhar, nao grava no app para manter consistencia imediata.
    foreach ($nodeIds as $nid) {
      $moodleSync['attempted']++;
      try {
        $syncMeta = Moodle::sync_node_completion_from_app(
          $courseId,
          (int)$nid,
          $userId,
          $mark === 'completed',
          isset($admin->id) ? (int)$admin->id : null
        );
        if (self::is_successful_moodle_completion_sync($syncMeta)) {
          $moodleSync['synced']++;
          continue;
        }

        $moodleSync['skipped']++;
        $reason = trim((string)($syncMeta['reason'] ?? ''));
        if ($moodleSync['first_reason'] === '' && $reason !== '') {
          $moodleSync['first_reason'] = $reason;
        }
        $moodleSync['failed_nodes'][] = (int)$nid;
      } catch (\Throwable $e) {
        $moodleSync['errors']++;
        if ($moodleSync['first_reason'] === '') {
          $moodleSync['first_reason'] = 'exception';
        }
        $moodleSync['failed_nodes'][] = (int)$nid;
        error_log('[app_v3] Falha ao sincronizar conclusao APP -> Moodle (bulk): ' . $e->getMessage());
      }
    }

    if ($moodleSync['skipped'] > 0 || $moodleSync['errors'] > 0) {
      $reason = $moodleSync['first_reason'] !== '' ? $moodleSync['first_reason'] : 'sync_failed';
      Response::json([
        'ok' => false,
        'error' => self::moodle_completion_sync_error_message($reason),
        'changed' => 0,
        'moodle_sync' => $moodleSync,
      ], 409);
    }

    $changed = 0;
    Db::tx(function() use ($nodeIds, $existingByNode, $mark, $courseId, $userId, &$changed) {
      foreach ($nodeIds as $nid) {
        $existingId = (int)($existingByNode[$nid] ?? 0);

        if ($mark === 'completed') {
          if ($existingId > 0) {
            Db::exec(
              "UPDATE app_progress
                  SET status = 'completed',
                      percent = GREATEST(percent, 100),
                      last_seen_at = NOW(),
                      completed_at = NOW()
                WHERE id = :id",
              ['id' => $existingId]
            );
          } else {
            Db::exec(
              "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
               VALUES (:uid, :cid, :nid, 'completed', 100, 0, 0, NOW(), NOW())",
              [
                'uid' => $userId,
                'cid' => $courseId,
                'nid' => $nid,
              ]
            );
          }
          $changed++;
          continue;
        }

        // pending => remove qualquer linha local de progresso do item.
        Db::exec(
          "DELETE FROM app_progress
            WHERE moodle_userid = :uid
              AND course_id = :cid
              AND node_id = :nid",
          [
            'uid' => $userId,
            'cid' => $courseId,
            'nid' => $nid,
          ]
        );
        $changed++;
      }
    });

    error_log(
      '[' . date('c') . '] ADMIN_PROGRESS_BULK '
      . 'admin=' . (int)($admin->id ?? 0)
      . ' user=' . $userId
      . ' course=' . $courseId
      . ' mark=' . $mark
      . ' changed=' . $changed
      . ' moodle_attempted=' . (int)$moodleSync['attempted']
      . ' moodle_synced=' . (int)$moodleSync['synced']
      . ' moodle_skipped=' . (int)$moodleSync['skipped']
      . ' moodle_errors=' . (int)$moodleSync['errors']
    );

    Response::json([
      'ok' => true,
      'changed' => $changed,
      'moodle_sync' => $moodleSync,
    ]);
  }

  private static function lesson_session(): void {
    global $CFG;
    require_once($CFG->dirroot . '/mod/lesson/classes/external.php');

    $user = Auth::user();
    $isAdmin = Auth::is_app_admin((int)($user->id ?? 0));
    $courseId = (int)($_POST['course_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $requestedCmid = (int)($_POST['cmid'] ?? 0);
    $lessonPassword = trim((string)($_POST['lesson_password'] ?? ''));
    $autostart = (int)($_POST['autostart'] ?? 0) === 1;

    if (!$user || $courseId <= 0 || $nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }

    try {
      $mapping = self::lesson_resolve_mapping($courseId, $nodeId, $requestedCmid);
      $cmid = (int)$mapping['cmid'];
      $lessonId = (int)$mapping['lessonid'];

      $access = self::quiz_normalize_external_result(
        \mod_lesson_external::get_lesson_access_information($lessonId)
      );
      $entry = self::lesson_build_entry_payload($lessonId, $cmid, $access);

      if (!$autostart) {
        Response::json([
          'ok' => true,
          'entry' => $entry,
        ]);
      }

      $requiresPassword = !empty($entry['requires_password']);
      if ($requiresPassword && $lessonPassword === '') {
        Response::json([
          'ok' => false,
          'code' => 'lesson_preflight_required',
          'error' => 'Esta licao exige senha.',
        ], 403);
      }

      $resumePage = 0;
      if (!empty($entry['left_during_timed_session']) && (int)$entry['last_page_seen'] > 0) {
        $resumePage = (int)$entry['last_page_seen'];
      }

      \mod_lesson_external::view_lesson($lessonId, $lessonPassword);
      self::quiz_normalize_external_result(
        \mod_lesson_external::launch_attempt(
          $lessonId,
          $lessonPassword,
          $resumePage > 0 ? $resumePage : 0,
          false
        )
      );

      $startPage = $resumePage > 0 ? $resumePage : (int)$entry['first_page_id'];
      if ($startPage <= 0) {
        throw new \RuntimeException('Licao sem paginas disponiveis.');
      }

      $payload = self::lesson_page_payload($lessonId, $startPage, $lessonPassword);
      Response::json([
        'ok' => true,
        'entry' => $entry,
        'lesson' => $payload,
      ]);
    } catch (\Throwable $e) {
      error_log(
        '[app_v3][lesson_session] user=' . (int)($user->id ?? 0)
        . ' course=' . $courseId
        . ' node=' . $nodeId
        . ' cmid=' . $requestedCmid
        . ' error=' . $e->getMessage()
      );
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function lesson_page(): void {
    global $CFG;
    require_once($CFG->dirroot . '/mod/lesson/classes/external.php');

    $user = Auth::user();
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $pageId = (int)($_POST['page_id'] ?? 0);
    $lessonPassword = trim((string)($_POST['lesson_password'] ?? ''));
    $data = self::quiz_parse_attempt_data((string)($_POST['data'] ?? ''));

    if (!$user || $lessonId <= 0 || $pageId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos para processar a licao.'], 400);
    }

    try {
      $processed = self::quiz_normalize_external_result(
        \mod_lesson_external::process_page($lessonId, $pageId, $data, $lessonPassword, false)
      );
      $newPageId = (int)($processed['newpageid'] ?? 0);
      $isEol = $newPageId === self::lesson_eol_value();

      $payload = null;
      if (!$isEol && $newPageId > 0) {
        $payload = self::lesson_page_payload($lessonId, $newPageId, $lessonPassword);
      }

      Response::json([
        'ok' => true,
        'finished' => $isEol,
        'process' => [
          'newpageid' => $newPageId,
          'inmediatejump' => !empty($processed['inmediatejump']),
          'nodefaultresponse' => !empty($processed['nodefaultresponse']),
          'attemptsremaining' => array_key_exists('attemptsremaining', $processed) ? (int)$processed['attemptsremaining'] : null,
          'feedback' => trim((string)($processed['feedback'] ?? '')),
          'correctanswer' => !empty($processed['correctanswer']),
          'noanswer' => !empty($processed['noanswer']),
          'maxattemptsreached' => !empty($processed['maxattemptsreached']),
          'reviewmode' => !empty($processed['reviewmode']),
          'messages' => self::lesson_messages_from_external($processed['messages'] ?? []),
        ],
        'lesson' => $payload,
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3][lesson_page] user=' . (int)($user->id ?? 0)
        . ' lesson=' . $lessonId
        . ' page=' . $pageId
        . ' error=' . $e->getMessage());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function lesson_page_view(): void {
    global $CFG;
    require_once($CFG->dirroot . '/mod/lesson/classes/external.php');

    $user = Auth::user();
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $pageId = (int)($_POST['page_id'] ?? 0);
    $lessonPassword = trim((string)($_POST['lesson_password'] ?? ''));

    if (!$user || $lessonId <= 0 || $pageId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos para carregar a pagina da licao.'], 400);
    }

    try {
      $payload = self::lesson_page_payload($lessonId, $pageId, $lessonPassword);
      Response::json([
        'ok' => true,
        'lesson' => $payload,
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3][lesson_page_view] user=' . (int)($user->id ?? 0)
        . ' lesson=' . $lessonId
        . ' page=' . $pageId
        . ' error=' . $e->getMessage());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function lesson_finish(): void {
    global $CFG;
    require_once($CFG->dirroot . '/mod/lesson/classes/external.php');

    $user = Auth::user();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $requestedCmid = (int)($_POST['cmid'] ?? 0);
    $lessonPassword = trim((string)($_POST['lesson_password'] ?? ''));

    if (!$user || $courseId <= 0 || $nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos para finalizar a licao.'], 400);
    }

    try {
      $mapping = self::lesson_resolve_mapping($courseId, $nodeId, $requestedCmid);
      $cmid = (int)$mapping['cmid'];
      $lessonId = (int)$mapping['lessonid'];
      if ($lessonId <= 0) {
        throw new \RuntimeException('Licao nao configurada para este item.');
      }

      $finish = [];
      $finishThrowable = null;
      $snapshotBeforeFinish = Moodle::lesson_completion_snapshot($lessonId, (int)$user->id, $cmid);
      $shouldCallFinishAttempt = empty($snapshotBeforeFinish['completion_met']);
      try {
        if ($shouldCallFinishAttempt) {
          $finish = self::quiz_normalize_external_result(
            \mod_lesson_external::finish_attempt($lessonId, $lessonPassword, false, false)
          );
        }
      } catch (\Throwable $error) {
        $finishThrowable = $error;
        $finish = [
          'messages' => [],
        ];
      }
      $gradeMeta = self::quiz_normalize_external_result(
        \mod_lesson_external::get_user_grade($lessonId, (int)$user->id)
      );

      $lessonSnapshot = Moodle::lesson_completion_snapshot($lessonId, (int)$user->id, $cmid);
      $completionState = (int)($lessonSnapshot['completion_state'] ?? 0);
      $completionMet = !empty($lessonSnapshot['completion_met']);
      if ($finishThrowable instanceof \Throwable && !$completionMet && !self::lesson_finish_exception_is_recoverable($finishThrowable)) {
        throw $finishThrowable;
      }
      $grade = isset($gradeMeta['grade']) && $gradeMeta['grade'] !== null
        ? (float)$gradeMeta['grade']
        : null;

      Response::json([
        'ok' => true,
        'lesson' => [
          'lesson_id' => $lessonId,
          'cmid' => $cmid,
          'messages' => self::lesson_messages_from_external($finish['messages'] ?? []),
          'data' => is_array($finish['data'] ?? null) ? $finish['data'] : [],
          'grade' => $grade,
          'formatted_grade' => (string)($gradeMeta['formattedgrade'] ?? ''),
          'completion' => [
            'met' => $completionMet,
            'completion_state' => $completionState,
            'viewed' => (int)($lessonSnapshot['viewed'] ?? 0),
            'timemodified' => (int)($lessonSnapshot['timemodified'] ?? 0),
            'timer_completed' => !empty($lessonSnapshot['timer_completed']),
            'grade_completed' => !empty($lessonSnapshot['grade_completed']),
            'completion_source' => (string)($lessonSnapshot['completion_source'] ?? 'none'),
            'grade' => $grade !== null ? $grade : ($lessonSnapshot['grade'] ?? null),
          ],
        ],
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3][lesson_finish] user=' . (int)($user->id ?? 0)
        . ' course=' . $courseId
        . ' node=' . $nodeId
        . ' cmid=' . $requestedCmid
        . ' lesson=' . $lessonId
        . ' error=' . $e->getMessage());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function lesson_resolve_mapping(int $courseId, int $nodeId, int $requestedCmid = 0): array {
    $node = Db::one(
      "SELECT id, course_id, is_published, moodle_cmid, moodle_modname, content_json
         FROM app_node
        WHERE id = :id
        LIMIT 1",
      ['id' => $nodeId]
    );
    if (!$node) {
      throw new \RuntimeException('Item nao encontrado.');
    }
    if ((int)($node['course_id'] ?? 0) !== $courseId) {
      throw new \RuntimeException('Item nao pertence ao curso informado.');
    }
    if ((int)($node['is_published'] ?? 0) !== 1) {
      throw new \RuntimeException('Item indisponivel.');
    }

    $content = json_decode((string)($node['content_json'] ?? ''), true);
    if (!is_array($content)) {
      $content = [];
    }

    $cmid = (int)($node['moodle_cmid'] ?? 0);
    if ($cmid <= 0) {
      $cmid = self::extract_cmid_from_content($content);
    }
    if ($cmid <= 0) {
      $cmid = $requestedCmid;
    }
    if ($cmid <= 0) {
      throw new \RuntimeException('Licao sem CMID configurado.');
    }

    $modname = strtolower(trim((string)($node['moodle_modname'] ?? $content['moodle']['modname'] ?? $content['moodle_modname'] ?? '')));
    $cmModname = strtolower(trim((string)Moodle::cm_modname($cmid)));
    if ($cmModname !== '') {
      $modname = $cmModname;
    }
    if ($modname !== 'lesson') {
      throw new \RuntimeException('Este item nao esta mapeado como licao no Moodle.');
    }

    $lessonId = (int)(Moodle::lesson_instance_from_cmid($cmid) ?? 0);
    if ($lessonId <= 0) {
      throw new \RuntimeException('CMID informado nao pertence a uma licao valida.');
    }

    return [
      'node' => $node,
      'cmid' => $cmid,
      'lessonid' => $lessonId,
    ];
  }

  private static function lesson_build_entry_payload(int $lessonId, int $cmid, array $access): array {
    $prevent = [];
    $rawReasons = $access['preventaccessreasons'] ?? [];
    if (is_array($rawReasons)) {
      foreach ($rawReasons as $row) {
        if (is_array($row)) {
          $msg = trim((string)($row['message'] ?? ''));
          if ($msg !== '') {
            $prevent[] = $msg;
          }
        }
      }
    }

    $requiresPassword = false;
    foreach ($rawReasons as $row) {
      if (!is_array($row)) {
        continue;
      }
      $reason = trim((string)($row['reason'] ?? ''));
      if ($reason === 'passwordprotectedlesson') {
        $requiresPassword = true;
        break;
      }
    }

    return [
      'lesson_id' => $lessonId,
      'cmid' => $cmid,
      'attempts_count' => (int)($access['attemptscount'] ?? 0),
      'first_page_id' => (int)($access['firstpageid'] ?? 0),
      'last_page_seen' => (int)($access['lastpageseen'] ?? 0),
      'left_during_timed_session' => !empty($access['leftduringtimedsession']),
      'review_mode' => !empty($access['reviewmode']),
      'prevent_reasons' => $prevent,
      'requires_password' => $requiresPassword,
    ];
  }

  private static function lesson_page_payload(int $lessonId, int $pageId, string $password = ''): array {
    global $CFG;
    require_once($CFG->dirroot . '/mod/lesson/classes/external.php');

    $pageData = self::quiz_normalize_external_result(
      \mod_lesson_external::get_page_data($lessonId, $pageId, $password, false, true)
    );

    $newPageId = (int)($pageData['newpageid'] ?? 0);
    $isEol = $newPageId === self::lesson_eol_value();
    $page = is_array($pageData['page'] ?? null) ? $pageData['page'] : [];
    $answers = is_array($pageData['answers'] ?? null) ? $pageData['answers'] : [];
    $html = trim((string)($pageData['pagecontent'] ?? ''));
    if ($html === '') {
      $html = (string)($page['contents'] ?? '');
    }

    return [
      'lesson_id' => $lessonId,
      'page_id' => $newPageId,
      'is_eol' => $isEol,
      'page_title' => trim((string)($page['title'] ?? '')),
      'page_type' => trim((string)($page['typestring'] ?? '')),
      'prev_page_id' => (int)($page['prevpageid'] ?? 0),
      'next_page_id' => (int)($page['nextpageid'] ?? 0),
      'answer_count' => count($answers),
      'page_html' => $html,
      'progress' => max(0, min(100, (int)($pageData['progress'] ?? 0))),
      'ongoing_score' => trim((string)($pageData['ongoingscore'] ?? '')),
      'displaymenu' => !empty($pageData['displaymenu']),
      'messages' => self::lesson_messages_from_external($pageData['messages'] ?? []),
    ];
  }

  private static function lesson_messages_from_external($messages): array {
    $out = [];
    if (!is_array($messages)) {
      return $out;
    }
    foreach ($messages as $row) {
      if (is_array($row)) {
        $msg = trim((string)($row['message'] ?? ''));
      } else if (is_object($row)) {
        $msg = trim((string)($row->message ?? ''));
      } else {
        $msg = '';
      }
      if ($msg !== '') {
        $out[] = $msg;
      }
    }
    return $out;
  }

  private static function lesson_finish_exception_is_recoverable(\Throwable $error): bool {
    $message = strtolower(trim((string)$error->getMessage()));
    if ($message === '') {
      return false;
    }

    $patterns = [
      'attempt is closed',
      'attempt is finished',
      'no ongoing attempt',
      'already completed',
      'attemptalreadyclosed',
      'attemptalreadyfinished',
      'attemptsremaining',
      'undefined property: stdclass::$attemptsremaining',
      'erro ao ler a base de dados',
      'database read error',
      'tentativa ja finalizada',
      'tentativa já finalizada',
      'tentativa nao iniciada',
      'tentativa não iniciada',
      'nao ha tentativa em andamento',
      'não há tentativa em andamento',
    ];

    foreach ($patterns as $pattern) {
      if (strpos($message, $pattern) !== false) {
        return true;
      }
    }

    return false;
  }

  private static function lesson_eol_value(): int {
    if (defined('LESSON_EOL')) {
      return (int)LESSON_EOL;
    }
    return -9;
  }

  private static function lesson_status(): void {
    $user = Auth::user();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $requestedCmid = (int)($_POST['cmid'] ?? 0);

    if (!$user || $courseId <= 0 || $nodeId <= 0) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }

    $node = Db::one(
      "SELECT id, course_id, is_published, moodle_cmid, moodle_modname, content_json
         FROM app_node
        WHERE id = :id
        LIMIT 1",
      ['id' => $nodeId]
    );
    if (!$node) {
      Response::json(['ok' => false, 'error' => 'Item nao encontrado.'], 404);
    }
    if ((int)($node['course_id'] ?? 0) !== $courseId) {
      Response::json(['ok' => false, 'error' => 'Item nao pertence ao curso informado.'], 400);
    }
    if ((int)($node['is_published'] ?? 0) !== 1) {
      Response::json(['ok' => false, 'error' => 'Item indisponivel.'], 403);
    }

    $content = json_decode((string)($node['content_json'] ?? ''), true);
    if (!is_array($content)) {
      $content = [];
    }

    $cmid = (int)($node['moodle_cmid'] ?? 0);
    if ($cmid <= 0) {
      $cmid = self::extract_cmid_from_content($content);
    }
    if ($cmid <= 0) {
      $cmid = $requestedCmid;
    }
    if ($cmid <= 0) {
      Response::json(['ok' => false, 'error' => 'Licao sem CMID configurado.'], 400);
    }

    $modname = strtolower(trim((string)($node['moodle_modname'] ?? $content['moodle']['modname'] ?? $content['moodle_modname'] ?? '')));
    $cmModname = strtolower(trim((string)Moodle::cm_modname($cmid)));
    if ($cmModname !== '') {
      $modname = $cmModname;
    }
    if ($modname !== 'lesson') {
      Response::json(['ok' => false, 'error' => 'Este item nao esta mapeado como licao no Moodle.'], 400);
    }

    $sync = ProgressSyncService::sync_user_course_from_moodle($courseId, (int)$user->id, true);
    $snapshot = Moodle::lesson_completion_snapshot((int)(Moodle::lesson_instance_from_cmid($cmid) ?? 0), (int)$user->id, $cmid);
    $completionState = (int)($snapshot['completion_state'] ?? 0);
    $viewed = (int)($snapshot['viewed'] ?? 0);
    $completionMet = !empty($snapshot['completion_met']);

    $existing = Db::one(
      "SELECT id, status, percent, completed_at
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
          AND node_id = :nid
        ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC
        LIMIT 1",
      [
        'uid' => (int)$user->id,
        'cid' => $courseId,
        'nid' => $nodeId,
      ]
    );

    if ($completionMet) {
      $whenTs = (int)($snapshot['timemodified'] ?? 0);
      if ($whenTs <= 0) {
        $whenTs = time();
      }
      $when = date('Y-m-d H:i:s', $whenTs);

      if ($existing) {
        Db::exec(
          "UPDATE app_progress
              SET status = 'completed',
                  percent = GREATEST(percent, 100),
                  last_seen_at = :when_seen,
                  completed_at = COALESCE(completed_at, :when_done)
            WHERE id = :id",
          [
            'when_seen' => $when,
            'when_done' => $when,
            'id' => (int)$existing['id'],
          ]
        );
      } else {
        Db::exec(
          "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
           VALUES (:uid, :cid, :nid, 'completed', 100, 0, 0, :when_seen, :when_done)",
          [
            'uid' => (int)$user->id,
            'cid' => $courseId,
            'nid' => $nodeId,
            'when_seen' => $when,
            'when_done' => $when,
          ]
        );
      }
    } else if ($viewed > 0) {
      $whenTs = (int)($snapshot['timemodified'] ?? 0);
      if ($whenTs <= 0) {
        $whenTs = time();
      }
      $when = date('Y-m-d H:i:s', $whenTs);
      if ($existing) {
        $status = (string)($existing['status'] ?? 'not_started');
        if ($status !== 'completed') {
          Db::exec(
            "UPDATE app_progress
                SET status = CASE WHEN status = 'not_started' THEN 'in_progress' ELSE status END,
                    percent = GREATEST(percent, 5),
                    last_seen_at = :when_seen
              WHERE id = :id",
            [
              'when_seen' => $when,
              'id' => (int)$existing['id'],
            ]
          );
        }
      } else {
        Db::exec(
          "INSERT INTO app_progress (moodle_userid, course_id, node_id, status, percent, seconds_spent, last_position, last_seen_at, completed_at)
           VALUES (:uid, :cid, :nid, 'in_progress', 5, 0, 0, :when_seen, NULL)",
          [
            'uid' => (int)$user->id,
            'cid' => $courseId,
            'nid' => $nodeId,
            'when_seen' => $when,
          ]
        );
      }
    }

    $progress = Db::one(
      "SELECT status, percent, last_seen_at, completed_at
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
          AND node_id = :nid
        ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC
        LIMIT 1",
      [
        'uid' => (int)$user->id,
        'cid' => $courseId,
        'nid' => $nodeId,
      ]
    );

    $status = (string)($progress['status'] ?? 'not_started');
    if ($status === '') {
      $status = 'not_started';
    }
    $percent = max(0, min(100, (int)($progress['percent'] ?? 0)));
    $completed = $status === 'completed';

    Response::json([
      'ok' => true,
      'status' => $status,
      'completed' => $completed,
      'percent' => $percent,
      'cmid' => $cmid,
      'completion_state' => $completionState,
      'viewed' => $viewed,
      'completion_source' => (string)($snapshot['completion_source'] ?? 'none'),
      'timer_completed' => !empty($snapshot['timer_completed']),
      'grade_completed' => !empty($snapshot['grade_completed']),
      'last_seen_at' => (string)($progress['last_seen_at'] ?? ''),
      'completed_at' => (string)($progress['completed_at'] ?? ''),
      'sync' => $sync,
    ]);
  }

  private static function quiz_session(): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quiz/classes/external.php');

    $user = Auth::user();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $requestedCmid = (int)($_POST['cmid'] ?? 0);
    $quizPassword = trim((string)($_POST['quiz_password'] ?? ''));
    $autostart = (int)($_POST['autostart'] ?? 0) === 1;
    $preflightData = self::quiz_build_preflight_data($quizPassword);
    $cmid = self::quiz_resolve_cmid(
      $requestedCmid,
      $courseId,
      $nodeId
    );

    if (!$user || $cmid <= 0) {
      error_log('[app_v3][quiz_session] quiz_not_configured user=' . (int)($user->id ?? 0)
        . ' course=' . $courseId
        . ' node=' . $nodeId
        . ' requested_cmid=' . $requestedCmid
        . ' resolved_cmid=' . $cmid);
      Response::json(['ok' => false, 'error' => 'Quiz nao configurado para este item.'], 400);
    }

    $quizId = Moodle::quiz_instance_from_cmid($cmid);
    if ($quizId <= 0 && $requestedCmid > 0) {
      // Fallback: em alguns fluxos o operador informa quizid em vez de CMID.
      $cmidFromQuizId = (int)(Moodle::quiz_cmid_from_quiz_instance($requestedCmid) ?? 0);
      if ($cmidFromQuizId > 0) {
        $quizId = $requestedCmid;
        $cmid = $cmidFromQuizId;
      }
    }

    if ($quizId <= 0) {
      error_log('[app_v3][quiz_session] invalid_quiz_mapping user=' . (int)($user->id ?? 0)
        . ' course=' . $courseId
        . ' node=' . $nodeId
        . ' requested_cmid=' . $requestedCmid
        . ' resolved_cmid=' . $cmid);
      Response::json(['ok' => false, 'error' => 'CMID informado nao pertence a um quiz valido.'], 400);
    }

    try {
      $attemptId = 0;
      $resumePage = 0;
      $attemptsTotal = 0;
      $finishedCount = 0;
      $unfinishedCount = 0;
      $unfinishedAttemptId = 0;
      $lastFinishedAttemptId = 0;
      $appFinalExamCompleted = false;
      $finalExamGate = [
        'enabled' => false,
        'blocked' => false,
        'unlocked' => true,
        'hours' => 0,
        'first_access_ts' => 0,
        'unlock_ts' => 0,
        'remaining_seconds' => 0,
      ];
      $isFinalExamNode = false;

      if ($courseId > 0 && $nodeId > 0) {
        $nodeMeta = Db::one(
          "SELECT kind, subtype
             FROM app_node
            WHERE id = :node_id
              AND course_id = :course_id
            LIMIT 1",
          [
            'node_id' => $nodeId,
            'course_id' => $courseId,
          ]
        );
        $isFinalExamNode = $nodeMeta
          && (string)($nodeMeta['kind'] ?? '') === 'action'
          && (string)($nodeMeta['subtype'] ?? '') === 'final_exam';

        if ($isFinalExamNode && !$isAdmin) {
          $appProgressRow = Db::one(
            "SELECT status
               FROM app_progress
              WHERE course_id = :course_id
                AND node_id = :node_id
                AND moodle_userid = :user_id
              ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC
              LIMIT 1",
            [
              'course_id' => $courseId,
              'node_id' => $nodeId,
              'user_id' => (int)$user->id,
            ]
          );
          $appFinalExamCompleted = $appProgressRow
            && (string)($appProgressRow['status'] ?? '') === 'completed';

          $courseMeta = Db::one(
            "SELECT id, moodle_courseid, final_exam_unlock_hours
               FROM app_course
              WHERE id = :id
              LIMIT 1",
            ['id' => $courseId]
          );
          if ($courseMeta) {
            $finalExamGate = CoursePolicyService::final_exam_gate_snapshot($courseMeta, (int)$user->id, true);
          }
        }
      }

      $access = self::quiz_normalize_external_result(
        \mod_quiz_external::get_attempt_access_information($quizId, 0)
      );
      if (!is_array($access)) {
        $access = [];
      }
      $requiresPreflight = !empty($access['ispreflightcheckrequired']);
      if ($autostart && $requiresPreflight && $quizPassword === '') {
        error_log('[app_v3][quiz_session] preflight_required user=' . (int)($user->id ?? 0)
          . ' course=' . $courseId
          . ' node=' . $nodeId
          . ' cmid=' . $cmid
          . ' quizid=' . $quizId);
        Response::json([
          'ok' => false,
          'code' => 'quiz_preflight_required',
          'error' => 'Este questionario exige senha antes de iniciar.',
        ], 400);
      }

      $attemptRows = $DB->get_records(
        'quiz_attempts',
        [
          'quiz' => $quizId,
          'userid' => (int)$user->id,
        ],
        'attempt DESC, id DESC',
        'id, attempt, state, currentpage, sumgrades, timefinish'
      );
      if ($attemptRows) {
        $attemptsTotal = count($attemptRows);
        foreach ($attemptRows as $attemptMeta) {
          $state = strtolower(trim((string)($attemptMeta->state ?? '')));
          if ($state === 'finished') {
            $finishedCount++;
            if ($lastFinishedAttemptId <= 0) {
              $lastFinishedAttemptId = (int)($attemptMeta->id ?? 0);
            }
          } else if ($state === 'inprogress' || $state === 'overdue') {
            $unfinishedCount++;
            if ($unfinishedAttemptId <= 0) {
              $unfinishedAttemptId = (int)($attemptMeta->id ?? 0);
              $attemptId = $unfinishedAttemptId;
              $resumePage = max(0, (int)($attemptMeta->currentpage ?? 0));
            }
          }
        }
      }

      $quizMeta = self::quiz_load_meta($quizId);
      $snapshot = Moodle::quiz_completion_snapshot($quizId, (int)$user->id, $cmid);
      $lastFinishedAttemptGrade = $lastFinishedAttemptId > 0
        ? self::quiz_attempt_grade_value($lastFinishedAttemptId)
        : null;
      $maxAttempts = (int)($quizMeta['attempts'] ?? 0);
      $remainingAttempts = $maxAttempts > 0 ? max(0, $maxAttempts - $finishedCount) : null;
      $preventReasons = [];
      if (!empty($access['preventnewattemptreasons']) && is_array($access['preventnewattemptreasons'])) {
        foreach ($access['preventnewattemptreasons'] as $reason) {
          $reason = trim((string)$reason);
          if ($reason !== '') {
            $preventReasons[] = $reason;
          }
        }
      }

      $entry = [
        'quiz_id' => $quizId,
        'quiz_name' => (string)($quizMeta['name'] ?? ''),
        'attempts_total' => $attemptsTotal,
        'finished_count' => $finishedCount,
        'unfinished_count' => $unfinishedCount,
        'has_unfinished' => $unfinishedAttemptId > 0,
        'unfinished_attempt_id' => $unfinishedAttemptId,
        'last_finished_attempt_id' => $lastFinishedAttemptId,
        'max_attempts' => $maxAttempts,
        'remaining_attempts' => $remainingAttempts,
        'requires_preflight' => $requiresPreflight,
        'grade' => $lastFinishedAttemptGrade,
        'overall_grade' => $snapshot['grade'] ?? null,
        'pass_required' => !empty($snapshot['pass_required']),
        'pass_grade' => $snapshot['pass_grade'] ?? null,
        'completion_met' => !empty($snapshot['completion_met']),
        'can_start' => $unfinishedAttemptId <= 0 && empty($access['isfinished']),
        'prevent_reasons' => $preventReasons,
        'final_exam_gate' => $finalExamGate,
      ];

      if ($isFinalExamNode && !$isAdmin && !empty($finalExamGate['enabled'])) {
        $shouldBypassGate = $unfinishedAttemptId > 0 || !empty($snapshot['completion_met']) || $appFinalExamCompleted;
        if ($shouldBypassGate) {
          $finalExamGate['blocked'] = false;
          $finalExamGate['unlocked'] = true;
          $finalExamGate['remaining_seconds'] = 0;
          $entry['final_exam_gate'] = $finalExamGate;
        } else if (!empty($finalExamGate['blocked'])) {
          $gateMessage = self::final_exam_gate_message($finalExamGate);
          $entry['can_start'] = false;
          array_unshift($preventReasons, $gateMessage);
          $entry['prevent_reasons'] = array_values(array_unique(array_filter($preventReasons)));
        }
      }

      if (!$autostart) {
        Response::json([
          'ok' => true,
          'entry' => $entry,
          'quiz' => null,
        ]);
      }

      if ($attemptId <= 0) {
        if ($isFinalExamNode && !$isAdmin && !empty($finalExamGate['blocked'])) {
          $gateMessage = self::final_exam_gate_message($finalExamGate);
          Response::json([
            'ok' => false,
            'code' => 'final_exam_locked',
            'error' => $gateMessage,
            'entry' => $entry,
          ], 400);
        }

        if (!empty($access['isfinished'])) {
          $warning = !empty($preventReasons) ? $preventReasons[0] : 'Nao ha novas tentativas disponiveis para este questionario.';
          Response::json([
            'ok' => false,
            'code' => 'quiz_start_blocked',
            'error' => $warning,
            'entry' => $entry,
          ], 400);
        }

        $started = null;
        try {
          $started = self::quiz_normalize_external_result(
            \mod_quiz_external::start_attempt($quizId, $preflightData, false)
          );
          $attemptId = (int)($started['attempt']['id'] ?? 0);
        } catch (\Throwable $startError) {
          $resumeAttemptId = self::quiz_find_open_attempt_id($quizId, (int)$user->id);
          if ($resumeAttemptId > 0 && self::quiz_exception_is_attempt_in_progress($startError)) {
            $attemptId = $resumeAttemptId;
            $resumePage = self::quiz_find_attempt_current_page($attemptId, (int)$user->id);
            error_log('[app_v3][quiz_session] resumed_open_attempt user=' . (int)($user->id ?? 0)
              . ' course=' . $courseId
              . ' node=' . $nodeId
              . ' cmid=' . $cmid
              . ' quizid=' . $quizId
              . ' attempt=' . $attemptId);
          } else {
            throw $startError;
          }
        }
        if ($attemptId <= 0) {
          $warning = '';
          if (is_array($started) && !empty($started['warnings'][0]['message'])) {
            $warning = (string)$started['warnings'][0]['message'];
          }
          if ($warning === '' && !empty($access['preventnewattemptreasons']) && is_array($access['preventnewattemptreasons'])) {
            $reasons = [];
            foreach ($access['preventnewattemptreasons'] as $reason) {
              $reason = trim((string)$reason);
              if ($reason !== '') {
                $reasons[] = $reason;
              }
            }
            if (!empty($reasons)) {
              $warning = implode(' ', $reasons);
            }
          }
          if ($warning === '') {
            $warning = 'Nao foi possivel iniciar a tentativa do quiz.';
          }
          error_log('[app_v3][quiz_session] start_attempt_failed user=' . (int)($user->id ?? 0)
            . ' course=' . $courseId
            . ' node=' . $nodeId
            . ' cmid=' . $cmid
            . ' quizid=' . $quizId
            . ' warning=' . $warning);
          Response::json([
            'ok' => false,
            'code' => 'quiz_start_failed',
            'error' => $warning,
          ], 400);
        }
      }

      $payload = self::quiz_attempt_payload($quizId, $attemptId, max(0, $resumePage), $preflightData);
      $payload['cmid'] = $cmid;
      $payload['course_id'] = $courseId;
      $payload['node_id'] = $nodeId;

      Response::json([
        'ok' => true,
        'entry' => $entry,
        'quiz' => $payload,
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3][quiz_session] user=' . (int)($user->id ?? 0)
        . ' course=' . $courseId
        . ' node=' . $nodeId
        . ' cmid=' . $cmid
        . ' error=' . $e->getMessage()
        . ' at ' . $e->getFile() . ':' . $e->getLine());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function quiz_page(): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quiz/classes/external.php');

    $user = Auth::user();
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $page = (int)($_POST['page'] ?? 0);
    $quizPassword = trim((string)($_POST['quiz_password'] ?? ''));
    $preflightData = self::quiz_build_preflight_data($quizPassword);
    if ($page < 0) $page = 0;

    if (!$user || $attemptId <= 0) {
      Response::json(['ok' => false, 'error' => 'Tentativa invalida.'], 400);
    }

    $attempt = $DB->get_record(
      'quiz_attempts',
      ['id' => $attemptId, 'userid' => (int)$user->id],
      'id,quiz,state',
      IGNORE_MISSING
    );
    if (!$attempt) {
      Response::json(['ok' => false, 'error' => 'Tentativa nao encontrada.'], 404);
    }

    $quizId = (int)($attempt->quiz ?? 0);
    if ($quizId <= 0) {
      Response::json(['ok' => false, 'error' => 'Quiz invalido para esta tentativa.'], 400);
    }

    try {
      $data = self::quiz_parse_attempt_data((string)($_POST['data'] ?? ''));
      if (!empty($data)) {
        self::quiz_normalize_external_result(
          \mod_quiz_external::process_attempt($attemptId, $data, false, false, $preflightData)
        );
      }

      $payload = self::quiz_attempt_payload($quizId, $attemptId, $page, $preflightData);
      Response::json([
        'ok' => true,
        'quiz' => $payload,
      ]);
    } catch (\Throwable $e) {
      error_log('[app_v3][quiz_page] user=' . (int)($user->id ?? 0)
        . ' attempt=' . $attemptId
        . ' page=' . $page
        . ' error=' . $e->getMessage()
        . ' at ' . $e->getFile() . ':' . $e->getLine());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function quiz_finish(): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quiz/classes/external.php');

    $user = Auth::user();
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $nodeId = (int)($_POST['node_id'] ?? 0);
    $requestedCmid = (int)($_POST['cmid'] ?? 0);
    $quizPassword = trim((string)($_POST['quiz_password'] ?? ''));
    $preflightData = self::quiz_build_preflight_data($quizPassword);
    if (!$user || $attemptId <= 0) {
      Response::json(['ok' => false, 'error' => 'Tentativa invalida.'], 400);
    }

    $attempt = $DB->get_record(
      'quiz_attempts',
      ['id' => $attemptId, 'userid' => (int)$user->id],
      'id,quiz,state,sumgrades,timefinish',
      IGNORE_MISSING
    );
    if (!$attempt) {
      Response::json(['ok' => false, 'error' => 'Tentativa nao encontrada.'], 404);
    }

    $quizId = (int)($attempt->quiz ?? 0);
    if ($quizId <= 0) {
      Response::json(['ok' => false, 'error' => 'Quiz invalido para esta tentativa.'], 400);
    }

    try {
      $data = self::quiz_parse_attempt_data((string)($_POST['data'] ?? ''));
      $result = [];
      $finishThrowable = null;
      try {
        $result = self::quiz_normalize_external_result(
          \mod_quiz_external::process_attempt($attemptId, $data, true, false, $preflightData)
        );
      } catch (\Throwable $processError) {
        $finishThrowable = $processError;
        $result = [];
      }

      $attemptAfter = $DB->get_record(
        'quiz_attempts',
        ['id' => $attemptId, 'userid' => (int)$user->id],
        'id,quiz,state,sumgrades,timefinish',
        IGNORE_MISSING
      );
      $attemptFinished = $attemptAfter && strtolower(trim((string)($attemptAfter->state ?? ''))) === 'finished';
      if ($finishThrowable instanceof \Throwable && !$attemptFinished) {
        throw $finishThrowable;
      }
      if ($finishThrowable instanceof \Throwable && $attemptFinished) {
        error_log('[app_v3][quiz_finish] recovered_after_error user=' . (int)($user->id ?? 0)
          . ' attempt=' . $attemptId
          . ' state=' . (string)($attemptAfter->state ?? '')
          . ' warning=' . $finishThrowable->getMessage());
      }
      $state = (string)($result['state'] ?? ($attemptAfter->state ?? 'inprogress'));
      $attemptGrade = self::quiz_attempt_grade_value($attemptId);
      $resolvedCmid = self::quiz_resolve_cmid($requestedCmid, $courseId, $nodeId);
      if ($resolvedCmid <= 0) {
        $resolvedCmid = (int)(Moodle::quiz_cmid_from_quiz_instance($quizId) ?? 0);
      }
      $snapshot = Moodle::quiz_completion_snapshot($quizId, (int)$user->id, $resolvedCmid);

      try {
        $accessAfterFinish = self::quiz_normalize_external_result(
          \mod_quiz_external::get_attempt_access_information($quizId, 0)
        );
      } catch (\Throwable $accessError) {
        $accessAfterFinish = [];
        error_log('[app_v3][quiz_finish] access_info_failed user=' . (int)($user->id ?? 0)
          . ' attempt=' . $attemptId
          . ' warning=' . $accessError->getMessage());
      }
      if (!is_array($accessAfterFinish)) {
        $accessAfterFinish = [];
      }

      $retryReasons = [];
      if (!empty($accessAfterFinish['preventnewattemptreasons']) && is_array($accessAfterFinish['preventnewattemptreasons'])) {
        foreach ($accessAfterFinish['preventnewattemptreasons'] as $reason) {
          $reason = trim((string)$reason);
          if ($reason !== '') {
            $retryReasons[] = $reason;
          }
        }
      }
      $canRetry = empty($snapshot['completion_met'])
        && empty($accessAfterFinish['isfinished'])
        && empty($retryReasons);

      Response::json([
        'ok' => true,
        'quiz' => [
          'attempt_id' => $attemptId,
          'quiz_id' => $quizId,
          'cmid' => $resolvedCmid,
          'state' => $state,
          'timefinish' => (int)($attemptAfter->timefinish ?? 0),
          'sumgrades' => isset($attemptAfter->sumgrades) ? (float)$attemptAfter->sumgrades : null,
          'attempt_grade' => $attemptGrade,
          'grade' => $snapshot['grade'],
          'overall_grade' => $snapshot['grade'],
          'completion' => [
            'met' => !empty($snapshot['completion_met']),
            'pass_required' => !empty($snapshot['pass_required']),
            'pass_grade' => $snapshot['pass_grade'],
            'attempt_grade' => $attemptGrade,
            'grade' => $snapshot['grade'],
            'overall_grade' => $snapshot['grade'],
            'has_finished_attempt' => !empty($snapshot['has_finished_attempt']),
            'can_retry' => $canRetry,
            'retry_reasons' => $retryReasons,
          ],
          'warnings' => $result['warnings'] ?? [],
        ],
      ]);
    } catch (\Throwable $e) {
      $debugDetails = self::exception_debug_details($e);
      error_log('[app_v3][quiz_finish] user=' . (int)($user->id ?? 0)
        . ' attempt=' . $attemptId
        . ' error=' . $e->getMessage()
        . ($debugDetails !== '' ? ' details=' . $debugDetails : '')
        . ' at ' . $e->getFile() . ':' . $e->getLine());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function quiz_review(): void {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quiz/classes/external.php');

    $user = Auth::user();
    $attemptId = (int)($_POST['attempt_id'] ?? 0);
    $page = isset($_POST['page']) ? (int)$_POST['page'] : -1;

    if (!$user || $attemptId <= 0) {
      Response::json(['ok' => false, 'error' => 'Tentativa invalida.'], 400);
    }

    $attempt = $DB->get_record(
      'quiz_attempts',
      ['id' => $attemptId, 'userid' => (int)$user->id],
      'id,state,quiz',
      IGNORE_MISSING
    );
    if (!$attempt) {
      Response::json(['ok' => false, 'error' => 'Tentativa nao encontrada.'], 404);
    }

    try {
      $review = self::quiz_normalize_external_result(
        \mod_quiz_external::get_attempt_review($attemptId, $page)
      );
      if (!is_array($review)) {
        $review = [];
      }

      Response::json([
        'ok' => true,
        'review_available' => true,
        'review' => $review,
      ]);
    } catch (\Throwable $e) {
      if (self::quiz_exception_is_review_blocked($e)) {
        $quizId = (int)($attempt->quiz ?? 0);
        $grade = $quizId > 0 ? Moodle::quiz_last_grade($quizId, (int)$user->id) : null;
        Response::json([
          'ok' => true,
          'review_available' => false,
          'message' => 'A revisao desta tentativa nao esta liberada pelas regras do questionario.',
          'review' => [
            'questions' => [],
            'additionaldata' => [],
            'grade' => $grade,
          ],
        ]);
      }

      error_log('[app_v3][quiz_review] user=' . (int)($user->id ?? 0)
        . ' attempt=' . $attemptId
        . ' error=' . $e->getMessage()
        . ' at ' . $e->getFile() . ':' . $e->getLine());
      Response::json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
  }

  private static function quiz_load_meta(int $quizId): array {
    global $DB;
    if ($quizId <= 0) {
      return ['id' => 0, 'name' => '', 'attempts' => 0];
    }

    try {
      $quiz = $DB->get_record('quiz', ['id' => $quizId], 'id,name,attempts', IGNORE_MISSING);
      if (!$quiz) {
        return ['id' => $quizId, 'name' => '', 'attempts' => 0];
      }

      return [
        'id' => (int)($quiz->id ?? $quizId),
        'name' => (string)($quiz->name ?? ''),
        'attempts' => (int)($quiz->attempts ?? 0),
      ];
    } catch (\Throwable $e) {
      return ['id' => $quizId, 'name' => '', 'attempts' => 0];
    }
  }

  private static function quiz_attempt_payload(int $quizId, int $attemptId, int $page, array $preflightData = []): array {
    $access = self::quiz_normalize_external_result(
      \mod_quiz_external::get_attempt_access_information($quizId, $attemptId)
    );
    if (!is_array($access)) {
      $access = [];
    }
    $attemptData = self::quiz_normalize_external_result(
      \mod_quiz_external::get_attempt_data($attemptId, $page, $preflightData)
    );
    if (!is_array($attemptData)) {
      $attemptData = [];
    }
    $attempt = $attemptData['attempt'] ?? [];
    if (!is_array($attempt)) {
      $attempt = self::quiz_normalize_external_result($attempt);
      if (!is_array($attempt)) {
        $attempt = [];
      }
    }
    $layout = (string)($attempt['layout'] ?? '');

    return [
      'attempt_id' => $attemptId,
      'quiz_id' => $quizId,
      'attempt' => $attempt,
      'page' => (int)$page,
      'currentpage' => (int)($attempt['currentpage'] ?? $page),
      'nextpage' => (int)($attemptData['nextpage'] ?? -1),
      'totalpages' => self::quiz_total_pages_from_layout($layout),
      'questions' => $attemptData['questions'] ?? [],
      'messages' => $attemptData['messages'] ?? [],
      'warnings' => $attemptData['warnings'] ?? [],
      'endtime' => (int)($access['endtime'] ?? 0),
      'isfinished' => !empty($access['isfinished']),
      'ispreflightcheckrequired' => !empty($access['ispreflightcheckrequired']),
      'preventnewattemptreasons' => $access['preventnewattemptreasons'] ?? [],
    ];
  }

  private static function quiz_total_pages_from_layout(string $layout): int {
    $layout = trim($layout);
    if ($layout === '') {
      return 1;
    }

    $tokens = array_filter(array_map('trim', explode(',', $layout)), static function($value) {
      return $value !== '';
    });
    if (!$tokens) {
      return 1;
    }

    $pageBreaks = 0;
    foreach ($tokens as $token) {
      if ((int)$token === 0) {
        $pageBreaks++;
      }
    }

    return max(1, $pageBreaks);
  }

  private static function quiz_normalize_external_result($value) {
    if (is_object($value)) {
      $value = get_object_vars($value);
    }

    if (!is_array($value)) {
      return $value;
    }

    foreach ($value as $key => $item) {
      if (is_object($item) || is_array($item)) {
        $value[$key] = self::quiz_normalize_external_result($item);
      }
    }

    return $value;
  }

  private static function quiz_attempt_grade_value(int $attemptId): ?float {
    global $CFG, $DB;

    if ($attemptId <= 0) {
      return null;
    }

    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    $row = $DB->get_record_sql(
      "SELECT qa.sumgrades, q.id AS quizid, q.grade, q.sumgrades AS quizsumgrades, q.decimalpoints
         FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
        WHERE qa.id = :attemptid",
      ['attemptid' => $attemptId],
      IGNORE_MISSING
    );
    if (!$row || !isset($row->sumgrades) || $row->sumgrades === null) {
      return null;
    }

    $quiz = (object)[
      'id' => (int)($row->quizid ?? 0),
      'grade' => isset($row->grade) ? (float)$row->grade : 0.0,
      'sumgrades' => isset($row->quizsumgrades) ? (float)$row->quizsumgrades : 0.0,
      'decimalpoints' => isset($row->decimalpoints) ? (int)$row->decimalpoints : 2,
    ];

    return (float)quiz_rescale_grade((float)$row->sumgrades, $quiz, false);
  }

  private static function quiz_parse_attempt_data(string $rawJson): array {
    $rawJson = trim($rawJson);
    if ($rawJson === '') {
      return [];
    }

    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
      return [];
    }

    $out = [];
    foreach ($decoded as $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = trim((string)($item['name'] ?? ''));
      if ($name === '') {
        continue;
      }
      $value = (string)($item['value'] ?? '');
      $out[] = [
        'name' => $name,
        'value' => $value,
      ];
    }
    return $out;
  }

  private static function quiz_exception_is_attempt_in_progress(\Throwable $error): bool {
    if (method_exists($error, 'getErrorCode')) {
      try {
        $code = (string)$error->getErrorCode();
        if ($code === 'attemptstillinprogress') {
          return true;
        }
      } catch (\Throwable $ignored) {
        // Fallback pelo texto da mensagem abaixo.
      }
    }

    $message = strtolower(trim((string)$error->getMessage()));
    if ($message === '') {
      return false;
    }

    return strpos($message, 'tentativa em andamento') !== false
      || strpos($message, 'attempt still in progress') !== false
      || strpos($message, 'attemptstillinprogress') !== false;
  }

  private static function quiz_exception_is_review_blocked(\Throwable $error): bool {
    $code = '';
    if (method_exists($error, 'getErrorCode')) {
      try {
        $code = strtolower(trim((string)$error->getErrorCode()));
      } catch (\Throwable $ignored) {
        $code = '';
      }
    }
    if (in_array($code, ['noreview', 'noreviewattempt'], true)) {
      return true;
    }

    $message = strtolower(trim((string)$error->getMessage()));
    if ($message === '') {
      return false;
    }

    return strpos($message, 'noreview') !== false
      || strpos($message, 'revis') !== false
      || strpos($message, 'review is not allowed') !== false;
  }

  private static function exception_debug_details(\Throwable $error): string {
    $parts = [
      'class=' . get_class($error),
    ];

    if (property_exists($error, 'error') && isset($error->error) && $error->error !== '') {
      $parts[] = 'db_error=' . trim((string)$error->error);
    }
    if (property_exists($error, 'errorcode') && isset($error->errorcode) && $error->errorcode !== '') {
      $parts[] = 'errorcode=' . trim((string)$error->errorcode);
    }
    if (property_exists($error, 'sql') && isset($error->sql) && $error->sql !== '') {
      $sql = preg_replace('/\s+/', ' ', trim((string)$error->sql));
      $parts[] = 'sql=' . $sql;
    }
    if (property_exists($error, 'params') && isset($error->params)) {
      $encoded = json_encode($error->params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (is_string($encoded) && $encoded !== '') {
        $parts[] = 'params=' . $encoded;
      }
    }
    if (property_exists($error, 'debuginfo') && isset($error->debuginfo) && $error->debuginfo !== '') {
      $debug = preg_replace('/\s+/', ' ', trim((string)$error->debuginfo));
      $parts[] = 'debuginfo=' . $debug;
    }

    return implode(' | ', $parts);
  }

  private static function quiz_find_open_attempt_id(int $quizId, int $userId): int {
    if ($quizId <= 0 || $userId <= 0) {
      return 0;
    }

    global $DB;
    try {
      $attempt = $DB->get_record_sql(
        "SELECT id
           FROM {quiz_attempts}
          WHERE quiz = :quizid
            AND userid = :userid
            AND state IN ('inprogress', 'overdue')
       ORDER BY attempt DESC, id DESC",
        [
          'quizid' => $quizId,
          'userid' => $userId,
        ],
        IGNORE_MULTIPLE
      );
      return (int)($attempt->id ?? 0);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  private static function quiz_find_attempt_current_page(int $attemptId, int $userId): int {
    if ($attemptId <= 0 || $userId <= 0) {
      return 0;
    }

    global $DB;
    try {
      $attempt = $DB->get_record(
        'quiz_attempts',
        ['id' => $attemptId, 'userid' => $userId],
        'id,currentpage',
        IGNORE_MISSING
      );
      if (!$attempt) {
        return 0;
      }
      return max(0, (int)($attempt->currentpage ?? 0));
    } catch (\Throwable $e) {
      return 0;
    }
  }

  private static function quiz_build_preflight_data(string $quizPassword): array {
    $quizPassword = trim($quizPassword);
    if ($quizPassword === '') {
      return [];
    }

    return [
      [
        'name' => 'quizpassword',
        'value' => $quizPassword,
      ],
    ];
  }

  private static function quiz_extract_cmid_from_url(string $url): int {
    $url = trim($url);
    if ($url === '') {
      return 0;
    }

    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    if ($path === '' || stripos($path, '/mod/quiz/') === false) {
      return 0;
    }

    $query = (string)(parse_url($url, PHP_URL_QUERY) ?? '');
    if ($query === '') {
      return 0;
    }

    parse_str($query, $params);
    $cmid = (int)($params['id'] ?? 0);
    return $cmid > 0 ? $cmid : 0;
  }

  private static function quiz_extract_quizid_from_content(array $content): int {
    $quizId = (int)($content['moodle']['quizid'] ?? 0);
    if ($quizId > 0) {
      return $quizId;
    }

    $quizId = (int)($content['quizid'] ?? 0);
    if ($quizId > 0) {
      return $quizId;
    }

    $quizId = (int)($content['quiz_id'] ?? 0);
    return $quizId > 0 ? $quizId : 0;
  }

  private static function quiz_validate_cmid(int $cmid): int {
    if ($cmid <= 0) {
      return 0;
    }

    try {
      $quizId = (int)(Moodle::quiz_instance_from_cmid($cmid) ?? 0);
      return $quizId > 0 ? $cmid : 0;
    } catch (\Throwable $e) {
      return 0;
    }
  }

  private static function quiz_resolve_cmid(int $cmid, int $courseId, int $nodeId): int {
    if ($cmid > 0) {
      $validRequested = self::quiz_validate_cmid($cmid);
      if ($validRequested > 0) {
        return $validRequested;
      }
    }

    if ($courseId > 0 && $nodeId > 0) {
      $node = Db::one(
        "SELECT moodle_cmid, content_json
           FROM app_node
          WHERE id = :id
            AND course_id = :cid
          LIMIT 1",
        [
          'id' => $nodeId,
          'cid' => $courseId,
        ]
      );
      if ($node) {
        $mappedCmid = (int)($node['moodle_cmid'] ?? 0);
        if ($mappedCmid > 0) {
          $validMapped = self::quiz_validate_cmid($mappedCmid);
          if ($validMapped > 0) {
            return $validMapped;
          }
        }

        $content = json_decode((string)($node['content_json'] ?? ''), true);
        if (is_array($content)) {
          $contentCmid = self::extract_cmid_from_content($content);
          $contentCmid = self::quiz_validate_cmid($contentCmid);
          if ($contentCmid <= 0) {
            $urlCandidates = [
              (string)($content['moodle']['url'] ?? ''),
              (string)($content['moodle_url'] ?? ''),
              (string)($content['url'] ?? ''),
              (string)($content['source_url'] ?? ''),
            ];
            foreach ($urlCandidates as $urlCandidate) {
              $contentCmid = self::quiz_extract_cmid_from_url($urlCandidate);
              $contentCmid = self::quiz_validate_cmid($contentCmid);
              if ($contentCmid > 0) {
                break;
              }
            }
          }
          if ($contentCmid <= 0) {
            $quizIdFromContent = self::quiz_extract_quizid_from_content($content);
            if ($quizIdFromContent > 0) {
              $contentCmid = (int)(Moodle::quiz_cmid_from_quiz_instance($quizIdFromContent) ?? 0);
              $contentCmid = self::quiz_validate_cmid($contentCmid);
            }
          }
          if ($contentCmid > 0) {
            return $contentCmid;
          }
        }
      }
    }

    if ($courseId > 0) {
      $exam = Db::one(
        "SELECT quiz_cmid
           FROM app_course_exam
          WHERE course_id = :cid
          LIMIT 1",
        ['cid' => $courseId]
      );
      $examCmid = (int)($exam['quiz_cmid'] ?? 0);
      if ($examCmid > 0) {
        $validExam = self::quiz_validate_cmid($examCmid);
        if ($validExam > 0) {
          return $validExam;
        }
      }
    }

    return 0;
  }

  private static function course_biometric_verify(): void {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $photoData = trim((string)($_POST['photo_data'] ?? ''));
    $returnUrlRaw = trim((string)($_POST['return_url'] ?? ''));
    $user = Auth::user();
    if ($courseId <= 0 || !$user) {
      Response::json(['ok' => false, 'error' => 'Parametros invalidos.'], 400);
    }
    $defaultRedirect = App::base_url('/course/' . $courseId);
    $redirect = $defaultRedirect;
    if ($returnUrlRaw !== '') {
      $expectedPrefix = App::base_url('/course/' . $courseId);
      if (strpos($returnUrlRaw, $expectedPrefix) === 0) {
        $redirect = $returnUrlRaw;
      }
    }

    $course = Db::one("SELECT * FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course || (string)($course['status'] ?? '') !== 'published') {
      Response::json(['ok' => false, 'error' => 'Curso nao encontrado ou indisponivel.'], 404);
    }

    $requireBiometric = CoursePolicyService::biometric_required($course);
    $isAdmin = Auth::is_app_admin((int)$user->id);
    if (!$requireBiometric || $isAdmin) {
      CoursePolicyService::mark_biometric_verified($courseId, (int)$user->id);
      Response::json(['ok' => true, 'redirect' => $redirect]);
    }

    $mcid = (int)($course['moodle_courseid'] ?? 0);
    if ($mcid > 0) {
      $window = Moodle::enrol_window((int)$user->id, $mcid);
      if (!$window || !Moodle::is_enrolled_in_course($mcid, (int)$user->id)) {
        Response::json(['ok' => false, 'error' => 'Matricula Moodle nao encontrada para este curso.'], 403);
      }

      $now = time();
      $tstart = (int)($window['timestart'] ?? 0);
      $tend = (int)($window['timeend'] ?? 0);
      if ($tstart > 0 && $tstart > $now) {
        Response::json(['ok' => false, 'error' => 'Acesso ainda nao liberado para este curso.'], 403);
      }
      if ($tend > 0 && $now > $tend) {
        Response::json(['ok' => false, 'error' => 'Acesso expirado para este curso.'], 403);
      }
    }

    if ($photoData === '') {
      Response::json(['ok' => false, 'error' => 'Envie a foto da biometria para validar o acesso.'], 400);
    }

    $photoData = self::normalize_biometric_payload($photoData);
    $binary = base64_decode($photoData, true);
    if ($binary === false || strlen($binary) < 512) {
      Response::json(['ok' => false, 'error' => 'Imagem invalida. Tente capturar novamente.'], 400);
    }
    if (strlen($binary) > (5 * 1024 * 1024)) {
      Response::json(['ok' => false, 'error' => 'Imagem acima do limite de 5MB.'], 400);
    }

    $providerBinary = self::compact_biometric_binary($binary, [
      'max_width' => max(640, (int)App::cfg('biometric_provider_max_width', 720)),
      'max_height' => max(760, (int)App::cfg('biometric_provider_max_height', 960)),
      'max_bytes' => max(96 * 1024, (int)App::cfg('biometric_provider_max_bytes', 180 * 1024)),
      'qualities' => [88, 84, 80, 76, 72, 68],
    ]);
    if ($providerBinary === '' || strlen($providerBinary) < 512) {
      Response::json(['ok' => false, 'error' => 'Falha ao preparar a selfie para validacao. Capture novamente.'], 400);
    }

    $compactBinary = self::compact_biometric_binary($binary, [
      'max_width' => max(240, (int)App::cfg('biometric_max_width', 360)),
      'max_height' => max(280, (int)App::cfg('biometric_max_height', 480)),
      'max_bytes' => max(12 * 1024, (int)App::cfg('biometric_max_compact_bytes', 32 * 1024)),
      'qualities' => [42, 36, 32, 28, 24, 20],
    ]);
    if ($compactBinary === '' || strlen($compactBinary) < 256) {
      Response::json(['ok' => false, 'error' => 'Imagem invalida apos compactacao.'], 400);
    }

    $maxCompactBytes = (int)App::cfg('biometric_max_compact_bytes', 32 * 1024);
    if ($maxCompactBytes < 12 * 1024) {
      $maxCompactBytes = 12 * 1024;
    }

    // Garante registro pequeno no banco para nao inflar storage.
    if (strlen($compactBinary) > $maxCompactBytes) {
      Response::json(['ok' => false, 'error' => 'Imagem acima do limite compacto. Capture novamente.'], 400);
    }

    $providerPhotoB64 = base64_encode($providerBinary);
    if ($providerPhotoB64 === false || $providerPhotoB64 === '') {
      Response::json(['ok' => false, 'error' => 'Falha ao processar selfie para validacao.'], 500);
    }

    $photoB64 = base64_encode($compactBinary);
    if ($photoB64 === false || $photoB64 === '') {
      Response::json(['ok' => false, 'error' => 'Falha ao processar imagem biometrica.'], 500);
    }

    $providerResult = BiometricProviderService::verify_for_user((int)$user->id, $providerPhotoB64);
    $remoteIp = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (strlen($userAgent) > 255) {
      $userAgent = substr($userAgent, 0, 255);
    }

    self::insert_biometric_audit([
      'course_id' => $courseId,
      'moodle_userid' => (int)$user->id,
      'photo_b64' => $photoB64,
      'photo_size_bytes' => strlen($compactBinary),
      'status' => (string)($providerResult['status'] ?? 'approved'),
      'provider_name' => (string)($providerResult['provider'] ?? ''),
      'provider_operation' => (string)($providerResult['operation'] ?? ''),
      'provider_external_id' => (string)($providerResult['external_id'] ?? ''),
      'provider_score' => $providerResult['score'] ?? null,
      'provider_http_status' => (int)($providerResult['http_status'] ?? 0),
      'provider_message' => (string)($providerResult['message'] ?? ''),
      'provider_response_json' => (string)($providerResult['response_json'] ?? ''),
      'ip_address' => $remoteIp,
      'user_agent' => $userAgent,
    ]);

    if (!$providerResult['approved']) {
      error_log(
        '[app_v3][biometric] rejected'
        . ' user=' . (int)$user->id
        . ' course=' . $courseId
        . ' provider=' . (string)($providerResult['provider'] ?? '')
        . ' operation=' . (string)($providerResult['operation'] ?? '')
        . ' http=' . (int)($providerResult['http_status'] ?? 0)
        . ' message=' . trim((string)($providerResult['message'] ?? ''))
      );
      $statusCode = ((string)($providerResult['status'] ?? '') === 'error') ? 502 : 422;
      $message = trim((string)($providerResult['message'] ?? ''));
      if ($message === '') {
        $message = 'Foto rejeitada na validacao biometrica. Capture novamente.';
      }
      Response::json(['ok' => false, 'error' => $message], $statusCode);
    }

    if ((string)($providerResult['provider'] ?? '') !== '') {
      error_log(
        '[app_v3][biometric] approved'
        . ' user=' . (int)$user->id
        . ' course=' . $courseId
        . ' provider=' . (string)($providerResult['provider'] ?? '')
        . ' operation=' . (string)($providerResult['operation'] ?? '')
        . ' http=' . (int)($providerResult['http_status'] ?? 0)
      );
    }

    CoursePolicyService::mark_biometric_verified($courseId, (int)$user->id);

    Response::json([
      'ok' => true,
      'redirect' => $redirect,
    ]);
  }

  private static function normalize_biometric_payload(string $photoData): string {
    $photoData = trim($photoData);
    if (preg_match('#^data:image/[^;]+;base64,#i', $photoData) === 1) {
      $photoData = (string)preg_replace('#^data:image/[^;]+;base64,#i', '', $photoData);
    }
    return str_replace(' ', '+', $photoData);
  }

  private static function format_bytes(int $bytes): string {
    if ($bytes <= 0) {
      return '-';
    }
    if ($bytes < 1024) {
      return $bytes . ' B';
    }
    return number_format($bytes / 1024, 1, ',', '.') . ' KB';
  }

  private static function compact_biometric_binary(string $binary, array $options = []): string {
    if ($binary === '') {
      return '';
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
      return $binary;
    }

    $source = @imagecreatefromstring($binary);
    if (!is_resource($source) && !(is_object($source) && get_class($source) === 'GdImage')) {
      return $binary;
    }

    $sourceWidth = (int)imagesx($source);
    $sourceHeight = (int)imagesy($source);
    if ($sourceWidth <= 0 || $sourceHeight <= 0) {
      imagedestroy($source);
      return $binary;
    }

    $maxWidth = max(64, (int)($options['max_width'] ?? App::cfg('biometric_max_width', 360)));
    $maxHeight = max(64, (int)($options['max_height'] ?? App::cfg('biometric_max_height', 480)));

    $scale = min(1.0, min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight));
    $targetWidth = max(1, (int)floor($sourceWidth * $scale));
    $targetHeight = max(1, (int)floor($sourceHeight * $scale));

    $target = $source;
    if ($targetWidth !== $sourceWidth || $targetHeight !== $sourceHeight) {
      $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
      if ($canvas !== false) {
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        $target = $canvas;
      }
    }

    $candidates = $options['qualities'] ?? [38, 32, 26, 22, 18];
    if (!is_array($candidates) || $candidates === []) {
      $candidates = [38, 32, 26, 22, 18];
    }
    $best = '';
    $bestBytes = 0;
    $desiredMaxBytes = max(12 * 1024, (int)($options['max_bytes'] ?? App::cfg('biometric_max_compact_bytes', 32 * 1024)));

    foreach ($candidates as $quality) {
      ob_start();
      $ok = imagejpeg($target, null, (int)$quality);
      $jpeg = (string)ob_get_clean();
      if (!$ok || $jpeg === '') {
        continue;
      }

      $bytes = strlen($jpeg);
      if ($best === '' || $bytes < $bestBytes) {
        $best = $jpeg;
        $bestBytes = $bytes;
      }

      if ($bytes <= $desiredMaxBytes) {
        $best = $jpeg;
        $bestBytes = $bytes;
        break;
      }
    }

    if ($target !== $source && (is_resource($target) || (is_object($target) && get_class($target) === 'GdImage'))) {
      imagedestroy($target);
    }
    imagedestroy($source);

    if ($best !== '') {
      return $best;
    }

    return $binary;
  }

  private static function insert_biometric_audit(array $payload): void {
    try {
      $providerMessage = trim((string)($payload['provider_message'] ?? ''));
      if ($providerMessage !== '' && function_exists('mb_substr')) {
        $providerMessage = mb_substr($providerMessage, 0, 255, 'UTF-8');
      } else if ($providerMessage !== '') {
        $providerMessage = substr($providerMessage, 0, 255);
      }

      $providerResponseJson = (string)($payload['provider_response_json'] ?? '');
      if ($providerResponseJson !== '' && function_exists('mb_substr')) {
        $providerResponseJson = mb_substr($providerResponseJson, 0, 64000, 'UTF-8');
      } else if ($providerResponseJson !== '') {
        $providerResponseJson = substr($providerResponseJson, 0, 64000);
      }

      $providerScore = array_key_exists('provider_score', $payload) && $payload['provider_score'] !== null
        ? (float)$payload['provider_score']
        : null;

      $params = [
        'course_id' => (int)($payload['course_id'] ?? 0),
        'user_id' => (int)($payload['moodle_userid'] ?? 0),
        'photo_b64' => (string)($payload['photo_b64'] ?? ''),
        'photo_size_bytes' => (int)($payload['photo_size_bytes'] ?? 0),
        'status' => (string)($payload['status'] ?? 'approved'),
        'ip' => ($payload['ip_address'] ?? '') !== '' ? (string)$payload['ip_address'] : null,
        'ua' => ($payload['user_agent'] ?? '') !== '' ? (string)$payload['user_agent'] : null,
      ];

      if (self::biometric_provider_columns_available()) {
        $params['provider_name'] = ($payload['provider_name'] ?? '') !== '' ? (string)$payload['provider_name'] : null;
        $params['provider_operation'] = ($payload['provider_operation'] ?? '') !== '' ? (string)$payload['provider_operation'] : null;
        $params['provider_external_id'] = ($payload['provider_external_id'] ?? '') !== '' ? (string)$payload['provider_external_id'] : null;
        $params['provider_score'] = $providerScore;
        $params['provider_http_status'] = (int)($payload['provider_http_status'] ?? 0) > 0 ? (int)$payload['provider_http_status'] : null;
        $params['provider_message'] = $providerMessage !== '' ? $providerMessage : null;
        $params['provider_response_json'] = $providerResponseJson !== '' ? $providerResponseJson : null;

        Db::exec(
          "INSERT INTO app_course_biometric_audit (
             course_id,
             moodle_userid,
             photo_path,
             photo_b64,
             photo_size_bytes,
             provider_name,
             provider_operation,
             provider_external_id,
             provider_score,
             provider_http_status,
             provider_message,
             provider_response_json,
             status,
             ip_address,
             user_agent
           ) VALUES (
             :course_id,
             :user_id,
             NULL,
             :photo_b64,
             :photo_size_bytes,
             :provider_name,
             :provider_operation,
             :provider_external_id,
             :provider_score,
             :provider_http_status,
             :provider_message,
             :provider_response_json,
             :status,
             :ip,
             :ua
           )",
          $params
        );
      } else {
        Db::exec(
          "INSERT INTO app_course_biometric_audit (
             course_id,
             moodle_userid,
             photo_path,
             photo_b64,
             photo_size_bytes,
             status,
             ip_address,
             user_agent
           ) VALUES (
             :course_id,
             :user_id,
             NULL,
             :photo_b64,
             :photo_size_bytes,
             :status,
             :ip,
             :ua
           )",
          $params
        );
      }
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha ao registrar auditoria biometrica: ' . $e->getMessage());
    }
  }

  private static function biometric_provider_columns_available(): bool {
    if (self::$biometricProviderColumnsAvailable !== null) {
      return self::$biometricProviderColumnsAvailable;
    }

    self::$biometricProviderColumnsAvailable = self::check_biometric_provider_columns();
    if (!self::$biometricProviderColumnsAvailable) {
      try {
        \App\Schema::ensure_biometric_provider_columns();
      } catch (\Throwable $exception) {
        error_log('[app_v3] Falha ao garantir colunas extras da auditoria biometrica: ' . $exception->getMessage());
      }

      self::$biometricProviderColumnsAvailable = self::check_biometric_provider_columns();
    }

    return self::$biometricProviderColumnsAvailable;
  }

  private static function check_biometric_provider_columns(): bool {
    try {
      $row = Db::one(
        "SELECT COUNT(*) AS total
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'app_course_biometric_audit'
            AND COLUMN_NAME IN (
              'provider_name',
              'provider_operation',
              'provider_external_id',
              'provider_score',
              'provider_http_status',
              'provider_message',
              'provider_response_json'
            )"
      );
      return (int)($row['total'] ?? 0) >= 7;
    } catch (\Throwable $exception) {
      return false;
    }
  }

  private static function delete_course_storage_dir(int $courseId): void {
    if ($courseId <= 0) {
      return;
    }

    $base = str_replace('\\', '/', rtrim((string)APP_DIR, '/'));
    $root = $base . '/storage/courses/';
    $target = $root . $courseId;
    $normalizedTarget = str_replace('\\', '/', $target);

    if (!\str_starts_with($normalizedTarget, $root)) {
      return;
    }

    if (!is_dir($target)) {
      return;
    }

    self::delete_dir_recursive($target);
  }

  private static function delete_dir_recursive(string $dir): void {
    $items = @scandir($dir);
    if (!is_array($items)) {
      return;
    }

    foreach ($items as $item) {
      if ($item === '.' || $item === '..') {
        continue;
      }

      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_dir($path) && !is_link($path)) {
        self::delete_dir_recursive($path);
      } else {
        @unlink($path);
      }
    }

    @rmdir($dir);
  }

  private static function upload(): void {
    // Upload de PDF/arquivos para /app/storage/courses/{courseid}/
    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId <= 0) Response::json(['ok'=>false,'error'=>'course invalido.'], 400);

    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
      Response::json(['ok'=>false,'error'=>'Arquivo nao enviado.'], 400);
    }

    $max = (int)App::cfg('upload_max_bytes', 50*1024*1024);
    $file = $_FILES['file'];

    if ((int)$file['size'] > $max) Response::json(['ok'=>false,'error'=>'Arquivo acima do limite.'], 400);

    $name = (string)$file['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf','mp4','webm','jpg','jpeg','png','zip','doc','docx','ppt','pptx','xls','xlsx','txt'];
    if (!in_array($ext, $allowed, true)) {
      Response::json(['ok'=>false,'error'=>'Extensao nao permitida.'], 400);
    }

    $dir = APP_DIR . '/storage/courses/' . $courseId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $name);
    $fname = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe;
    $dest = $dir . '/' . $fname;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      Response::json(['ok'=>false,'error'=>'Falha ao salvar.'], 500);
    }

    $url = App::base_url('/storage/courses/' . $courseId . '/' . rawurlencode($fname));
    Response::json(['ok'=>true,'url'=>$url,'path'=>'/storage/courses/'.$courseId.'/'.$fname,'name'=>$safe]);
  }
}





