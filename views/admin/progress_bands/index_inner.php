<?php
use App\App;

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt_dt(int $timestamp): string {
  if ($timestamp <= 0) {
    return '-';
  }
  return date('d/m/Y H:i', $timestamp);
}

$rows = $rows ?? [];
$summary = $summary ?? [
  'total' => 0,
  'lt25' => 0,
  '25to50' => 0,
  'gt50' => 0,
];
$courseFilters = $courseFilters ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$selectedCourse = $selectedCourse ?? null;
$hasSelectedCourse = $selectedCourseId > 0;

$filterCards = [
  ['key' => 'all', 'label' => 'Todos', 'value' => (int)($summary['total'] ?? 0), 'icon' => 'fa-users'],
  ['key' => 'lt25', 'label' => 'Abaixo de 25%', 'value' => (int)($summary['lt25'] ?? 0), 'icon' => 'fa-gauge-simple-low'],
  ['key' => '25to50', 'label' => '25% ate 50%', 'value' => (int)($summary['25to50'] ?? 0), 'icon' => 'fa-chart-simple'],
  ['key' => 'gt50', 'label' => 'Acima de 50%', 'value' => (int)($summary['gt50'] ?? 0), 'icon' => 'fa-chart-line'],
];

global $CFG;
$moodleBase = rtrim((string)$CFG->wwwroot, '/');
?>

