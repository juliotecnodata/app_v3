<?php
use App\App;
global $CFG, $USER;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$course = $course ?? [];
$tree = $tree ?? ['root' => [], 'byId' => []];
$selected = $selected ?? null;
$progress = $progress ?? null;
$exam = $exam ?? null;
$examStatus = $examStatus ?? null;
$csrf = $csrf ?? '';

$courseId = (int)($course['id'] ?? 0);
$courseTitle = (string)($course['title'] ?? 'Curso');
$courseDesc = (string)($course['short_description'] ?? $course['description'] ?? '');
$courseProgress = (int)($course['progress_percent'] ?? 0);
$sequenceLockEnabled = (int)($course['enable_sequence_lock'] ?? 1) === 1;
$showNumbering = ((int)($course['show_numbering'] ?? 0) === 1);

$selectedId = $selected ? (int)$selected['id'] : 0;

$contentJson = [];
if ($selected && !empty($selected['content_json'])) {
  $decoded = json_decode((string)$selected['content_json'], true);
  if (is_array($decoded)) {
    $contentJson = $decoded;
  }
}

$rulesJson = [];
if ($selected && !empty($selected['rules_json'])) {
  $decodedRules = json_decode((string)$selected['rules_json'], true);
  if (is_array($decodedRules)) {
    $rulesJson = $decodedRules;
  }
}

$selectedKind = (string)($selected['kind'] ?? 'content');
$selectedSubtype = (string)($selected['subtype'] ?? 'text');
$isCertificateItem = $selectedKind === 'action' && $selectedSubtype === 'certificate';
$finalExamGate = is_array($finalExamGate ?? null) ? $finalExamGate : [];
$finalExamBlocked = $selectedKind === 'action'
  && $selectedSubtype === 'final_exam'
  && !empty($finalExamGate['blocked']);
$finalExamFirstAccessText = !empty($finalExamGate['first_access_ts'])
  ? date('d/m/Y H:i', (int)$finalExamGate['first_access_ts'])
  : '';
$finalExamUnlockText = !empty($finalExamGate['unlock_ts'])
  ? date('d/m/Y H:i', (int)$finalExamGate['unlock_ts'])
  : '';

$selectedMoodle = is_array($contentJson['moodle'] ?? null) ? $contentJson['moodle'] : [];
$selectedMoodleCmid = (int)($selected['moodle_cmid'] ?? $selectedMoodle['cmid'] ?? $contentJson['cmid'] ?? $contentJson['moodle_cmid'] ?? 0);
$selectedMoodleModname = strtolower(trim((string)($selected['moodle_modname'] ?? $selectedMoodle['modname'] ?? $contentJson['moodle_modname'] ?? '')));
if ($selectedMoodleModname === '' && $selectedMoodleCmid > 0) {
  $selectedMoodleModname = strtolower(trim((string)\App\Moodle::cm_modname($selectedMoodleCmid)));
}
$selectedMoodleUrl = trim((string)($selected['moodle_url'] ?? $selectedMoodle['url'] ?? $contentJson['moodle_url'] ?? ''));

$quizCmid = 0;
if ($selectedKind === 'action' && $selectedSubtype === 'final_exam') {
  $quizCmid = $selectedMoodleCmid;
  if ($quizCmid <= 0) {
    $quizCmid = (int)($exam['quiz_cmid'] ?? 0);
  }
} else if ($selectedMoodleModname === 'quiz') {
  $quizCmid = $selectedMoodleCmid;
}
$isQuizItem = $quizCmid > 0
  && (
    ($selectedKind === 'action' && $selectedSubtype === 'final_exam')
    || $selectedMoodleModname === 'quiz'
  );
$isLessonItem = $selectedMoodleCmid > 0
  && $selectedMoodleModname === 'lesson';
$isMoodleManagedItem = $isQuizItem || $isLessonItem;

$selectedQuizStatus = null;
if ($isQuizItem && $quizCmid > 0) {
  $selectedQuizId = (int)(\App\Moodle::quiz_instance_from_cmid($quizCmid) ?? 0);
  $selectedQuizUserId = (int)($USER->id ?? 0);
  if ($selectedQuizId > 0 && $selectedQuizUserId > 0) {
    $selectedQuizSnapshot = \App\Moodle::quiz_completion_snapshot($selectedQuizId, $selectedQuizUserId, $quizCmid);
    $selectedQuizStatus = [
      'grade' => $selectedQuizSnapshot['grade'] ?? null,
      'pass_grade' => $selectedQuizSnapshot['pass_grade'] ?? null,
      'pass_required' => !empty($selectedQuizSnapshot['pass_required']),
    ];
  }
}

