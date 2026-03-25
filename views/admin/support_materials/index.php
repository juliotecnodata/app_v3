<?php
$viewFile = __DIR__ . '/index_inner.php';
$title = 'Admin - Material de apoio';
$courseFilters = $courseFilters ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$admin_page_title = 'Material de apoio';
$admin_page_subtitle = 'Cadastre links externos por curso para aparecerem ao aluno fora do fluxo do curso.';
$admin_page_actions = (function(array $courseFilters, int $selectedCourseId): string {
    ob_start();
    ?>
    <div class="admin-page-controls admin-page-controls--support-materials">
      <label class="admin-page-controls__field" for="selSupportMaterialCourse">
        <span>Curso</span>
        <select class="form-select" id="selSupportMaterialCourse">
          <option value="" <?= $selectedCourseId > 0 ? '' : 'selected' ?>>Todos os cursos</option>
          <?php foreach ($courseFilters as $course): ?>
            <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $selectedCourseId ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Moodle #<?= (int)($course['moodle_courseid'] ?? 0) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="btn btn-ui btn-ui--neutral btn-ui--sm" id="btnRefreshSupportMaterials">
        <i class="fa-solid fa-rotate"></i> Atualizar
      </button>
    </div>
    <?php
    return (string)ob_get_clean();
})($courseFilters, $selectedCourseId);
$full_width = true;
$body_class = 'app-body admin-shell admin-shell--support-materials admin-shell--fluid';
?>
<?php include APP_DIR . '/views/layouts/admin.php'; ?>
