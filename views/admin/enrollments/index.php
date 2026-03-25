<?php
$viewFile = __DIR__ . '/index_inner.php';
$title = 'Admin - Alunos Inscritos';
$courseFilters = $courseFilters ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$admin_page_title = 'Inscritos e rastreabilidade';
$admin_page_subtitle = 'Acompanhe acessos, progresso e trilha operacional dos alunos por curso.';
$admin_page_actions = (function(array $courseFilters, int $selectedCourseId): string {
    ob_start();
    ?>
    <div class="admin-page-controls admin-page-controls--enrollments">
      <label class="admin-page-controls__field" for="selCourseFilter">
        <span>Curso</span>
        <select class="form-select" id="selCourseFilter">
          <option value="" <?= $selectedCourseId > 0 ? '' : 'selected' ?>>Selecione um curso</option>
          <?php foreach ($courseFilters as $course): ?>
            <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $selectedCourseId ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Moodle #<?= (int)($course['moodle_courseid'] ?? 0) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="btn btn-ui btn-ui--primary btn-ui--sm" id="btnSyncCourseFromMoodle" <?= $selectedCourseId > 0 ? '' : 'disabled' ?>>
        <i class="fa-solid fa-arrows-rotate"></i> Sincronizar curso
      </button>
      <button type="button" class="btn btn-ui btn-ui--neutral btn-ui--sm" id="btnRefreshEnrollments">
        <i class="fa-solid fa-rotate"></i> Atualizar
      </button>
    </div>
    <?php
    return (string)ob_get_clean();
})($courseFilters, $selectedCourseId);
$full_width = true;
$body_class = 'app-body admin-shell admin-shell--enrollments admin-shell--fluid';
?>
<?php include APP_DIR . '/views/layouts/admin.php'; ?>
