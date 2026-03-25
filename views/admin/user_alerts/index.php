<?php
$viewFile = __DIR__ . '/index_inner.php';
$title = 'Admin - Alertas ao aluno';
$courseFilters = $courseFilters ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$admin_page_title = 'Alertas ao aluno';
$admin_page_subtitle = 'Dispare avisos pontuais para um aluno e exiba a mensagem em tempo quase real nas telas do app.';
$admin_page_actions = (function(array $courseFilters, int $selectedCourseId): string {
    ob_start();
    ?>
    <div class="admin-page-controls admin-page-controls--user-alerts">
      <label class="admin-page-controls__field" for="selUserAlertCourse">
        <span>Curso</span>
        <select class="form-select" id="selUserAlertCourse">
          <option value="" <?= $selectedCourseId > 0 ? '' : 'selected' ?>>Todos os cursos</option>
          <?php foreach ($courseFilters as $course): ?>
            <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $selectedCourseId ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)($course['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (Moodle #<?= (int)($course['moodle_courseid'] ?? 0) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="button" class="btn btn-ui btn-ui--neutral btn-ui--sm" id="btnRefreshUserAlerts">
        <i class="fa-solid fa-rotate"></i> Atualizar
      </button>
      <button
        type="button"
        class="btn btn-ui btn-ui--primary btn-ui--sm"
        id="btnNewUserAlert"
        data-bs-toggle="modal"
        data-bs-target="#userAlertModal"
        data-mode="new"
      >
        <i class="fa-solid fa-bell"></i> Novo alerta
      </button>
    </div>
    <?php
    return (string)ob_get_clean();
})($courseFilters, $selectedCourseId);
$full_width = true;
$body_class = 'app-body admin-shell admin-shell--user-alerts admin-shell--fluid';
?>
<?php include APP_DIR . '/views/layouts/admin.php'; ?>

