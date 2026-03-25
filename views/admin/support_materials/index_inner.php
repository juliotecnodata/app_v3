<?php
use App\App;

function h_support($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'published' => 0, 'hidden' => 0, 'courses' => 0];
$courseFilters = $courseFilters ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$selectedCourse = $selectedCourse ?? null;
$editMaterial = $editMaterial ?? null;
$flash = $flash ?? null;
$csrf = $csrf ?? '';

$cards = [
  ['label' => 'Materiais', 'value' => (int)($summary['total'] ?? 0), 'icon' => 'fa-book-open'],
  ['label' => 'Publicados', 'value' => (int)($summary['published'] ?? 0), 'icon' => 'fa-circle-check'],
  ['label' => 'Ocultos', 'value' => (int)($summary['hidden'] ?? 0), 'icon' => 'fa-eye-slash'],
  ['label' => 'Cursos', 'value' => (int)($summary['courses'] ?? 0), 'icon' => 'fa-layer-group'],
];

$editId = (int)($editMaterial['id'] ?? 0);
$formCourseId = (int)($editMaterial['course_id'] ?? $selectedCourseId);
$formTitle = (string)($editMaterial['title'] ?? '');
$formUrl = (string)($editMaterial['url'] ?? '');
$formSort = (int)($editMaterial['sort_order'] ?? 0);
$formPublished = (int)($editMaterial['is_published'] ?? 1);
?>

<?php if ($flash && !empty($flash['msg'])): ?>
  <div class="alert alert-<?= h_support($flash['type'] ?? 'info') ?> admin-inline-alert" role="alert">
    <?= h_support($flash['msg']) ?>
  </div>
<?php endif; ?>

