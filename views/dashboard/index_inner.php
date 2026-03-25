<?php
use App\App;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$courses = $courses ?? [];
$ebookShelf = $ebookShelf ?? [];
$totalCourses = count($courses);
$totalEbooks = count($ebookShelf);

$sumPercent = 0;
$completed = 0;
$inProgress = 0;
$notStarted = 0;
$expiringSoon = 0;
$availableCourses = 0;

$nextCourse = null;
$bestCourse = null;
$bestPercent = -1;

foreach ($courses as $course) {
  $percent = max(0, min(100, (int)($course['progress_percent'] ?? 0)));
  $canEnter = (bool)($course['_canEnter'] ?? true);
  $sumPercent += $percent;

  if ($canEnter) {
    $availableCourses++;
  }

  if ($percent >= 100) {
    $completed++;
  } else if ($percent > 0) {
    $inProgress++;
  } else {
    $notStarted++;
  }

  $daysLeft = $course['_daysLeft'] ?? null;
  if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 3) {
    $expiringSoon++;
  }

  if ($nextCourse === null && $canEnter && !empty($course['_continueNodeId'])) {
    $nextCourse = $course;
  }

  if ($percent > $bestPercent) {
    $bestPercent = $percent;
    $bestCourse = $course;
  }
}

if ($nextCourse === null && !empty($courses)) {
  foreach ($courses as $course) {
    if (!empty($course['_canEnter'])) {
      $nextCourse = $course;
      break;
    }
  }
}

$avgPercent = $totalCourses > 0 ? (int)floor($sumPercent / $totalCourses) : 0;
$featuredCourse = $nextCourse ?: $bestCourse;

$primaryActionUrl = '';
$primaryActionLabel = 'Continuar';
$primaryActionIcon = 'fa-forward-step';

if ($featuredCourse) {
  $featuredCourseId = (int)($featuredCourse['id'] ?? 0);
  $primaryActionUrl = App::base_url('/course/' . $featuredCourseId);
  if (!empty($featuredCourse['_continueNodeId'])) {
    $primaryActionUrl = App::base_url('/course/' . $featuredCourseId . '?node=' . (int)$featuredCourse['_continueNodeId']);
  }
}

$heroTitle = $featuredCourse
  ? 'Retome seus estudos'
  : 'Painel de estudos';
$heroText = $featuredCourse
  ? 'Entre rapido no curso certo e acompanhe seu progresso sem excesso de informacao.'
  : 'Acesse seus cursos e materiais extras com uma navegacao mais objetiva.';
?>

