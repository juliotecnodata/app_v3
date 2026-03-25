<?php
use App\App;

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

global $CFG;
$moodleBase = rtrim((string)$CFG->wwwroot, '/');
$appCourses = $appCourses ?? [];
$moodleCourses = $moodleCourses ?? [];
$moodleMap = $moodleMap ?? [];
$stats = $stats ?? ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
?>

<section class="admin-toolbar admin-toolbar--courses-page">
  <div class="admin-toolbar__stack">
    <div class="admin-toolbar__copy">
      <h1 class="admin-toolbar__title">Mapeamento com o LMS</h1>
      <p class="admin-toolbar__subtitle">Relacione curso do app com curso do Moodle e revise a integracao.</p>
    </div>
  </div>

  <div class="admin-toolbar__stats" aria-label="Resumo de mapeamento">
    <span class="admin-toolbar__stat">
      <small>Cursos APP</small>
      <strong id="mapStatTotal"><?= (int)($stats['total'] ?? 0) ?></strong>
    </span>
    <span class="admin-toolbar__stat">
      <small>Mapeados</small>
      <strong id="mapStatMapped"><?= (int)($stats['mapped'] ?? 0) ?></strong>
    </span>
    <span class="admin-toolbar__stat">
      <small>Sem mapeamento</small>
      <strong id="mapStatUnmapped"><?= (int)($stats['unmapped'] ?? 0) ?></strong>
    </span>
  </div>
</section>

<section class="admin-toolbar">
  <select id="mapFilterStatus" class="form-select" style="max-width:220px;">
    <option value="all">Todos</option>
    <option value="mapped">Somente mapeados</option>
    <option value="unmapped">Somente sem mapeamento</option>
  </select>
</section>