<section class="support-material-page">
  <div class="biometric-audit-cards">
    <?php foreach ($cards as $card): ?>
      <article class="biometric-audit-card">
        <span class="biometric-audit-card__icon"><i class="fa-solid <?= h_support($card['icon']) ?>"></i></span>
        <div class="biometric-audit-card__body">
          <span class="biometric-audit-card__label"><?= h_support($card['label']) ?></span>
          <strong class="biometric-audit-card__value"><?= (int)$card['value'] ?></strong>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="support-material-layout">
    <section class="admin-panel support-material-form-panel">
      <div class="admin-panel__header">
        <div>
          <div class="admin-panel__title"><?= $editId > 0 ? 'Editar material' : 'Novo material de apoio' ?></div>
          <div class="admin-panel__hint">Cadastre links externos por curso. O aluno acessa isso fora do curso, em nova aba.</div>
        </div>
      </div>

      <form method="post" class="support-material-form">
        <input type="hidden" name="csrf" value="<?= h_support($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editId ?>">

        <label class="support-material-form__field">
          <span>Curso</span>
          <select class="form-select" name="course_id" required>
            <option value="">Selecione o curso</option>
            <?php foreach ($courseFilters as $course): ?>
              <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $formCourseId ? 'selected' : '' ?>>
                <?= h_support((string)($course['title'] ?? '')) ?> (Moodle #<?= (int)($course['moodle_courseid'] ?? 0) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="support-material-form__field">
          <span>Titulo</span>
          <input type="text" class="form-control" name="title" value="<?= h_support($formTitle) ?>" placeholder="Ex.: E-book de legislacao" required>
        </label>

        <label class="support-material-form__field">
          <span>URL</span>
          <input type="url" class="form-control" name="url" value="<?= h_support($formUrl) ?>" placeholder="https://..." required>
        </label>

        <div class="support-material-form__row">
          <label class="support-material-form__field">
            <span>Ordem</span>
            <input type="number" class="form-control" name="sort_order" value="<?= (int)$formSort ?>" min="0" step="1">
          </label>

          <label class="support-material-form__field">
            <span>Publicado</span>
            <select class="form-select" name="is_published">
              <option value="1" <?= $formPublished === 1 ? 'selected' : '' ?>>Sim</option>
              <option value="0" <?= $formPublished === 0 ? 'selected' : '' ?>>Nao</option>
            </select>
          </label>
        </div>

        <div class="support-material-form__actions">
          <button type="submit" class="btn btn-ui btn-ui--primary">
            <i class="fa-solid fa-floppy-disk"></i>
            <?= $editId > 0 ? 'Salvar material' : 'Criar material' ?>
          </button>
          <?php if ($editId > 0): ?>
            <a class="btn btn-ui btn-ui--neutral" href="<?= h_support(App::base_url('/admin/material-apoio' . ($selectedCourseId > 0 ? '?course_id=' . $selectedCourseId : ''))) ?>">
              <i class="fa-solid fa-xmark"></i> Cancelar edicao
            </a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="enroll-table-shell enroll-table-shell--v2 support-material-table-panel">
      <div class="enroll-table-shell__head enroll-table-shell__head--compact">
        <div>
          <h3>Links cadastrados</h3>
          <p>
            <?= $selectedCourseId > 0 && is_array($selectedCourse)
              ? 'Curso filtrado: ' . h_support((string)($selectedCourse['title'] ?? ''))
              : 'Todos os materiais de apoio vinculados aos cursos do app.' ?>
          </p>
        </div>
        <div class="enroll-table-shell__head-actions">
          <div class="enroll-table-shell__meta" id="supportMaterialVisibleCount"><?= (int)($summary['total'] ?? 0) ?> materiais</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table admin-table admin-table--support-materials" id="supportMaterialTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Curso</th>
              <th>Titulo</th>
              <th>URL</th>
              <th>Ordem</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                  Nenhum material de apoio encontrado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $id = (int)($row['id'] ?? 0);
                  $courseId = (int)($row['course_id'] ?? 0);
                  $courseTitle = (string)($row['course_title'] ?? 'Curso');
                  $materialTitle = (string)($row['title'] ?? 'Material');
                  $url = (string)($row['url'] ?? '');
                  $sortOrder = (int)($row['sort_order'] ?? 0);
                  $isPublished = (int)($row['is_published'] ?? 0) === 1;
                  $editUrl = App::base_url('/admin/material-apoio?course_id=' . $courseId . '&edit=' . $id);
                ?>
                <tr>
                  <td data-order="<?= $id ?>"><strong>#<?= $id ?></strong></td>
                  <td>
                    <div class="biometric-audit-course"><?= h_support($courseTitle) ?></div>
                    <div class="biometric-audit-meta">Curso app #<?= $courseId ?></div>
                  </td>
                  <td><?= h_support($materialTitle) ?></td>
                  <td>
                    <a href="<?= h_support($url) ?>" target="_blank" rel="noopener" class="support-material-url">
                      <?= h_support($url) ?>
                    </a>
                  </td>
                  <td data-order="<?= $sortOrder ?>"><?= $sortOrder ?></td>
                  <td>
                    <span class="audit-chip <?= $isPublished ? 'audit-chip--success' : 'audit-chip--neutral' ?>">
                      <?= $isPublished ? 'Publicado' : 'Oculto' ?>
                    </span>
                  </td>
                  <td>
                    <div class="admin-actions admin-actions--audit">
                      <a href="<?= h_support($editUrl) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-pen"></i>
                      </a>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= h_support($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="course_id" value="<?= $selectedCourseId > 0 ? $selectedCourseId : $courseId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir este material de apoio?');">
                          <i class="fa-solid fa-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</section>

<script>
(() => {
  const init = () => {
    const courseSelect = document.getElementById('selSupportMaterialCourse');
    const refreshButton = document.getElementById('btnRefreshSupportMaterials');
    const tableNode = document.getElementById('supportMaterialTable');
    const visibleCount = document.getElementById('supportMaterialVisibleCount');
    const appBase = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
    let table = null;

    const canBootDataTable = () => {
      if (!tableNode) return false;
      const bodyRows = Array.from(tableNode.querySelectorAll('tbody > tr'));
      if (!bodyRows.length) return false;
      if (bodyRows.length === 1) {
        const cells = bodyRows[0].querySelectorAll('td');
        if (cells.length === 1 && Number(cells[0].getAttribute('colspan') || 0) > 1) {
          return false;
        }
      }
      return true;
    };

    const rebuildUrl = (courseId) => {
      const url = new URL(appBase + '/admin/material-apoio', window.location.origin);
      if (courseId) {
        url.searchParams.set('course_id', courseId);
      }
      return url.toString();
    };

    const updateVisibleCount = () => {
      if (!visibleCount) return;
      if (table) {
        visibleCount.textContent = `${table.rows({ filter: 'applied' }).count()} materiais`;
        return;
      }
      const rows = Array.from(tableNode?.querySelectorAll('tbody tr') || []).filter((row) => row.children.length > 1);
      visibleCount.textContent = `${rows.length} materiais`;
    };

    courseSelect?.addEventListener('change', () => {
      window.location.href = rebuildUrl(courseSelect.value);
    });

    refreshButton?.addEventListener('click', () => window.location.reload());

    if (tableNode && canBootDataTable() && window.jQuery && $.fn.DataTable) {
      table = window.APP_DATA_TABLE
        ? window.APP_DATA_TABLE.init(tableNode, { order: [[0, 'desc']] })
        : $(tableNode).DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
            order: [[0, 'desc']],
            autoWidth: false,
            orderClasses: false,
            language: { url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json' }
          });

      if (table && typeof table.on === 'function') {
        table.on('draw', updateVisibleCount);
      }
    }

    updateVisibleCount();
  };

  window.addEventListener('load', init, { once: true });
})();
</script>

