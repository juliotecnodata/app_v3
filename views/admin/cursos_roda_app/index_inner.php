<?php
use App\App;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$courses = $courses ?? [];
?>

<section class="admin-panel">
  <div class="admin-panel__header">
    <div>
      <div class="admin-panel__title">Controle de roteamento</div>
      <div class="admin-panel__hint">Coluna "Somente APP": Sim = aluno vai para o APP. Nao = aluno vai para o Moodle.</div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table admin-table" id="runtimeCourseTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Curso</th>
          <th>Status</th>
          <th>Moodle ID</th>
          <th>Somente APP</th>
          <th>Ultima atualizacao</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $course): ?>
          <?php
            $courseId = (int)($course['id'] ?? 0);
            $onlyApp = ((int)($course['only_app'] ?? 0) === 1);
            $updated = trim((string)($course['updated_at'] ?? ''));
            $updatedText = $updated !== '' ? date('d/m/Y H:i', strtotime($updated) ?: time()) : '-';
          ?>
          <tr data-course-id="<?= $courseId ?>">
            <td><?= $courseId ?></td>
            <td>
              <div class="course-title"><?= h((string)($course['title'] ?? '')) ?></div>
              <div class="course-meta">Slug: <?= h((string)($course['slug'] ?? '')) ?></div>
            </td>
            <td><?= h((string)($course['status'] ?? '')) ?></td>
            <td><?= h((string)($course['moodle_courseid'] ?? '-')) ?></td>
            <td>
              <div class="form-check form-switch m-0">
                <input
                  class="form-check-input js-only-app-toggle"
                  type="checkbox"
                  role="switch"
                  data-course-id="<?= $courseId ?>"
                  <?= $onlyApp ? 'checked' : '' ?>
                >
                <label class="form-check-label ms-1">
                  <span class="js-only-app-label"><?= $onlyApp ? 'Sim' : 'Nao' ?></span>
                </label>
              </div>
            </td>
            <td class="js-updated-at"><?= h($updatedText) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(function () {
  const init = () => {
    const tableEl = document.getElementById('runtimeCourseTable');
    if (!tableEl) return;

    if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.DataTable !== 'undefined') {
      if (window.APP_DATA_TABLE) {
        window.APP_DATA_TABLE.init(tableEl, {
          pageLength: 10,
          order: [[0, 'desc']]
        });
      } else {
        window.jQuery(tableEl).DataTable({
          pageLength: 10,
          lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
          order: [[0, 'desc']],
          autoWidth: false,
          orderClasses: false,
          language: {
            url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json'
          }
        });
      }
    }

    const post = window.APP_HTTP && typeof window.APP_HTTP.post === 'function'
      ? window.APP_HTTP.post
      : null;
    if (!post) {
      console.error('[app_v3] APP_HTTP.post indisponivel na tela Cursos Roda App.');
      return;
    }

    tableEl.addEventListener('change', async (event) => {
      const input = event.target.closest('.js-only-app-toggle');
      if (!input) return;

      const courseId = String(input.getAttribute('data-course-id') || '').trim();
      if (!courseId) return;

      const onlyApp = input.checked ? 1 : 0;
      const label = input.closest('tr')?.querySelector('.js-only-app-label');
      const updatedCell = input.closest('tr')?.querySelector('.js-updated-at');

      input.disabled = true;
      const before = onlyApp ? 0 : 1;

      try {
        await post('/api/admin/course/runtime/update', {
          course_id: courseId,
          only_app: onlyApp
        });

        if (label) label.textContent = onlyApp ? 'Sim' : 'Nao';
        if (updatedCell) {
          const now = new Date();
          const dd = String(now.getDate()).padStart(2, '0');
          const mm = String(now.getMonth() + 1).padStart(2, '0');
          const yyyy = String(now.getFullYear());
          const hh = String(now.getHours()).padStart(2, '0');
          const mi = String(now.getMinutes()).padStart(2, '0');
          updatedCell.textContent = `${dd}/${mm}/${yyyy} ${hh}:${mi}`;
        }
        if (window.showToast) window.showToast('Configuracao atualizada.', 'success');
      } catch (error) {
        input.checked = before === 1;
        if (label) label.textContent = before === 1 ? 'Sim' : 'Nao';
        if (window.Swal) {
          window.Swal.fire({ icon: 'error', title: 'Erro', text: String(error.message || error) });
        }
      } finally {
        input.disabled = false;
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
