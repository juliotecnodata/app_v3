<?php
use App\App;
use App\UserAlertService;

function h_alert($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function split_alert_datetime(?string $value): array {
  $raw = trim((string)$value);
  if ($raw === '' || $raw === '0000-00-00 00:00:00') {
    return ['', ''];
  }
  $timestamp = strtotime($raw);
  if ($timestamp === false) {
    return ['', ''];
  }
  return [date('Y-m-d', $timestamp), date('H:i', $timestamp)];
}

$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'active' => 0, 'blocking' => 0, 'read' => 0, 'expired' => 0];
$courseFilters = $courseFilters ?? [];
$students = $students ?? [];
$selectedCourseId = (int)($selectedCourseId ?? 0);
$selectedCourse = $selectedCourse ?? null;
$editAlert = $editAlert ?? null;
$openModal = !empty($openModal);
$flash = $flash ?? null;
$csrf = $csrf ?? '';

$cards = [
  ['label' => 'Ativos', 'value' => (int)($summary['active'] ?? 0), 'icon' => 'fa-bell'],
  ['label' => 'Bloqueantes', 'value' => (int)($summary['blocking'] ?? 0), 'icon' => 'fa-triangle-exclamation'],
  ['label' => 'Lidos', 'value' => (int)($summary['read'] ?? 0), 'icon' => 'fa-circle-check'],
  ['label' => 'Expirados', 'value' => (int)($summary['expired'] ?? 0), 'icon' => 'fa-clock'],
];

$scopes = [
  'all' => 'Todas as telas',
  'dashboard' => 'Dashboard',
  'study' => 'Curso / estudo',
  'biometric' => 'Biometria',
];

$severities = [
  'info' => 'Informativo',
  'warning' => 'Atencao',
  'danger' => 'Critico',
];

$presets = [
  [
    'label' => 'Fraude suspeita',
    'scope' => 'biometric',
    'severity' => 'danger',
    'blocking' => 1,
    'title' => 'Validacao biometrica sob revisao',
    'message' => "Detectamos inconsistencias na sua validacao biometrica.\nO uso de foto de foto, tela, impressao ou terceiro e proibido e auditado.\nSe a irregularidade persistir, seu acesso podera ser bloqueado para revisao.",
  ],
  [
    'label' => 'Nova selfie',
    'scope' => 'biometric',
    'severity' => 'warning',
    'blocking' => 0,
    'title' => 'Nova captura biometrica necessaria',
    'message' => "Precisamos de uma nova selfie para validar seu acesso.\nTire a foto com boa iluminacao, rosto centralizado e sem usar tela, foto impressa ou imagem de outro dispositivo.",
  ],
  [
    'label' => 'Revisao admin',
    'scope' => 'study',
    'severity' => 'warning',
    'blocking' => 1,
    'title' => 'Acesso temporariamente em revisao',
    'message' => "Seu acesso esta temporariamente em revisao pela equipe administrativa.\nAguarde a validacao e evite novas tentativas irregulares durante esse periodo.",
  ],
  [
    'label' => 'Aviso formal',
    'scope' => 'dashboard',
    'severity' => 'info',
    'blocking' => 0,
    'title' => 'Aviso importante sobre seu acesso',
    'message' => "Identificamos um evento que exige sua atencao.\nLeia as orientacoes da equipe e mantenha seu acesso regularizado para evitar bloqueios.",
  ],
];

