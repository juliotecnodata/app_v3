<?php
use App\App;

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
$remainingItems = max(0, $totalItems - $doneItems);
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
$selectedKind = (string)($selected['kind'] ?? '');
$selectedSubtype = (string)($selected['subtype'] ?? '');
$isCertificateItem = $selectedKind === 'action' && $selectedSubtype === 'certificate';
$selectedTypeLabel = $selected ? $typeLabel($selectedKind, $selectedSubtype) : '';

$selectedNumber = ($showNumbering && $selectedId) ? ($numberMap[$selectedId] ?? '') : '';
$breadcrumbItems = [];
$selectedModuleId = 0;

if ($selectedId > 0) {
  $path = [];
  $findPath($tree['root'] ?? [], $selectedId, $path);

  $moduleNode = null;
  foreach ($path as $step) {
    if ((string)($step['kind'] ?? '') === 'container' && (string)($step['subtype'] ?? '') === 'module') {
      $moduleNode = $step;
      break;
    }
  }

  if (!$moduleNode) {
    foreach ($path as $step) {
      if ((string)($step['kind'] ?? '') === 'container' && (string)($step['subtype'] ?? '') !== 'root') {
        $moduleNode = $step;
        break;
      }
    }
  }

  if ($moduleNode) {
    $selectedModuleId = (int)($moduleNode['id'] ?? 0);
  }

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

$moduleStats = [];
$ensureModuleStats = function (int $moduleId, string $title, string $number = '') use (&$moduleStats): void {
  if (!isset($moduleStats[$moduleId])) {
    $moduleStats[$moduleId] = [
      'id' => $moduleId,
      'title' => $title,
      'number' => $number,
      'total' => 0,
      'done' => 0,
    ];
  }
};

$buildModuleStats = function (array $nodes, ?array $moduleContext = null) use (&$buildModuleStats, &$moduleStats, $numberMap, $ensureModuleStats, $showNumbering): void {
  foreach ($nodes as $node) {
    if ((int)($node['is_published'] ?? 0) !== 1) continue;

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    $context = $moduleContext;

    if ($kind === 'container' && $subtype === 'module') {
      $moduleId = (int)($node['id'] ?? 0);
      $context = [
        'id' => $moduleId,
        'title' => (string)($node['title'] ?? 'Modulo'),
        'number' => $showNumbering ? (string)($numberMap[$moduleId] ?? '') : '',
      ];
      $ensureModuleStats($moduleId, $context['title'], $context['number']);
    }

    if ($kind !== 'container') {
      if ($context) {
        $moduleId = (int)$context['id'];
        $ensureModuleStats($moduleId, (string)$context['title'], (string)$context['number']);
        $moduleStats[$moduleId]['total']++;
        if (!empty($node['meta_completed'])) {
          $moduleStats[$moduleId]['done']++;
        }
      } else {
        $ensureModuleStats(0, 'Trilha geral', '');
        $moduleStats[0]['total']++;
        if (!empty($node['meta_completed'])) {
          $moduleStats[0]['done']++;
        }
      }
    }

    if (!empty($node['children']) && is_array($node['children'])) {
      $buildModuleStats($node['children'], $context);
    }
  }
};
$buildModuleStats($tree['root'] ?? [], null);
$moduleStatsList = array_values($moduleStats);

$containsSelected = function (array $node, int $selectedId) use (&$containsSelected): bool {
  if ((int)($node['id'] ?? 0) === $selectedId) return true;
  if (empty($node['children']) || !is_array($node['children'])) return false;
  foreach ($node['children'] as $child) {
    if ($containsSelected($child, $selectedId)) return true;
  }
  return false;
};

$renderTrail = function (array $nodes, int $depth, int $selectedId, array $numberMap, callable $typeLabel, callable $typeIcon, callable $nodeUrl, callable $containsSelected, bool $showNumbering) use (&$renderTrail) {
  foreach ($nodes as $node) {
    if ((int)($node['is_published'] ?? 0) !== 1) continue;

    $kind = (string)($node['kind'] ?? '');
    $subtype = (string)($node['subtype'] ?? '');
    $id = (int)($node['id'] ?? 0);
    $number = $numberMap[$id] ?? '';

    if ($kind === 'container') {
      if ($subtype === 'root') {
        if (!empty($node['children']) && is_array($node['children'])) {
          $renderTrail($node['children'], $depth + 1, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering);
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

      echo '<section class="trail-container' . ($isActivePath ? ' is-active-path' : '') . ($isCollapsible ? ' is-collapsible' : '') . ($isOpen ? ' is-open' : ' is-collapsed') . '" data-node-id="' . (int)$id . '" data-collapsible="' . ($isCollapsible ? '1' : '0') . '" data-search="' . h($searchLabel) . '">';
      echo '<div class="trail-group" style="padding-left:' . (int)$indentPx . 'px;">';
      echo '<div class="trail-group-main">';
      echo '<span class="trail-group-kind">' . h($label) . '</span>';
      if ($showNumbering && $number !== '') echo '<span class="trail-group-num">' . h($number) . '</span>';
      echo '<span class="trail-group-title">' . h($nodeTitle) . '</span>';
      echo '</div>';
      echo '<span class="trail-group-line"></span>';
      if ($isCollapsible) {
        echo '<button type="button" class="trail-group-toggle" aria-label="Expandir ou recolher" aria-expanded="' . ($isOpen ? 'true' : 'false') . '"><i class="fa-solid fa-chevron-down"></i></button>';
      }
      echo '</div>';

      if ($hasChildren) {
        echo '<div class="trail-container-children">';
        $renderTrail($node['children'], $depth + 1, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering);
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
$renderTrail($tree['root'] ?? [], 0, $selectedId, $numberMap, $typeLabel, $typeIcon, $nodeUrl, $containsSelected, $showNumbering);
$trailHtml = ob_get_clean();
?>

<div class="study-shell">
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
      <div class="study-meta-chip"><i class="fa-solid fa-layer-group me-1"></i><?= (int)$totalItems ?> itens</div>
      <div class="study-meta-chip"><i class="fa-solid fa-check me-1"></i><?= (int)$doneItems ?> concluidos</div>
      <div class="study-meta-chip"><i class="fa-solid fa-list-check me-1"></i><?= (int)$remainingItems ?> restantes</div>
    </div>

    <div class="study-header-right">
      <div class="study-progress-wrap">
        <div class="progress study-progress" role="progressbar" aria-label="Progresso do curso" aria-valuenow="<?= (int)$courseProgress ?>" aria-valuemin="0" aria-valuemax="100">
          <div id="studyProgressBar" class="progress-bar" style="width:<?= (int)$courseProgress ?>%"></div>
        </div>
        <span id="studyProgressLabel" class="study-progress-label"><?= (int)$courseProgress ?>%</span>
      </div>
      <a href="<?= h(App::base_url('/dashboard')) ?>" class="btn btn-sm btn-light study-back-btn ms-2">
        <i class="fa-solid fa-house me-1"></i>Painel
      </a>
    </div>
  </header>

  <div class="study-layout">
    <aside class="study-sidebar d-none d-md-flex">
      <div class="study-sidebar__header">
        <div class="study-sidebar__title"><i class="fa-solid fa-list-ul me-1"></i>Trilha do curso</div>
        <input id="trailSearchDesktop" type="text" class="form-control form-control-sm study-search" placeholder="Buscar aula ou modulo">
        <div class="trail-actions mt-2">
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
        <input id="trailSearchMobile" type="text" class="form-control form-control-sm study-search mb-2" placeholder="Buscar aula ou modulo">
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
      <section class="study-overview mb-3">
        <div class="study-overview__head">
          <div>
            <div class="study-overview__title">Visao de progresso</div>
            <div class="study-overview__subtitle">Acompanhe o curso e cada modulo em tempo real.</div>
          </div>
          <div class="study-overview__total"><?= (int)$doneItems ?>/<?= (int)$totalItems ?></div>
        </div>

        <?php if (!empty($moduleStatsList)): ?>
          <div class="study-overview__modules">
            <?php foreach ($moduleStatsList as $module): ?>
              <?php
                $moduleId = (int)($module['id'] ?? 0);
                $moduleTotal = (int)($module['total'] ?? 0);
                $moduleDone = (int)($module['done'] ?? 0);
                $modulePct = $moduleTotal > 0 ? (int)floor(($moduleDone / $moduleTotal) * 100) : 0;
                $moduleName = (string)($module['title'] ?? 'Modulo');
                $moduleNumber = (string)($module['number'] ?? '');
                $isCurrentModule = ($selectedModuleId > 0 && $moduleId > 0 && $selectedModuleId === $moduleId);
              ?>
              <article class="study-module-card <?= $isCurrentModule ? 'is-current' : '' ?>">
                <div class="study-module-card__head">
                  <div class="study-module-card__name">
                    <?php if ($moduleNumber !== ''): ?>
                      <span class="study-module-card__num"><?= h($moduleNumber) ?></span>
                    <?php endif; ?>
                    <span><?= h($moduleName) ?></span>
                  </div>
                  <span class="study-module-card__pct"><?= (int)$modulePct ?>%</span>
                </div>
                <div class="study-module-card__bar">
                  <div class="study-module-card__fill" style="width:<?= (int)$modulePct ?>%;"></div>
                </div>
                <div class="study-module-card__meta"><?= (int)$moduleDone ?>/<?= (int)$moduleTotal ?> concluidos</div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

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

        <section class="study-context mb-3">
          <div class="study-context__chips">
            <span class="study-context-chip"><i class="fa-solid fa-book-open me-1"></i><?= h($selectedTypeLabel) ?></span>
            <?php if ($selectedNumber !== ''): ?>
              <span class="study-context-chip"><i class="fa-solid fa-hashtag me-1"></i><?= h($selectedNumber) ?></span>
            <?php endif; ?>
            <span class="study-context-chip"><i class="fa-solid fa-chart-line me-1"></i>Curso <?= (int)$courseProgress ?>%</span>
          </div>
          <div class="study-context__status <?= $isSelectedDone ? 'is-done' : 'is-open' ?>">
            <?php if ($isSelectedDone): ?>
              <i class="fa-solid fa-circle-check me-1"></i>Item concluido
            <?php else: ?>
              <i class="fa-solid fa-circle-play me-1"></i>Item em andamento
            <?php endif; ?>
          </div>
        </section>

        <section class="study-content">
          <?php
            $kind = (string)($selected['kind'] ?? 'content');
            $subtype = (string)($selected['subtype'] ?? 'text');
          ?>

          <?php if ($kind === 'content' && $subtype === 'video'): ?>
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
            ?>
            <?php if ($pdfUrl === ''): ?>
              <div class="alert alert-warning mb-0">PDF nao configurado. Ajuste no Builder.</div>
            <?php else: ?>
              <div class="mb-2 d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold"><i class="fa-solid fa-file-pdf text-danger me-1"></i>Material PDF</span>
                <a class="btn btn-outline-secondary btn-sm" href="<?= h($pdfUrl) ?>" target="_blank" rel="noopener">Abrir</a>
                <a class="btn btn-primary btn-sm" href="<?= h($pdfUrl) ?>" download>Baixar</a>
              </div>
              <div class="ratio ratio-16x9"><iframe src="<?= h($pdfUrl) ?>" loading="lazy"></iframe></div>
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
                <a class="btn btn-primary btn-sm ms-auto" href="<?= h($downloadUrl) ?>" target="_blank" rel="noopener">Baixar</a>
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
                  <a class="btn study-link-btn study-link-btn--primary" href="<?= h($linkUrl) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-up-right-from-square me-1"></i> Abrir material
                  </a>
                </div>
              </section>
            <?php endif; ?>

          <?php elseif ($kind === 'action' && $subtype === 'final_exam'): ?>
            <?php
              $quizUrl = null;
              if (!empty($exam['quiz_cmid'])) {
                global $CFG;
                $quizUrl = rtrim((string)$CFG->wwwroot, '/') . '/mod/quiz/view.php?id=' . (int)$exam['quiz_cmid'];
              }
            ?>
            <?php if ($quizUrl): ?>
              <div class="alert alert-info mb-2">Prova final do curso.</div>
              <div class="quiz-embed mb-3">
                <iframe id="quizFrame" src="<?= h($quizUrl) ?>" allow="fullscreen" loading="lazy"></iframe>
              </div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="btn btn-outline-secondary" href="<?= h($quizUrl) ?>" target="_blank" rel="noopener">Abrir em nova aba</a>
                <?php if ($examStatus && $examStatus['grade'] !== null): ?>
                  <span class="badge bg-success-subtle text-success">Nota: <?= h(number_format((float)$examStatus['grade'], 2, ',', '.')) ?></span>
                <?php endif; ?>
                <?php if ($examStatus && $examStatus['min_grade'] !== null): ?>
                  <span class="badge bg-light text-dark">Minima: <?= h(number_format((float)$examStatus['min_grade'], 2, ',', '.')) ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="alert alert-warning mb-0">Quiz nao configurado. Defina o CMID no Builder.</div>
            <?php endif; ?>

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

          <?php if (!$isSelectedDone && !$isCertificateItem): ?>
            <button class="btn btn-study-done" id="btnMarkDone" data-course="<?= (int)$courseId ?>" data-node="<?= (int)$selected['id'] ?>">
              <i class="fa-solid fa-check me-1"></i> Marcar como concluido
            </button>
          <?php elseif ($isCertificateItem): ?>
            
          <?php else: ?>
            <button class="btn btn-outline-secondary w-100 mt-3" disabled>
              <i class="fa-solid fa-check me-1"></i> Aula concluida
            </button>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <?php if ($selected): ?>
    <nav class="study-nav-footer">
      <div class="nav-actions">
        <?php if ($prevNode): ?>
          <a class="btn btn-outline-secondary js-study-link" href="<?= h(App::base_url('/course/' . $courseId . '?node=' . (int)$prevNode['id'] . '&autoplay=1')) ?>">
            <i class="fa-solid fa-arrow-left me-1"></i> Anterior
          </a>
        <?php else: ?>
          <button class="btn btn-outline-secondary" disabled>
            <i class="fa-solid fa-arrow-left me-1"></i> Anterior
          </button>
        <?php endif; ?>

        <?php if ($nextNode && $isSelectedDone): ?>
          <a class="btn btn-primary js-study-link" href="<?= h(App::base_url('/course/' . $courseId . '?node=' . (int)$nextNode['id'] . '&autoplay=1')) ?>">
            Proximo <i class="fa-solid fa-arrow-right ms-1"></i>
          </a>
        <?php elseif ($nextNode): ?>
          <button id="btnNextLocked" type="button" class="btn btn-primary" data-message="Conclua esta aula ou tarefa para liberar o proximo item." data-next-url="<?= h(App::base_url('/course/' . $courseId . '?node=' . (int)$nextNode['id'] . '&autoplay=1')) ?>">
            Concluir para liberar proximo
          </button>
        <?php else: ?>
          <button class="btn btn-primary" disabled>
            Proximo <i class="fa-solid fa-arrow-right ms-1"></i>
          </button>
        <?php endif; ?>
      </div>
      <?php if ($nextNode && !$isSelectedDone): ?>
        <div class="small text-muted mt-2">
          Para avancar, conclua esta aula ou tarefa.
        </div>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
