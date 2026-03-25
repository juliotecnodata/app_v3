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

final class DashboardController {

  public static function index(): void {
    global $CFG;

    Auth::require_login();
    $u = Auth::user();
    $isAdmin = Auth::is_app_admin((int)$u->id);

    if (!$isAdmin && !Auth::has_any_cached_app_enrollment()) {
      Response::redirect(rtrim((string)$CFG->wwwroot, '/') . '/my/courses.php');
    }

    // Cursos publicados do app.
    $courses = Db::all(
      "SELECT c.*
         FROM app_course c
         JOIN (
           SELECT course_id, MAX(only_app) AS only_app
             FROM app_course_runtime
            GROUP BY course_id
         ) r ON r.course_id = c.id
        WHERE c.status = 'published'
          AND r.only_app = 1
        ORDER BY c.published_at DESC, c.id DESC"
    );

    $visible = [];
    foreach ($courses as $c) {
      $mcid = (int)($c['moodle_courseid'] ?? 0);
      $snapshot = Auth::course_access_snapshot((int)$c['id']);
      $isCurrentlyEnrolled = false;
      $tstart = 0;
      $tend = 0;

      if ($mcid > 0) {
        if (is_array($snapshot)) {
          $isCurrentlyEnrolled = $isAdmin ? true : ((int)($snapshot['is_enrolled'] ?? 0) === 1);
          $tstart = (int)($snapshot['timestart'] ?? 0);
          $tend = (int)($snapshot['timeend'] ?? 0);
        }

        if (!$isAdmin && !$isCurrentlyEnrolled) {
          continue;
        }
      } else {
        // Sem mapeamento no Moodle: apenas admin visualiza.
        if (!$isAdmin) {
          continue;
        }
      }

      // Access window from Moodle (source of truth).
      $expiresAt = null;
      $daysLeft = null;
      $lmsAccessState = 'unknown';
      $lmsAccessLabel = 'Sem prazo de acesso informado no Moodle';
      $canEnter = true;

      if ($mcid > 0) {
        if (is_array($snapshot) && (int)($snapshot['has_window'] ?? 0) === 1) {
          $now = time();

          if ($tstart > 0 && $tstart > $now) {
            $canEnter = false;
            $lmsAccessState = 'blocked';
            $lmsAccessLabel = 'Acesso inicia em ' . date('d/m/Y', $tstart);
          } elseif ($tend > 0) {
            $expiresAt = $tend;
            $daysLeft = (int)floor(($expiresAt - $now) / 86400);

            if ($daysLeft < 0) {
              $canEnter = false;
              $lmsAccessState = 'expired';
              $gone = abs($daysLeft);
              $lmsAccessLabel = $gone === 1 ? 'Expirado ha 1 dia' : ('Expirado ha ' . $gone . ' dias');
            } elseif ($daysLeft === 0) {
              $lmsAccessState = 'expiring';
              $lmsAccessLabel = 'Expira hoje';
            } elseif ($daysLeft === 1) {
              $lmsAccessState = 'expiring';
              $lmsAccessLabel = '1 dia restante';
            } else {
              $lmsAccessState = $daysLeft <= 7 ? 'expiring' : 'active';
              $lmsAccessLabel = $daysLeft . ' dias restantes';
            }
          } else {
            $lmsAccessState = 'unlimited';
            $lmsAccessLabel = 'Sem limite de prazo no Moodle';
          }
        } else {
          if (!$isAdmin) {
            $canEnter = false;
            $lmsAccessState = 'blocked';
            $lmsAccessLabel = 'Sem snapshot de matricula. Entre novamente no sistema.';
          } else {
            $lmsAccessState = 'active';
            $lmsAccessLabel = 'Visualizacao administrativa do curso.';
          }
        }

        if (!$isAdmin && !$isCurrentlyEnrolled && $lmsAccessState !== 'expired' && $lmsAccessState !== 'blocked') {
          $canEnter = false;
          $lmsAccessState = 'blocked';
          $lmsAccessLabel = 'Matricula inativa no Moodle';
        }
      } else {
        $lmsAccessState = 'local';
        $lmsAccessLabel = 'Curso local sem mapeamento Moodle';
      }

      // Define o ponto de continuacao:
      // 1) ultimo item acessado/concluido
      // 2) primeiro nao concluido
      // 3) primeiro item do curso
      $nodes = array_values(array_filter(
        Tree::nodes_for_course((int)$c['id']),
        static function (array $node): bool {
          return !CoursePolicyService::node_is_library_only($node);
        }
      ));
      $tree = Tree::build($nodes);
      $linear = Tree::linearize_content($tree['root']);

      $linearById = [];
      foreach ($linear as $item) {
        $linearById[(int)$item['id']] = $item;
      }

      $progressRows = Db::all(
        "SELECT id, node_id, status, percent, last_position, last_seen_at, completed_at
           FROM app_progress
          WHERE moodle_userid = :uid
            AND course_id = :cid
          ORDER BY COALESCE(last_seen_at, completed_at, '1970-01-01 00:00:00') DESC, id DESC",
        [
          'uid' => (int)$u->id,
          'cid' => (int)$c['id'],
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

      $progressItems = [];
      $hasFinalExamItem = false;
      $finalExamItemCompleted = false;
      $finalExamItemCmid = 0;
      $certificateNodeId = 0;

      foreach ($linear as $n) {
        if ((int)($n['is_published'] ?? 0) !== 1) {
          continue;
        }

        $nid = (int)($n['id'] ?? 0);
        $kind = (string)($n['kind'] ?? '');
        $subtype = (string)($n['subtype'] ?? '');

        if ($kind === 'action' && $subtype === 'certificate') {
          if ($certificateNodeId <= 0) {
            $certificateNodeId = $nid;
          }
        }

        if ($kind === 'action' && $subtype === 'final_exam') {
          $hasFinalExamItem = true;
          if ($finalExamItemCmid <= 0) {
            $content = [];
            if (!empty($n['content_json'])) {
              $decodedContent = json_decode((string)$n['content_json'], true);
              if (is_array($decodedContent)) {
                $content = $decodedContent;
              }
            }
            $moodleMeta = is_array($content['moodle'] ?? null) ? $content['moodle'] : [];
            $finalExamItemCmid = (int)($n['moodle_cmid']
              ?? $moodleMeta['cmid']
              ?? $content['cmid']
              ?? $content['moodle_cmid']
              ?? 0);
          }
          $row = $progressByNode[$nid] ?? null;
          if ($row && (string)($row['status'] ?? '') === 'completed') {
            $finalExamItemCompleted = true;
          }
        }

        if (!CoursePolicyService::node_counts_towards_progress($n)) {
          continue;
        }

        $progressItems[] = $n;
      }

      if (!$progressItems) {
        $progressItems = $linear;
      }

      $totalItems = count($progressItems);
      $doneItems = 0;
      foreach ($progressItems as $item) {
        $nid = (int)($item['id'] ?? 0);
        $row = $progressByNode[$nid] ?? null;
        if ($row && (string)($row['status'] ?? '') === 'completed') {
          $doneItems++;
        }
      }

      $courseCompleted = $totalItems > 0 && $doneItems >= $totalItems;
      $c['progress_percent'] = $totalItems > 0
        ? (int)floor(($doneItems * 100) / $totalItems)
        : 0;

      $exam = Db::one(
        "SELECT quiz_cmid, min_grade
           FROM app_course_exam
          WHERE course_id = :cid
          LIMIT 1",
        ['cid' => (int)$c['id']]
      );
      $finalExamPassedByGrade = false;

      $examQuizCmid = (int)($exam['quiz_cmid'] ?? 0);
      if ($examQuizCmid <= 0) {
        $examQuizCmid = $finalExamItemCmid;
      }
      if ($examQuizCmid > 0) {
        $quizId = (int)(Moodle::quiz_instance_from_cmid($examQuizCmid) ?? 0);
        if ($quizId > 0) {
          $snapshot = Moodle::quiz_completion_snapshot($quizId, (int)$u->id, $examQuizCmid);
          $grade = Moodle::quiz_last_grade($quizId, (int)$u->id);
          $passGrade = $snapshot['pass_grade'] !== null
            ? (float)$snapshot['pass_grade']
            : ($exam['min_grade'] !== null ? (float)$exam['min_grade'] : null);
          if (!empty($snapshot['completion_met'])) {
            $finalExamPassedByGrade = true;
          } else if ($grade !== null && $passGrade !== null) {
            $finalExamPassedByGrade = (float)$grade >= $passGrade;
          }
        }
      }
      $hasFinalExam = $hasFinalExamItem || $examQuizCmid > 0;
      $finalExamCompleted = $examQuizCmid > 0
        ? $finalExamPassedByGrade
        : ($finalExamItemCompleted || $finalExamPassedByGrade);
      $moodleUserId = (int)$u->id;
      $certificateReady = $courseCompleted
        && $hasFinalExam
        && $finalExamCompleted
        && $mcid > 0
        && $moodleUserId > 0;
      $certificateUrl = '';
      if ($certificateReady) {
        $certificateUrl = 'https://tecnodataead.com.br/acarde/detrans_transito/re2.0/cert/index.php?'
          . http_build_query([
            'oa' => $moodleUserId,
            'course' => $mcid,
          ]);
      }

      $continueNodeId = null;

      foreach ($progressRows as $row) {
        $nid = (int)($row['node_id'] ?? 0);
        if ($nid <= 0 || !isset($linearById[$nid])) {
          continue;
        }

        $status = (string)($row['status'] ?? '');
        $percent = (int)($row['percent'] ?? 0);
        $position = (int)($row['last_position'] ?? 0);
        if ($status === 'not_started' && $percent <= 0 && $position <= 0) {
          continue;
        }

        $continueNodeId = $nid;
        break;
      }

      if ($continueNodeId === null) {
        foreach ($linear as $n) {
          $nid = (int)$n['id'];
          $row = $progressByNode[$nid] ?? null;
          if (!$row || (string)($row['status'] ?? '') !== 'completed') {
            $continueNodeId = $nid;
            break;
          }
        }
      }

      if ($continueNodeId === null && !empty($linear)) {
        $continueNodeId = (int)$linear[0]['id'];
      }

      $c['_expiresAt'] = $expiresAt;
      $c['_daysLeft'] = $daysLeft;
      $c['_lmsAccessState'] = $lmsAccessState;
      $c['_lmsAccessLabel'] = $lmsAccessLabel;
      $c['_canEnter'] = $canEnter || $isAdmin;
      $c['_continueNodeId'] = $continueNodeId;
      $c['_certificate_ready'] = $certificateReady;
      $c['_certificate_url'] = $certificateUrl;
      $c['_certificate_node_id'] = $certificateNodeId > 0 ? $certificateNodeId : null;
      $visible[] = $c;
    }

    $visibleCourseIds = [];
    foreach ($visible as $course) {
      $courseId = (int)($course['id'] ?? 0);
      if ($courseId > 0) {
        $visibleCourseIds[] = $courseId;
      }
    }

    $blockMap = [];
    if (!$isAdmin && !empty($visibleCourseIds)) {
      $blockMap = UserCourseBlockService::active_map_for_user_courses((int)$u->id, $visibleCourseIds);
    }

    foreach ($visible as &$course) {
      $courseId = (int)($course['id'] ?? 0);
      $block = $blockMap[$courseId] ?? null;
      $isBlocked = $block !== null;
      $course['_isBlocked'] = $isBlocked;
      $course['_blockTitle'] = UserCourseBlockService::title_for_row($block);
      $course['_blockMessage'] = UserCourseBlockService::message_for_row($block);
      $course['_blockExpiresAt'] = (string)($block['expires_at'] ?? '');
      if ($isBlocked) {
        $course['_canEnter'] = false;
        $course['_lmsAccessState'] = 'blocked';
        $course['_lmsAccessLabel'] = $course['_blockTitle'];
      }
    }
    unset($course);

    $ebookShelf = [];
    $visibleForLibrary = array_values(array_filter($visible, static function (array $course): bool {
      return !empty($course['_canEnter']);
    }));

    if (!empty($visibleForLibrary)) {
      foreach ($visibleForLibrary as $course) {
        $courseId = (int)($course['id'] ?? 0);
        if ($courseId <= 0) {
          continue;
        }

        $ebookRows = Db::all(
          "SELECT id,
                  course_id,
                  title,
                  url,
                  sort_order
             FROM app_support_material
            WHERE course_id = :cid
              AND is_published = 1
         ORDER BY sort_order ASC, id ASC",
          ['cid' => $courseId]
        );

        foreach ($ebookRows as $row) {
          $rawUrl = trim((string)($row['url'] ?? ''));
          if ($rawUrl === '') {
            continue;
          }

          $url = $rawUrl;
          if (preg_match('#^https?://#i', $url) !== 1) {
            $url = App::base_url('/' . ltrim($url, '/'));
          }

          $title = trim((string)($row['title'] ?? ''));
          $label = trim((string)($row['title'] ?? ''));
          if ($title === '') {
            $title = $label !== '' ? $label : 'Material de apoio';
          }

          $ebookShelf[] = [
            'id' => (int)($row['id'] ?? 0),
            'course_id' => (int)($row['course_id'] ?? 0),
            'course_title' => (string)($course['title'] ?? 'Curso'),
            'title' => $title,
            'label' => $label !== '' ? $label : 'Abrir material',
            'url' => $url,
            'host' => (string)parse_url($url, PHP_URL_HOST),
          ];
        }
      }
    }

    Response::html(App::render('dashboard/index.php', [
      'user' => $u,
      'courses' => $visible,
      'ebookShelf' => $ebookShelf,
      'flash' => App::flash_get(),
    ]));
  }
}