<section class="enroll-audit-v2 progress-band-admin">
  <div class="enroll-audit-v2__cards" aria-label="Faixas de progresso">
    <label class="audit-filter-card audit-filter-card--course" for="selProgressBandCourse">
      <span class="audit-filter-card__icon"><i class="fa-solid fa-book-open-reader"></i></span>
      <span class="audit-filter-card__body">
        <span class="audit-filter-card__label">Curso</span>
        <select class="form-select audit-filter-card__select" id="selProgressBandCourse">
          <option value="" <?= $hasSelectedCourse ? '' : 'selected' ?>>Selecione um curso</option>
          <?php foreach ($courseFilters as $course): ?>
            <option value="<?= (int)$course['id'] ?>" <?= (int)$course['id'] === $selectedCourseId ? 'selected' : '' ?>>
              <?= h($course['title'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </span>
    </label>

    <?php foreach ($filterCards as $card): ?>
      <button
        type="button"
        class="audit-filter-card js-progress-band-filter <?= $card['key'] === 'all' ? 'is-active' : '' ?>"
        data-filter="<?= h($card['key']) ?>"
        <?= $hasSelectedCourse ? '' : 'disabled' ?>
      >
        <span class="audit-filter-card__icon"><i class="fa-solid <?= h($card['icon']) ?>"></i></span>
        <span class="audit-filter-card__body">
          <span class="audit-filter-card__label"><?= h($card['label']) ?></span>
          <span class="audit-filter-card__value"><?= (int)$card['value'] ?></span>
        </span>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="enroll-audit-v2__shell">
    <div class="enroll-audit-v2__head">
      <div>
        <h1>Alunos por percentual</h1>
        <p>
        <?= $hasSelectedCourse && is_array($selectedCourse)
          ? 'Curso selecionado: ' . h($selectedCourse['title'] ?? '')
          : 'Selecione um curso para carregar os alunos.' ?>
        </p>
        <?php if ($hasSelectedCourse && is_array($selectedCourse)): ?>
          <?php $unlockHours = max(0, (int)($selectedCourse['final_exam_unlock_hours'] ?? 0)); ?>
          <div class="admin-panel__hint mt-1">
            <?= $unlockHours > 0
              ? ('Prova final liberada ' . $unlockHours . 'h apos o primeiro acesso do aluno.')
              : 'Prova final sem trava temporal neste curso.' ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table admin-table admin-table--progress-band" id="progressBandTable">
      <thead>
        <tr>
          <th>UID</th>
          <th>Aluno</th>
          <th>Progresso</th>
          <th>1 acesso no curso</th>
          <th>Ultimo acesso</th>
          <th>Acoes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">
              <?= $hasSelectedCourse
                ? 'Nenhum aluno encontrado para o curso selecionado.'
                : 'Selecione um curso para carregar a tabela.' ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $moodleUserId = (int)($row['moodle_userid'] ?? 0);
              $progressPercent = (int)($row['progress_percent'] ?? 0);
              $progressDone = (int)($row['progress_done'] ?? 0);
              $progressTotal = (int)($row['progress_total'] ?? 0);
              $isBlocked = !empty($row['is_blocked']);
              $moodleProfileUrl = $moodleBase . '/user/profile.php?id=' . $moodleUserId;
            ?>
            <tr
              data-band="<?= h($row['progress_band'] ?? 'lt25') ?>"
              data-course-id="<?= (int)($row['app_course_id'] ?? 0) ?>"
              data-user-id="<?= $moodleUserId ?>"
              data-course-title="<?= h($row['app_course_title'] ?? '') ?>"
              data-user-name="<?= h($row['fullname'] ?? '') ?>"
              data-user-email="<?= h($row['email'] ?? '-') ?>"
            data-blocked="<?= $isBlocked ? '1' : '0' ?>"
              data-block-title="<?= h($row['block_title'] ?? '') ?>"
              data-block-message="<?= h($row['block_message'] ?? '') ?>"
            >
              <td data-order="<?= $moodleUserId ?>"><strong>#<?= $moodleUserId ?></strong></td>
              <td>
                <div class="progress-band-user"><?= h($row['fullname'] ?? '') ?></div>
                <div class="progress-band-user__meta">@<?= h($row['username'] ?? '-') ?></div>
                <div class="progress-band-user__meta"><?= h($row['email'] ?? '-') ?></div>
              </td>
              <td data-order="<?= $progressPercent ?>">
                <div class="progress-band-meter">
                  <div class="progress-band-meter__bar">
                    <span style="width: <?= $progressPercent ?>%"></span>
                  </div>
                  <div class="progress-band-meter__meta">
                    <strong><?= $progressPercent ?>%</strong>
                    <span><?= $progressDone ?>/<?= $progressTotal ?> itens</span>
                  </div>
                <?php if ($isBlocked): ?>
                    <div class="mt-2">
                      <span class="audit-chip audit-chip--danger">Bloqueado</span>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
              <td data-order="<?= (int)($row['course_first_access'] ?? 0) ?>"><?= h(fmt_dt((int)($row['course_first_access'] ?? 0))) ?></td>
              <td data-order="<?= (int)($row['course_last_access'] ?? 0) ?>"><?= h(fmt_dt((int)($row['course_last_access'] ?? 0))) ?></td>
              <td>
                <div class="admin-actions admin-actions--audit">
                  <button type="button" class="btn btn-sm btn-outline-secondary js-progress-band-sync" title="Sincronizar progresso do Moodle">
                    <i class="fa-solid fa-arrows-rotate"></i>
                  </button>
                  <a class="btn btn-sm btn-outline-dark" href="<?= h($moodleProfileUrl) ?>" target="_blank" rel="noopener" title="Abrir perfil no Moodle">
                    <i class="fa-solid fa-id-card"></i>
                  </a>
                  <button
                    type="button"
                    class="btn btn-sm <?= $isBlocked ? 'btn-outline-success' : 'btn-outline-danger' ?> js-toggle-course-block"
                    title="<?= $isBlocked ? 'Desbloquear acesso do aluno' : 'Bloquear acesso do aluno' ?>"
                  >
                    <i class="fa-solid <?= $isBlocked ? 'fa-lock-open' : 'fa-user-lock' ?>"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary js-open-tracker" title="Acompanhar progresso detalhado">
                    <i class="fa-solid fa-list-check"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      </table>
    </div>
  </div>
</section>

<div class="modal fade" id="modalTracker" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog tracker-modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content tracker-modal">
      <div class="modal-header tracker-modal__header">
        <div class="tracker-modal__head">
          <h5 class="modal-title tracker-modal__title mb-1">Acompanhamento detalhado do aluno</h5>
          <div class="tracker-modal__subtitle" id="trackerModalSubtitle">-</div>
          <div class="tracker-modal__hint">Use os atalhos para apoiar o aluno. Em "Limpar progresso", os registros locais do app sao removidos para evitar status residual.</div>
        </div>
        <div class="tracker-modal__actions">
          <button type="button" class="btn btn-sm btn-outline-primary tracker-modal__action" id="btnTrackerSync" disabled>
            <i class="fa-solid fa-rotate me-1"></i> Sincronizar Moodle
          </button>
          <button type="button" class="btn btn-sm btn-success tracker-modal__action" id="btnTrackerMarkAllDone" disabled>
            <i class="fa-solid fa-check-double me-1"></i> Marcar todos
          </button>
          <button type="button" class="btn btn-sm btn-outline-warning tracker-modal__action" id="btnTrackerUnmarkAll" disabled>
            <i class="fa-solid fa-rotate-left me-1"></i> Limpar progresso
          </button>
          <button type="button" class="btn-close tracker-modal__close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
      </div>
      <div class="modal-body tracker-modal__body container-fluid">
        <div class="tracker-summary" id="trackerSummary"></div>
        <div class="tracker-module-grid" id="trackerModuleGrid"></div>
        <div class="tracker-tools">
          <label class="tracker-tools__search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="txtTrackerSearch" placeholder="Filtrar por titulo, tipo ou numero do item">
          </label>
          <div class="tracker-tools__status" id="trackerStatusFilters">
            <button type="button" class="tracker-filter-btn js-tracker-filter is-active" data-filter="all">Todos</button>
            <button type="button" class="tracker-filter-btn js-tracker-filter" data-filter="completed">Concluidos</button>
            <button type="button" class="tracker-filter-btn js-tracker-filter" data-filter="in_progress">Em andamento</button>
            <button type="button" class="tracker-filter-btn js-tracker-filter" data-filter="not_started">Limpos</button>
          </div>
          <div class="tracker-tools__module-actions">
            <button type="button" class="tracker-module-action" id="btnTrackerExpandModules" disabled>
              <i class="fa-solid fa-angles-down"></i> Expandir modulos
            </button>
            <button type="button" class="tracker-module-action" id="btnTrackerCollapseModules" disabled>
              <i class="fa-solid fa-angles-up"></i> Contrair modulos
            </button>
          </div>
          <div class="tracker-tools__meta" id="trackerVisibleCount">-</div>
        </div>

        <div id="trackerLoading" class="text-center py-4 d-none">
          <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
          <div class="small text-muted">Carregando trilha do aluno...</div>
        </div>

        <div id="trackerError" class="alert alert-danger d-none mb-3"></div>

        <div class="table-responsive">
          <table class="table tracker-table mb-0">
            <thead>
              <tr>
                <th style="width:42px;">#</th>
                <th>Item</th>
                <th style="width:140px;">Status</th>
                <th style="width:110px;">Progresso</th>
                <th style="width:160px;">Concluido em</th>
                <th style="width:140px;">Apoio admin</th>
              </tr>
            </thead>
            <tbody id="trackerBody">
              <tr><td colspan="6" class="text-center text-muted py-4">Selecione um aluno para acompanhar.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const init = () => {
    const courseSelect = document.getElementById('selProgressBandCourse');
    const filterButtons = Array.from(document.querySelectorAll('.js-progress-band-filter'));
    const tableNode = document.getElementById('progressBandTable');
    const trackerModalEl = document.getElementById('modalTracker');
    const trackerSubtitle = document.getElementById('trackerModalSubtitle');
    const trackerSummary = document.getElementById('trackerSummary');
    const trackerModuleGrid = document.getElementById('trackerModuleGrid');
    const trackerBody = document.getElementById('trackerBody');
    const trackerLoading = document.getElementById('trackerLoading');
    const trackerError = document.getElementById('trackerError');
    const btnTrackerSync = document.getElementById('btnTrackerSync');
    const btnTrackerMarkAllDone = document.getElementById('btnTrackerMarkAllDone');
    const btnTrackerUnmarkAll = document.getElementById('btnTrackerUnmarkAll');
    const btnTrackerExpandModules = document.getElementById('btnTrackerExpandModules');
    const btnTrackerCollapseModules = document.getElementById('btnTrackerCollapseModules');
    const trackerSearchInput = document.getElementById('txtTrackerSearch');
    const trackerVisibleCount = document.getElementById('trackerVisibleCount');
    const trackerFilterButtons = Array.from(document.querySelectorAll('.js-tracker-filter'));
    const appBase = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
    const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';
    const modalApi = window.APP_MODAL || null;

    if (!courseSelect || !tableNode) {
      return;
    }

    let activeFilter = 'all';
    let table = null;
    let trackerContext = null;
    let trackerFilterState = 'all';
    const trackerCollapsedModules = new Set();

    const statusLabel = {
      completed: 'Concluido',
      in_progress: 'Em andamento',
      not_started: 'Limpo (sem progresso)'
    };

    const statusClass = {
      completed: 'tracker-pill--done',
      in_progress: 'tracker-pill--progress',
      not_started: 'tracker-pill--todo'
    };

    const apiGet = async (path, params = {}) => {
      const url = new URL(appBase + path, window.location.origin);
      Object.entries(params).forEach(([key, value]) => {
        url.searchParams.set(key, String(value ?? ''));
      });
      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json || json.ok === false) {
        throw new Error((json && json.error) ? json.error : 'Falha na consulta.');
      }
      return json;
    };

    const apiPost = async (path, params = {}) => {
      if (window.APP_HTTP && typeof window.APP_HTTP.post === 'function') {
        return window.APP_HTTP.post(path, params);
      }
      const fd = new FormData();
      Object.entries(params).forEach(([key, value]) => fd.append(key, value ?? ''));
      if (!fd.has('csrf')) fd.append('csrf', csrf);
      const res = await fetch(appBase + path, { method: 'POST', body: fd, credentials: 'same-origin' });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json || json.ok === false) {
        throw new Error((json && json.error) ? json.error : 'Falha na operacao.');
      }
      return json;
    };

    const rowMatchesFilter = (row) => {
      if (!row || !row.dataset) return false;
      if (activeFilter === 'all') return true;
      return String(row.dataset.band || '') === activeFilter;
    };

    const applyActiveFilterState = () => {
      filterButtons.forEach((button) => {
        button.classList.toggle('is-active', String(button.dataset.filter || 'all') === activeFilter);
      });
    };

    const showTrackerLoading = (show) => {
      if (trackerLoading) trackerLoading.classList.toggle('d-none', !show);
    };

    const showTrackerError = (message) => {
      if (!trackerError) return;
      trackerError.textContent = message || '';
      trackerError.classList.toggle('d-none', !message);
    };

    const setTrackerBulkState = (enabled) => {
      const disabled = !enabled;
      if (btnTrackerSync) btnTrackerSync.disabled = disabled;
      if (btnTrackerMarkAllDone) btnTrackerMarkAllDone.disabled = disabled;
      if (btnTrackerUnmarkAll) btnTrackerUnmarkAll.disabled = disabled;
    };

    const setTrackerBulkBusy = (busy) => {
      if (btnTrackerSync) btnTrackerSync.disabled = busy || !trackerContext;
      if (btnTrackerMarkAllDone) btnTrackerMarkAllDone.disabled = busy || !trackerContext;
      if (btnTrackerUnmarkAll) btnTrackerUnmarkAll.disabled = busy || !trackerContext;
      btnTrackerSync?.classList.toggle('is-loading', !!busy);
      btnTrackerMarkAllDone?.classList.toggle('is-loading', !!busy);
      btnTrackerUnmarkAll?.classList.toggle('is-loading', !!busy);
    };

    const setTrackerModuleToolsState = (enabled) => {
      const disabled = !enabled;
      if (btnTrackerExpandModules) btnTrackerExpandModules.disabled = disabled;
      if (btnTrackerCollapseModules) btnTrackerCollapseModules.disabled = disabled;
    };

    const updateTrackerFilterButtons = () => {
      trackerFilterButtons.forEach((button) => {
        const filter = String(button.getAttribute('data-filter') || 'all');
        button.classList.toggle('is-active', filter === trackerFilterState);
      });
    };

    const formatDateTime = (value) => {
      const ts = Number(value || 0);
      if (!Number.isFinite(ts) || ts <= 0) return '-';
      const dt = new Date(ts * 1000);
      if (Number.isNaN(dt.getTime())) return '-';
      const dd = String(dt.getDate()).padStart(2, '0');
      const mm = String(dt.getMonth() + 1).padStart(2, '0');
      const yyyy = dt.getFullYear();
      const hh = String(dt.getHours()).padStart(2, '0');
      const ii = String(dt.getMinutes()).padStart(2, '0');
      return `${dd}/${mm}/${yyyy} ${hh}:${ii}`;
    };

    const escapeHtml = (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const applyTrackerFilters = () => {
      if (!trackerBody) return;
      const rows = Array.from(trackerBody.querySelectorAll('tr[data-node-id]'));
      if (!rows.length) {
        if (trackerVisibleCount) trackerVisibleCount.textContent = '0 itens';
        setTrackerModuleToolsState(false);
        return;
      }

      const term = String(trackerSearchInput?.value || '').trim().toLowerCase();
      rows.forEach((row) => {
        const status = String(row.getAttribute('data-status') || 'not_started');
        const indexText = String(row.querySelector('.tracker-index')?.textContent || '').trim().toLowerCase();
        const title = String(row.querySelector('.tracker-item-title')?.textContent || '').toLowerCase();
        const meta = String(row.querySelector('.tracker-item-meta')?.textContent || '').toLowerCase();
        const matchesStatus = trackerFilterState === 'all' ? true : status === trackerFilterState;
        const matchesText = term === '' || indexText.includes(term) || title.includes(term) || meta.includes(term);
        const show = matchesStatus && matchesText;
        row.classList.toggle('d-none', !show);
      });

      const moduleRows = Array.from(trackerBody.querySelectorAll('tr.tracker-module-row'));
      let visibleModules = 0;
      moduleRows.forEach((moduleRow) => {
        const moduleKey = String(moduleRow.getAttribute('data-module-key') || '');
        const hasVisibleItem = rows.some((row) => (
          String(row.getAttribute('data-module-key') || '') === moduleKey
          && !row.classList.contains('d-none')
        ));
        moduleRow.classList.toggle('d-none', !hasVisibleItem);
        if (hasVisibleItem) visibleModules++;

        const isCollapsed = hasVisibleItem && trackerCollapsedModules.has(moduleKey);
        moduleRow.classList.toggle('is-collapsed', isCollapsed);
        const toggle = moduleRow.querySelector('.js-tracker-module-toggle');
        if (toggle) {
          toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        }

        rows.forEach((row) => {
          if (String(row.getAttribute('data-module-key') || '') !== moduleKey) return;
          row.classList.toggle('tracker-hidden-by-module', isCollapsed);
        });
      });

      const visible = rows.filter((row) => (
        !row.classList.contains('d-none')
        && !row.classList.contains('tracker-hidden-by-module')
      )).length;

      setTrackerModuleToolsState(moduleRows.length > 0 && visibleModules > 0);
      if (trackerVisibleCount) {
        trackerVisibleCount.textContent = `${visible} de ${rows.length} itens em ${visibleModules} modulos`;
      }
    };

    const toggleTrackerModule = (moduleKey) => {
      const key = String(moduleKey || '').trim();
      if (key === '') return;
      if (trackerCollapsedModules.has(key)) trackerCollapsedModules.delete(key);
      else trackerCollapsedModules.add(key);
      applyTrackerFilters();
    };

    const setTrackerAllModulesCollapsed = (collapsed) => {
      if (!trackerBody) return;
      const moduleRows = Array.from(trackerBody.querySelectorAll('tr.tracker-module-row'));
      if (!moduleRows.length) return;
      if (!collapsed) trackerCollapsedModules.clear();
      moduleRows.forEach((moduleRow) => {
        if (moduleRow.classList.contains('d-none')) return;
        const key = String(moduleRow.getAttribute('data-module-key') || '').trim();
        if (key === '') return;
        if (collapsed) trackerCollapsedModules.add(key);
        else trackerCollapsedModules.delete(key);
      });
      applyTrackerFilters();
    };

    const resetTrackerFilters = () => {
      trackerFilterState = 'all';
      trackerCollapsedModules.clear();
      if (trackerSearchInput) trackerSearchInput.value = '';
      updateTrackerFilterButtons();
      if (trackerVisibleCount) trackerVisibleCount.textContent = '-';
      setTrackerModuleToolsState(false);
    };

    const renderTracker = (tracker) => {
      if (!tracker || !trackerBody) return;
      const user = tracker.user || {};
      const course = tracker.course || {};
      const summary = tracker.summary || {};
      const finalExamGate = tracker.final_exam_gate || {};
      const finalExam = tracker.final_exam || {};
      const items = Array.isArray(tracker.items) ? tracker.items : [];
      const modulesFromApi = Array.isArray(tracker.modules) ? tracker.modules : [];
      trackerCollapsedModules.clear();

      const moduleLookup = new Map();
      const normalizeModule = (raw, fallback = {}) => {
        const keyRaw = raw?.key ?? fallback.key ?? raw?.id ?? fallback.id ?? '0';
        const key = String(keyRaw);
        return {
          key,
          id: Number(raw?.id ?? fallback.id ?? 0),
          title: String(raw?.title ?? fallback.title ?? 'Sem modulo'),
          completed: Number(raw?.completed ?? raw?.done ?? fallback.completed ?? 0),
          in_progress: Number(raw?.in_progress ?? fallback.in_progress ?? 0),
          not_started: Number(raw?.not_started ?? fallback.not_started ?? 0),
          total: Number(raw?.total ?? fallback.total ?? 0),
          percent: Number(raw?.percent ?? fallback.percent ?? 0),
          _agg_total: 0,
          _agg_completed: 0,
          _agg_in_progress: 0,
          _agg_not_started: 0
        };
      };

      modulesFromApi.forEach((module, idx) => {
        const normalized = normalizeModule(module, { key: `module-${idx}` });
        moduleLookup.set(normalized.key, normalized);
      });

      if (trackerSubtitle) {
        trackerSubtitle.textContent = `${user.name || 'Aluno'} - ${user.email || '-'} - ${course.title || 'Curso'}`;
      }

      if (trackerSummary) {
        const gatePills = [];
        if (finalExamGate.enabled) {
          gatePills.push(`
            <span class="tracker-summary__pill">
              <i class="fa-regular fa-clock"></i>
              Prova final em <strong>${Number(finalExamGate.hours || 0)}h</strong>
            </span>
          `);
          gatePills.push(`
            <span class="tracker-summary__pill">
              <i class="fa-regular fa-calendar-check"></i>
              1 acesso: ${escapeHtml(formatDateTime(Number(finalExamGate.first_access_ts || 0)))}
            </span>
          `);
          gatePills.push(`
            <span class="tracker-summary__pill ${finalExamGate.blocked ? '' : 'tracker-summary__pill--primary'}">
              <i class="fa-solid ${finalExamGate.blocked ? 'fa-lock' : 'fa-lock-open'}"></i>
              ${finalExamGate.blocked ? 'Libera em ' : 'Liberada em '}
              <strong>${escapeHtml(formatDateTime(Number(finalExamGate.unlock_ts || 0)))}</strong>
            </span>
          `);
        }
        if (finalExam.exists) {
          gatePills.push(`
            <span class="tracker-summary__pill">
              <i class="fa-solid fa-medal"></i>
              Prova final: <strong>${escapeHtml(String(finalExam.state_label || 'Liberada'))}</strong>
            </span>
          `);
        }
        trackerSummary.innerHTML = `
          <span class="tracker-summary__pill tracker-summary__pill--primary">
            <i class="fa-solid fa-chart-line"></i>
            <strong>${summary.percent || 0}%</strong> concluido
          </span>
          <span class="tracker-summary__pill">
            <i class="fa-solid fa-list-check"></i>
            ${summary.completed || 0}/${summary.total || 0} itens concluidos
          </span>
          <span class="tracker-summary__pill">
            <i class="fa-solid fa-spinner"></i>
            ${summary.in_progress || 0} em andamento
          </span>
          <span class="tracker-summary__pill">
            <i class="fa-regular fa-circle"></i>
            ${summary.not_started || 0} limpos
          </span>
          ${gatePills.join('' )}
        `;
      }
      if (!items.length) {
        if (trackerModuleGrid) trackerModuleGrid.innerHTML = '';
        trackerBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Curso sem itens publicados.</td></tr>';
        setTrackerBulkState(false);
        setTrackerModuleToolsState(false);
        if (trackerVisibleCount) trackerVisibleCount.textContent = '0 itens';
        return;
      }

      setTrackerBulkState(true);

      const resolveModule = (item) => {
        const keyFromItem = String(item.module_key ?? item.module_id ?? '0');
        const titleFromItem = String(item.module_title || 'Sem modulo');
        const key = keyFromItem !== '' ? keyFromItem : '0';
        if (!moduleLookup.has(key)) {
          moduleLookup.set(key, normalizeModule({}, {
            key,
            id: Number(item.module_id || 0),
            title: titleFromItem
          }));
        }
        return moduleLookup.get(key);
      };

      const moduleOrder = [];
      let currentModuleKey = '__none__';
      const htmlRows = [];

      items.forEach((item) => {
        const status = String(item.status || 'not_started');
        const completed = !!item.completed;
        const action = completed ? 'pending' : 'completed';
        const actionLabel = completed ? 'Desmarcar' : 'Marcar concluido';
        const actionClass = completed ? 'btn-outline-warning' : 'btn-outline-success';
        const itemType = String(item.subtype || '-');
        const trail = String(item.trail || '').trim();
        const indexLabel = String(item.sequence_label || item.index || '');
        const lockBadge = item.locked_for_student
          ? '<span class="tracker-lock"><i class="fa-solid fa-lock me-1"></i>Bloqueado para aluno</span>'
          : '';
        const moduleInfo = resolveModule(item);
        moduleInfo._agg_total += 1;
        if (status === 'completed') moduleInfo._agg_completed += 1;
        else if (status === 'in_progress') moduleInfo._agg_in_progress += 1;
        else moduleInfo._agg_not_started += 1;

        if (!moduleOrder.includes(moduleInfo.key)) moduleOrder.push(moduleInfo.key);

        const moduleTotal = moduleInfo.total > 0 ? moduleInfo.total : moduleInfo._agg_total;
        const moduleCompleted = moduleInfo.total > 0 ? moduleInfo.completed : moduleInfo._agg_completed;
        const modulePercent = moduleTotal > 0
          ? Math.max(0, Math.min(100, moduleInfo.percent > 0 ? moduleInfo.percent : Math.floor((moduleCompleted * 100) / moduleTotal)))
          : 0;

        if (currentModuleKey !== moduleInfo.key) {
          currentModuleKey = moduleInfo.key;
          htmlRows.push(`
            <tr class="tracker-module-row" data-module-key="${escapeHtml(moduleInfo.key)}">
              <td colspan="6">
                <div class="tracker-module-row__head">
                  <button type="button" class="tracker-module-toggle js-tracker-module-toggle" data-module-key="${escapeHtml(moduleInfo.key)}" aria-expanded="true">
                    <span class="tracker-module-toggle__icon"><i class="fa-solid fa-chevron-down"></i></span>
                    <span class="tracker-module-row__title">
                      <i class="fa-solid fa-layer-group"></i>
                      ${escapeHtml(moduleInfo.title)}
                    </span>
                  </button>
                  <div class="tracker-module-row__meta">
                    <span>${moduleCompleted}/${moduleTotal} concluidos</span>
                    <strong>${modulePercent}%</strong>
                  </div>
                </div>
              </td>
            </tr>
          `);
        }

        const metaParts = [];
        if (trail !== '') metaParts.push(escapeHtml(trail));
        metaParts.push(escapeHtml(itemType));
        if (lockBadge !== '') metaParts.push(lockBadge);

        htmlRows.push(`
          <tr data-node-id="${Number(item.id || 0)}" data-status="${escapeHtml(status)}" data-module-key="${escapeHtml(moduleInfo.key)}">
            <td><span class="tracker-index">${escapeHtml(indexLabel)}</span></td>
            <td>
              <div class="tracker-item-title">${escapeHtml(item.title || 'Sem titulo')}</div>
              <div class="tracker-item-meta">${metaParts.join(' ')}</div>
            </td>
            <td><span class="tracker-pill ${statusClass[status] || statusClass.not_started}">${escapeHtml(statusLabel[status] || status)}</span></td>
            <td><strong>${Number(item.percent || 0)}%</strong></td>
            <td>${escapeHtml(formatDateTime(item.completed_at_ts || 0))}</td>
            <td>
              <button type="button" class="btn btn-sm ${actionClass} js-tracker-toggle" data-node-id="${Number(item.id || 0)}" data-mark="${action}">
                ${actionLabel}
              </button>
            </td>
          </tr>
        `);
      });

      trackerBody.innerHTML = htmlRows.join('');

      if (trackerModuleGrid) {
        trackerModuleGrid.innerHTML = moduleOrder.map((key) => {
          const info = moduleLookup.get(key);
          if (!info) return '';
          const total = info.total > 0 ? info.total : info._agg_total;
          const completed = info.total > 0 ? info.completed : info._agg_completed;
          const inProgress = info.total > 0 ? info.in_progress : info._agg_in_progress;
          const notStarted = info.total > 0 ? info.not_started : info._agg_not_started;
          const percent = total > 0
            ? Math.max(0, Math.min(100, info.percent > 0 ? info.percent : Math.floor((completed * 100) / total)))
            : 0;
          return `
            <article class="tracker-module-card js-tracker-module-card" data-module-key="${escapeHtml(info.key)}">
              <header class="tracker-module-card__head">
                <h6 class="tracker-module-card__title">${escapeHtml(info.title)}</h6>
                <span class="tracker-module-card__percent">${percent}%</span>
              </header>
              <div class="tracker-module-card__bar"><span style="width:${percent}%"></span></div>
              <div class="tracker-module-card__meta">
                <span><i class="fa-solid fa-check"></i> ${completed}/${total}</span>
                <span><i class="fa-solid fa-spinner"></i> ${inProgress}</span>
                <span><i class="fa-regular fa-circle"></i> ${notStarted}</span>
              </div>
            </article>
          `;
        }).join('');
      }

      setTrackerModuleToolsState(moduleOrder.length > 0);
      applyTrackerFilters();
    };

    const updateMainRowProgress = (row, summary) => {
      if (!row || !summary) return;
      const progressBar = row.querySelector('.progress-band-meter__bar span');
      const progressStrong = row.querySelector('.progress-band-meter__meta strong');
      const progressText = row.querySelector('.progress-band-meter__meta span');
      const percent = Number(summary.percent || 0);
      const done = Number(summary.completed || 0);
      const total = Number(summary.total || 0);
      if (progressBar) progressBar.style.width = percent + '%';
      if (progressStrong) progressStrong.textContent = percent + '%';
      if (progressText) progressText.textContent = `${done}/${total} itens`;
      row.dataset.band = percent < 25 ? 'lt25' : (percent <= 50 ? '25to50' : 'gt50');
      if (table) table.row(row).invalidate('dom').draw(false);
    };

    const syncProgressFromMoodle = async (courseId, userId, row) => {
      const json = await apiPost('/api/admin/enrollment/progress/sync', {
        course_id: courseId,
        user_id: userId
      });
      const summary = json?.progress?.summary || null;
      if (summary) updateMainRowProgress(row, summary);
      showToast('Progresso sincronizado com o Moodle.', 'success');
      return json;
    };

    const loadTracker = async () => {
      if (!trackerContext) return;
      showTrackerError('');
      showTrackerLoading(true);
      setTrackerBulkState(false);
      try {
        const json = await apiGet('/api/admin/enrollment/tracker', {
          course_id: trackerContext.courseId,
          user_id: trackerContext.userId
        });
        trackerContext.data = json.tracker || null;
        renderTracker(trackerContext.data);
        if (trackerContext.data?.summary) updateMainRowProgress(trackerContext.row, trackerContext.data.summary);
      } catch (error) {
        showTrackerError(String(error.message || error));
        setTrackerBulkState(false);
        if (trackerBody) trackerBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Falha ao carregar trilha do aluno.</td></tr>';
      } finally {
        showTrackerLoading(false);
      }
    };

    const openTrackerModal = () => {
      if (!trackerModalEl) return;
      if (modalApi) {
        modalApi.open(trackerModalEl);
        return;
      }
      trackerModalEl.style.display = 'block';
      trackerModalEl.classList.add('show');
      trackerModalEl.removeAttribute('aria-hidden');
      trackerModalEl.setAttribute('aria-modal', 'true');
      document.body.classList.add('modal-open');
    };

    const closeTrackerModalFallback = () => {
      if (!trackerModalEl || modalApi) return;
      trackerModalEl.classList.remove('show');
      trackerModalEl.style.display = 'none';
      trackerModalEl.setAttribute('aria-hidden', 'true');
      trackerModalEl.removeAttribute('aria-modal');
      document.body.classList.remove('modal-open');
      trackerModalEl.dispatchEvent(new Event('hidden.bs.modal', { bubbles: true }));
    };

    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.DataTable === 'function') {
      const dataTableSearch = (settings, _data, dataIndex) => {
        if (!settings || settings.nTable !== tableNode) return true;
        const row = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
        return rowMatchesFilter(row);
      };

      window.jQuery.fn.dataTable.ext.search.push(dataTableSearch);
      table = window.APP_DATA_TABLE
        ? window.APP_DATA_TABLE.init(tableNode, {
            order: [[0, 'desc']],
            columnDefs: [{ targets: -1, orderable: false, searchable: false }]
          })
        : window.jQuery(tableNode).DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
            order: [[0, 'desc']],
            autoWidth: false,
            orderClasses: false,
            language: {
              url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json'
            },
            columnDefs: [{ targets: -1, orderable: false, searchable: false }]
          });
    }

    applyActiveFilterState();
    updateTrackerFilterButtons();

    courseSelect.addEventListener('change', () => {
      const selected = String(courseSelect.value || '').trim();
      const url = new URL(window.location.href);
      if (selected === '') url.searchParams.delete('course_id');
      else url.searchParams.set('course_id', selected);
      window.location.href = url.toString();
    });

    filterButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (button.disabled) return;
        activeFilter = String(button.dataset.filter || 'all');
        applyActiveFilterState();
        if (table) table.draw();
        else {
          Array.from(tableNode.querySelectorAll('tbody tr[data-band]')).forEach((row) => {
            row.style.display = rowMatchesFilter(row) ? '' : 'none';
          });
        }
      });
    });

    tableNode.addEventListener('click', async (event) => {
      const syncButton = event.target.closest('.js-progress-band-sync');
      if (syncButton) {
        event.preventDefault();
        const row = syncButton.closest('tr');
        if (!row || !row.dataset) return;
        const courseId = Number(row.dataset.courseId || 0);
        const userId = Number(row.dataset.userId || 0);
        if (courseId <= 0 || userId <= 0) {
          showToast('Nao foi possivel identificar o aluno para sincronizar.', 'error');
          return;
        }
        syncButton.disabled = true;
        syncButton.classList.add('is-loading');
        try {
          await syncProgressFromMoodle(courseId, userId, row);
        } catch (error) {
          showToast(String(error.message || error), 'error');
        } finally {
          syncButton.disabled = false;
          syncButton.classList.remove('is-loading');
        }
        return;
      }

      const blockButton = event.target.closest('.js-toggle-course-block');
      if (blockButton) {
        event.preventDefault();
        const row = blockButton.closest('tr');
        if (!row || !row.dataset) return;
        const courseId = Number(row.dataset.courseId || 0);
        const userId = Number(row.dataset.userId || 0);
        const isBlocked = String(row.dataset.blocked || '0') === '1';
        if (courseId <= 0 || userId <= 0) {
          showToast('Nao foi possivel identificar o aluno para bloqueio.', 'error');
          return;
        }
        if (!window.APP_COURSE_BLOCK) {
          showToast('Rotina de bloqueio indisponivel.', 'error');
          return;
        }

        blockButton.disabled = true;
        blockButton.classList.add('is-loading');
        try {
          const result = isBlocked
            ? await window.APP_COURSE_BLOCK.unblock({
                courseId,
                userId,
                courseTitle: String(row.dataset.courseTitle || ''),
                userName: String(row.dataset.userName || ''),
              })
            : await window.APP_COURSE_BLOCK.block({
                courseId,
                userId,
                courseTitle: String(row.dataset.courseTitle || ''),
                userName: String(row.dataset.userName || ''),
                currentTitle: String(row.dataset.blockTitle || ''),
                currentMessage: String(row.dataset.blockMessage || ''),
              });

          if (result) {
            showToast(isBlocked ? 'Aluno desbloqueado com sucesso.' : 'Aluno bloqueado com sucesso.', 'success');
            window.location.reload();
          }
        } catch (error) {
          showToast(String(error.message || error), 'error');
        } finally {
          blockButton.disabled = false;
          blockButton.classList.remove('is-loading');
        }
        return;
      }

      const trackerButton = event.target.closest('.js-open-tracker');
      if (!trackerButton) return;
      event.preventDefault();
      const row = trackerButton.closest('tr');
      if (!row || !row.dataset) return;
      trackerContext = {
        row,
        courseId: Number(row.dataset.courseId || 0),
        userId: Number(row.dataset.userId || 0),
        userName: String(row.dataset.userName || ''),
        userEmail: String(row.dataset.userEmail || ''),
        courseTitle: String(row.dataset.courseTitle || '')
      };
      resetTrackerFilters();
      if (trackerSubtitle) {
        trackerSubtitle.textContent = `${trackerContext.userName || 'Aluno'} - ${trackerContext.userEmail || '-'} - ${trackerContext.courseTitle || 'Curso'}`;
      }
      if (trackerSummary) trackerSummary.innerHTML = '';
      if (trackerModuleGrid) trackerModuleGrid.innerHTML = '';
      if (trackerBody) trackerBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Carregando trilha do aluno...</td></tr>';
      setTrackerBulkState(false);
      showTrackerError('');
      openTrackerModal();
      await loadTracker();
    });

    trackerFilterButtons.forEach((button) => {
      button.addEventListener('click', () => {
        trackerFilterState = String(button.getAttribute('data-filter') || 'all');
        updateTrackerFilterButtons();
        applyTrackerFilters();
      });
    });

    trackerSearchInput?.addEventListener('input', applyTrackerFilters);

    trackerModuleGrid?.addEventListener('click', (event) => {
      const card = event.target.closest('.js-tracker-module-card');
      if (card) toggleTrackerModule(card.getAttribute('data-module-key'));
    });

    trackerBody?.addEventListener('click', async (event) => {
      const toggleModuleButton = event.target.closest('.js-tracker-module-toggle');
      if (toggleModuleButton) {
        event.preventDefault();
        toggleTrackerModule(toggleModuleButton.getAttribute('data-module-key'));
        return;
      }
      const button = event.target.closest('.js-tracker-toggle');
      if (!button || !trackerContext) return;
      const nodeId = Number(button.getAttribute('data-node-id') || 0);
      const mark = String(button.getAttribute('data-mark') || '');
      if (nodeId <= 0 || !mark) return;
      button.disabled = true;
      try {
        await apiPost('/api/admin/enrollment/progress/set', {
          course_id: trackerContext.courseId,
          user_id: trackerContext.userId,
          node_id: nodeId,
          mark
        });
        showToast(
          mark === 'completed'
            ? 'Item marcado como concluido.'
            : 'Item desmarcado e progresso local removido no app.',
          'success'
        );
        await loadTracker();
      } catch (error) {
        showToast(String(error.message || error), 'error');
      } finally {
        button.disabled = false;
      }
    });

    btnTrackerSync?.addEventListener('click', async () => {
      if (!trackerContext) return;
      setTrackerBulkBusy(true);
      try {
        await syncProgressFromMoodle(trackerContext.courseId, trackerContext.userId, trackerContext.row);
        await loadTracker();
      } catch (error) {
        showToast(String(error.message || error), 'error');
      } finally {
        setTrackerBulkBusy(false);
      }
    });

    btnTrackerMarkAllDone?.addEventListener('click', async () => {
      if (!trackerContext) return;
      setTrackerBulkBusy(true);
      try {
        await apiPost('/api/admin/enrollment/progress/bulk', {
          course_id: trackerContext.courseId,
          user_id: trackerContext.userId,
          mark: 'completed'
        });
        showToast('Todos os itens foram marcados como concluidos.', 'success');
        await loadTracker();
      } catch (error) {
        showToast(String(error.message || error), 'error');
      } finally {
        setTrackerBulkBusy(false);
      }
    });

    btnTrackerUnmarkAll?.addEventListener('click', async () => {
      if (!trackerContext) return;
      if (typeof window.Swal !== 'undefined') {
        const answer = await window.Swal.fire({
          icon: 'warning',
          title: 'Limpar progresso do aluno?',
          text: 'Os registros locais do app serao removidos para este aluno neste curso.',
          showCancelButton: true,
          confirmButtonText: 'Sim, limpar',
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        if (!answer.isConfirmed) return;
      }
      setTrackerBulkBusy(true);
      try {
        await apiPost('/api/admin/enrollment/progress/bulk', {
          course_id: trackerContext.courseId,
          user_id: trackerContext.userId,
          mark: 'pending'
        });
        showToast('Progresso local removido com sucesso.', 'success');
        await loadTracker();
      } catch (error) {
        showToast(String(error.message || error), 'error');
      } finally {
        setTrackerBulkBusy(false);
      }
    });
    btnTrackerExpandModules?.addEventListener('click', () => {
      setTrackerAllModulesCollapsed(false);
    });

    btnTrackerCollapseModules?.addEventListener('click', () => {
      setTrackerAllModulesCollapsed(true);
    });

    trackerModalEl?.addEventListener('hidden.bs.modal', () => {
      trackerContext = null;
      if (trackerSubtitle) trackerSubtitle.textContent = '-';
      if (trackerSummary) trackerSummary.innerHTML = '';
      if (trackerModuleGrid) trackerModuleGrid.innerHTML = '';
      if (trackerBody) trackerBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Selecione um aluno para acompanhar.</td></tr>';
      resetTrackerFilters();
      setTrackerBulkState(false);
      showTrackerError('');
      showTrackerLoading(false);
    });

    if (!modalApi && trackerModalEl) {
      trackerModalEl.querySelectorAll('[data-bs-dismiss=\"modal\"]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          closeTrackerModalFallback();
        });
      });
      trackerModalEl.addEventListener('click', (event) => {
        if (event.target === trackerModalEl) closeTrackerModalFallback();
      });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && trackerModalEl.classList.contains('show')) closeTrackerModalFallback();
      });
    }
  };

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init, { once: true });
  }
})();
</script>