<div class="home-v6">
  <section class="home-v6__hero-shell">
    <div class="home-v6__hero-card">
      <div class="home-v6__hero-main">
        <span class="home-v6__eyebrow">Area do aluno</span>
        <h1 class="home-v6__title"><?= h($heroTitle) ?></h1>
        <p class="home-v6__subtitle"><?= h($heroText) ?></p>

        <div class="home-v6__hero-meta">
          <span class="home-v6__hero-pill">
            <i class="fa-solid fa-graduation-cap"></i>
            <?= (int)$availableCourses ?> curso(s) no app
          </span>
          <span class="home-v6__hero-pill home-v6__hero-pill--soft">
            <i class="fa-solid fa-chart-simple"></i>
            media <?= (int)$avgPercent ?>%
          </span>
          <?php if ($totalEbooks > 0): ?>
            <span class="home-v6__hero-pill home-v6__hero-pill--soft">
              <i class="fa-solid fa-book-open"></i>
              <?= (int)$totalEbooks ?> material(is)
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="home-v6__hero-actions">
        <?php if ($primaryActionUrl !== ''): ?>
          <a class="btn home-v6__btn home-v6__btn--primary" href="<?= h($primaryActionUrl) ?>">
            <i class="fa-solid <?= h($primaryActionIcon) ?>"></i>
            <span><?= h($primaryActionLabel) ?></span>
          </a>
        <?php else: ?>
          <button class="btn home-v6__btn home-v6__btn--primary" type="button" disabled>
            <i class="fa-solid fa-lock"></i>
            <span>Continuar</span>
          </button>
        <?php endif; ?>

        <a class="btn home-v6__btn home-v6__btn--ghost" href="<?= h(App::base_url('/dashboard')) ?>">
          <i class="fa-solid fa-rotate"></i>
          <span>Atualizar</span>
        </a>
      </div>
    </div>
  </section>

  <div class="home-v6__layout">
    <aside class="home-v6__sidebar">
      <section class="home-v6__panel home-v6__panel--stats">
        <span class="home-v6__panel-label">Resumo rapido</span>
        <div class="home-v6__stats">
          <div class="home-v6__stat">
            <span class="home-v6__stat-label">Disponiveis</span>
            <strong class="home-v6__stat-value"><?= (int)$availableCourses ?></strong>
          </div>
          <div class="home-v6__stat">
            <span class="home-v6__stat-label">Media</span>
            <strong class="home-v6__stat-value"><?= (int)$avgPercent ?>%</strong>
          </div>
          <div class="home-v6__stat">
            <span class="home-v6__stat-label">Andamento</span>
            <strong class="home-v6__stat-value"><?= (int)$inProgress ?></strong>
          </div>
          <div class="home-v6__stat">
            <span class="home-v6__stat-label">Concluidos</span>
            <strong class="home-v6__stat-value"><?= (int)$completed ?></strong>
          </div>
        </div>
      </section>

      <section class="home-v6__panel home-v6__panel--filters home-v6__panel--filters-desktop">
        <span class="home-v6__panel-label">Filtrar cursos</span>
        <div class="home-v6__filters" role="tablist" aria-label="Filtro de cursos desktop">
          <button type="button" class="home-v6__filter is-active" data-filter="all">Todos</button>
          <button type="button" class="home-v6__filter" data-filter="progress">Em andamento</button>
          <button type="button" class="home-v6__filter" data-filter="done">Concluidos</button>
          <button type="button" class="home-v6__filter" data-filter="expiring">Expirando</button>
          <button type="button" class="home-v6__filter" data-filter="new">Nao iniciados</button>
        </div>
      </section>
    </aside>

    <section class="home-v6__content">
      <div class="home-v6__content-stack">
        <section class="home-v6__section">
          <header class="home-v6__content-head">
            <div>
              <div class="home-v6__content-kicker">Seus cursos</div>
              <h2 class="home-v6__content-title">Cursos disponiveis no app</h2>
            </div>
            <div class="home-v6__content-note">
              <?= $totalCourses > 0 ? h((string)$totalCourses . ' curso(s) mapeado(s)') : 'Sem cursos disponiveis' ?>
            </div>
          </header>

          <?php if (!empty($courses)): ?>
            <div class="home-v6__filters home-v6__filters--mobile" role="tablist" aria-label="Filtro de cursos mobile">
              <button type="button" class="home-v6__filter is-active" data-filter="all">Todos</button>
              <button type="button" class="home-v6__filter" data-filter="progress">Andamento</button>
              <button type="button" class="home-v6__filter" data-filter="done">Concluidos</button>
              <button type="button" class="home-v6__filter" data-filter="expiring">Expirando</button>
              <button type="button" class="home-v6__filter" data-filter="new">Novos</button>
            </div>
          <?php endif; ?>

          <?php if (empty($courses)): ?>
            <section class="home-v6__empty">
              <div class="home-v6__empty-icon"><i class="fa-solid fa-circle-info"></i></div>
              <h2>Nenhum curso disponivel</h2>
              <p>Se voce ja esta matriculado no Moodle, solicite ao admin o mapeamento para aparecer aqui.</p>
            </section>
          <?php else: ?>
            <section class="home-v6__grid" id="dashGrid">
              <?php foreach ($courses as $index => $course): ?>
            <?php
              $courseId = (int)$course['id'];
              $percent = max(0, min(100, (int)($course['progress_percent'] ?? 0)));
              $daysLeft = $course['_daysLeft'] ?? null;
              $expiresAt = $course['_expiresAt'] ?? null;
              $lmsAccessState = (string)($course['_lmsAccessState'] ?? 'unknown');
              $lmsAccessLabel = (string)($course['_lmsAccessLabel'] ?? 'Sem prazo de acesso informado no Moodle');
              $canEnter = (bool)($course['_canEnter'] ?? true);
              $isBlocked = !empty($course['_isBlocked']);
              $blockTitle = (string)($course['_blockTitle'] ?? 'Acesso bloqueado');
              $blockMessage = (string)($course['_blockMessage'] ?? '');

              $state = 'new';
              if ($isBlocked) {
                $state = 'blocked';
              } else {
                if ($percent >= 100) {
                  $state = 'done';
                } else if ($percent > 0) {
                  $state = 'progress';
                }
                if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 3) {
                  $state = 'expiring';
                }
                if (($daysLeft !== null && $daysLeft < 0) || !$canEnter) {
                  $state = 'expired';
                }
              }

              $stateLabel = 'Nao iniciado';
              if ($state === 'blocked') $stateLabel = 'Bloqueado';
              else if ($state === 'done') $stateLabel = 'Concluido';
              else if ($state === 'progress') $stateLabel = 'Em andamento';
              else if ($state === 'expiring') $stateLabel = 'Expira em breve';
              else if ($state === 'expired') $stateLabel = !$canEnter && $lmsAccessState === 'blocked' ? 'Acesso bloqueado' : 'Expirado';

              $lmsIcon = 'fa-regular fa-clock';
              if ($lmsAccessState === 'active') $lmsIcon = 'fa-solid fa-shield-halved';
              else if ($lmsAccessState === 'expiring') $lmsIcon = 'fa-solid fa-hourglass-half';
              else if ($lmsAccessState === 'expired') $lmsIcon = 'fa-solid fa-ban';
              else if ($lmsAccessState === 'unlimited') $lmsIcon = 'fa-solid fa-infinity';
              else if ($lmsAccessState === 'local') $lmsIcon = 'fa-solid fa-link';
              else if ($lmsAccessState === 'blocked') $lmsIcon = 'fa-solid fa-lock';

              $title = (string)($course['title'] ?? 'Curso');
              $slug = (string)($course['slug'] ?? '');
              $continueUrl = App::base_url('/course/' . $courseId);
              if (!empty($course['_continueNodeId'])) {
                $continueUrl = App::base_url('/course/' . $courseId . '?node=' . (int)$course['_continueNodeId']);
              }
              $entryLabel = !empty($course['_continueNodeId']) ? 'Continuar' : 'Iniciar';
              $entryIcon = !empty($course['_continueNodeId']) ? 'fa-forward-step' : 'fa-play';
              $certificateReady = !empty($course['_certificate_ready']);
              $certificateUrl = (string)($course['_certificate_url'] ?? '');
              $certificateNodeId = (int)($course['_certificate_node_id'] ?? 0);
              $daysLeftLabel = '';
              $expiryDateLabel = '';
              if ($expiresAt) {
                $expiryDateLabel = date('d/m/Y', (int)$expiresAt);
              }
              if ($daysLeft !== null && $daysLeft >= 0 && $state !== 'expired') {
                if ((int)$daysLeft === 0) {
                  $daysLeftLabel = 'Ultimo dia';
                } else if ((int)$daysLeft === 1) {
                  $daysLeftLabel = '1 dia restante';
                } else {
                  $daysLeftLabel = (int)$daysLeft . ' dias restantes';
                }
              }

              $imageRaw = trim((string)($course['image'] ?? ''));
              $imageSrc = '';
              if ($imageRaw !== '') {
                if (preg_match('#^https?://#i', $imageRaw) === 1) {
                  $imageSrc = $imageRaw;
                } else {
                  $imageSrc = App::base_url('/' . ltrim($imageRaw, '/'));
                }
              }
            ?>
              <article class="home-v6__course" style="--i: <?= (int)$index ?>;" data-state="<?= h($state) ?>">
                <div class="home-v6__course-media">
                  <?php if ($imageSrc !== ''): ?>
                    <img src="<?= h($imageSrc) ?>" alt="<?= h($title) ?>">
                  <?php else: ?>
                    <div class="home-v6__course-fallback">
                      <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="home-v6__course-body">
                  <div class="home-v6__course-top">
                    <div class="home-v6__course-head">
                      <div class="home-v6__course-title"><?= h($title) ?></div>
                      <?php if ($slug !== ''): ?>
                        <div class="home-v6__course-slug"><?= h($slug) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="home-v6__course-percent"><?= (int)$percent ?>%</div>
                  </div>

                  <div class="home-v6__course-row">
                    <span class="home-v6__badge home-v6__badge--<?= h($state) ?>"><?= h($stateLabel) ?></span>
                    <?php if ($daysLeftLabel !== ''): ?>
                      <span class="home-v6__course-deadline<?= ($daysLeft !== null && (int)$daysLeft <= 3) ? ' home-v6__course-deadline--warning' : '' ?>">
                        <i class="fa-regular fa-clock"></i>
                        <span><?= h($daysLeftLabel) ?></span>
                      </span>
                    <?php endif; ?>
                    <?php if ($expiryDateLabel !== ''): ?>
                      <span class="home-v6__course-date">
                        <i class="fa-regular fa-calendar"></i>
                        <span><?= h($expiryDateLabel) ?></span>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="home-v6__progress">
                    <div class="home-v6__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$percent ?>">
                      <div class="home-v6__progress-fill" style="width: <?= (int)$percent ?>%;"></div>
                    </div>
                  </div>

                  <?php if ($isBlocked && $blockMessage !== ''): ?>
                    <div class="home-v6__block-note">
                      <strong><?= h($blockTitle) ?></strong>
                      <span><?= h($blockMessage) ?></span>
                    </div>
                  <?php endif; ?>

                  <div class="home-v6__course-actions">
                    <?php if ($canEnter): ?>
                      <a class="btn home-v6__btn home-v6__btn--primary home-v6__btn--block" href="<?= h($continueUrl) ?>">
                        <i class="fa-solid <?= h($entryIcon) ?>"></i>
                        <span><?= h($entryLabel) ?></span>
                      </a>
                    <?php else: ?>
                      <button class="btn home-v6__btn home-v6__btn--primary home-v6__btn--block" type="button" disabled>
                        <i class="fa-solid fa-lock"></i>
                        <span>Bloqueado</span>
                      </button>
                    <?php endif; ?>

                    <?php if (!$isBlocked && $certificateReady && $certificateUrl !== ''): ?>
                      <button
                        class="btn home-v6__btn home-v6__btn--certificate js-generate-certificate"
                        type="button"
                        data-url="<?= h($certificateUrl) ?>"
                        data-course="<?= (int)$courseId ?>"
                        data-node="<?= (int)$certificateNodeId ?>"
                      >
                        <i class="fa-solid fa-certificate"></i>
                        <span>Gerar certificado</span>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
            </section>
          <?php endif; ?>
        </section>

        <?php if (!empty($ebookShelf)): ?>
          <section class="home-v6__section" id="ebookShelf">
            <header class="home-v6__section-head">
              <div>
                <div class="home-v6__content-kicker">Material de apoio</div>
                <h2 class="home-v6__section-title">Links e materiais extras</h2>
              </div>
              <div class="home-v6__content-note"><?= (int)$totalEbooks ?> link(s) liberado(s)</div>
            </header>

            <div class="home-v6__ebook-grid">
              <?php foreach ($ebookShelf as $ebook): ?>
                <article class="home-v6__ebook">
                  <div class="home-v6__ebook-top">
                    <span class="home-v6__ebook-course"><?= h($ebook['course_title']) ?></span>
                    <?php if (!empty($ebook['host'])): ?>
                      <span class="home-v6__ebook-host"><?= h($ebook['host']) ?></span>
                    <?php endif; ?>
                  </div>
                  <h3 class="home-v6__ebook-title"><?= h($ebook['title']) ?></h3>
                  <p class="home-v6__ebook-text"><?= h($ebook['label']) ?></p>
                  <a class="btn home-v6__btn home-v6__btn--ghost home-v6__btn--block home-v6__ebook-action" href="<?= h($ebook['url']) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-up-right-from-square"></i>
                    <span>Abrir em nova aba</span>
                  </a>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>