<section class="admin-panel">
  <div class="admin-panel__header">
    <div>
      <div class="admin-panel__title">Mapa de cursos</div>
      <div class="admin-panel__hint">Coluna esquerda: APP. Coluna direita: LMS Moodle.</div>
    </div>
    <div class="admin-panel__hint" id="mapVisibleCount"></div>
  </div>

  <div class="table-responsive">
    <table class="table admin-table course-map-table" id="courseMapTable">
      <thead>
        <tr>
          <th style="width:74px;">APP ID</th>
          <th style="min-width:260px;">Curso APP</th>
          <th style="width:150px;">Status APP</th>
          <th style="min-width:260px;">Curso LMS atual</th>
          <th style="min-width:300px;">Selecionar curso LMS</th>
          <th style="width:130px;">Mapeamento</th>
          <th style="min-width:230px;">Acoes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($appCourses as $course): ?>
          <?php
            $appId = (int)($course['id'] ?? 0);
            $appTitle = trim((string)($course['title'] ?? ''));
            $appSlug = trim((string)($course['slug'] ?? ''));
            $appStatus = trim((string)($course['status'] ?? 'draft'));
            $onlyApp = ((int)($course['only_app'] ?? 0) === 1);
            $mappedMoodleId = (int)($course['moodle_courseid'] ?? 0);
            $mappedMoodle = $mappedMoodleId > 0 ? ($moodleMap[$mappedMoodleId] ?? null) : null;
            $isMapped = $mappedMoodleId > 0 && is_array($mappedMoodle);
            $mapStatusClass = $isMapped ? 'text-bg-success' : 'text-bg-secondary';
            $mapStatusText = $isMapped ? 'Mapeado' : 'Pendente';
            $statusText = $appStatus !== '' ? strtoupper($appStatus) : 'DRAFT';
            $statusClass = $appStatus === 'published' ? 'text-bg-success' : ($appStatus === 'draft' ? 'text-bg-warning' : 'text-bg-secondary');
            $moodleName = $isMapped
              ? trim((string)($mappedMoodle['fullname'] ?? ''))
              : 'Sem mapeamento';
            $moodleShortname = $isMapped
              ? trim((string)($mappedMoodle['shortname'] ?? ''))
              : '';
            $moodleVisible = $isMapped ? ((int)($mappedMoodle['visible'] ?? 0) === 1) : false;
            $openLmsHref = $isMapped ? ($moodleBase . '/course/view.php?id=' . $mappedMoodleId) : '#';
          ?>
          <tr
            data-app-course-id="<?= $appId ?>"
            data-current-moodle-id="<?= $mappedMoodleId ?>"
            data-mapped="<?= $isMapped ? '1' : '0' ?>"
          >
            <td class="fw-semibold">#<?= $appId ?></td>
            <td>
              <div class="map-app-title"><?= h($appTitle !== '' ? $appTitle : 'Sem titulo') ?></div>
              <div class="map-app-meta">Slug: <?= h($appSlug !== '' ? $appSlug : '-') ?></div>
            </td>
            <td>
              <span class="badge <?= h($statusClass) ?>"><?= h($statusText) ?></span>
              <?php if ($onlyApp): ?>
                <div class="mt-1"><span class="badge text-bg-primary">Somente APP</span></div>
              <?php endif; ?>
            </td>
            <td class="js-current-map">
              <?php if ($isMapped): ?>
                <div class="map-lms-title">#<?= $mappedMoodleId ?> - <?= h($moodleName) ?></div>
                <div class="map-lms-meta">
                  <?= h($moodleShortname !== '' ? $moodleShortname : '-') ?>
                  <span class="mx-1">|</span>
                  <?= $moodleVisible ? 'Visivel' : 'Oculto' ?>
                </div>
              <?php else: ?>
                <div class="map-lms-empty">Sem curso LMS vinculado</div>
              <?php endif; ?>
            </td>
            <td>
              <select class="form-select form-select-sm js-lms-select">
                <option value="0">-- Sem mapeamento --</option>
                <?php foreach ($moodleCourses as $mCourse): ?>
                  <?php
                    $mId = (int)($mCourse['id'] ?? 0);
                    $selected = $mId === $mappedMoodleId ? 'selected' : '';
                    $mFullname = trim((string)($mCourse['fullname'] ?? ''));
                    $mShortname = trim((string)($mCourse['shortname'] ?? ''));
                    $mCategory = trim((string)($mCourse['category_name'] ?? ''));
                    $mVisible = ((int)($mCourse['visible'] ?? 0) === 1) ? 'Visivel' : 'Oculto';
                    $label = '#' . $mId . ' - ' . $mFullname;
                    if ($mShortname !== '') {
                      $label .= ' [' . $mShortname . ']';
                    }
                    if ($mCategory !== '') {
                      $label .= ' {' . $mCategory . '}';
                    }
                    $label .= ' - ' . $mVisible;
                  ?>
                  <option value="<?= $mId ?>" <?= $selected ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <span class="badge <?= h($mapStatusClass) ?> js-map-status"><?= h($mapStatusText) ?></span>
            </td>
            <td>
              <div class="map-actions">
                <button type="button" class="btn btn-sm btn-primary js-save-map">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Salvar
                </button>
                <a class="btn btn-sm btn-outline-primary" href="<?= App::base_url('/admin/courses/' . $appId . '/builder') ?>">
                  <i class="fa-solid fa-screwdriver-wrench me-1"></i> Builder
                </a>
                <a class="btn btn-sm btn-outline-secondary js-open-lms <?= $isMapped ? '' : 'd-none' ?>"
                   href="<?= h($openLmsHref) ?>" target="_blank" rel="noopener">
                  <i class="fa-solid fa-up-right-from-square me-1"></i> LMS
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<style>
.course-map-table .map-app-title,
.course-map-table .map-lms-title {
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
}

.course-map-table .map-app-meta,
.course-map-table .map-lms-meta {
  margin-top: 4px;
  font-size: 0.8rem;
  color: #64748b;
}

.course-map-table .map-lms-empty {
  color: #94a3b8;
  font-size: 0.86rem;
}

.course-map-table .map-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.course-map-table .btn {
  border-radius: 10px;
}

@media (max-width: 991.98px) {
  .course-map-table .map-actions .btn {
    flex: 1 1 auto;
  }
}
</style>