$formId = (int)($editAlert['id'] ?? 0);
$formCourseId = (int)($editAlert['course_id'] ?? $selectedCourseId);
$formUid = (int)($editAlert['moodle_userid'] ?? 0);
$formScope = (string)($editAlert['scope'] ?? 'all');
$formSeverity = (string)($editAlert['severity'] ?? 'warning');
$formTitle = (string)($editAlert['title'] ?? '');
$formMessage = (string)($editAlert['message'] ?? '');
$formBlocking = (int)($editAlert['is_blocking'] ?? 0) === 1;
$formRequireAck = (int)($editAlert['require_ack'] ?? 1) === 1;
[$activeDate, $activeTime] = split_alert_datetime((string)($editAlert['active_from'] ?? ''));
[$expiresDate, $expiresTime] = split_alert_datetime((string)($editAlert['expires_at'] ?? ''));
$cancelUrl = App::base_url('/admin/alertas-aluno' . ($selectedCourseId > 0 ? '?course_id=' . $selectedCourseId : ''));
$initialState = [
  'id' => $formId,
  'course_id' => $formCourseId,
  'moodle_userid' => $formUid,
  'scope' => $formScope,
  'severity' => $formSeverity,
  'title' => $formTitle,
  'message' => $formMessage,
  'is_blocking' => $formBlocking,
  'require_ack' => $formRequireAck,
  'active_from_date' => $activeDate,
  'active_from_time' => $activeTime,
  'expires_at_date' => $expiresDate,
  'expires_at_time' => $expiresTime,
];
$blankState = [
  'id' => 0,
  'course_id' => $selectedCourseId,
  'moodle_userid' => 0,
  'scope' => 'all',
  'severity' => 'warning',
  'title' => '',
  'message' => '',
  'is_blocking' => false,
  'require_ack' => true,
  'active_from_date' => '',
  'active_from_time' => '',
  'expires_at_date' => '',
  'expires_at_time' => '',
];
?>

<?php if ($flash && !empty($flash['msg'])): ?>
  <div class="alert alert-<?= h_alert($flash['type'] ?? 'info') ?> admin-inline-alert" role="alert">
    <?= h_alert($flash['msg']) ?>
  </div>
<?php endif; ?>

