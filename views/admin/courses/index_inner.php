<?php
use App\App;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$courses = $courses ?? [];
$statusLabel = [
  'published' => 'Publicado',
  'draft' => 'Rascunho',
  'archived' => 'Arquivado',
];

$total = count($courses);
$published = 0;
$draft = 0;
foreach ($courses as $course) {
  if (($course['status'] ?? '') === 'published') {
    $published++;
  } else if (($course['status'] ?? '') === 'draft') {
    $draft++;
  }
}
?>

<section class="admin-toolbar admin-toolbar--courses-page">
  <div class="admin-toolbar__stack">
    <div class="admin-toolbar__copy">
      <h1 class="admin-toolbar__title">Gestao de cursos</h1>
      <p class="admin-toolbar__subtitle">Crie, sincronize, publique e acompanhe os cursos operados pelo app.</p>
    </div>

    <div class="admin-toolbar__group admin-toolbar__group--actions">
      <a class="btn btn-primary btn-admin" href="<?= App::base_url('/admin/courses/new') ?>">
        <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Novo curso guiado
      </a>
      <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCourseCreate">
        <i class="fa-solid fa-plus me-2"></i> Criacao rapida
      </button>
      <button type="button" class="btn btn-outline-secondary" id="btnReloadCourses">
        <i class="fa-solid fa-rotate me-2"></i> Atualizar
      </button>
    </div>
  </div>

  <div class="admin-toolbar__stats" aria-label="Resumo de cursos">
    <span class="admin-toolbar__stat">
      <small>Cursos totais</small>
      <strong id="statTotal"><?= (int)$total ?></strong>
    </span>
    <span class="admin-toolbar__stat">
      <small>Publicados</small>
      <strong id="statPublished"><?= (int)$published ?></strong>
    </span>
    <span class="admin-toolbar__stat">
      <small>Rascunho</small>
      <strong id="statDraft"><?= (int)$draft ?></strong>
    </span>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel__header">
    <div>
      <div class="admin-panel__title">Catalogo de cursos</div>
      <div class="admin-panel__hint">Use os botoes da linha para editar, sincronizar e publicar.</div>
    </div>
    <div class="admin-courses-head-tools">
      <div class="admin-courses-filters" role="radiogroup" aria-label="Filtrar cursos por status">
        <label class="admin-courses-filter">
          <input type="radio" class="js-course-status-filter" name="course-status-filter" value="all" checked>
          <span>Todos</span>
        </label>
        <label class="admin-courses-filter">
          <input type="radio" class="js-course-status-filter" name="course-status-filter" value="published">
          <span>Publicados</span>
        </label>
        <label class="admin-courses-filter">
          <input type="radio" class="js-course-status-filter" name="course-status-filter" value="draft">
          <span>Rascunho</span>
        </label>
        <label class="admin-courses-filter">
          <input type="radio" class="js-course-status-filter" name="course-status-filter" value="archived">
          <span>Arquivados</span>
        </label>
      </div>
      <div class="admin-panel__hint" id="tableVisibleCount">0 cursos visiveis</div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table admin-table" id="courseTable">
      <thead>
        <tr>
          <th>Curso</th>
          <th>Status</th>
          <th>Moodle ID</th>
          <th>Acesso</th>
          <th>Politicas</th>
          <th>Atualizado</th>
          <th>Acoes</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($courses)): ?>
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">Nenhum curso cadastrado.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($courses as $course): ?>
          <?php
            $updatedText = '-';
            $updatedOrder = 0;
            if (!empty($course['updated_at'])) {
              $updatedTs = strtotime((string)$course['updated_at']);
              if ($updatedTs) {
                $updatedOrder = (int)$updatedTs;
                $updatedText = date('d/m/Y H:i', $updatedTs);
              }
            }
            $status = (string)($course['status'] ?? 'draft');
            $sequenceLockEnabled = ((int)($course['enable_sequence_lock'] ?? 1) === 1);
            $biometricRequired = ((int)($course['require_biometric'] ?? 0) === 1);
            $finalExamUnlockHours = max(0, (int)($course['final_exam_unlock_hours'] ?? 0));
            $showNumbering = ((int)($course['show_numbering'] ?? 0) === 1);
          ?>
          <tr
            data-id="<?= (int)$course['id'] ?>"
            data-title="<?= h($course['title'] ?? '') ?>"
            data-slug="<?= h($course['slug'] ?? '') ?>"
            data-description="<?= h($course['description'] ?? '') ?>"
            data-status="<?= h($status) ?>"
            data-moodle="<?= h((string)($course['moodle_courseid'] ?? '')) ?>"
            data-days="<?= h((string)($course['access_days'] ?? '')) ?>"
            data-sequence-lock="<?= $sequenceLockEnabled ? '1' : '0' ?>"
            data-require-biometric="<?= $biometricRequired ? '1' : '0' ?>"
            data-final-exam-unlock-hours="<?= (int)$finalExamUnlockHours ?>"
            data-show-numbering="<?= $showNumbering ? '1' : '0' ?>"
          >
            <td>
              <div class="course-title"><?= h($course['title'] ?? '') ?></div>
              <div class="course-meta">Slug: <?= h($course['slug'] ?? '') ?></div>
            </td>
            <td>
              <span class="status-pill status-<?= h($status) ?>"><?= h($statusLabel[$status] ?? $status) ?></span>
            </td>
            <td><?= h((string)($course['moodle_courseid'] ?? '-')) ?></td>
            <td><?= h((string)($course['access_days'] ?? '-')) ?> dias</td>
            <td>
              <span class="badge text-bg-light border me-1"><?= $sequenceLockEnabled ? 'Com trava' : 'Sem trava' ?></span>
              <span class="badge text-bg-light border me-1"><?= $biometricRequired ? 'Com biometria' : 'Sem biometria' ?></span>
              <span class="badge text-bg-light border me-1"><?= $finalExamUnlockHours > 0 ? ('Prova final em ' . $finalExamUnlockHours . 'h') : 'Prova final livre' ?></span>
              <span class="badge text-bg-light border"><?= $showNumbering ? 'Com numeracao' : 'Sem numeracao' ?></span>
            </td>
            <td data-order="<?= (int)$updatedOrder ?>"><?= h($updatedText) ?></td>
            <td>
              <div class="admin-actions">
                <button type="button" class="btn btn-sm btn-outline-dark js-edit" title="Editar curso">
                  <i class="fa-solid fa-pen-to-square"></i>
                </button>
                <a class="btn btn-sm btn-outline-info" href="<?= App::base_url('/admin/inscritos?course_id=' . (int)$course['id']) ?>" title="Ver inscritos deste curso">
                  <i class="fa-solid fa-users-viewfinder"></i>
                </a>
                <a class="btn btn-sm btn-outline-primary" href="<?= App::base_url('/admin/courses/' . (int)$course['id'] . '/builder') ?>" title="Abrir builder">
                  <i class="fa-solid fa-screwdriver-wrench"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-info js-sync" title="Sincronizar com Moodle">
                  <i class="fa-solid fa-rotate"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary js-sync-progress-push" title="Sincronizar progresso APP -> Moodle">
                  <i class="fa-solid fa-right-left"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success js-progress-catchup" title="Preservar 100% dos alunos concluidos ao incluir novos itens">
                  <i class="fa-solid fa-user-check"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-success js-publish" title="Alternar status">
                  <i class="fa-solid <?= $status === 'published' ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning js-toggle-numbering" title="<?= $showNumbering ? 'Desativar numeracao no menu' : 'Ativar numeracao no menu' ?>">
                  <i class="fa-solid <?= $showNumbering ? 'fa-list-check' : 'fa-list-ol' ?>"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger js-delete" title="Excluir curso e dados vinculados no app">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
                <a class="btn btn-sm btn-outline-secondary" href="<?= App::base_url('/course/' . (int)$course['id']) ?>" target="_blank" rel="noopener" title="Abrir area do aluno">
                  <i class="fa-solid fa-arrow-up-right-from-square"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal fade" id="modalCourseCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Criacao rapida de curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="frmCourseCreate" class="row g-3">
          <div class="col-12 col-lg-8">
            <label class="form-label">Titulo</label>
            <input type="text" class="form-control" name="title" id="createTitle" required>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Slug</label>
            <input type="text" class="form-control" name="slug" id="createSlug" required>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Estrutura</label>
            <select class="form-select" name="structure">
              <option value="simple">Simples</option>
              <option value="complex">Com modulos</option>
            </select>
          </div>
          <div class="col-6 col-lg-4">
            <label class="form-label">Moodle Course ID</label>
            <input type="number" class="form-control" name="moodle_courseid" min="1">
          </div>
          <div class="col-6 col-lg-4">
            <label class="form-label">Acesso (dias)</label>
            <input type="number" class="form-control" name="access_days" min="0">
          </div>

          <div class="col-6 col-lg-4">
            <label class="form-label">Fluxo de estudo</label>
            <select class="form-select" name="enable_sequence_lock">
              <option value="1" selected>Com trava sequencial</option>
              <option value="0">Sem trava (livre)</option>
            </select>
          </div>
          <div class="col-6 col-lg-4">
            <label class="form-label">Biometria</label>
            <select class="form-select" name="require_biometric">
              <option value="0" selected>Sem biometria</option>
              <option value="1">Com biometria por foto</option>
            </select>
          </div>
          <div class="col-6 col-lg-4">
            <label class="form-label">Liberacao prova final (horas)</label>
            <input type="number" class="form-control" name="final_exam_unlock_hours" min="0" step="1" value="0">
            <div class="form-text">0 desativa. Ex.: 72 = libera 72h apos o primeiro acesso no curso.</div>
          </div>

          <div class="col-12">
            <label class="form-label">Descricao</label>
            <textarea class="form-control" name="description" rows="3"></textarea>
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="chkOpenBuilder" checked>
              <label class="form-check-label" for="chkOpenBuilder">Abrir builder apos criar</label>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-admin" id="btnSubmitCreateCourse">Criar curso</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCourseEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="frmCourseEdit" class="row g-3">
          <input type="hidden" id="editCourseId" name="course_id">
          <div class="col-12 col-lg-8">
            <label class="form-label">Titulo</label>
            <input type="text" class="form-control" id="editTitle" name="title" required>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Moodle Course ID</label>
            <input type="number" class="form-control" id="editMoodleId" name="moodle_courseid" min="1">
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Acesso (dias)</label>
            <input type="number" class="form-control" id="editAccessDays" name="access_days" min="0">
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Fluxo de estudo</label>
            <select class="form-select" id="editSequenceLock" name="enable_sequence_lock">
              <option value="1">Com trava sequencial</option>
              <option value="0">Sem trava (livre)</option>
            </select>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Biometria</label>
            <select class="form-select" id="editRequireBiometric" name="require_biometric">
              <option value="0">Sem biometria</option>
              <option value="1">Com biometria por foto</option>
            </select>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Liberacao prova final (horas)</label>
            <input type="number" class="form-control" id="editFinalExamUnlockHours" name="final_exam_unlock_hours" min="0" step="1">
            <div class="form-text">0 desativa a trava. Ex.: 72 = libera 72h apos o primeiro acesso no curso.</div>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Numeracao no menu</label>
            <select class="form-select" id="editShowNumbering" name="show_numbering">
              <option value="0">Nao exibir numeracao</option>
              <option value="1">Exibir numeracao automatica</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Descricao</label>
            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-info" id="btnSyncCourseCover" type="button">
          <i class="fa-solid fa-image me-1"></i> Sincronizar imagem do curso
        </button>
        <button class="btn btn-primary btn-admin" id="btnSubmitEditCourse">Salvar alteracoes</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSyncPreview" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pre-visualizacao da sincronizacao</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2">
          <i class="fa-solid fa-circle-info me-1"></i>
          Revise a estrutura importada do Moodle. Por padrao, a sincronizacao usa merge seguro e nao sobrescreve o que ja esta no app.
        </div>

        <div id="syncPreviewLoading" class="text-center py-4">
          <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
          <div class="small text-muted">Carregando estrutura do curso...</div>
        </div>

        <div id="syncPreviewError" class="alert alert-danger d-none mb-0"></div>

        <div id="syncPreviewContent" class="d-none">
          <div class="sync-preview-head">
            <div class="sync-preview-title" id="syncPreviewCourseName">-</div>
            <div class="sync-preview-subtitle" id="syncPreviewCourseCode">-</div>
          </div>

          <div class="sync-preview-stats">
            <span class="sync-preview-pill">Modulos: <strong id="syncPreviewStatSections">0</strong></span>
            <span class="sync-preview-pill">Itens: <strong id="syncPreviewStatItems">0</strong></span>
            <span class="sync-preview-pill">Novos: <strong id="syncPreviewStatNew">0</strong></span>
            <span class="sync-preview-pill">Ja no app: <strong id="syncPreviewStatExisting">0</strong></span>
            <span class="sync-preview-pill">Quizzes: <strong id="syncPreviewStatQuiz">0</strong></span>
          </div>

          <div class="sync-preview-list" id="syncPreviewSections"></div>
        </div>

        <input type="hidden" id="syncPreviewCourseId" value="">
        <input type="hidden" id="syncPreviewMoodleId" value="">
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-secondary" id="btnSyncToggleEdit" disabled>Editar antes de salvar</button>
        <button class="btn btn-primary btn-admin" id="btnSyncApply" disabled>
          Sincronizar em modo seguro
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const init = () => {
  const statusLabel = {
    published: 'Publicado',
    draft: 'Rascunho',
    archived: 'Arquivado'
  };

  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';

  const slugify = (value) => {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 120);
  };

  const tableNode = document.getElementById('courseTable');
  const statusFilters = Array.from(document.querySelectorAll('.js-course-status-filter'));
  const visibleCount = document.getElementById('tableVisibleCount');

  const statTotal = document.getElementById('statTotal');
  const statPublished = document.getElementById('statPublished');
  const statDraft = document.getElementById('statDraft');

  const getSelectedStatus = () => {
    const checked = statusFilters.find((input) => input.checked);
    return checked ? checked.value : 'all';
  };

  let table;
  const syncPreviewModalEl = document.getElementById('modalSyncPreview');
  const modalApi = window.APP_MODAL || null;
  const syncPreviewLoading = document.getElementById('syncPreviewLoading');
  const syncPreviewError = document.getElementById('syncPreviewError');
  const syncPreviewContent = document.getElementById('syncPreviewContent');
  const syncPreviewCourseName = document.getElementById('syncPreviewCourseName');
  const syncPreviewCourseCode = document.getElementById('syncPreviewCourseCode');
  const syncPreviewStatSections = document.getElementById('syncPreviewStatSections');
  const syncPreviewStatItems = document.getElementById('syncPreviewStatItems');
  const syncPreviewStatNew = document.getElementById('syncPreviewStatNew');
  const syncPreviewStatExisting = document.getElementById('syncPreviewStatExisting');
  const syncPreviewStatQuiz = document.getElementById('syncPreviewStatQuiz');
  const syncPreviewSections = document.getElementById('syncPreviewSections');
  const syncPreviewCourseId = document.getElementById('syncPreviewCourseId');
  const syncPreviewMoodleId = document.getElementById('syncPreviewMoodleId');
  const btnSyncToggleEdit = document.getElementById('btnSyncToggleEdit');
  const btnSyncApply = document.getElementById('btnSyncApply');
  let syncEditMode = false;

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const syncTypeOptions = [
    { value: 'text', label: 'Texto' },
    { value: 'video', label: 'Video' },
    { value: 'pdf', label: 'PDF' },
    { value: 'download', label: 'Download' },
    { value: 'link', label: 'Link externo' },
    { value: 'final_exam', label: 'Prova final' },
    { value: 'certificate', label: 'Certificado' }
  ];
  const syncTypeLabel = {
    text: 'Texto',
    video: 'Video',
    pdf: 'PDF',
    download: 'Download',
    link: 'Link externo',
    final_exam: 'Prova final',
    certificate: 'Certificado'
  };
  const syncSectionTypeOptions = [
    { value: 'module', label: 'Modulo' },
    { value: 'topic', label: 'Topico' },
    { value: 'section', label: 'Secao' },
    { value: 'subsection', label: 'Subsecao' }
  ];
  const syncSectionTypeLabel = {
    module: 'Modulo',
    topic: 'Topico',
    section: 'Secao',
    subsection: 'Subsecao'
  };

  const recalcStats = () => {
    const rows = table
      ? table.rows({ search: 'applied' }).nodes().toArray()
      : Array.from(tableNode?.querySelectorAll('tbody tr[data-id]') || []);
    let total = 0;
    let published = 0;
    let draft = 0;

    rows.forEach((row) => {
      if (!row || !row.dataset) return;
      total++;
      if (row.dataset.status === 'published') published++;
      if (row.dataset.status === 'draft') draft++;
    });

    statTotal.textContent = String(total);
    statPublished.textContent = String(published);
    statDraft.textContent = String(draft);
    visibleCount.textContent = `${total} cursos visiveis`;
  };

  const renderPolicyBadges = (row) => {
    if (!row || !row.cells || !row.cells[4]) return;
    row.cells[4].innerHTML =
      `<span class="badge text-bg-light border me-1">${row.dataset.sequenceLock === '1' ? 'Com trava' : 'Sem trava'}</span>` +
      `<span class="badge text-bg-light border me-1">${row.dataset.requireBiometric === '1' ? 'Com biometria' : 'Sem biometria'}</span>` +
      `<span class="badge text-bg-light border me-1">${(parseInt(String(row.dataset.finalExamUnlockHours || '0'), 10) || 0) > 0 ? `Prova final em ${parseInt(String(row.dataset.finalExamUnlockHours || '0'), 10) || 0}h` : 'Prova final livre'}</span>` +
      `<span class="badge text-bg-light border">${row.dataset.showNumbering === '1' ? 'Com numeracao' : 'Sem numeracao'}</span>`;
  };

  const renderNumberingButton = (row) => {
    if (!row) return;
    const button = row.querySelector('.js-toggle-numbering');
    if (!button) return;

    const enabled = row.dataset.showNumbering === '1';
    button.title = enabled ? 'Desativar numeracao no menu' : 'Ativar numeracao no menu';

    const icon = button.querySelector('i');
    if (icon) {
      icon.className = `fa-solid ${enabled ? 'fa-list-check' : 'fa-list-ol'}`;
    }
  };

  const setSyncPreviewState = (state, message = '') => {
    if (syncPreviewLoading) syncPreviewLoading.classList.toggle('d-none', state !== 'loading');
    if (syncPreviewContent) syncPreviewContent.classList.toggle('d-none', state !== 'ready');
    if (syncPreviewError) {
      const isError = state === 'error';
      syncPreviewError.classList.toggle('d-none', !isError);
      syncPreviewError.textContent = isError ? message : '';
    }
    if (btnSyncApply) {
      btnSyncApply.disabled = state !== 'ready';
    }
    if (btnSyncToggleEdit) {
      btnSyncToggleEdit.disabled = state !== 'ready';
    }
  };

  const setSyncEditMode = (editing) => {
    syncEditMode = !!editing;
    if (syncPreviewContent) {
      syncPreviewContent.classList.toggle('is-editing', syncEditMode);
    }
    if (btnSyncToggleEdit) {
      btnSyncToggleEdit.textContent = syncEditMode ? 'Visualizar sem editar' : 'Editar antes de salvar';
    }
    if (btnSyncApply) {
      btnSyncApply.textContent = syncEditMode ? 'Salvar ajustes e sincronizar (seguro)' : 'Sincronizar em modo seguro';
    }
  };

  const renderSyncPreview = (preview) => {
    const course = preview && preview.course ? preview.course : {};
    const stats = preview && preview.stats ? preview.stats : {};
    const sections = Array.isArray(preview && preview.sections ? preview.sections : null)
      ? preview.sections
      : [];

    if (syncPreviewCourseName) {
      syncPreviewCourseName.textContent = course.fullname || 'Curso sem titulo';
    }
    if (syncPreviewCourseCode) {
      syncPreviewCourseCode.textContent = `Moodle ID: ${course.id || '-'}`;
    }
    if (syncPreviewStatSections) syncPreviewStatSections.textContent = String(stats.sections || 0);
    if (syncPreviewStatItems) syncPreviewStatItems.textContent = String(stats.items || 0);
    if (syncPreviewStatNew) syncPreviewStatNew.textContent = String(stats.new_items || 0);
    if (syncPreviewStatExisting) syncPreviewStatExisting.textContent = String(stats.existing_items || 0);
    if (syncPreviewStatQuiz) syncPreviewStatQuiz.textContent = String(stats.quiz_count || 0);

    if (syncPreviewSections) {
      if (!sections.length) {
        syncPreviewSections.innerHTML = '<div class="text-muted small py-2">Nenhum modulo com conteudo visivel encontrado.</div>';
      } else {
        syncPreviewSections.innerHTML = sections.map((section) => {
          const title = escapeHtml(section.title || 'Modulo');
          const subtitle = section.visible ? 'Visivel' : 'Oculto';
          const sectionLevel = Math.max(1, Number(section.level || 1));
          const sectionSubtypeRaw = String(section.container_subtype || '').trim();
          const sectionSubtype = sectionSubtypeRaw || 'module';
          const sectionSubtypeLabel = escapeHtml(syncSectionTypeLabel[sectionSubtype] || sectionSubtype);
          const sectionParent = Number(section.parent_section ?? -1);
          const sectionMetaText = sectionParent >= 0
            ? `${sectionSubtypeLabel} - Nivel ${sectionLevel} - Pai secao ${sectionParent}`
            : `${sectionSubtypeLabel} - Nivel ${sectionLevel}`;
          const count = Number(section.items_count || 0);
          const sectionNewCount = Number(section.new_items || 0);
          const sectionExistingCount = Number(section.existing_items || 0);
          const sectionNum = Number(section.section || 0);
          const summary = section.summary ? `<div class="sync-preview-summary">${escapeHtml(section.summary)}</div>` : '';
          const items = Array.isArray(section.items) ? section.items : [];
          const sectionSubtypeEditOptions = syncSectionTypeOptions.map((opt) => {
            const selected = opt.value === sectionSubtype ? ' selected' : '';
            return `<option value="${escapeHtml(opt.value)}"${selected}>${escapeHtml(opt.label)}</option>`;
          }).join('');
          const sectionSkipView = `<span class="sync-preview-skip-label sync-preview-view">Importar</span>`;
          const sectionSkipEdit = `
            <div class="sync-preview-skip sync-preview-edit">
              <label class="form-check form-check-inline mb-0">
                <input class="form-check-input sync-preview-section-skip" type="radio" name="sync-section-${sectionNum}" data-section="${sectionNum}" value="0" checked>
                <span>Importar</span>
              </label>
              <label class="form-check form-check-inline mb-0">
                <input class="form-check-input sync-preview-section-skip" type="radio" name="sync-section-${sectionNum}" data-section="${sectionNum}" value="1">
                <span>Ignorar</span>
              </label>
            </div>
          `;

          const itemsHtml = items.map((item) => {
            const itemTitleRaw = String(item.title || 'Item');
            const itemTitle = escapeHtml(itemTitleRaw);
            const subtype = String(item.subtype || 'link').trim();
            const subtypeLabel = escapeHtml(syncTypeLabel[subtype] || subtype);
            const cmid = Number(item.cmid || 0);
            const editable = Boolean(item.editable) && cmid > 0;
            const defaultSubtype = String(item.default_subtype || subtype);
            const source = String(item.source || '');
            const modname = String(item.modname || source || '');
            const viewUrl = String(item.view_url || '');
            const contentUrl = String(item.url || viewUrl || '');
            const viewUrlSafe = escapeHtml(viewUrl || '-');
            const contentUrlSafe = escapeHtml(contentUrl || '');
            const existsInApp = Boolean(item.exists_in_app);
            const allowedOptions = source === 'quiz'
              ? syncTypeOptions
              : syncTypeOptions.filter((opt) => opt.value !== 'final_exam' && opt.value !== 'certificate');

            const optionsHtml = allowedOptions.map((opt) => {
              const selected = opt.value === subtype ? ' selected' : '';
              return `<option value="${escapeHtml(opt.value)}"${selected}>${escapeHtml(opt.label)}</option>`;
            }).join('');

            const typeView = `<span class="sync-preview-item-type sync-preview-view">${subtypeLabel}</span>`;
            const typeEdit = editable
              ? `<select class="form-select form-select-sm sync-preview-type sync-preview-edit" data-cmid="${cmid}" data-default="${escapeHtml(defaultSubtype)}">${optionsHtml}</select>`
              : '';
            const titleView = `<span class="sync-preview-item-title sync-preview-view">${itemTitle}</span>`;
            const titleEdit = editable
              ? `<input type="text" class="form-control form-control-sm sync-preview-item-title-input sync-preview-edit" data-cmid="${cmid}" data-default="${itemTitle}" value="${itemTitle}">`
              : '';
            const cmidView = cmid > 0
              ? `<span class="sync-preview-view small text-muted">CMID: <strong>${cmid}</strong>${modname ? ` - ${escapeHtml(modname)}` : ''}</span>`
              : `<span class="sync-preview-view small text-muted">Sem CMID (conteudo de secao).</span>`;
            const cmidEdit = editable
              ? `<input type="number" min="1" step="1" class="form-control form-control-sm sync-preview-item-cmid-input sync-preview-edit mt-1" data-cmid="${cmid}" data-default="${cmid}" value="${cmid}" placeholder="CMID">`
              : '';
            const urlView = `<div class="sync-preview-view small text-muted mt-1">URL: <code>${contentUrlSafe || viewUrlSafe}</code></div>`;
            const urlEdit = editable
              ? `<input type="text" class="form-control form-control-sm sync-preview-item-url-input sync-preview-edit mt-1" data-cmid="${cmid}" data-default="${escapeHtml(contentUrl)}" value="${escapeHtml(contentUrl)}" placeholder="URL da view/link do item">`
              : '';
            const itemOriginBadge = existsInApp
              ? '<span class="badge text-bg-light border sync-preview-view">Ja no app</span>'
              : '<span class="badge text-bg-success sync-preview-view">Novo</span>';

            const skipView = editable
              ? `<span class="sync-preview-skip-label sync-preview-view">${existsInApp ? 'Ja no app' : 'Importar'}</span>`
              : '';
            const skipEdit = editable ? `
              <div class="sync-preview-skip sync-preview-edit">
                <label class="form-check form-check-inline mb-0">
                  <input class="form-check-input sync-preview-item-skip" type="radio" name="sync-item-${cmid}" data-cmid="${cmid}" value="0"${existsInApp ? '' : ' checked'}>
                  <span>Importar</span>
                </label>
                <label class="form-check form-check-inline mb-0">
                  <input class="form-check-input sync-preview-item-skip" type="radio" name="sync-item-${cmid}" data-cmid="${cmid}" value="1"${existsInApp ? ' checked' : ''}>
                  <span>${existsInApp ? 'Nao importar (ja existe)' : 'Ignorar'}</span>
                </label>
              </div>
            ` : '';

            return `
              <li>
                <div class="sync-preview-item-main">
                  ${titleView}
                  ${titleEdit}
                  ${cmidView}
                  ${cmidEdit}
                  ${urlView}
                  ${urlEdit}
                </div>
                <div class="sync-preview-item-side">
                  ${itemOriginBadge}
                  ${typeView}
                  ${typeEdit}
                  ${skipView}
                  ${skipEdit}
                </div>
              </li>
            `;
          }).join('');

          return `
            <article class="sync-preview-section">
              <div class="sync-preview-section__head">
                <div>
                  <div class="sync-preview-section__title-wrap">
                    <div class="sync-preview-section__title sync-preview-view">${title}</div>
                    <input type="text" class="form-control form-control-sm sync-preview-section-title-input sync-preview-edit" data-section="${sectionNum}" data-default="${title}" value="${title}">
                  </div>
                  <div class="sync-preview-section__meta">${subtitle} - ${sectionMetaText}</div>
                  <select class="form-select form-select-sm sync-preview-section-subtype sync-preview-edit mt-2" data-section="${sectionNum}" data-default="${escapeHtml(sectionSubtype)}">
                    ${sectionSubtypeEditOptions}
                  </select>
                </div>
                <div class="sync-preview-section__actions">
                  <span class="sync-preview-count">${count} itens</span>
                  <span class="badge text-bg-success">Novos: ${sectionNewCount}</span>
                  <span class="badge text-bg-light border">Ja no app: ${sectionExistingCount}</span>
                  ${sectionSkipView}
                  ${sectionSkipEdit}
                </div>
              </div>
              ${summary}
              <ul class="sync-preview-items">${itemsHtml}</ul>
            </article>
          `;
        }).join('');
      }
    }
  };

  const openSyncPreview = async (row, moodleId) => {
    if (!row || !syncPreviewModalEl) return;
    const courseId = String(row.dataset.id || '');
    if (!courseId || !moodleId) return;

    if (syncPreviewCourseId) syncPreviewCourseId.value = courseId;
    if (syncPreviewMoodleId) syncPreviewMoodleId.value = moodleId;
    setSyncEditMode(false);
    setSyncPreviewState('loading');
    modalApi?.open(syncPreviewModalEl);

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('moodle_courseid', moodleId);
      fd.append('csrf', csrf);
      const json = await apiPost('<?= App::base_url('/api/admin/course/sync/preview') ?>', fd);
      renderSyncPreview(json.preview || {});
      setSyncPreviewState('ready');
    } catch (error) {
      setSyncPreviewState('error', String(error.message || error));
    }
  };

  syncPreviewModalEl?.addEventListener('hidden.bs.modal', () => {
    if (syncPreviewCourseId) syncPreviewCourseId.value = '';
    if (syncPreviewMoodleId) syncPreviewMoodleId.value = '';
    if (syncPreviewSections) syncPreviewSections.innerHTML = '';
    if (syncPreviewCourseName) syncPreviewCourseName.textContent = '-';
    if (syncPreviewCourseCode) syncPreviewCourseCode.textContent = '-';
    if (syncPreviewStatSections) syncPreviewStatSections.textContent = '0';
    if (syncPreviewStatItems) syncPreviewStatItems.textContent = '0';
    if (syncPreviewStatNew) syncPreviewStatNew.textContent = '0';
    if (syncPreviewStatExisting) syncPreviewStatExisting.textContent = '0';
    if (syncPreviewStatQuiz) syncPreviewStatQuiz.textContent = '0';
    setSyncEditMode(false);
    setSyncPreviewState('loading');
  });

  const formDataWithCsrf = (form) => {
    const fd = new FormData(form);
    if (!fd.has('csrf')) fd.append('csrf', csrf);
    return fd;
  };

  const apiPost = async (path, data) => {
    const res = await fetch(path, { method: 'POST', body: data, credentials: 'same-origin' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok === false) {
      const message = json && json.error ? json.error : 'Falha na requisicao.';
      throw new Error(message);
    }
    return json;
  };

  const getRow = (button) => {
    const tr = button.closest('tr');
    if (!tr) return null;
    if (!table) return tr;
    return table.row(tr).node() || tr;
  };

  if (window.jQuery && $.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
    $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
      if (settings.nTable.id !== 'courseTable') return true;
      if (!table) return true;
      const status = getSelectedStatus();
      if (status === 'all') return true;
      const row = table.row(dataIndex).node();
      if (!row || !row.dataset) return true;
      return row.dataset.status === status;
    });
  }

  if (window.jQuery && $.fn.DataTable && tableNode) {
    table = window.APP_DATA_TABLE
      ? window.APP_DATA_TABLE.init(tableNode, {
          order: [[5, 'desc']]
        })
      : $(tableNode).DataTable({
          pageLength: 10,
          lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
          order: [[5, 'desc']],
          autoWidth: false,
          orderClasses: false,
          language: {
            url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json'
          }
        });

    if (statusFilters.length) {
      statusFilters.forEach((input) => input.addEventListener('change', () => {
        table.draw();
      }));
    }

    table.on('draw', recalcStats);
    recalcStats();
  } else if (statusFilters.length && tableNode) {
    const applyFallbackStatusFilter = () => {
      const selected = getSelectedStatus();
      const rows = Array.from(tableNode.querySelectorAll('tbody tr[data-id]'));
      rows.forEach((row) => {
        const visible = selected === 'all' || row.dataset.status === selected;
        row.classList.toggle('d-none', !visible);
      });
      recalcStats();
    };

    statusFilters.forEach((input) => input.addEventListener('change', applyFallbackStatusFilter));
    applyFallbackStatusFilter();
  }

  document.getElementById('btnReloadCourses')?.addEventListener('click', () => location.reload());

  const createTitle = document.getElementById('createTitle');
  const createSlug = document.getElementById('createSlug');
  if (createTitle && createSlug) {
    createTitle.addEventListener('input', () => {
      if (!createSlug.dataset.touched) {
        createSlug.value = slugify(createTitle.value);
      }
    });
    createSlug.addEventListener('input', () => {
      createSlug.dataset.touched = '1';
    });
  }

  btnSyncToggleEdit?.addEventListener('click', () => {
    setSyncEditMode(!syncEditMode);
  });

  document.getElementById('btnSubmitCreateCourse')?.addEventListener('click', async () => {
    const form = document.getElementById('frmCourseCreate');
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    try {
      const json = await apiPost('<?= App::base_url('/api/admin/course/create') ?>', formDataWithCsrf(form));
      const course = json.course || null;
      showToast('Curso criado com sucesso.', 'success');
      const modalCreate = document.getElementById('modalCourseCreate');
      if (modalApi && modalCreate) modalApi.close(modalCreate);

      if (course && document.getElementById('chkOpenBuilder')?.checked) {
        window.location.href = `<?= App::base_url('/admin/courses') ?>/${course.id}/builder`;
        return;
      }
      location.reload();
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao criar curso', text: String(error.message || error) });
    }
  });

  const editModalEl = document.getElementById('modalCourseEdit');
  const tableBody = tableNode ? tableNode.querySelector('tbody') : null;

  const bindRowAction = (selector, handler) => {
    if (!tableBody) return;
    tableBody.addEventListener('click', (event) => {
      const button = event.target.closest(selector);
      if (!button || !tableBody.contains(button)) return;
      handler(event, button);
    });
  };

  bindRowAction('.js-edit', (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    document.getElementById('editCourseId').value = row.dataset.id || '';
    document.getElementById('editTitle').value = row.dataset.title || '';
    document.getElementById('editMoodleId').value = row.dataset.moodle || '';
    document.getElementById('editAccessDays').value = row.dataset.days || '';
    document.getElementById('editSequenceLock').value = row.dataset.sequenceLock || '1';
    document.getElementById('editRequireBiometric').value = row.dataset.requireBiometric || '0';
    document.getElementById('editFinalExamUnlockHours').value = row.dataset.finalExamUnlockHours || '0';
    document.getElementById('editShowNumbering').value = row.dataset.showNumbering || '0';
    document.getElementById('editDescription').value = row.dataset.description || '';
    if (!modalApi || !editModalEl) {
      Swal.fire({ icon: 'error', title: 'Falha na interface', text: 'Modal de edicao indisponivel. Recarregue a pagina.' });
      return;
    }
    modalApi.open(editModalEl);
  });

  document.getElementById('btnSyncCourseCover')?.addEventListener('click', async () => {
    const courseId = String(document.getElementById('editCourseId')?.value || '').trim();
    const moodleId = String(document.getElementById('editMoodleId')?.value || '').trim();
    if (!courseId) {
      Swal.fire({ icon: 'warning', title: 'Curso invalido', text: 'Abra a configuracao de um curso valido.' });
      return;
    }
    if (!moodleId) {
      Swal.fire({ icon: 'warning', title: 'Moodle ID ausente', text: 'Informe o Moodle Course ID antes de sincronizar a imagem.' });
      return;
    }

    const confirm = await Swal.fire({
      icon: 'question',
      title: 'Sincronizar imagem do curso?',
      text: 'A imagem atual do app sera atualizada com a capa do curso no Moodle.',
      showCancelButton: true,
      confirmButtonText: 'Sincronizar imagem',
      cancelButtonText: 'Cancelar'
    });
    if (!confirm.isConfirmed) return;

    const button = document.getElementById('btnSyncCourseCover');
    const originalHtml = button ? button.innerHTML : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Sincronizando...';
    }

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('moodle_courseid', moodleId);
      fd.append('csrf', csrf);
      const json = await apiPost('<?= App::base_url('/api/admin/course/sync-cover') ?>', fd);

      const row = tableNode.querySelector(`tbody tr[data-id="${courseId}"]`);
      if (row) {
        row.dataset.moodle = moodleId;
        row.cells[2].textContent = moodleId;
        if (table) {
          table.row(row).invalidate().draw(false);
        }
      }

      const imagePath = String((json?.result && json.result.image) || '').trim();
      if (imagePath) {
        showToast('Imagem do curso sincronizada com sucesso.', 'success');
      } else {
        showToast('Sincronizacao concluida.', 'success');
      }
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao sincronizar imagem', text: String(error.message || error) });
    } finally {
      if (button) {
        button.disabled = false;
        button.innerHTML = originalHtml;
      }
    }
  });

  document.getElementById('btnSubmitEditCourse')?.addEventListener('click', async () => {
    const form = document.getElementById('frmCourseEdit');
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    try {
      await apiPost('<?= App::base_url('/api/admin/course/update') ?>', formDataWithCsrf(form));

      const id = document.getElementById('editCourseId').value;
      const row = tableNode.querySelector(`tbody tr[data-id="${id}"]`);
      if (row) {
        row.dataset.title = document.getElementById('editTitle').value;
        row.dataset.moodle = document.getElementById('editMoodleId').value;
        row.dataset.days = document.getElementById('editAccessDays').value;
        row.dataset.sequenceLock = document.getElementById('editSequenceLock').value;
        row.dataset.requireBiometric = document.getElementById('editRequireBiometric').value;
        row.dataset.finalExamUnlockHours = document.getElementById('editFinalExamUnlockHours').value;
        row.dataset.showNumbering = document.getElementById('editShowNumbering').value;
        row.dataset.description = document.getElementById('editDescription').value;

        row.querySelector('.course-title').textContent = row.dataset.title;
        row.querySelector('.course-meta').textContent = `Slug: ${row.dataset.slug || ''}`;
        row.cells[2].textContent = row.dataset.moodle || '-';
        row.cells[3].textContent = `${row.dataset.days || '-'} dias`;
        renderPolicyBadges(row);
        renderNumberingButton(row);

        if (table) {
          table.row(row).invalidate().draw(false);
        }
      }

      if (modalApi && editModalEl) modalApi.close(editModalEl);
      showToast('Curso atualizado.', 'success');
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao salvar', text: String(error.message || error) });
    }
  });

  bindRowAction('.js-sync', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    let moodleId = String(row.dataset.moodle || '').trim();

    if (!moodleId) {
      const answer = await Swal.fire({
        title: 'Moodle Course ID',
        text: 'Informe o Course ID no Moodle para sincronizar o conteudo.',
        input: 'number',
        inputAttributes: { min: 1, step: 1 },
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar'
      });
      if (!answer.isConfirmed) return;
      moodleId = String(answer.value || '').trim();
    }

    if (!moodleId) {
      Swal.fire({ icon: 'warning', title: 'Moodle ID invalido', text: 'Informe um ID valido.' });
      return;
    }

    openSyncPreview(row, moodleId);
  });

  bindRowAction('.js-sync-progress-push', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    const courseId = String(row.dataset.id || '').trim();
    const moodleId = String(row.dataset.moodle || '').trim();
    if (!courseId) return;

    if (!moodleId) {
      Swal.fire({
        icon: 'warning',
        title: 'Moodle ID ausente',
        text: 'Defina o Moodle Course ID antes de sincronizar progresso para o Moodle.'
      });
      return;
    }

    const ok = await Swal.fire({
      icon: 'question',
      title: 'Sincronizar progresso APP -> Moodle?',
      text: 'Vai enviar para o Moodle os itens concluidos no app deste curso.',
      showCancelButton: true,
      confirmButtonText: 'Sincronizar agora',
      cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('csrf', csrf);
      const json = await apiPost('<?= App::base_url('/api/admin/course/progress/push') ?>', fd);
      const sync = json && json.sync ? json.sync : {};

      await Swal.fire({
        icon: 'success',
        title: 'Sincronizacao concluida',
        html:
          `<div class="text-start small">` +
          `Tentativas: <strong>${Number(sync.attempted || 0)}</strong><br>` +
          `Sincronizados: <strong>${Number(sync.synced || 0)}</strong><br>` +
          `Ja sincronizados: <strong>${Number(sync.already_synced || 0)}</strong><br>` +
          `Ignorados: <strong>${Number(sync.skipped || 0)}</strong><br>` +
          `Erros: <strong>${Number(sync.errors || 0)}</strong>` +
          `</div>`
      });
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro de sincronizacao', text: String(error.message || error) });
    } finally {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  });

  bindRowAction('.js-progress-catchup', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    const courseId = String(row.dataset.id || '').trim();
    if (!courseId) return;

    const ok = await Swal.fire({
      icon: 'question',
      title: 'Preservar alunos com 100%?',
      text: 'Use esta rotina depois de incluir novos itens que contam no percentual, para nao derrubar alunos que ja tinham concluido o curso.',
      showCancelButton: true,
      confirmButtonText: 'Rodar catchup',
      cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('csrf', csrf);
      const json = await apiPost('<?= App::base_url('/api/admin/course/progress/catchup') ?>', fd);
      const summary = json && json.summary ? json.summary : {};

      await Swal.fire({
        icon: 'success',
        title: 'Catchup concluido',
        html:
          `<div class="text-start small">` +
          `Itens elegiveis: <strong>${Number(summary.eligible_nodes || 0)}</strong><br>` +
          `Itens processados: <strong>${Number(summary.processed_nodes || 0)}</strong><br>` +
          `Marcacoes aplicadas: <strong>${Number(summary.auto_completed_rows || 0)}</strong>` +
          `</div>`
      });
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro no catchup', text: String(error.message || error) });
    } finally {
      button.disabled = false;
      button.innerHTML = originalHtml;
    }
  });

  btnSyncApply?.addEventListener('click', async () => {
    const courseId = String(syncPreviewCourseId?.value || '').trim();
    const moodleId = String(syncPreviewMoodleId?.value || '').trim();
    if (!courseId || !moodleId) return;

    const ok = await Swal.fire({
      icon: 'question',
      title: syncEditMode ? 'Salvar ajustes e sincronizar (merge seguro)?' : 'Sincronizar em modo seguro?',
      text: syncEditMode
        ? 'Serao importados somente itens novos do Moodle. Itens ja existentes no app nao serao sobrescritos.'
        : 'A sincronizacao vai importar apenas o que ainda nao existe no app (merge incremental).',
      showCancelButton: true,
      confirmButtonText: 'Sincronizar em modo seguro',
      cancelButtonText: 'Cancelar'
    });
    if (!ok.isConfirmed) return;

    const originalText = btnSyncApply.textContent;
    btnSyncApply.disabled = true;
    btnSyncApply.textContent = 'Sincronizando...';

    try {
      const overrides = {
        item_subtype: {},
        item_title: {},
        section_title: {},
        section_subtype: {},
        item_cmid: {},
        item_url: {},
        item_skip: {},
        section_skip: {}
      };

      syncPreviewSections?.querySelectorAll('.sync-preview-type[data-cmid]').forEach((el) => {
        const select = el;
        const cmid = String(select.dataset.cmid || '').trim();
        const selectedValue = String(select.value || '').trim();
        const defaultValue = String(select.dataset.default || '').trim();
        if (!cmid || !selectedValue) return;
        if (selectedValue === defaultValue) return;
        overrides.item_subtype[cmid] = selectedValue;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-item-title-input[data-cmid]').forEach((el) => {
        const input = el;
        const cmid = String(input.dataset.cmid || '').trim();
        const value = String(input.value || '').trim();
        const defaultValue = String(input.dataset.default || '').trim();
        if (!cmid || value === '' || value === defaultValue) return;
        overrides.item_title[cmid] = value;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-item-cmid-input[data-cmid]').forEach((el) => {
        const input = el;
        const cmid = String(input.dataset.cmid || '').trim();
        const value = String(input.value || '').trim();
        const defaultValue = String(input.dataset.default || '').trim();
        if (!cmid || value === '' || value === defaultValue) return;
        const parsed = parseInt(value, 10);
        if (!Number.isFinite(parsed) || parsed <= 0) return;
        overrides.item_cmid[cmid] = parsed;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-item-url-input[data-cmid]').forEach((el) => {
        const input = el;
        const cmid = String(input.dataset.cmid || '').trim();
        const value = String(input.value || '').trim();
        const defaultValue = String(input.dataset.default || '').trim();
        if (!cmid || value === '' || value === defaultValue) return;
        overrides.item_url[cmid] = value;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-item-skip[data-cmid]:checked').forEach((el) => {
        const input = el;
        const cmid = String(input.dataset.cmid || '').trim();
        if (!cmid) return;
        if (String(input.value || '') === '1') {
          overrides.item_skip[cmid] = 1;
        }
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-section-title-input[data-section]').forEach((el) => {
        const input = el;
        const section = String(input.dataset.section || '').trim();
        const value = String(input.value || '').trim();
        const defaultValue = String(input.dataset.default || '').trim();
        if (!section || value === '' || value === defaultValue) return;
        overrides.section_title[section] = value;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-section-subtype[data-section]').forEach((el) => {
        const select = el;
        const section = String(select.dataset.section || '').trim();
        const value = String(select.value || '').trim();
        const defaultValue = String(select.dataset.default || '').trim();
        if (!section || !value || value === defaultValue) return;
        overrides.section_subtype[section] = value;
      });

      syncPreviewSections?.querySelectorAll('.sync-preview-section-skip[data-section]:checked').forEach((el) => {
        const input = el;
        const section = String(input.dataset.section || '').trim();
        if (!section) return;
        if (String(input.value || '') === '1') {
          overrides.section_skip[section] = 1;
        }
      });

      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('moodle_courseid', moodleId);
      fd.append('mode', 'merge');
      fd.append('sync_overrides', JSON.stringify(overrides));
      fd.append('csrf', csrf);

      const syncResult = await apiPost('<?= App::base_url('/api/admin/course/sync') ?>', fd);

      const row = tableNode.querySelector(`tbody tr[data-id="${courseId}"]`);
      if (row) {
        row.dataset.moodle = moodleId;
        row.cells[2].textContent = moodleId;
        if (table) table.row(row).invalidate().draw(false);
      }

      if (modalApi && syncPreviewModalEl) modalApi.close(syncPreviewModalEl);

      const next = await Swal.fire({
        icon: 'success',
        title: 'Sincronizacao concluida',
        text: 'Deseja abrir o Builder para revisar e ajustar antes de publicar?',
        showCancelButton: true,
        confirmButtonText: 'Abrir Builder',
        cancelButtonText: 'Ficar nesta tela'
      });

      if (next.isConfirmed) {
        window.location.href = '<?= App::base_url('/admin/courses') ?>/' + courseId + '/builder';
        return;
      }

      const mappedNodes = Number((syncResult?.stats && syncResult.stats.mapped_nodes) || 0);
      const totalNodes = Number((syncResult?.stats && syncResult.stats.total_nodes) || 0);
      const importedNodes = Number((syncResult?.stats && syncResult.stats.nodes) || 0);
      const skippedExisting = Number((syncResult?.stats && syncResult.stats.skipped_existing) || 0);
      if (totalNodes > 0 && mappedNodes === 0) {
        await Swal.fire({
          icon: 'warning',
          title: 'Sincronizacao concluida com alerta',
          text: 'Nenhum item foi salvo com CMID em coluna dedicada. Revise os mapeamentos na pre-visualizacao.'
        });
      }
      showToast(`Merge concluido: ${importedNodes} novos itens importados, ${skippedExisting} itens preservados.`, 'success');
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro de sincronizacao', text: String(error.message || error) });
    } finally {
      btnSyncApply.disabled = false;
      btnSyncApply.textContent = originalText;
    }
  });

  bindRowAction('.js-publish', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    const current = row.dataset.status || 'draft';
    const next = current === 'published' ? 'draft' : 'published';

    const ok = await Swal.fire({
      icon: 'question',
      title: next === 'published' ? 'Publicar curso?' : 'Voltar para rascunho?',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar'
    });

    if (!ok.isConfirmed) return;

    try {
      const fd = new FormData();
      fd.append('course_id', row.dataset.id || '');
      fd.append('status', next);
      fd.append('csrf', csrf);
      await apiPost('<?= App::base_url('/api/admin/course/publish') ?>', fd);

      row.dataset.status = next;
      const statusEl = row.querySelector('.status-pill');
      if (statusEl) {
        statusEl.className = `status-pill status-${next}`;
        statusEl.textContent = statusLabel[next] || next;
      }

      const icon = button.querySelector('i');
      if (icon) {
        icon.className = `fa-solid ${next === 'published' ? 'fa-eye-slash' : 'fa-eye'}`;
      }

      if (table) table.row(row).invalidate().draw(false);
      recalcStats();
      showToast('Status atualizado.', 'success');
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao atualizar status', text: String(error.message || error) });
    }
  });

  bindRowAction('.js-toggle-numbering', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    const courseId = String(row.dataset.id || '').trim();
    if (!courseId) return;

    const current = row.dataset.showNumbering === '1';
    const next = current ? '0' : '1';

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('show_numbering', next);
      fd.append('csrf', csrf);
      const json = await apiPost('<?= App::base_url('/api/admin/course/numbering/toggle') ?>', fd);

      row.dataset.showNumbering = String((json && json.show_numbering) ? '1' : '0');
      renderPolicyBadges(row);
      renderNumberingButton(row);

      if (table) {
        table.row(row).invalidate().draw(false);
      }

      showToast(row.dataset.showNumbering === '1' ? 'Numeracao ativada.' : 'Numeracao desativada.', 'success');
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao atualizar numeracao', text: String(error.message || error) });
    }
  });

  bindRowAction('.js-delete', async (_event, button) => {
    const row = getRow(button);
    if (!row || !row.dataset) return;

    const courseId = String(row.dataset.id || '').trim();
    const title = String(row.dataset.title || 'Curso').trim();
    if (!courseId) return;

    const confirm = await Swal.fire({
      icon: 'warning',
      title: 'Excluir curso do app?',
      html: `
        <div class="text-start small">
          <p class="mb-2"><strong>${escapeHtml(title)}</strong></p>
          <p class="mb-2">Esta acao remove o curso e tudo vinculado no app:</p>
          <ul class="mb-2 text-start">
            <li>estrutura (nodes)</li>
            <li>progresso dos alunos</li>
            <li>configuracao de prova</li>
            <li>auditoria biometrica</li>
            <li>arquivos em storage/courses/${escapeHtml(courseId)}</li>
          </ul>
          <p class="mb-0">Digite <strong>EXCLUIR</strong> para confirmar.</p>
        </div>
      `,
      input: 'text',
      inputPlaceholder: 'EXCLUIR',
      showCancelButton: true,
      confirmButtonText: 'Excluir agora',
      confirmButtonColor: '#dc3545',
      cancelButtonText: 'Cancelar',
      preConfirm: (value) => {
        if (String(value || '').trim().toUpperCase() !== 'EXCLUIR') {
          Swal.showValidationMessage('Digite EXCLUIR para confirmar.');
          return false;
        }
        return true;
      }
    });

    if (!confirm.isConfirmed) return;

    try {
      const fd = new FormData();
      fd.append('course_id', courseId);
      fd.append('csrf', csrf);
      await apiPost('<?= App::base_url('/api/admin/course/delete') ?>', fd);

      if (table) {
        table.row(row).remove().draw(false);
      } else {
        row.remove();
      }
      recalcStats();
      showToast('Curso excluido com sucesso.', 'success');
    } catch (error) {
      Swal.fire({ icon: 'error', title: 'Erro ao excluir curso', text: String(error.message || error) });
    }
  });
  };

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init, { once: true });
  }
})();
</script>