<script>
(function () {
  const init = () => {
  const tableEl = document.getElementById('courseMapTable');
  const statusFilter = document.getElementById('mapFilterStatus');
  const visibleCount = document.getElementById('mapVisibleCount');
  const statTotal = document.getElementById('mapStatTotal');
  const statMapped = document.getElementById('mapStatMapped');
  const statUnmapped = document.getElementById('mapStatUnmapped');

  let table = null;

  const recalcGlobalStats = () => {
    if (!tableEl) return;
    const allRows = Array.from(tableEl.querySelectorAll('tbody tr[data-app-course-id]'));
    const total = allRows.length;
    const mapped = allRows.filter((row) => String(row.dataset.mapped || '0') === '1').length;
    const unmapped = Math.max(0, total - mapped);
    if (statTotal) statTotal.textContent = String(total);
    if (statMapped) statMapped.textContent = String(mapped);
    if (statUnmapped) statUnmapped.textContent = String(unmapped);
  };

  const recalcVisibleCount = () => {
    if (!visibleCount) return;
    let visibleRows = 0;
    if (table) {
      visibleRows = table.rows({ search: 'applied' }).count();
    } else if (tableEl) {
      visibleRows = Array.from(tableEl.querySelectorAll('tbody tr[data-app-course-id]')).filter((row) => row.style.display !== 'none').length;
    }
    visibleCount.textContent = `${visibleRows} cursos visiveis`;
  };

  const rowMatchesFilter = (row) => {
    const filterValue = String(statusFilter?.value || 'all');
    const mapped = String(row.dataset.mapped || '0');
    if (filterValue === 'mapped') return mapped === '1';
    if (filterValue === 'unmapped') return mapped !== '1';
    return true;
  };

  if (window.jQuery && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
    $.fn.dataTable.ext.search.push((settings, _data, dataIndex) => {
      if (!table || settings.nTable.id !== 'courseMapTable') return true;
      const row = table.row(dataIndex).node();
      if (!row) return true;
      return rowMatchesFilter(row);
    });
  }

  if (window.jQuery && $.fn.DataTable && tableEl) {
    try {
      table = window.APP_DATA_TABLE
        ? window.APP_DATA_TABLE.init(tableEl, {
            order: [[0, 'desc']]
          })
        : $(tableEl).DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
            order: [[0, 'desc']],
            autoWidth: false,
            orderClasses: false,
            language: {
              url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json'
            }
          });
      statusFilter?.addEventListener('change', () => {
        table.draw();
      });
      table.on('draw', recalcVisibleCount);
      recalcVisibleCount();
    } catch (_error) {
      table = null;
    }
  }

  if (!table && tableEl) {
    const applySimpleFilter = () => {
      const rows = Array.from(tableEl.querySelectorAll('tbody tr[data-app-course-id]'));
      rows.forEach((row) => {
        row.style.display = rowMatchesFilter(row) ? '' : 'none';
      });
      recalcVisibleCount();
    };
    statusFilter?.addEventListener('change', applySimpleFilter);
    applySimpleFilter();
  }

  const applyMappedVisualState = (row, data) => {
    const mapped = !!data.mapped;
    row.dataset.mapped = mapped ? '1' : '0';
    row.dataset.currentMoodleId = String(data.moodleCourseId || 0);

    const statusBadge = row.querySelector('.js-map-status');
    if (statusBadge) {
      statusBadge.className = 'badge js-map-status ' + (mapped ? 'text-bg-success' : 'text-bg-secondary');
      statusBadge.textContent = mapped ? 'Mapeado' : 'Pendente';
    }

    const currentCell = row.querySelector('.js-current-map');
    if (currentCell) {
      if (mapped) {
        const shortname = data.shortname ? String(data.shortname) : '-';
        const visibleText = data.visible ? 'Visivel' : 'Oculto';
        currentCell.innerHTML = `
          <div class="map-lms-title">#${Number(data.moodleCourseId)} - ${String(data.fullname || '')}</div>
          <div class="map-lms-meta">${shortname} <span class="mx-1">|</span> ${visibleText}</div>
        `;
      } else {
        currentCell.innerHTML = '<div class="map-lms-empty">Sem curso LMS vinculado</div>';
      }
    }

    const openLms = row.querySelector('.js-open-lms');
    if (openLms) {
      if (mapped) {
        openLms.classList.remove('d-none');
        openLms.setAttribute('href', String(data.url || '#'));
      } else {
        openLms.classList.add('d-none');
        openLms.setAttribute('href', '#');
      }
    }
  };

  const saveMapping = async (row, moodleCourseId, forceRemap = false) => {
    const courseId = Number(row.dataset.appCourseId || 0);
    if (courseId <= 0) {
      throw new Error('Curso APP invalido.');
    }

    const payload = {
      course_id: courseId,
      moodle_courseid: Number(moodleCourseId || 0)
    };
    if (forceRemap) {
      payload.force_remap = 1;
    }

    return window.APP_HTTP.post('/api/admin/course/mapping/save', payload);
  };

  tableEl?.addEventListener('click', async (event) => {
    const button = event.target.closest('.js-save-map');
    if (!button) return;

    const row = button.closest('tr[data-app-course-id]');
    if (!row) return;

    const select = row.querySelector('.js-lms-select');
    if (!select) return;

    const selectedMoodleId = Number(select.value || 0);
    const currentMoodleId = Number(row.dataset.currentMoodleId || 0);

    if (selectedMoodleId === currentMoodleId) {
      if (window.showToast) window.showToast('Nenhuma alteracao para salvar.', 'info');
      return;
    }

    if (selectedMoodleId <= 0 && window.Swal) {
      const answer = await window.Swal.fire({
        icon: 'warning',
        title: 'Remover mapeamento?',
        text: 'Este curso APP ficara sem vinculo com curso do Moodle.',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
      });
      if (!answer.isConfirmed) {
        select.value = String(currentMoodleId || 0);
        return;
      }
    }

    button.disabled = true;
    button.classList.add('is-loading');
    select.disabled = true;

    try {
      let json = null;
      try {
        json = await saveMapping(row, selectedMoodleId, false);
      } catch (error) {
        const payload = error && error.payload ? error.payload : null;
        const isConflict = Number(error && error.status || 0) === 409 && payload && payload.code === 'mapping_conflict';
        if (!isConflict) {
          throw error;
        }

        const conflict = payload.conflict_course || {};
        if (!window.Swal) {
          throw error;
        }

        const confirm = await window.Swal.fire({
          icon: 'question',
          title: 'Curso LMS ja mapeado',
          text: `Este curso LMS esta no curso APP #${Number(conflict.id || 0)} - ${String(conflict.title || '')}. Deseja remapear mesmo assim?`,
          showCancelButton: true,
          confirmButtonText: 'Sim, remapear',
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        if (!confirm.isConfirmed) {
          throw new Error('Remapeamento cancelado.');
        }

        json = await saveMapping(row, selectedMoodleId, true);
      }

      const moodleCourse = json && json.moodle_course ? json.moodle_course : null;
      applyMappedVisualState(row, {
        mapped: moodleCourse && Number(moodleCourse.id || 0) > 0,
        moodleCourseId: moodleCourse ? Number(moodleCourse.id || 0) : 0,
        fullname: moodleCourse ? String(moodleCourse.fullname || '') : '',
        shortname: moodleCourse ? String(moodleCourse.shortname || '') : '',
        visible: moodleCourse ? Number(moodleCourse.visible || 0) === 1 : false,
        url: moodleCourse ? (`<?= h($moodleBase) ?>/course/view.php?id=${Number(moodleCourse.id || 0)}`) : '#'
      });

      recalcGlobalStats();
      if (table) table.draw(false); else recalcVisibleCount();
      if (window.showToast) window.showToast('Mapeamento salvo com sucesso.', 'success');
    } catch (error) {
      select.value = String(currentMoodleId || 0);
      if (window.Swal) {
        window.Swal.fire({ icon: 'error', title: 'Erro ao salvar', text: String(error.message || error) });
      }
    } finally {
      button.disabled = false;
      button.classList.remove('is-loading');
      select.disabled = false;
    }
  });

  recalcGlobalStats();
  recalcVisibleCount();
  };

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init, { once: true });
  }
})();
</script>