<section class="user-alert-page">
  <div class="biometric-audit-cards">
    <?php foreach ($cards as $card): ?>
      <article class="biometric-audit-card">
        <span class="biometric-audit-card__icon"><i class="fa-solid <?= h_alert($card['icon']) ?>"></i></span>
        <div class="biometric-audit-card__body">
          <span class="biometric-audit-card__label"><?= h_alert($card['label']) ?></span>
          <strong class="biometric-audit-card__value"><?= (int)$card['value'] ?></strong>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="enroll-table-shell enroll-table-shell--v2 user-alert-table-panel">
    <div class="enroll-table-shell__head enroll-table-shell__head--compact">
      <div>
        <h3>Alertas emitidos</h3>
        <p>
          <?= $selectedCourseId > 0 && is_array($selectedCourse)
            ? 'Curso filtrado: ' . h_alert((string)($selectedCourse['title'] ?? ''))
            : 'Historico recente de alertas emitidos para os alunos do app.' ?>
        </p>
      </div>
      <div class="enroll-table-shell__head-actions">
        <div class="enroll-table-shell__meta" id="userAlertVisibleCount"><?= count($rows) ?> alertas</div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table admin-table admin-table--user-alerts" id="userAlertTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Aluno</th>
            <th>Curso / escopo</th>
            <th>Mensagem</th>
            <th>Estado</th>
            <th>Emitido</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                Nenhum alerta encontrado.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $alertId = (int)($row['id'] ?? 0);
                $runtimeStatus = UserAlertService::status_snapshot($row);
                $isBlocking = (int)($row['is_blocking'] ?? 0) === 1;
                $severity = (string)($row['severity'] ?? 'warning');
                $scope = (string)($row['scope'] ?? 'all');
                $fullName = trim((string)($row['full_name'] ?? ''));
                $fullName = $fullName !== '' ? $fullName : (string)($row['username'] ?? 'Aluno');
                $statusClass = $runtimeStatus === 'active'
                  ? ($isBlocking ? 'audit-chip--danger' : 'audit-chip--warning')
                  : ($runtimeStatus === 'expired' ? 'audit-chip--neutral' : 'audit-chip--success');
                $statusLabel = $runtimeStatus === 'active'
                  ? ($isBlocking ? 'Ativo / bloqueante' : 'Ativo')
                  : ($runtimeStatus === 'expired' ? 'Expirado' : 'Encerrado');
                $rowState = [
                  'id' => $alertId,
                  'course_id' => (int)($row['course_id'] ?? 0),
                  'moodle_userid' => (int)($row['moodle_userid'] ?? 0),
                  'scope' => $scope,
                  'severity' => $severity,
                  'title' => (string)($row['title'] ?? ''),
                  'message' => (string)($row['message'] ?? ''),
                  'is_blocking' => $isBlocking,
                  'require_ack' => (int)($row['require_ack'] ?? 1) === 1,
                  'active_from_date' => split_alert_datetime((string)($row['active_from'] ?? ''))[0],
                  'active_from_time' => split_alert_datetime((string)($row['active_from'] ?? ''))[1],
                  'expires_at_date' => split_alert_datetime((string)($row['expires_at'] ?? ''))[0],
                  'expires_at_time' => split_alert_datetime((string)($row['expires_at'] ?? ''))[1],
                ];
              ?>
              <tr>
                <td data-order="<?= $alertId ?>"><strong>#<?= $alertId ?></strong></td>
                <td>
                  <div class="biometric-audit-course"><?= h_alert($fullName) ?></div>
                  <div class="biometric-audit-meta">
                    UID <?= (int)($row['moodle_userid'] ?? 0) ?>
                    <?php if (!empty($row['username'])): ?>
                      &middot; <?= h_alert((string)$row['username']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="biometric-audit-course"><?= h_alert((string)($row['course_title'] ?? 'Mensagem geral')) ?></div>
                  <div class="biometric-audit-meta"><?= h_alert($scopes[$scope] ?? ucfirst($scope)) ?></div>
                </td>
                <td>
                  <div class="user-alert-table__title"><?= h_alert((string)($row['title'] ?? '')) ?></div>
                  <div class="user-alert-table__message"><?= nl2br(h_alert((string)($row['message'] ?? ''))) ?></div>
                </td>
                <td>
                  <div class="user-alert-table__chips">
                    <span class="audit-chip <?= h_alert($statusClass) ?>"><?= h_alert($statusLabel) ?></span>
                    <span class="audit-chip audit-chip--neutral"><?= h_alert($severities[$severity] ?? ucfirst($severity)) ?></span>
                    <?php if (!empty($row['acknowledged_at'])): ?>
                      <span class="audit-chip audit-chip--success">Lido</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td data-order="<?= strtotime((string)($row['created_at'] ?? 'now')) ?>">
                  <div class="biometric-audit-meta"><?= h_alert(UserAlertService::format_datetime((string)($row['created_at'] ?? ''))) ?></div>
                  <?php if (!empty($row['created_by_name']) || !empty($row['created_by_username'])): ?>
                    <div class="biometric-audit-meta">
                      por <?= h_alert(trim((string)($row['created_by_name'] ?? '')) ?: (string)($row['created_by_username'] ?? '')) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="admin-actions admin-actions--audit">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary js-user-alert-edit"
                      title="Editar alerta"
                      data-bs-toggle="modal"
                      data-bs-target="#userAlertModal"
                      data-mode="edit"
                      data-alert='<?= h_alert(json_encode($rowState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                    >
                      <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php if ($runtimeStatus === 'active'): ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= h_alert($csrf) ?>">
                        <input type="hidden" name="action" value="dismiss">
                        <input type="hidden" name="id" value="<?= $alertId ?>">
                        <input type="hidden" name="selected_course_id" value="<?= $selectedCourseId ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Encerrar este alerta?');" title="Encerrar alerta">
                          <i class="fa-solid fa-bell-slash"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= h_alert($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $alertId ?>">
                      <input type="hidden" name="selected_course_id" value="<?= $selectedCourseId ?>">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Excluir este alerta permanentemente?');" title="Excluir alerta">
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
</section>

<div class="modal fade user-alert-modal" id="userAlertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable user-alert-modal__dialog">
    <div class="modal-content user-alert-modal__content">
      <div class="modal-header user-alert-modal__header">
        <div>
          <div class="user-alert-modal__eyebrow">Mensagem direcionada</div>
          <h2 class="user-alert-modal__title" id="userAlertModalTitle"><?= $formId > 0 ? 'Editar alerta' : 'Novo alerta' ?></h2>
          <p class="user-alert-modal__subtitle">Dispare um aviso pontual ao aluno com texto pronto, agenda de exibicao e opcao bloqueante.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body user-alert-modal__body">
        <form method="post" class="user-alert-form user-alert-form--modal" id="userAlertForm">
          <input type="hidden" name="csrf" value="<?= h_alert($csrf) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $formId ?>" id="userAlertIdModal">
          <input type="hidden" name="selected_course_id" value="<?= $selectedCourseId ?>">

          <div class="user-alert-presets user-alert-presets--modal">
            <div class="user-alert-presets__title">Textos prontos</div>
            <div class="user-alert-presets__grid user-alert-presets__grid--modal">
              <?php foreach ($presets as $preset): ?>
                <button
                  type="button"
                  class="user-alert-preset"
                  data-title="<?= h_alert($preset['title']) ?>"
                  data-message="<?= h_alert($preset['message']) ?>"
                  data-scope="<?= h_alert($preset['scope']) ?>"
                  data-severity="<?= h_alert($preset['severity']) ?>"
                  data-blocking="<?= (int)$preset['blocking'] ?>"
                >
                  <span class="user-alert-preset__label"><?= h_alert($preset['label']) ?></span>
                  <span class="user-alert-preset__meta"><?= h_alert($scopes[$preset['scope']] ?? '') ?> &middot; <?= h_alert($severities[$preset['severity']] ?? '') ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="user-alert-form__grid user-alert-form__grid--primary">
            <label class="user-alert-form__field">
              <span>Curso</span>
              <select class="form-select" name="course_id" id="userAlertCourseFieldModal">
                <option value="">Mensagem geral</option>
                <?php foreach ($courseFilters as $course): ?>
                  <option value="<?= (int)($course['id'] ?? 0) ?>" <?= (int)($course['id'] ?? 0) === $formCourseId ? 'selected' : '' ?>>
                    <?= h_alert((string)($course['title'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="user-alert-form__field">
              <span>UID do aluno</span>
              <input type="number" class="form-control" name="moodle_userid" id="userAlertUidModal" min="1" step="1" placeholder="Ex.: 18083" value="<?= $formUid > 0 ? $formUid : '' ?>" required>
            </label>

            <label class="user-alert-form__field">
              <span>Aluno do curso</span>
              <select class="form-select" id="userAlertStudentSelectModal">
                <option value="">Selecione para preencher o UID</option>
                <?php foreach ($students as $student): ?>
                  <?php $studentName = trim((string)($student['full_name'] ?? '')); ?>
                  <option value="<?= (int)($student['id'] ?? 0) ?>" <?= (int)($student['id'] ?? 0) === $formUid ? 'selected' : '' ?>>
                    #<?= (int)($student['id'] ?? 0) ?> - <?= h_alert($studentName !== '' ? $studentName : (string)($student['username'] ?? '')) ?><?= !empty($student['username']) ? ' (' . h_alert((string)$student['username']) . ')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="user-alert-form__grid user-alert-form__grid--secondary">
            <label class="user-alert-form__field">
              <span>Escopo</span>
              <select class="form-select" name="scope" id="userAlertScopeModal">
                <?php foreach ($scopes as $scopeValue => $scopeLabel): ?>
                  <option value="<?= h_alert($scopeValue) ?>" <?= $scopeValue === $formScope ? 'selected' : '' ?>><?= h_alert($scopeLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label class="user-alert-form__field">
              <span>Severidade</span>
              <select class="form-select" name="severity" id="userAlertSeverityModal">
                <?php foreach ($severities as $severityValue => $severityLabel): ?>
                  <option value="<?= h_alert($severityValue) ?>" <?= $severityValue === $formSeverity ? 'selected' : '' ?>><?= h_alert($severityLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <div class="user-alert-form__field user-alert-form__field--timepack">
              <span>Ativar em</span>
              <div class="user-alert-form__datetime">
                <input type="date" class="form-control" name="active_from_date" id="userAlertActiveDateModal" value="<?= h_alert($activeDate) ?>">
                <input type="time" class="form-control" name="active_from_time" id="userAlertActiveTimeModal" value="<?= h_alert($activeTime) ?>">
              </div>
            </div>

            <div class="user-alert-form__field user-alert-form__field--timepack">
              <span>Expirar em</span>
              <div class="user-alert-form__datetime">
                <input type="date" class="form-control" name="expires_at_date" id="userAlertExpireDateModal" value="<?= h_alert($expiresDate) ?>">
                <input type="time" class="form-control" name="expires_at_time" id="userAlertExpireTimeModal" value="<?= h_alert($expiresTime) ?>">
              </div>
            </div>
          </div>

          <div class="user-alert-form__grid user-alert-form__grid--content">
            <label class="user-alert-form__field">
              <span>Titulo</span>
              <input type="text" class="form-control" name="title" id="userAlertTitleModal" maxlength="255" placeholder="Ex.: Validacao biometrica sob revisao" value="<?= h_alert($formTitle) ?>" required>
            </label>

            <label class="user-alert-form__field">
              <span>Mensagem</span>
              <textarea class="form-control" name="message" id="userAlertMessageModal" rows="8" placeholder="Ex.: Detectamos inconsistencias na sua validacao biometrica. O uso de foto de foto, tela ou terceiro e auditado." required><?= h_alert($formMessage) ?></textarea>
            </label>
          </div>

          <div class="user-alert-form__checks user-alert-form__checks--modal">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="is_blocking" id="userAlertBlockingModal" value="1" <?= $formBlocking ? 'checked' : '' ?>>
              <span class="form-check-label">Exibir como alerta bloqueante</span>
            </label>
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="require_ack" id="userAlertRequireAckModal" value="1" <?= $formRequireAck ? 'checked' : '' ?>>
              <span class="form-check-label">Exigir confirmacao do aluno</span>
            </label>
          </div>

          <div class="user-alert-form__footnote">
            Dica: para casos de fraude biometrica, use escopo <strong>Biometria</strong> ou <strong>Curso / estudo</strong>. Se quiser endurecer, marque como bloqueante.
          </div>

          <div class="modal-footer user-alert-modal__footer">
            <button type="button" class="btn btn-ui btn-ui--neutral" data-bs-dismiss="modal">
              <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button type="submit" class="btn btn-ui btn-ui--primary">
              <i class="fa-solid fa-floppy-disk"></i> <span id="userAlertSubmitLabel"><?= $formId > 0 ? 'Salvar alerta' : 'Enviar alerta' ?></span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const init = () => {
    const appBase = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
    const cleanUrl = <?= json_encode($cancelUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const filterCourse = document.getElementById('selUserAlertCourse');
    const refreshButton = document.getElementById('btnRefreshUserAlerts');
    const btnNew = document.getElementById('btnNewUserAlert');
    const tableNode = document.getElementById('userAlertTable');
    const visibleCount = document.getElementById('userAlertVisibleCount');
    const studentSelect = document.getElementById('userAlertStudentSelectModal');
    const uidField = document.getElementById('userAlertUidModal');
    const titleField = document.getElementById('userAlertTitleModal');
    const messageField = document.getElementById('userAlertMessageModal');
    const scopeField = document.getElementById('userAlertScopeModal');
    const severityField = document.getElementById('userAlertSeverityModal');
    const blockingField = document.getElementById('userAlertBlockingModal');
    const requireAckField = document.getElementById('userAlertRequireAckModal');
    const courseField = document.getElementById('userAlertCourseFieldModal');
    const activeDateField = document.getElementById('userAlertActiveDateModal');
    const activeTimeField = document.getElementById('userAlertActiveTimeModal');
    const expireDateField = document.getElementById('userAlertExpireDateModal');
    const expireTimeField = document.getElementById('userAlertExpireTimeModal');
    const idField = document.getElementById('userAlertIdModal');
    const modalTitle = document.getElementById('userAlertModalTitle');
    const submitLabel = document.getElementById('userAlertSubmitLabel');
    const presetButtons = Array.from(document.querySelectorAll('.user-alert-preset'));
    const editButtons = Array.from(document.querySelectorAll('.js-user-alert-edit'));
    const modal = document.getElementById('userAlertModal');
    const shouldOpenModal = <?= $openModal ? 'true' : 'false' ?>;
    const initialState = <?= json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const blankState = <?= json_encode($blankState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
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
      const url = new URL(appBase + '/admin/alertas-aluno', window.location.origin);
      if (courseId) {
        url.searchParams.set('course_id', courseId);
      }
      return url.toString();
    };

    const updateVisibleCount = () => {
      if (!visibleCount) return;
      if (table) {
        visibleCount.textContent = `${table.rows({ filter: 'applied' }).count()} alertas`;
        return;
      }
      const rows = Array.from(tableNode?.querySelectorAll('tbody tr') || []).filter((row) => row.children.length > 1);
      visibleCount.textContent = `${rows.length} alertas`;
    };

    const applyState = (state, mode) => {
      const payload = state || blankState;
      if (idField) idField.value = payload.id || 0;
      if (courseField) courseField.value = payload.course_id || '';
      if (uidField) uidField.value = payload.moodle_userid || '';
      if (scopeField) scopeField.value = payload.scope || 'all';
      if (severityField) severityField.value = payload.severity || 'warning';
      if (titleField) titleField.value = payload.title || '';
      if (messageField) messageField.value = payload.message || '';
      if (blockingField) blockingField.checked = !!payload.is_blocking;
      if (requireAckField) requireAckField.checked = payload.require_ack !== false;
      if (activeDateField) activeDateField.value = payload.active_from_date || '';
      if (activeTimeField) activeTimeField.value = payload.active_from_time || '';
      if (expireDateField) expireDateField.value = payload.expires_at_date || '';
      if (expireTimeField) expireTimeField.value = payload.expires_at_time || '';
      if (studentSelect) {
        const targetValue = payload.moodle_userid ? String(payload.moodle_userid) : '';
        studentSelect.value = targetValue;
        if (window.jQuery && typeof window.jQuery.fn.select2 !== 'undefined') {
          window.jQuery(studentSelect).val(targetValue).trigger('change.select2');
        }
      }
      if (modalTitle) modalTitle.textContent = mode === 'edit' ? 'Editar alerta' : 'Novo alerta';
      if (submitLabel) submitLabel.textContent = mode === 'edit' ? 'Salvar alerta' : 'Enviar alerta';
    };

    const stateFromTrigger = (trigger) => {
      if (!trigger) {
        return { state: blankState, mode: 'new' };
      }
      const mode = trigger.dataset.mode === 'edit' ? 'edit' : 'new';
      if (mode !== 'edit' || !trigger.dataset.alert) {
        return { state: blankState, mode };
      }
      try {
        return { state: JSON.parse(trigger.dataset.alert || '{}'), mode };
      } catch (_error) {
        return { state: blankState, mode: 'new' };
      }
    };

    const openModalWith = (state, mode) => {
      applyState(state, mode);
      if (modal && window.APP_MODAL) {
        window.APP_MODAL.open(modal);
      }
    };

    filterCourse?.addEventListener('change', () => {
      window.location.href = rebuildUrl(filterCourse.value);
    });

    refreshButton?.addEventListener('click', () => window.location.reload());

    if (modal) {
      modal.addEventListener('show.bs.modal', (event) => {
        const payload = stateFromTrigger(event.relatedTarget || null);
        applyState(payload.state, payload.mode);
      });
    }

    if (!(window.bootstrap && window.bootstrap.Modal)) {
      btnNew?.addEventListener('click', () => openModalWith(blankState, 'new'));
      editButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const payload = stateFromTrigger(button);
          openModalWith(payload.state, payload.mode);
        });
      });
    }

    studentSelect?.addEventListener('change', () => {
      if (uidField) {
        uidField.value = studentSelect.value || '';
      }
    });

    presetButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (titleField) titleField.value = button.dataset.title || '';
        if (messageField) messageField.value = button.dataset.message || '';
        if (scopeField && button.dataset.scope) scopeField.value = button.dataset.scope;
        if (severityField && button.dataset.severity) severityField.value = button.dataset.severity;
        if (blockingField) blockingField.checked = button.dataset.blocking === '1';
      });
    });

    if (tableNode && canBootDataTable() && window.APP_DATA_TABLE) {
      table = window.APP_DATA_TABLE.init(tableNode, { order: [[0, 'desc']] });
      if (table && typeof table.on === 'function') {
        table.on('draw', updateVisibleCount);
      }
    }

    updateVisibleCount();

    if (studentSelect && window.jQuery && typeof window.jQuery.fn.select2 !== 'undefined') {
      window.jQuery(studentSelect).select2({
        width: '100%',
        dropdownParent: window.jQuery(modal)
      });
    }

    if (shouldOpenModal && modal && window.APP_MODAL) {
      openModalWith(initialState, initialState && Number(initialState.id || 0) > 0 ? 'edit' : 'new');
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({}, document.title, cleanUrl);
      }
    }
  };

  window.addEventListener('load', init, { once: true });
})();
</script>




