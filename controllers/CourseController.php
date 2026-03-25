<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\CoursePolicyService;
use App\Db;
use App\Moodle;
use App\Response;
use App\Tree;
use App\UserCourseBlockService;

final class CourseController {

  public static function view(int $courseId): void {
    Auth::require_login();
    $u = Auth::user();

    $course = Db::one("SELECT * FROM app_course WHERE id = :id LIMIT 1", ['id' => $courseId]);
    if (!$course || $course['status'] !== 'published') {
      throw new \RuntimeException('Curso nao encontrado ou indisponivel.');
    }

    $sequenceLockEnabled = CoursePolicyService::sequence_lock_enabled($course);
    $biometricRequired = CoursePolicyService::biometric_required($course);
    $course['enable_sequence_lock'] = $sequenceLockEnabled ? 1 : 0;
    $course['require_biometric'] = $biometricRequired ? 1 : 0;
    $nodeId = (int)($_GET['node'] ?? 0);

    $mcid = (int)($course['moodle_courseid'] ?? 0);
    $isAdmin = Auth::is_app_admin((int)$u->id);
    if ($mcid > 0) {
      $snapshot = Auth::course_access_snapshot($courseId);
      if (!$isAdmin) {
        if (!is_array($snapshot) || (int)($snapshot['has_window'] ?? 0) !== 1) {
          App::flash_set('error', 'Nao foi possivel validar sua matricula localmente. Entre novamente no sistema.');
          Response::redirect(App::base_url('/dashboard'));
        }

        $isCurrentlyEnrolled = (int)($snapshot['is_enrolled'] ?? 0) === 1;
        if (!$isCurrentlyEnrolled) {
          throw new \RuntimeException('Voce nao esta matriculado neste curso.');
        }

        $now = time();
        $tstart = (int)($snapshot['timestart'] ?? 0);
        $tend = (int)($snapshot['timeend'] ?? 0);

        if ($tstart > 0 && $tstart > $now) {
          App::flash_set('error', 'Seu acesso a este curso inicia em ' . date('d/m/Y', $tstart) . '.');
          Response::redirect(App::base_url('/dashboard'));
        }

        if ($tend > 0 && $now > $tend) {
          App::flash_set('error', 'Seu acesso a este curso expirou em ' . date('d/m/Y', $tend) . '.');
          Response::redirect(App::base_url('/dashboard'));
        }
      }
    } else {
      if (!$isAdmin) {
        throw new \RuntimeException('Curso nao liberado (sem mapeamento no Moodle).');
      }
    }

    if (!$isAdmin) {
      $block = UserCourseBlockService::active_for_user($courseId, (int)$u->id);
      if ($block) {
        App::flash_set(
          'error',
          UserCourseBlockService::title_for_row($block) . ': ' . UserCourseBlockService::message_for_row($block)
        );
        Response::redirect(App::base_url('/dashboard'));
      }
    }

    $courseAccessGate = [
      'enabled' => false,
      'blocked' => false,
      'unlocked' => true,
      'hours' => 0,
      'first_access_ts' => 0,
      'unlock_ts' => 0,
      'remaining_seconds' => 0,
    ];
    if (!$isAdmin) {
      $courseAccessGate = CoursePolicyService::register_course_access($course, (int)$u->id);
      if ($mcid > 0) {
        try {
          Moodle::sync_course_access($mcid, (int)$u->id, true);
        } catch (\Throwable $e) {
          error_log('[app_v3] Falha ao sincronizar acesso do curso no entrypoint: ' . $e->getMessage());
        }
      }
    }

    if (!$isAdmin && $biometricRequired && !CoursePolicyService::is_biometric_verified($courseId, (int)$u->id)) {
      $returnUrl = App::base_url('/course/' . $courseId);
      if ($nodeId > 0) {
        $returnUrl .= '?node=' . $nodeId;
      }

      Response::html(App::render('estudo/biometric_gate.php', [
        'user' => $u,
        'course' => $course,
        'return_url' => $returnUrl,
        'flash' => App::flash_get(),
        'csrf' => \App\Csrf::token(),
      ]));
      return;
    }

    $nodes = array_values(array_filter(
      Tree::nodes_for_course($courseId),
      static function (array $node): bool {
        return !CoursePolicyService::node_is_library_only($node);
      }
    ));
    $tree = Tree::build($nodes);

    $linear = Tree::linearize_content($tree['root']);
    if (!$linear) {
      throw new \RuntimeException('Curso sem conteudo publicado.');
    }

    // Carrega progresso de todos os nos do curso em uma unica consulta.
    $progressRows = Db::all(
      "SELECT id, node_id, status, percent, last_position, seconds_spent, last_seen_at, completed_at
         FROM app_progress
        WHERE moodle_userid = :uid
          AND course_id = :cid
        ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC",
      [
        'uid' => (int)$u->id,
        'cid' => $courseId,
      ]
    );

    $progressByNode = [];
    foreach ($progressRows as $row) {
      $nid = (int)($row['node_id'] ?? 0);
      if ($nid <= 0) {
        continue;
      }
      $progressByNode[$nid] = $row;
    }

    // Injeta metadados para o menu (badge de concluido e percentual).
    foreach ($tree['byId'] as $id => &$node) {
      $row = $progressByNode[(int)$id] ?? null;
      $status = (string)($row['status'] ?? '');
      $node['meta_status'] = $status !== '' ? $status : 'not_started';
      $node['meta_percent'] = (int)($row['percent'] ?? 0);
      $node['meta_completed'] = ($status === 'completed');
    }
    unset($node);

    foreach ($linear as &$item) {
      $row = $progressByNode[(int)$item['id']] ?? null;
      $status = (string)($row['status'] ?? '');
      $item['meta_status'] = $status !== '' ? $status : 'not_started';
      $item['meta_percent'] = (int)($row['percent'] ?? 0);
      $item['meta_completed'] = ($status === 'completed');
    }
    unset($item);

    $lockedById = [];
    if ($sequenceLockEnabled) {
      // Bloqueio sequencial: considera apenas itens marcados como sequenciais.
      $firstIncompleteIndex = null;
      foreach ($linear as $idx => $item) {
        if ((int)($item['is_published'] ?? 0) !== 1) {
          continue;
        }
        if (!CoursePolicyService::node_is_sequential($item)) {
          continue;
        }
        if (empty($item['meta_completed'])) {
          $firstIncompleteIndex = (int)$idx;
          break;
        }
      }
      $maxAllowedIndex = $firstIncompleteIndex === null ? (count($linear) - 1) : $firstIncompleteIndex;
      foreach ($linear as $idx => $item) {
        $lockedById[(int)$item['id']] = CoursePolicyService::node_is_sequential($item)
          ? ((int)$idx > (int)$maxAllowedIndex)
          : false;
      }
    } else {
      foreach ($linear as $item) {
        $lockedById[(int)$item['id']] = false;
      }
    }

    foreach ($tree['byId'] as $id => &$node) {
      $node['meta_locked'] = (bool)($lockedById[(int)$id] ?? false);
    }
    unset($node);

    foreach ($linear as &$item) {
      $item['meta_locked'] = (bool)($lockedById[(int)$item['id']] ?? false);
    }
    unset($item);

    $linearById = [];
    foreach ($linear as $item) {
      $linearById[(int)$item['id']] = $item;
    }

    // Progresso do curso para a topbar (usuario atual).
    // Cada item respeita o flag "soma percentual".
    $totalItems = 0;
    $doneItems = 0;
    foreach ($linear as $item) {
      if ((int)($item['is_published'] ?? 0) !== 1) {
        continue;
      }

      if (!CoursePolicyService::node_counts_towards_progress($item)) {
        continue;
      }

      $totalItems++;
      if (!empty($item['meta_completed'])) {
        $doneItems++;
      }
    }
    $course['progress_percent'] = $totalItems > 0
      ? (int)floor(($doneItems * 100) / $totalItems)
      : 0;

    // Se node nao informado, prioriza o ultimo ponto em que o aluno parou.
    $selected = null;
    if ($nodeId > 0 && isset($linearById[$nodeId])) {
      $selected = $linearById[$nodeId];
    } else {
      foreach ($progressRows as $row) {
        $candidateId = (int)($row['node_id'] ?? 0);
        if ($candidateId <= 0 || !isset($linearById[$candidateId])) {
          continue;
        }

        $status = (string)($row['status'] ?? '');
        $percent = (int)($row['percent'] ?? 0);
        $position = (int)($row['last_position'] ?? 0);
        if ($status === 'not_started' && $percent <= 0 && $position <= 0) {
          continue;
        }

        $selected = $linearById[$candidateId];
        break;
      }

      if (!$selected) {
        foreach ($linear as $n) {
          if (empty($n['meta_completed'])) {
            $selected = $n;
            break;
          }
        }
      }

      if (!$selected) {
        $selected = $linear[0];
      }
    }

    $fallbackSelected = null;
    foreach ($linear as $item) {
      if (!empty($item['meta_locked'])) {
        break;
      }
      if (!CoursePolicyService::node_is_sequential($item)) {
        continue;
      }
      $fallbackSelected = $item;
      if (empty($item['meta_completed'])) {
        break;
      }
    }
    if (!$fallbackSelected) {
      $fallbackSelected = $linear[0];
    }

    if ($sequenceLockEnabled && $selected && !empty($selected['meta_locked'])) {
      if ($nodeId > 0) {
        App::flash_set('info', 'Conclua o conteudo atual para liberar o proximo.');
        Response::redirect(App::base_url('/course/' . $courseId . '?node=' . (int)$fallbackSelected['id']));
      }
      $selected = $fallbackSelected;
    }

    // Progresso do no selecionado sem nova consulta.
    $progress = $progressByNode[(int)$selected['id']] ?? null;

    // exam map
    $exam = Db::one("SELECT * FROM app_course_exam WHERE course_id = :cid LIMIT 1", ['cid' => $courseId]);
    $examStatus = null;
    $extractFinalExamCmid = static function(array $node) use ($exam): int {
      $content = [];
      if (!empty($node['content_json'])) {
        $decodedContent = json_decode((string)$node['content_json'], true);
        if (is_array($decodedContent)) {
          $content = $decodedContent;
        }
      }
      $moodleMeta = is_array($content['moodle'] ?? null) ? $content['moodle'] : [];
      $cmid = (int)($node['moodle_cmid']
        ?? $moodleMeta['cmid']
        ?? $content['cmid']
        ?? $content['moodle_cmid']
        ?? 0);
      if ($cmid <= 0) {
        $cmid = (int)($exam['quiz_cmid'] ?? 0);
      }
      return $cmid;
    };

    $selectedIsFinalExam = ((string)($selected['kind'] ?? '') === 'action')
      && ((string)($selected['subtype'] ?? '') === 'final_exam');

    $courseFinalExamNode = null;
    foreach ($linear as $candidate) {
      if ((int)($candidate['is_published'] ?? 0) !== 1) {
        continue;
      }
      if ((string)($candidate['kind'] ?? '') !== 'action' || (string)($candidate['subtype'] ?? '') !== 'final_exam') {
        continue;
      }
      $courseFinalExamNode = $candidate;
      break;
    }
    if ($selectedIsFinalExam) {
      $courseFinalExamNode = $selected;
    }

    $courseFinalExamCmid = $courseFinalExamNode ? $extractFinalExamCmid($courseFinalExamNode) : (int)($exam['quiz_cmid'] ?? 0);
    $courseFinalExamQuizId = $courseFinalExamCmid > 0 ? (int)(Moodle::quiz_instance_from_cmid($courseFinalExamCmid) ?? 0) : 0;
    if ($courseFinalExamQuizId > 0) {
      $grade = Moodle::quiz_last_grade($courseFinalExamQuizId, (int)$u->id);
      $snapshot = Moodle::quiz_completion_snapshot($courseFinalExamQuizId, (int)$u->id, $courseFinalExamCmid);
      $examStatus = [
        'grade' => $grade,
        'min_grade' => $snapshot['pass_grade'] !== null
          ? (float)$snapshot['pass_grade']
          : ($exam['min_grade'] !== null ? (float)$exam['min_grade'] : null),
        'completion_met' => !empty($snapshot['completion_met']),
      ];
    }

    $finalExamGate = $courseAccessGate;
    if ($selectedIsFinalExam && !$isAdmin) {
      $selectedQuizCmid = $extractFinalExamCmid($selected);
      $selectedQuizId = (int)(Moodle::quiz_instance_from_cmid($selectedQuizCmid) ?? 0);
      $hasOpenAttempt = $selectedQuizId > 0
        ? Moodle::quiz_has_open_attempt($selectedQuizId, (int)$u->id)
        : false;
      $quizSnapshot = $selectedQuizId > 0
        ? Moodle::quiz_completion_snapshot($selectedQuizId, (int)$u->id, $selectedQuizCmid)
        : ['completion_met' => false];

      if ($hasOpenAttempt || !empty($quizSnapshot['completion_met']) || ((string)($progress['status'] ?? '') === 'completed')) {
        $finalExamGate['blocked'] = false;
        $finalExamGate['unlocked'] = true;
      }
    }

    Response::html(App::render('estudo/view.php', [
      'user' => $u,
      'course' => $course,
      'tree' => $tree,
      'linear' => $linear,
      'selected' => $selected,
      'progress' => $progress,
      'finalExamGate' => $finalExamGate,
      'flash' => App::flash_get(),
      'exam' => $exam,
      'examStatus' => $examStatus,
      'csrf' => \App\Csrf::token(),
    ]));
  }
}