<script>
(function () {
  const grid = document.getElementById('dashGrid');
  const cards = grid ? Array.from(grid.querySelectorAll('[data-state]')) : [];
  const filterButtons = Array.from(document.querySelectorAll('.home-v6__filter'));
  let currentFilter = 'all';

  const syncActiveState = () => {
    filterButtons.forEach((btn) => {
      const filter = String(btn.dataset.filter || 'all');
      btn.classList.toggle('is-active', filter === currentFilter);
    });
  };

  const applyFilters = () => {
    cards.forEach((card) => {
      const state = String(card.dataset.state || 'new');
      const passFilter = currentFilter === 'all' ? true : state === currentFilter;
      card.style.display = passFilter ? '' : 'none';
    });
  };

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      currentFilter = String(button.dataset.filter || 'all');
      syncActiveState();
      applyFilters();
    });
  });

  const certButtons = Array.from(document.querySelectorAll('.js-generate-certificate'));
  certButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const rawUrl = String(button.getAttribute('data-url') || '').trim();
      const certificateUrl = rawUrl.replace(/&amp;/gi, '&').replace(/&#38;/g, '&');
      const courseId = String(button.getAttribute('data-course') || '').trim();
      const nodeId = String(button.getAttribute('data-node') || '').trim();
      if (!certificateUrl || !courseId) return;

      const originalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Gerando...</span>';

      try {
        if (window.APP_HTTP && typeof window.APP_HTTP.post === 'function' && nodeId) {
          await window.APP_HTTP.post('/api/progress/update', {
            course_id: courseId,
            node_id: nodeId,
            status: 'completed',
            percent: 100,
            seconds: 0,
            position: 0
          });
        }

        window.open(certificateUrl, '_blank', 'noopener');
        showToast('Certificado liberado com sucesso.', 'success');
      } catch (error) {
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Erro ao gerar certificado', text: String(error.message || error) });
        } else {
          showToast(String(error.message || error), 'error');
        }
      } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
      }
    });
  });

  syncActiveState();
  applyFilters();
})();
</script>