$lessonEmbedUrl = '';
if ($isLessonItem) {
  $moodleRoot = rtrim((string)($CFG->wwwroot ?? ''), '/');
  $candidateUrl = trim(str_replace('&amp;', '&', $selectedMoodleUrl));

  if ($candidateUrl !== '') {
    if (preg_match('~^(https?:)?//~i', $candidateUrl)) {
      $lessonEmbedUrl = $candidateUrl;
    } else if ($moodleRoot !== '') {
      $lessonEmbedUrl = $moodleRoot . '/' . ltrim($candidateUrl, '/');
    }
  }

  if ($lessonEmbedUrl === '' && $selectedMoodleCmid > 0 && $moodleRoot !== '') {
    $lessonEmbedUrl = $moodleRoot . '/mod/lesson/view.php?id=' . (int)$selectedMoodleCmid;
  }

  if ($lessonEmbedUrl !== '') {
    $parts = @parse_url($lessonEmbedUrl);
    if (is_array($parts)) {
      $query = [];
      if (!empty($parts['query'])) {
        parse_str((string)$parts['query'], $query);
      }
      $query['appv3embed'] = 1;
      $rebuilt = '';
      if (!empty($parts['scheme'])) {
        $rebuilt .= $parts['scheme'] . '://';
      }
      if (!empty($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (!empty($parts['pass'])) {
          $rebuilt .= ':' . $parts['pass'];
        }
        $rebuilt .= '@';
      }
      if (!empty($parts['host'])) {
        $rebuilt .= $parts['host'];
      }
      if (!empty($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
      }
      $rebuilt .= (string)($parts['path'] ?? '');
      $rebuilt .= '?' . http_build_query($query);
      if (!empty($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
      }
      $lessonEmbedUrl = $rebuilt;
    } else {
      $lessonEmbedUrl .= (strpos($lessonEmbedUrl, '?') === false ? '?' : '&') . 'appv3embed=1';
    }
  }
}

$isAssessmentLayout = false;
if ($selected) {
  $assessmentSubtypes = ['final_exam', 'exercise', 'quiz', 'questionnaire'];
  $isAssessmentLayout = $isQuizItem
    || $isLessonItem
    || (
      $selectedKind === 'action'
      && in_array($selectedSubtype, $assessmentSubtypes, true)
    );
}

$isVideoLayout = $selected && $selectedKind === 'content' && $selectedSubtype === 'video';
$studyShellClass = 'study-shell';
if ($isAssessmentLayout) {
  $studyShellClass .= ' study-shell--assessment';
}
if ($isVideoLayout) {
  $studyShellClass .= ' study-shell--video';
}

$studyContentClass = 'study-content';
if ($isAssessmentLayout) {
  $studyContentClass .= ' study-content--assessment';
}
if ($isVideoLayout) {
  $studyContentClass .= ' study-content--video';
}

$numberMap = [];
$buildNumbers = function (array $nodes, string $prefix = '') use (&$buildNumbers, &$numberMap) {
  $index = 0;
  foreach ($nodes as $node) {
    if ((int)($node['is_published'] ?? 0) !== 1) continue;

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    if ($kind === 'container' && $subtype === 'root') {
      if (!empty($node['children']) && is_array($node['children'])) {
        $buildNumbers($node['children'], $prefix);
      }
      continue;
    }

    $index++;
    $number = $prefix !== '' ? $prefix . '.' . $index : (string)$index;
    $numberMap[(int)$node['id']] = $number;

    if (!empty($node['children']) && is_array($node['children'])) {
      $buildNumbers($node['children'], $number);
    }
  }
};
if ($showNumbering) {
  $buildNumbers($tree['root'] ?? []);
}

$typeLabel = function (string $kind, string $subtype): string {
  if ($kind === 'content') {
    if ($subtype === 'video') return 'Video aula';
    if ($subtype === 'pdf') return 'PDF';
    if ($subtype === 'text') return 'Leitura';
    if ($subtype === 'download') return 'Download';
    if ($subtype === 'link') return 'Link externo';
    return 'Conteudo';
  }

  if ($kind === 'action') {
    if ($subtype === 'final_exam') return 'Prova final';
    if ($subtype === 'certificate') return 'Certificado';
    return 'Acao';
  }

  if ($subtype === 'module') return 'Modulo';
  if ($subtype === 'topic') return 'Topico';
  if ($subtype === 'section') return 'Secao';
  if ($subtype === 'subsection') return 'Subsecao';
  return 'Secao';
};

$typeIcon = function (string $kind, string $subtype): string {
  if ($kind === 'content') {
    if ($subtype === 'video') return 'fa-solid fa-circle-play';
    if ($subtype === 'pdf') return 'fa-solid fa-file-pdf';
    if ($subtype === 'text') return 'fa-solid fa-align-left';
    if ($subtype === 'download') return 'fa-solid fa-download';
    if ($subtype === 'link') return 'fa-solid fa-up-right-from-square';
    return 'fa-solid fa-file-lines';
  }

  if ($kind === 'action') {
    if ($subtype === 'certificate') return 'fa-solid fa-certificate';
    return 'fa-solid fa-bolt';
  }

  return 'fa-regular fa-folder-open';
};

$assetUrl = function (string $path): string {
  $path = trim($path);
  if ($path === '') return '';
  if (preg_match('~^(https?:)?//~i', $path)) return $path;
  return App::base_url($path);
};

$normalizeVideo = function (string $url, string $provider): array {
  $url = trim($url);
  if ($url === '') return [$url, false];

  $provider = strtolower($provider);

  if ($provider === 'youtube' || strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
    if (strpos($url, 'embed/') !== false) return [$url, true];
    if (preg_match('~youtu\.be/([^?&/]+)~', $url, $match)) return ['https://www.youtube.com/embed/' . $match[1], true];
    if (preg_match('~v=([^?&/]+)~', $url, $match)) return ['https://www.youtube.com/embed/' . $match[1], true];
    return [$url, true];
  }

  if ($provider === 'vimeo' || strpos($url, 'vimeo.com') !== false) {
    if (preg_match('~vimeo\.com/(?:video/)?([0-9]+)~', $url, $match)) {
      return ['https://player.vimeo.com/video/' . $match[1], true];
    }
    return [$url, true];
  }

  $isIframe = in_array($provider, ['videofront', 'iframe', 'embed'], true)
    || strpos($url, 'embed') !== false
    || strpos($url, 'player') !== false;

  return [$url, $isIframe];
};

$nodeUrl = function (array $node) use ($courseId): string {
  return App::base_url('/course/' . $courseId . '?node=' . (int)$node['id']);
};

$findPath = function (array $nodes, int $targetId, array &$path) use (&$findPath): bool {
  foreach ($nodes as $node) {
    $path[] = $node;
    if ((int)($node['id'] ?? 0) === $targetId) return true;
    if (!empty($node['children']) && is_array($node['children']) && $findPath($node['children'], $targetId, $path)) return true;
    array_pop($path);
  }
  return false;
};

$flat = [];
$flatten = function (array $nodes) use (&$flatten, &$flat) {
  foreach ($nodes as $node) {
    if ((int)($node['is_published'] ?? 0) !== 1) continue;

    $kind = (string)($node['kind'] ?? '');
    if ($kind !== 'container') {
      $flat[] = $node;
    }

    if (!empty($node['children']) && is_array($node['children'])) {
      $flatten($node['children']);
    }
  }
};
$flatten($tree['root'] ?? []);

$isCertificateNode = static function (array $node): bool {
  return (string)($node['kind'] ?? '') === 'action'
    && (string)($node['subtype'] ?? '') === 'certificate'
    && (int)($node['is_published'] ?? 0) === 1;
};

$progressItems = [];
foreach ($flat as $item) {
  if ($isCertificateNode($item)) {
    continue;
  }
  $progressItems[] = $item;
}
if (!$progressItems) {
  $progressItems = $flat;
}

$totalItems = count($progressItems);
$doneItems = 0;
foreach ($progressItems as $item) {
  if (!empty($item['meta_completed'])) $doneItems++;
}
$courseProgress = $totalItems > 0 ? (int)floor(($doneItems * 100) / $totalItems) : 0;

$moodleCourseId = (int)($course['moodle_courseid'] ?? 0);
$moodleUserId = 0;
if (isset($user)) {
  if (is_object($user) && isset($user->id)) {
    $moodleUserId = (int)$user->id;
  } else if (is_array($user) && isset($user['id'])) {
    $moodleUserId = (int)$user['id'];
  }
}

$hasFinalExamItem = false;
$finalExamItemCompleted = false;
foreach ($flat as $item) {
  if ((string)($item['kind'] ?? '') !== 'action' || (string)($item['subtype'] ?? '') !== 'final_exam') {
    continue;
  }
  if ((int)($item['is_published'] ?? 0) !== 1) {
    continue;
  }
  $hasFinalExamItem = true;
  if (!empty($item['meta_completed'])) {
    $finalExamItemCompleted = true;
  }
}

$examQuizCmid = (int)($exam['quiz_cmid'] ?? 0);
$hasFinalExam = $hasFinalExamItem || $examQuizCmid > 0;
$finalExamPassedByGrade = false;
if ($examStatus && $examStatus['grade'] !== null) {
  $grade = (float)$examStatus['grade'];
  $minGrade = $examStatus['min_grade'] !== null ? (float)$examStatus['min_grade'] : null;
  $finalExamPassedByGrade = $minGrade === null ? true : ($grade >= $minGrade);
}
$hasMoodleFinalExamControl = $examQuizCmid > 0 || !empty($examStatus);
$finalExamCompleted = $hasMoodleFinalExamControl
  ? (!empty($examStatus['completion_met']) || $finalExamPassedByGrade)
  : ($finalExamItemCompleted || $finalExamPassedByGrade);

$courseCompleted = $totalItems > 0 && $doneItems >= $totalItems;
$certificateReady = $courseCompleted
  && $hasFinalExam
  && $finalExamCompleted
  && $moodleCourseId > 0
  && $moodleUserId > 0;

$certificateUrl = '';
if ($certificateReady) {
  $certificateUrl = 'https://tecnodataead.com.br/acarde/detrans_transito/re2.0/cert/index.php?'
    . http_build_query([
      'oa' => $moodleUserId,
      'course' => $moodleCourseId,
    ]);
}

$certificateBlockReason = '';
if ($moodleCourseId <= 0) {
  $certificateBlockReason = 'Curso sem mapeamento Moodle (course id).';
} else if ($moodleUserId <= 0) {
  $certificateBlockReason = 'Usuario Moodle nao identificado.';
} else if (!$courseCompleted) {
  $certificateBlockReason = 'Conclua 100% do curso para liberar o certificado.';
} else if (!$hasFinalExam) {
  $certificateBlockReason = 'Prova final nao configurada neste curso.';
} else if (!$finalExamCompleted) {
  $certificateBlockReason = 'Conclua a prova final para liberar o certificado.';
}

$prevNode = null;
$nextNode = null;
for ($i = 0; $i < count($flat); $i++) {
  if ((int)$flat[$i]['id'] !== $selectedId) continue;
  if ($i > 0) $prevNode = $flat[$i - 1];
  if ($i < count($flat) - 1) $nextNode = $flat[$i + 1];
  break;
}

$selectedStatus = (string)($progress['status'] ?? 'not_started');
$isSelectedDone = $selectedStatus === 'completed';
$selectedSequential = $selected ? \App\CoursePolicyService::node_is_sequential($selected) : true;
$nextNodeLocked = $nextNode ? !empty($nextNode['meta_locked']) : false;

$manualCompletionEligible = !$isMoodleManagedItem && !$isCertificateItem;
$manualCompletionReady = $manualCompletionEligible && !$isSelectedDone;
$manualCompletionHint = '';

if ($manualCompletionEligible && !$isSelectedDone && $selectedKind === 'content' && $selectedSubtype === 'video') {
  $manualVideoMinPercent = (int)($contentJson['min_video_percent'] ?? $rulesJson['min_video_percent'] ?? 100);
  if ($manualVideoMinPercent < 1 || $manualVideoMinPercent > 100) {
    $manualVideoMinPercent = 100;
  }
  $currentVideoPercent = (int)($progress['percent'] ?? 0);
  $manualCompletionReady = $currentVideoPercent >= $manualVideoMinPercent;
  if (!$manualCompletionReady) {
    $manualCompletionHint = 'Continue assistindo o video para liberar a conclusao.';
  }
} elseif ($manualCompletionEligible && !$isSelectedDone && $selectedKind === 'content' && $selectedSubtype === 'pdf' && !empty($rulesJson['require_pdf'])) {
  $pdfTouched = in_array($selectedStatus, ['in_progress', 'completed'], true)
    || (int)($progress['percent'] ?? 0) > 0
    || (int)($progress['last_position'] ?? 0) > 0;
  $manualCompletionReady = $pdfTouched;
  if (!$manualCompletionReady) {
    $manualCompletionHint = 'Abra ou baixe o PDF para liberar a conclusao.';
  }
}

$footerActionMode = 'disabled';
$footerActionLabel = 'Concluir aula';
$footerActionClass = 'study-footer-btn--muted';
$footerActionDisabled = true;
$footerActionNextUrl = $nextNode ? App::base_url('/course/' . $courseId . '?node=' . (int)$nextNode['id'] . '&autoplay=1') : '';
$footerActionIcon = 'fa-check';

if ($isSelectedDone) {
  if ($footerActionNextUrl !== '') {
    $footerActionMode = 'next';
    $footerActionLabel = 'Proximo';
    $footerActionClass = 'study-footer-btn--next';
    $footerActionDisabled = false;
    $footerActionIcon = 'fa-arrow-right';
  } else {
    $footerActionMode = 'done';
    $footerActionLabel = 'Aula concluida';
    $footerActionClass = 'study-footer-btn--done';
    $footerActionDisabled = true;
    $footerActionIcon = 'fa-circle-check';
  }
} elseif ($manualCompletionEligible && !$selectedSequential && $footerActionNextUrl !== '' && !$nextNodeLocked) {
  $footerActionMode = 'next';
  $footerActionLabel = 'Proximo';
  $footerActionClass = 'study-footer-btn--next';
  $footerActionDisabled = false;
  $footerActionIcon = 'fa-arrow-right';
} elseif ($manualCompletionEligible) {
  $footerActionMode = 'complete';
  $footerActionLabel = 'Concluir aula';
  $footerActionClass = 'study-footer-btn--complete';
  $footerActionDisabled = !$manualCompletionReady;
  $footerActionIcon = 'fa-check';
} elseif ($finalExamBlocked) {
  $footerActionLabel = 'Prova bloqueada';
  $footerActionIcon = 'fa-lock';
} elseif ($isQuizItem) {
  $footerActionLabel = 'Responder avaliacao';
  $footerActionIcon = 'fa-clipboard-check';
} elseif ($isLessonItem) {
  $footerActionLabel = 'Concluir na licao';
  $footerActionIcon = 'fa-book-open';
} elseif ($isCertificateItem) {
  $footerActionLabel = $certificateReady ? 'Gerar certificado acima' : 'Certificado indisponivel';
  $footerActionIcon = $certificateReady ? 'fa-award' : 'fa-lock';
}

$selectedNumber = ($showNumbering && $selectedId) ? ($numberMap[$selectedId] ?? '') : '';
$breadcrumbItems = [];

if ($selectedId > 0) {
  $path = [];
  $findPath($tree['root'] ?? [], $selectedId, $path);

  $buildBreadcrumbLabel = function (array $node) use ($showNumbering, $numberMap, $typeLabel): string {
    $nodeId = (int)($node['id'] ?? 0);
    $nodeKind = (string)($node['kind'] ?? '');
    $nodeSubtype = (string)($node['subtype'] ?? '');
    $nodeTitle = trim((string)($node['title'] ?? ''));

    if ($nodeTitle === '') {
      $nodeTitle = (string)$typeLabel($nodeKind, $nodeSubtype);
    }

    if ($showNumbering) {
      $nodeNumber = trim((string)($numberMap[$nodeId] ?? ''));
      if ($nodeNumber !== '' && strpos($nodeTitle, $nodeNumber) !== 0) {
        $nodeTitle = $nodeNumber . ' - ' . $nodeTitle;
      }
    }

    return $nodeTitle;
  };

  foreach ($path as $step) {
    $stepKind = (string)($step['kind'] ?? '');
    $stepSubtype = (string)($step['subtype'] ?? '');
    if ($stepKind === 'container' && $stepSubtype === 'root') {
      continue;
    }

    $breadcrumbItems[] = [
      'label' => $buildBreadcrumbLabel($step),
    ];
  }

  if (!$breadcrumbItems && $selected) {
    $fallbackTitle = trim((string)($selected['title'] ?? ''));
    if ($fallbackTitle === '') {
      $fallbackTitle = 'Item';
    }
    if ($selectedNumber !== '' && strpos($fallbackTitle, $selectedNumber) !== 0) {
      $fallbackTitle = $selectedNumber . ' - ' . $fallbackTitle;
    }
    $breadcrumbItems[] = [
      'label' => $fallbackTitle,
    ];
  }
}

$containsSelected = function (array $node, int $selectedId) use (&$containsSelected): bool {
  if ((int)($node['id'] ?? 0) === $selectedId) return true;
  if (empty($node['children']) || !is_array($node['children'])) return false;
  foreach ($node['children'] as $child) {
    if ($containsSelected($child, $selectedId)) return true;
  }
  return false;
};

$moduleCompletionMap = [];
$collectCompletionStats = function (array $node) use (&$collectCompletionStats, &$moduleCompletionMap, $isCertificateNode): array {
  if ((int)($node['is_published'] ?? 0) !== 1) {
    return [0, 0];
  }

  $kind = (string)($node['kind'] ?? '');
  $subtype = (string)($node['subtype'] ?? '');
  $id = (int)($node['id'] ?? 0);

  if ($kind !== 'container') {
    if ($isCertificateNode($node)) {
      return [0, 0];
    }
    return [1, !empty($node['meta_completed']) ? 1 : 0];
  }

  $total = 0;
  $done = 0;
  foreach (($node['children'] ?? []) as $child) {
    [$childTotal, $childDone] = $collectCompletionStats($child);
    $total += $childTotal;
    $done += $childDone;
  }

  if ($id > 0) {
    $moduleCompletionMap[$id] = [
      'total' => $total,
      'done' => $done,
      'is_done' => $total > 0 && $done >= $total,
    ];
  }

  return [$total, $done];
};

foreach (($tree['root'] ?? []) as $rootNode) {
  $collectCompletionStats($rootNode);
}

$renderTrail = function (array $nodes, int $depth, int $selectedId, array $numberMap, callable $typeLabel, callable $typeIcon, callable $nodeUrl, callable $containsSelected, bool $showNumbering, array $moduleCompletionMap) use (&$renderTrail) {
  foreach ($nodes as $node) {
    if ((int)($node['is_published'] ?? 0) !== 1) continue;

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    $id = (int)($node['id'] ?? 0);
    $number = $numberMap[$id] ?? '';

    if ($kind === 'container') {
      if ($subtype === 'root') {
        if (!empty($node['children']) && is_array($node['children'])) {
          $renderTrail($node['children'], $depth + 1, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering, $moduleCompletionMap);
        }
        continue;
      }

      $indent = max(0, $depth - 1);
      $indentPx = 10 + ($indent * 14);
      $hasChildren = !empty($node['children']) && is_array($node['children']);
      $isModule = $subtype === 'module';
      $isCollapsible = $hasChildren && $isModule;
      $isActivePath = $containsSelected($node, $selectedId);
      $isOpen = !$isCollapsible || $isActivePath;
      $nodeTitle = (string)($node['title'] ?? '');
      $label = (string)$typeLabel($kind, $subtype);
      $searchNumber = $showNumbering ? $number : '';
      $searchLabel = strtolower(trim($label . ' ' . $searchNumber . ' ' . $nodeTitle));
      $moduleStats = $moduleCompletionMap[$id] ?? ['total' => 0, 'done' => 0, 'is_done' => false];
      $moduleTotal = (int)($moduleStats['total'] ?? 0);
      $moduleDone = (int)($moduleStats['done'] ?? 0);
      $isModuleDone = (bool)($moduleStats['is_done'] ?? false);

      echo '<section class="trail-container' . ($isActivePath ? ' is-active-path' : '') . ($isCollapsible ? ' is-collapsible' : '') . ($isOpen ? ' is-open' : ' is-collapsed') . ($isModuleDone ? ' is-module-done' : '') . '" data-node-id="' . (int)$id . '" data-collapsible="' . ($isCollapsible ? '1' : '0') . '" data-module="' . ($isModule ? '1' : '0') . '" data-module-total="' . (int)$moduleTotal . '" data-module-done="' . (int)$moduleDone . '" data-search="' . h($searchLabel) . '">';
      echo '<div class="trail-group" style="padding-left:' . (int)$indentPx . 'px;">';
      echo '<div class="trail-group-main">';
      if ($showNumbering && $number !== '') echo '<span class="trail-group-num">' . h($number) . '</span>';
      echo '<span class="trail-group-title">' . h((string)($node['title'] ?? '')) . '</span>';
      if ($isModule) {
        echo '<span class="trail-group-status' . ($isModuleDone ? ' is-done' : '') . '"' . ($isModuleDone ? '' : ' hidden') . ' data-module-status>' . ($isModuleDone ? '<i class="fa-solid fa-circle-check"></i> Concluido' : '') . '</span>';
      }
      echo '</div>';
      echo '<span class="trail-group-line"></span>';
      if ($isCollapsible) {
        echo '<button type="button" class="trail-group-toggle" aria-label="Expandir ou recolher modulo" aria-expanded="' . ($isOpen ? 'true' : 'false') . '"><i class="fa-solid fa-chevron-down"></i></button>';
      }
      echo '</div>';

      if ($hasChildren) {
        echo '<div class="trail-container-children">';
        $renderTrail($node['children'], $depth + 1, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering, $moduleCompletionMap);
        echo '</div>';
      }
      echo '</section>';
      continue;
    }

    $isActive = $id === $selectedId;
    $isDone = !empty($node['meta_completed']);
    $isLocked = !empty($node['meta_locked']);
    $indent = max(0, $depth - 1);
    $indentPx = 10 + ($indent * 14);
    $href = $nodeUrl($node);
    ?>
    <?php if ($isLocked): ?>
      <a href="<?= h($href) ?>" class="trail-item js-study-link d-flex align-items-center gap-2 py-3 <?= $isDone ? 'is-done' : '' ?> is-locked" style="padding-left:<?= (int)$indentPx ?>px; padding-right:10px;" aria-disabled="true" tabindex="-1" data-node-id="<?= (int)$id ?>" data-node-number="<?= h($showNumbering && $number !== '' ? $number : '') ?>" data-node-url="<?= h($href) ?>">
        <span class="trail-dot flex-shrink-0"><i class="fa-solid fa-lock"></i></span>
        <span class="flex-grow-1 min-w-0">
          <span class="d-block trail-title"><i class="<?= h($typeIcon($kind, $subtype)) ?> me-2"></i><?= h((string)($node['title'] ?? '')) ?></span>
          <span class="trail-meta">
            <span class="badge bg-light text-dark me-1"><?= h($typeLabel($kind, $subtype)) ?></span>
          </span>
        </span>
        <i class="fa-solid fa-lock text-muted"></i>
      </a>
    <?php else: ?>
      <a href="<?= h($href) ?>" class="trail-item js-study-link d-flex align-items-center gap-2 py-3 <?= $isActive ? 'is-active' : '' ?> <?= $isDone ? 'is-done' : '' ?>" style="padding-left:<?= (int)$indentPx ?>px; padding-right:10px;" data-node-id="<?= (int)$id ?>" data-node-number="<?= h($showNumbering && $number !== '' ? $number : '') ?>" data-node-url="<?= h($href) ?>">
        <span class="trail-dot flex-shrink-0"><?= $isDone ? '<i class="fa-solid fa-check"></i>' : ($showNumbering && $number !== '' ? h($number) : '<i class="fa-solid fa-circle" style="font-size:7px;"></i>') ?></span>
        <span class="flex-grow-1 min-w-0">
          <span class="d-block trail-title"><i class="<?= h($typeIcon($kind, $subtype)) ?> me-2"></i><?= h((string)($node['title'] ?? '')) ?></span>
          <span class="trail-meta">
            <span class="badge bg-light text-dark me-1"><?= h($typeLabel($kind, $subtype)) ?></span>
          </span>
        </span>
        <i class="fa-solid fa-chevron-right text-muted"></i>
      </a>
    <?php endif; ?>
    <?php
  }
};

ob_start();
$renderTrail($tree['root'] ?? [], 0, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering, $moduleCompletionMap);
$trailHtml = ob_get_clean();
?>

<div class="<?= h($studyShellClass) ?>">
  <header class="study-header">
    <div class="study-header-left d-flex align-items-center gap-2">
      <button id="btnToggleMenu" class="btn study-btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#studyOffcanvas" aria-controls="studyOffcanvas" aria-label="Abrir menu do curso" aria-pressed="false">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="study-title-wrap">
        <div class="study-title"><i class="fa-solid fa-graduation-cap me-2"></i><?= h($courseTitle) ?></div>
        <?php if ($courseDesc !== ''): ?>
          <div class="study-subtitle"><?= h($courseDesc) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="study-header-center d-none d-lg-flex align-items-center gap-2">
      <div class="study-meta-chip"><i class="fa-solid fa-layer-group me-1"></i><span id="studyTotalCount"><?= (int)$totalItems ?></span> itens</div>
      <div class="study-meta-chip"><i class="fa-solid fa-check me-1"></i><span id="studyDoneCount"><?= (int)$doneItems ?></span> concluidos</div>
    </div>

    <div class="study-header-right">
      <div class="study-progress-wrap">
        <div class="progress study-progress" role="progressbar" aria-label="Progresso do curso" aria-valuenow="<?= (int)$courseProgress ?>" aria-valuemin="0" aria-valuemax="100">
          <div id="studyProgressBar" class="progress-bar" style="width:<?= (int)$courseProgress ?>%"></div>
        </div>
        <span id="studyProgressLabel" class="study-progress-label"><?= (int)$courseProgress ?>%</span>
      </div>
      <div class="study-top-actions">
        <a class="btn btn-light btn-sm study-top-action" href="<?= h(App::base_url('/dashboard')) ?>">
          <i class="fa-solid fa-house me-1"></i> Meus cursos
        </a>
        <a class="btn btn-outline-light btn-sm study-top-action" href="<?= h(App::base_url('/logout')) ?>">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
      </div>
    </div>
  </header>

  <div class="study-layout">
    <aside class="study-sidebar d-none d-md-flex">
      <div class="study-sidebar__header">
        <div class="study-sidebar__title"><i class="fa-solid fa-list-ul me-1"></i>Trilha do curso</div>
        <div class="trail-actions">
          <button type="button" class="btn btn-sm btn-outline-secondary js-trail-expand-all" title="Expandir todos os modulos">
            <i class="fa-solid fa-up-right-and-down-left-from-center me-1"></i>Expandir tudo
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary js-trail-collapse-all" title="Recolher todos os modulos">
            <i class="fa-solid fa-down-left-and-up-right-to-center me-1"></i>Contrair tudo
          </button>
        </div>
      </div>
      <div class="rail-list" id="railListDesktop">
        <?= $trailHtml ?>
      </div>
    </aside>

    <div class="offcanvas offcanvas-start study-offcanvas d-md-none" tabindex="-1" id="studyOffcanvas" aria-labelledby="studyOffcanvasLabel" data-bs-backdrop="false" data-bs-scroll="true">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="studyOffcanvasLabel"><i class="fa-solid fa-list-ul me-1"></i>Trilha do curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
      </div>
      <div class="offcanvas-body p-2">
        <div class="trail-actions mb-2">
          <button type="button" class="btn btn-sm btn-outline-secondary js-trail-expand-all">
            <i class="fa-solid fa-up-right-and-down-left-from-center me-1"></i>Expandir tudo
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary js-trail-collapse-all">
            <i class="fa-solid fa-down-left-and-up-right-to-center me-1"></i>Contrair tudo
          </button>
        </div>
        <div class="rail-list" id="railListMobile">
          <?= $trailHtml ?>
        </div>
      </div>
    </div>

    <main class="study-main">
      <?php if (!$selected): ?>
        <section class="study-empty">
          <div class="study-empty__icon"><i class="fa-solid fa-play"></i></div>
          <div class="fw-bold mb-1">Selecione um item para iniciar</div>
          <div class="text-muted small">Abra a trilha do curso e escolha a proxima aula.</div>
        </section>
      <?php else: ?>
        <section class="study-title-block mb-3">
          <div class="d-flex align-items-center gap-2">
            <span class="study-type-icon" id="lessonTypeIcon"><i class="<?= h($typeIcon((string)$selected['kind'], (string)$selected['subtype'])) ?>"></i></span>
            <nav class="study-breadcrumb" aria-label="breadcrumb">
              <ol class="breadcrumb">
                <?php if (!$breadcrumbItems): ?>
                  <li class="breadcrumb-item active" aria-current="page">Trilha do curso</li>
                <?php else: ?>
                  <?php $lastBreadcrumbIndex = count($breadcrumbItems) - 1; ?>
                  <?php foreach ($breadcrumbItems as $index => $item): ?>
                    <?php $isActiveBreadcrumb = $index === $lastBreadcrumbIndex; ?>
                    <li class="breadcrumb-item<?= $isActiveBreadcrumb ? ' active' : '' ?>"<?= $isActiveBreadcrumb ? ' aria-current="page"' : '' ?>>
                      <?= h((string)($item['label'] ?? '')) ?>
                    </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ol>
            </nav>
          </div>
        </section>

        <section class="<?= h($studyContentClass) ?>">
          <?php
            $kind = $selectedKind;
            $subtype = $selectedSubtype;
          ?>

          <?php if ($isQuizItem && !$finalExamBlocked): ?>
            <div class="quiz-app" id="appQuiz" data-course-id="<?= (int)$courseId ?>" data-node-id="<?= (int)$selected['id'] ?>" data-cmid="<?= (int)$quizCmid ?>">
              <div class="quiz-app__header">
                <div class="quiz-app__title">
                  <i class="fa-solid fa-list-check me-1"></i>
                  <?= h($kind === 'action' ? 'Prova final' : 'Questionario') ?>
                </div>
                <div class="quiz-app__meta">
                  <span class="quiz-app__page" id="appQuizPageLabel">Carregando...</span>
                  <span class="quiz-app__timer d-none" id="appQuizTimer"></span>
                </div>
              </div>

              <div id="appQuizAlert" class="quiz-app__alert"></div>
              <form id="appQuizForm" class="quiz-app__form" autocomplete="off">
                <div id="appQuizQuestions" class="quiz-app__questions"></div>
              </form>

              <div class="quiz-app__actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="appQuizPrev" disabled>
                  <i class="fa-solid fa-arrow-left me-1"></i> Anterior
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="appQuizNext" disabled>
                  Proxima pagina <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="appQuizFinish" disabled>
                  <i class="fa-solid fa-check me-1"></i> Finalizar tentativa
                </button>
                <button type="button" class="btn btn-warning btn-sm d-none" id="appQuizRetry" disabled>
                  <i class="fa-solid fa-rotate-right me-1"></i> Tentar novamente
                </button>
              </div>

              <?php if ($selectedQuizStatus && $selectedQuizStatus['grade'] !== null): ?>
                <div class="quiz-app__lastgrade">
                  Ultima nota registrada: <strong><?= h(number_format((float)$selectedQuizStatus['grade'], 2, ',', '.')) ?></strong>
                  <?php if ($selectedQuizStatus['pass_grade'] !== null): ?>
                    <span class="ms-1 text-muted">(minima: <?= h(number_format((float)$selectedQuizStatus['pass_grade'], 2, ',', '.')) ?>)</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

          <?php elseif ($finalExamBlocked): ?>
            <section class="study-certificate is-locked">
              <div class="study-certificate__title">
                <i class="fa-solid fa-hourglass-half me-1"></i> Prova final bloqueada por tempo minimo
              </div>
              <button type="button" class="btn btn-study-certificate" disabled>
                <i class="fa-solid fa-lock me-1"></i> Ainda nao liberada
              </button>
              <div class="study-certificate__hint">
                <?php if (!empty($finalExamGate['hours'])): ?>
                  A prova final so sera liberada apos <?= (int)$finalExamGate['hours'] ?> hora<?= ((int)$finalExamGate['hours'] === 1) ? '' : 's' ?> do primeiro acesso no curso.
                <?php else: ?>
                  A prova final ainda nao esta liberada.
                <?php endif; ?>
              </div>
              <?php if ($finalExamFirstAccessText !== ''): ?>
                <div class="study-certificate__hint">Primeiro acesso: <?= h($finalExamFirstAccessText) ?></div>
              <?php endif; ?>
              <?php if ($finalExamUnlockText !== ''): ?>
                <div class="study-certificate__hint">Liberacao prevista: <?= h($finalExamUnlockText) ?></div>
              <?php endif; ?>
            </section>

          <?php elseif ($isLessonItem): ?>
            <?php if ($selectedMoodleCmid <= 0 || $lessonEmbedUrl === ''): ?>
              <div class="alert alert-warning mb-0">Licao sem CMID configurado. Ajuste o item no Builder.</div>
            <?php else: ?>
              <div class="lesson-embed-wrap" id="appLessonEmbed" data-course-id="<?= (int)$courseId ?>" data-node-id="<?= (int)$selected['id'] ?>" data-cmid="<?= (int)$selectedMoodleCmid ?>" data-completed="<?= $isSelectedDone ? '1' : '0' ?>">
                <div class="lesson-embed__header">
                  <div class="lesson-embed__title">
                    <i class="fa-solid fa-book-open-reader me-1"></i> Exercicio da licao
                  </div>
                  <div class="lesson-embed__status is-open" id="appLessonEmbedStatus">Nao concluido</div>
                </div>
                <div id="appLessonEmbedAlert" class="lesson-embed__alert"></div>
                <div class="lesson-embed">
                  <iframe
                    id="appLessonEmbedFrame"
                    src="<?= h($lessonEmbedUrl) ?>"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                  ></iframe>
                </div>
              </div>
            <?php endif; ?>

          <?php elseif ($kind === 'content' && $subtype === 'video'): ?>
            <?php
              $url = (string)($contentJson['url'] ?? '');
              $provider = (string)($contentJson['provider'] ?? 'mp4');
              $videoMinPercent = (int)($contentJson['min_video_percent'] ?? $rulesJson['min_video_percent'] ?? 100);
              if ($videoMinPercent < 1 || $videoMinPercent > 100) $videoMinPercent = 100;
            ?>
            <?php if ($url === ''): ?>
              <div class="alert alert-warning mb-0">Video nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <div class="ratio ratio-16x9 mb-2">
                <?php [$videoUrl, $isIframe] = $normalizeVideo($url, $provider); ?>
                <?php if ($isIframe): ?>
                  <iframe
                    id="playerIframe"
                    src="<?= h($videoUrl) ?>"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    data-provider="<?= h($provider) ?>"
                    data-course="<?= (int)$courseId ?>"
                    data-node="<?= (int)$selected['id'] ?>"
                    data-lastpos="<?= (int)($progress['last_position'] ?? 0) ?>"
                    data-minpercent="<?= (int)$videoMinPercent ?>"
                  ></iframe>
                <?php else: ?>
                  <video id="player" controls playsinline preload="metadata" data-course="<?= (int)$courseId ?>" data-node="<?= (int)$selected['id'] ?>" data-lastpos="<?= (int)($progress['last_position'] ?? 0) ?>" data-minpercent="<?= (int)$videoMinPercent ?>">
                    <source src="<?= h($videoUrl) ?>">
                  </video>
                <?php endif; ?>
              </div>
              <div class="study-video-actions d-md-none">
                <button type="button" class="btn study-action-btn study-video-toggle" id="btnVideoPlayPause" data-paused="1">
                  <i class="fa-solid fa-play me-1" data-icon></i>
                  <span data-label>Reproduzir video</span>
                </button>
              </div>
            <?php endif; ?>

          <?php elseif ($kind === 'content' && $subtype === 'text'): ?>
            <?php $html = (string)($contentJson['html'] ?? ''); ?>
            <?php if ($html === ''): ?>
              <div class="alert alert-warning mb-0">Texto nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <article class="lesson-article"><?= $html ?></article>
            <?php endif; ?>

          <?php elseif ($kind === 'content' && $subtype === 'pdf'): ?>
            <?php
              $path = (string)($contentJson['file_path'] ?? ($contentJson['url'] ?? ''));
              $pdfUrl = $assetUrl($path);
              $pdfTitle = trim((string)($selected['title'] ?? ''));
              if ($pdfTitle === '') {
                $pdfTitle = trim((string)($contentJson['label'] ?? $contentJson['title'] ?? ''));
              }
              if ($pdfTitle === '') {
                $pdfTitle = 'Material em PDF';
              }
            ?>
            <?php if ($pdfUrl === ''): ?>
              <div class="alert alert-warning mb-0">PDF nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <section class="study-pdf-card">
                <div class="study-pdf-card__icon">
                  <i class="fa-solid fa-file-pdf"></i>
                </div>
                <div class="study-pdf-card__body">
                  <h3 class="study-pdf-card__title"><?= h($pdfTitle) ?></h3>
                </div>
                <div class="study-pdf-card__actions">
                  <a class="btn study-pdf-btn study-pdf-btn--primary js-study-pdf-touch" href="<?= h($pdfUrl) ?>" download data-course="<?= (int)$courseId ?>" data-node="<?= (int)($selected['id'] ?? 0) ?>">
                    <i class="fa-solid fa-download me-1"></i> Baixar PDF
                  </a>
                  <a class="btn study-pdf-btn study-pdf-btn--secondary js-study-pdf-touch" href="<?= h($pdfUrl) ?>" target="_blank" rel="noopener" data-course="<?= (int)$courseId ?>" data-node="<?= (int)($selected['id'] ?? 0) ?>">
                    <i class="fa-solid fa-up-right-from-square me-1"></i> Abrir em nova aba
                  </a>
                </div>
              </section>
            <?php endif; ?>

          <?php elseif ($kind === 'content' && $subtype === 'download'): ?>
            <?php
              $path = (string)($contentJson['file_path'] ?? ($contentJson['url'] ?? ''));
              $downloadLabel = (string)($contentJson['label'] ?? 'Baixar arquivo');
              $downloadUrl = $assetUrl($path);
            ?>
            <?php if ($downloadUrl === ''): ?>
              <div class="alert alert-warning mb-0">Download nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <i class="fa-solid fa-download text-primary"></i>
                <div>
                  <div class="fw-semibold"><?= h($downloadLabel) ?></div>
                  <div class="small text-muted"><?= h($path) ?></div>
                </div>
                <a
                  class="btn btn-primary btn-sm ms-auto js-study-manual-touch"
                  href="<?= h($downloadUrl) ?>"
                  target="_blank"
                  rel="noopener"
                  data-course="<?= (int)$courseId ?>"
                  data-node="<?= (int)($selected['id'] ?? 0) ?>"
                  data-percent="1"
                  data-position="1"
                >Baixar</a>
              </div>
            <?php endif; ?>

          <?php elseif ($kind === 'content' && $subtype === 'link'): ?>
            <?php
              $rawLink = (string)($contentJson['url'] ?? '');
              $linkLabel = (string)($contentJson['label'] ?? 'Abrir link externo');
              $linkUrl = $assetUrl($rawLink);
              $linkHost = $linkUrl !== '' ? (string)parse_url($linkUrl, PHP_URL_HOST) : '';
            ?>
            <?php if ($linkUrl === ''): ?>
              <div class="alert alert-warning mb-0">Link nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <section class="study-link-card">
                <div class="study-link-card__icon">
                  <i class="fa-solid fa-book-open"></i>
                </div>
                <div class="study-link-card__body">
                  <div class="study-link-card__eyebrow">Material de apoio</div>
                  <h3 class="study-link-card__title"><?= h($linkLabel) ?></h3>
                  <div class="study-link-card__subtitle">
                    <?= $linkHost !== '' ? h($linkHost) : 'Link externo liberado para consulta em nova aba.' ?>
                  </div>
                </div>
                <div class="study-link-card__actions">
                  <a
                    class="btn study-link-btn study-link-btn--primary js-study-manual-touch"
                    href="<?= h($linkUrl) ?>"
                    target="_blank"
                    rel="noopener"
                    data-course="<?= (int)$courseId ?>"
                    data-node="<?= (int)($selected['id'] ?? 0) ?>"
                    data-percent="1"
                    data-position="1"
                  >
                    <i class="fa-solid fa-up-right-from-square me-1"></i> Abrir material
                  </a>
                </div>
              </section>
            <?php endif; ?>

          <?php elseif ($kind === 'action' && $subtype === 'final_exam'): ?>
            <div class="alert alert-warning mb-0">
              Prova final sem CMID configurado. Informe o CMID do quiz no Builder para executar dentro do app.
            </div>

          <?php elseif ($kind === 'action' && $subtype === 'certificate'): ?>
            <section class="study-certificate <?= $certificateReady ? 'is-ready' : 'is-locked' ?>">
              <div class="study-certificate__title">
                <i class="fa-solid fa-certificate me-1"></i> Certificado de conclusao
              </div>
              <?php if ($certificateReady): ?>
                <button
                  type="button"
                  class="btn btn-study-certificate"
                  id="btnGenerateCertificate"
                  data-course="<?= (int)$courseId ?>"
                  data-node="<?= (int)($selected['id'] ?? 0) ?>"
                  data-url="<?= h($certificateUrl) ?>"
                >
                  <i class="fa-solid fa-award me-1"></i> Gerar certificado
                </button>
              <?php else: ?>
                <button type="button" class="btn btn-study-certificate" disabled>
                  <i class="fa-solid fa-lock me-1"></i> Certificado indisponivel
                </button>
                <?php if ($certificateBlockReason !== ''): ?>
                  <div class="study-certificate__hint"><?= h($certificateBlockReason) ?></div>
                <?php endif; ?>
              <?php endif; ?>
            </section>

          <?php else: ?>
            <div class="alert alert-secondary mb-0">Tipo de conteudo ainda nao suportado.</div>
          <?php endif; ?>

          <input type="hidden" id="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" id="selectedCourseId" value="<?= (int)$courseId ?>">
          <input type="hidden" id="selectedNodeId" value="<?= (int)($selected['id'] ?? 0) ?>">
          <input type="hidden" id="studyTotalItems" value="<?= (int)$totalItems ?>">
          <input type="hidden" id="studyDoneItems" value="<?= (int)$doneItems ?>">
          <input type="hidden" id="studySelectedDone" value="<?= $isSelectedDone ? '1' : '0' ?>">

          <?php if ($finalExamBlocked): ?>
            <div class="quiz-app__note">
              A prova final sera liberada em <?= h($finalExamUnlockText !== '' ? $finalExamUnlockText : 'breve') ?>.
            </div>
          <?php elseif (!$isSelectedDone && $isQuizItem): ?>
            <div class="quiz-app__note">
              <?php if ($selectedQuizStatus && !empty($selectedQuizStatus['pass_required']) && $selectedQuizStatus['pass_grade'] !== null): ?>
                Este item so sera concluido quando a nota minima configurada no Moodle for atingida (<?= h(number_format((float)$selectedQuizStatus['pass_grade'], 2, ',', '.')) ?>).
              <?php else: ?>
                Este item so sera concluido quando os criterios do questionario forem atendidos.
              <?php endif; ?>
            </div>
          <?php elseif (!$isSelectedDone && $isLessonItem): ?>
            <div class="quiz-app__note">
              Este item sera concluido automaticamente quando a licao for concluida nesta tela.
            </div>
          <?php endif; ?>

        </section>
      <?php endif; ?>
    </main>
  </div>

  <?php if ($selected): ?>
    <nav class="study-nav-footer">
      <div class="nav-actions">
        <?php if ($prevNode): ?>
          <a class="btn study-footer-btn study-footer-btn--prev" href="<?= h(App::base_url('/course/' . $courseId . '?node=' . (int)$prevNode['id'] . '&autoplay=1')) ?>">
            <i class="fa-solid fa-arrow-left me-1"></i> Anterior
          </a>
        <?php else: ?>
          <button class="btn study-footer-btn study-footer-btn--prev" disabled>
            <i class="fa-solid fa-arrow-left me-1"></i> Anterior
          </button>
        <?php endif; ?>

        <button
          type="button"
          class="btn study-footer-btn <?= h($footerActionClass) ?>"
          id="btnMarkDone"
          data-course="<?= (int)$courseId ?>"
          data-node="<?= (int)$selected['id'] ?>"
          data-unlocked="<?= $manualCompletionReady ? '1' : '0' ?>"
          data-current-percent="<?= (int)($progress['percent'] ?? 0) ?>"
          data-current-position="<?= (int)($progress['last_position'] ?? 0) ?>"
          data-next-url="<?= h($footerActionNextUrl) ?>"
          data-mode="<?= h($footerActionMode) ?>"
          <?= $footerActionDisabled ? 'disabled' : '' ?>
        >
          <i class="fa-solid <?= h($footerActionIcon) ?> me-1"></i> <?= h($footerActionLabel) ?>
        </button>
      </div>
      <?php if ($sequenceLockEnabled && $nextNode && !$isSelectedDone && $manualCompletionEligible && $selectedSequential): ?>
        <div class="small text-muted mt-2" id="studyNextLockMessage">
          Para avancar, conclua esta aula ou tarefa.
        </div>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

