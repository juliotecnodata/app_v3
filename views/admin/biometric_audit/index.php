<?php
$viewFile = __DIR__ . '/index_inner.php';
$title = 'Admin - Biometrias';
$admin_page_title = 'Biometrias dos alunos';
$admin_page_subtitle = 'Revise as capturas aprovadas, confira qualidade e exclua fotos ruins para exigir uma nova validacao.';
$admin_page_actions = (function(array $courseFilters, int $selectedCourseId): string {
    ob_start();
    ?>
    <div class="admin-page-controls admin-page-controls--biometric">
      <label class="admin-page-controls__field" for="selBiometricCourse">
        <span>Curso</span>
        <select class="form-select" id="selBiometricCourse">
          <option value="" <?= $selectedCourseId > 0 ? '' : 'selected' ?>>Todos os cursos</option>
          <?php foreach ($courseFilters as $course): ?>
            <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $selectedCourseId ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="btn btn-ui btn-ui--neutral btn-ui--sm" id="btnRefreshBiometricAudit">
        <i class="fa-solid fa-rotate"></i> Atualizar
      </button>
    </div>
    <?php
    return (string)ob_get_clean();
})($courseFilters ?? [], (int)($selectedCourseId ?? 0));
$full_width = true;
$body_class = 'app-body admin-shell admin-shell--biometric-audit admin-shell--fluid';
?>
<?php include APP_DIR . '/views/layouts/admin.php'; ?>
