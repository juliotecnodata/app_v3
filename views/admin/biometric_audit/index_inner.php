<?php
use App\App;

function h($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmt_dt_audit(int $timestamp): string {
  if ($timestamp <= 0) {
    return '-';
  }
  return date('d/m/Y H:i', $timestamp);
}

function fmt_bytes_audit(?int $bytes): string {
  $value = (int)$bytes;
  if ($value <= 0) {
    return '-';
  }
  if ($value < 1024) {
    return $value . ' B';
  }
  return number_format($value / 1024, 1, ',', '.') . ' KB';
}

$rows = $rows ?? [];
$summary = $summary ?? ['total' => 0, 'today' => 0, 'users' => 0, 'courses' => 0];
$selectedCourse = $selectedCourse ?? null;
$selectedCourseId = (int)($selectedCourseId ?? 0);
$hasSelectedCourse = $selectedCourseId > 0;

$cards = [
  ['label' => 'Registros', 'value' => (int)($summary['total'] ?? 0), 'icon' => 'fa-images'],
  ['label' => 'Capturas hoje', 'value' => (int)($summary['today'] ?? 0), 'icon' => 'fa-calendar-day'],
  ['label' => 'Alunos', 'value' => (int)($summary['users'] ?? 0), 'icon' => 'fa-user-check'],
  ['label' => 'Cursos', 'value' => (int)($summary['courses'] ?? 0), 'icon' => 'fa-book-open-reader'],
];
?>

<section class="biometric-audit-page">
  <div class="biometric-audit-cards">
    <?php foreach ($cards as $card): ?>
      <article class="biometric-audit-card">
        <span class="biometric-audit-card__icon"><i class="fa-solid <?= h($card['icon']) ?>"></i></span>
        <div class="biometric-audit-card__body">
          <span class="biometric-audit-card__label"><?= h($card['label']) ?></span>
          <strong class="biometric-audit-card__value"><?= (int)$card['value'] ?></strong>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="enroll-audit-v2__shell biometric-audit-shell">
    <section class="enroll-table-shell enroll-table-shell--v2">
      <div class="enroll-table-shell__head enroll-table-shell__head--compact">
        <div>
          <h3>Capturas biometricas</h3>
          <p>
            <?= $hasSelectedCourse && is_array($selectedCourse)
              ? 'Curso filtrado: ' . h($selectedCourse['title'] ?? '')
              : 'Ultimas biometrias aprovadas registradas no app.' ?>
          </p>
        </div>
        <div class="enroll-table-shell__head-actions">
          <div class="enroll-table-shell__meta" id="biometricVisibleCount"><?= (int)($summary['total'] ?? 0) ?> registros</div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table admin-table admin-table--biometric" id="biometricAuditTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Curso</th>
              <th>UID</th>
              <th>Aluno</th>
              <th>Capturada em</th>
              <th>Tamanho</th>
              <th>Status</th>
              <th>Foto</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="9" class="text-center py-4 text-muted">
                  <?= $hasSelectedCourse
                    ? 'Nenhuma biometria encontrada para o curso selecionado.'
                    : 'Nenhuma biometria encontrada no app.' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $auditId = (int)($row['id'] ?? 0);
                  $courseId = (int)($row['course_id'] ?? 0);
                  $moodleUserId = (int)($row['moodle_userid'] ?? 0);
                  $capturedTs = (int)($row['captured_ts'] ?? 0);
                  $status = trim((string)($row['status'] ?? 'approved'));
                  $statusLabel = $status !== '' ? ucfirst($status) : 'Aprovada';
                ?>
                <tr
                  data-id="<?= $auditId ?>"
                  data-course-id="<?= $courseId ?>"
                  data-user-id="<?= $moodleUserId ?>"
                >
                  <td data-order="<?= $auditId ?>"><strong>#<?= $auditId ?></strong></td>
                  <td>
                    <div class="biometric-audit-course"><?= h($row['course_title'] ?? '-') ?></div>
                    <div class="biometric-audit-meta">Curso app #<?= $courseId ?></div>
                  </td>
                  <td data-order="<?= $moodleUserId ?>">#<?= $moodleUserId ?></td>
                  <td>
                    <div class="biometric-audit-user"><?= h($row['fullname'] ?? ('Usuario #' . $moodleUserId)) ?></div>
                    <div class="biometric-audit-meta"><?= h($row['email'] ?? '-') ?></div>
                    <div class="biometric-audit-meta">@<?= h($row['username'] ?? '-') ?></div>
                  </td>
                  <td data-order="<?= $capturedTs ?>">
                    <?= h(fmt_dt_audit($capturedTs)) ?>
                    <div class="biometric-audit-meta"><?= h((string)($row['ip_address'] ?? '-')) ?></div>
                  </td>
                  <td data-order="<?= (int)($row['photo_size_bytes'] ?? 0) ?>"><?= h(fmt_bytes_audit((int)($row['photo_size_bytes'] ?? 0))) ?></td>
                  <td>
                    <span class="audit-chip audit-chip--success"><?= h($statusLabel) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($row['has_photo'])): ?>
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-primary js-open-biometric"
                        data-id="<?= $auditId ?>"
                        data-fullname="<?= h($row['fullname'] ?? ('Usuario #' . $moodleUserId)) ?>"
                        data-course-title="<?= h($row['course_title'] ?? '-') ?>"
                        data-captured-at="<?= h(fmt_dt_audit($capturedTs)) ?>"
                      >
                        <i class="fa-solid fa-image"></i> Ver foto
                      </button>
                    <?php else: ?>
                      <span class="text-muted small">Sem foto</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="admin-actions admin-actions--audit">
                      <button type="button" class="btn btn-sm btn-outline-danger js-delete-biometric" data-id="<?= $auditId ?>">
                        <i class="fa-solid fa-trash"></i>
                      </button>
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

<div class="modal fade" id="modalBiometricPreview" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered biometric-preview-modal-dialog">
    <div class="modal-content biometric-preview-modal">
      <div class="modal-header">
        <div>
          <h5 class="modal-title">Biometria do aluno</h5>
          <div class="biometric-preview-modal__meta" id="biometricPreviewMeta">-</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="biometric-preview-modal__stage">
          <img id="biometricPreviewImage" alt="Biometria do aluno">
        </div>
        <div class="biometric-preview-modal__info" id="biometricPreviewInfo"></div>
      </div>
    </div>
  </div>
</div>

<div class="biometric-hover-card" id="biometricHoverCard" aria-hidden="true">
  <div class="biometric-hover-card__media">
    <img id="biometricHoverImage" alt="Preview da biometria">
  </div>
  <div class="biometric-hover-card__body">
    <div class="biometric-hover-card__title" id="biometricHoverTitle">Carregando...</div>
    <div class="biometric-hover-card__meta" id="biometricHoverMeta">-</div>
    <div class="biometric-hover-card__status" id="biometricHoverStatus">Passe o mouse para previsualizar.</div>
  </div>
</div>

<script>
(() => {
  const init = () => {
    const courseSelect = document.getElementById('selBiometricCourse');
    const refreshButton = document.getElementById('btnRefreshBiometricAudit');
    const tableNode = document.getElementById('biometricAuditTable');
    const previewModalEl = document.getElementById('modalBiometricPreview');
    const previewImage = document.getElementById('biometricPreviewImage');
    const previewMeta = document.getElementById('biometricPreviewMeta');
    const previewInfo = document.getElementById('biometricPreviewInfo');
    const hoverCard = document.getElementById('biometricHoverCard');
    const hoverImage = document.getElementById('biometricHoverImage');
    const hoverTitle = document.getElementById('biometricHoverTitle');
    const hoverMeta = document.getElementById('biometricHoverMeta');
    const hoverStatus = document.getElementById('biometricHoverStatus');
    const visibleCount = document.getElementById('biometricVisibleCount');
    const appBase = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
    const supportsHover = !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);

    let table = null;
    let previewModal = null;
    let hoverTimer = null;
    let hideTimer = null;
    let activeHoverButton = null;
    const previewCache = new Map();

    const escapeHtml = (value) => String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const rebuildUrl = (courseId) => {
      const url = new URL(window.location.href);
      if (courseId) {
        url.searchParams.set('course_id', courseId);
      } else {
        url.searchParams.delete('course_id');
      }
      return url.toString();
    };

    const updateVisibleCount = () => {
      if (!visibleCount) {
        return;
      }
      if (table) {
        visibleCount.textContent = `${table.rows({ filter: 'applied' }).count()} registros`;
        return;
      }
      const rows = Array.from(tableNode?.querySelectorAll('tbody tr[data-id]') || []);
      visibleCount.textContent = `${rows.length} registros`;
    };

    const loadPreview = async (auditId) => {
      const url = new URL(appBase + '/api/admin/biometric/read', window.location.origin);
      url.searchParams.set('id', String(auditId));
      const response = await fetch(url.toString(), {
        credentials: 'same-origin'
      });
      const json = await response.json().catch(() => null);
      if (!response.ok || !json || json.ok === false) {
        throw new Error((json && json.error) ? json.error : 'Falha ao carregar biometria.');
      }
      return json.audit || {};
    };

    const getPreviewAudit = async (auditId) => {
      if (previewCache.has(auditId)) {
        return await previewCache.get(auditId);
      }
      const pending = loadPreview(auditId)
        .then((audit) => {
          previewCache.set(auditId, audit);
          return audit;
        })
        .catch((error) => {
          previewCache.delete(auditId);
          throw error;
        });
      previewCache.set(auditId, pending);
      return await pending;
    };

    const fillPreviewModal = (audit) => {
      previewImage.src = audit.photo_src || '';
      previewMeta.textContent = `${audit.fullname || ('Usuario #' + (audit.moodle_userid || ''))} - ${audit.captured_at_label || '-'}`;
      previewInfo.innerHTML = `
        <div class="biometric-preview-modal__grid">
          <div><span>Curso</span><strong>${escapeHtml(audit.course_title || '-')}</strong></div>
          <div><span>UID</span><strong>#${escapeHtml(audit.moodle_userid || '-')}</strong></div>
          <div><span>Tamanho</span><strong>${escapeHtml(audit.photo_size_label || '-')}</strong></div>
          <div><span>IP</span><strong>${escapeHtml(audit.ip_address || '-')}</strong></div>
        </div>
      `;
    };

    const positionHoverCard = (button) => {
      if (!hoverCard || !button) {
        return;
      }
      const rect = button.getBoundingClientRect();
      const cardRect = hoverCard.getBoundingClientRect();
      const gap = 14;
      const viewportPadding = 12;
      let left = rect.right + gap;
      let top = rect.top + (rect.height / 2) - (cardRect.height / 2);

      if (left + cardRect.width > window.innerWidth - viewportPadding) {
        left = rect.left - cardRect.width - gap;
      }
      if (left < viewportPadding) {
        left = Math.max(viewportPadding, window.innerWidth - cardRect.width - viewportPadding);
      }
      if (top < viewportPadding) {
        top = viewportPadding;
      }
      if (top + cardRect.height > window.innerHeight - viewportPadding) {
        top = Math.max(viewportPadding, window.innerHeight - cardRect.height - viewportPadding);
      }

      hoverCard.style.left = `${Math.round(left)}px`;
      hoverCard.style.top = `${Math.round(top)}px`;
    };

    const hideHoverCard = () => {
      clearTimeout(hoverTimer);
      clearTimeout(hideTimer);
      activeHoverButton = null;
      if (!hoverCard) {
        return;
      }
      hoverCard.classList.remove('is-visible', 'is-loading');
      hoverCard.setAttribute('aria-hidden', 'true');
    };

    const scheduleHideHoverCard = () => {
      if (!supportsHover || !hoverCard) {
        return;
      }
      clearTimeout(hideTimer);
      hideTimer = window.setTimeout(() => {
        hideHoverCard();
      }, 120);
    };

    const primeHoverCard = (button) => {
      if (!hoverCard || !hoverImage || !hoverTitle || !hoverMeta || !hoverStatus) {
        return;
      }
      hoverTitle.textContent = button.dataset.fullname || 'Carregando...';
      hoverMeta.textContent = button.dataset.courseTitle || '-';
      hoverStatus.textContent = button.dataset.capturedAt ? `Capturada em ${button.dataset.capturedAt}` : 'Carregando preview...';
      hoverImage.removeAttribute('src');
      hoverCard.classList.add('is-visible', 'is-loading');
      hoverCard.setAttribute('aria-hidden', 'false');
      positionHoverCard(button);
    };

    const fillHoverCard = (button, audit) => {
      if (!hoverCard || !hoverImage || !hoverTitle || !hoverMeta || !hoverStatus) {
        return;
      }
      hoverImage.src = audit.photo_src || '';
      hoverTitle.textContent = audit.fullname || button.dataset.fullname || ('Usuario #' + (audit.moodle_userid || ''));
      hoverMeta.textContent = `${audit.course_title || button.dataset.courseTitle || '-'} · UID #${audit.moodle_userid || button.dataset.userId || '-'}`;
      hoverStatus.textContent = audit.captured_at_label ? `Capturada em ${audit.captured_at_label}` : (button.dataset.capturedAt ? `Capturada em ${button.dataset.capturedAt}` : 'Preview carregado');
      hoverCard.classList.remove('is-loading');
      hoverCard.classList.add('is-visible');
      hoverCard.setAttribute('aria-hidden', 'false');
      positionHoverCard(button);
    };

    const showHoverPreview = (button) => {
      if (!supportsHover || !hoverCard || !button) {
        return;
      }
      const auditId = Number(button.dataset.id || '0');
      if (auditId <= 0) {
        return;
      }
      clearTimeout(hoverTimer);
      clearTimeout(hideTimer);
      activeHoverButton = button;
      hoverTimer = window.setTimeout(async () => {
        primeHoverCard(button);
        try {
          const audit = await getPreviewAudit(auditId);
          if (activeHoverButton !== button) {
            return;
          }
          fillHoverCard(button, audit);
        } catch (error) {
          if (activeHoverButton !== button) {
            return;
          }
          if (hoverTitle) hoverTitle.textContent = button.dataset.fullname || 'Falha ao carregar';
          if (hoverMeta) hoverMeta.textContent = button.dataset.courseTitle || '-';
          if (hoverStatus) hoverStatus.textContent = error.message || 'Falha ao carregar preview da biometria.';
          if (hoverCard) {
            hoverCard.classList.remove('is-loading');
            hoverCard.classList.add('is-visible');
            hoverCard.setAttribute('aria-hidden', 'false');
            positionHoverCard(button);
          }
        }
      }, 160);
    };

    const openPreview = async (auditId) => {
      if (!previewModalEl || !previewImage || !previewMeta || !previewInfo) {
        return;
      }
      previewMeta.textContent = 'Carregando...';
      previewInfo.innerHTML = '';
      previewImage.removeAttribute('src');
      previewModal = previewModal || (window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(previewModalEl) : null);
      if (window.APP_MODAL) {
        window.APP_MODAL.open(previewModalEl);
      } else if (previewModal) {
        previewModal.show();
      }

      try {
        const audit = await getPreviewAudit(auditId);
        fillPreviewModal(audit);
      } catch (error) {
        previewMeta.textContent = 'Falha ao carregar';
        previewInfo.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Falha ao carregar biometria.')}</div>`;
      }
    };

    const deleteAudit = async (auditId, rowNode) => {
      const swal = window.Swal;
      let confirmed = true;
      if (swal) {
        const result = await swal.fire({
          title: 'Excluir biometria?',
          text: 'A captura sera removida do app. Se for a ultima valida, o aluno precisara tirar outra foto.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Excluir',
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        confirmed = !!result.isConfirmed;
      }
      if (!confirmed) {
        return;
      }

      await window.APP_HTTP.post('/api/admin/biometric/delete', { audit_id: auditId });
      previewCache.delete(auditId);
      if (table && rowNode) {
        table.row(rowNode).remove().draw(false);
      } else if (rowNode) {
        rowNode.remove();
      }
      hideHoverCard();
      updateVisibleCount();
      window.showToast('Biometria excluida. O aluno precisara validar novamente.', 'success');
    };

    if (courseSelect) {
      courseSelect.addEventListener('change', () => {
        window.location.href = rebuildUrl(courseSelect.value);
      });
    }

    refreshButton?.addEventListener('click', () => window.location.reload());

    if (window.jQuery && $.fn.DataTable && tableNode) {
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

      table.on('draw', () => {
        hideHoverCard();
        updateVisibleCount();
      });
      updateVisibleCount();
    }

    tableNode?.addEventListener('click', async (event) => {
      const previewButton = event.target.closest('.js-open-biometric');
      if (previewButton) {
        event.preventDefault();
        const auditId = Number(previewButton.dataset.id || '0');
        if (auditId > 0) {
          await openPreview(auditId);
        }
        return;
      }

      const deleteButton = event.target.closest('.js-delete-biometric');
      if (deleteButton) {
        event.preventDefault();
        const auditId = Number(deleteButton.dataset.id || '0');
        const rowNode = deleteButton.closest('tr');
        if (auditId > 0 && rowNode) {
          try {
            await deleteAudit(auditId, rowNode);
          } catch (error) {
            window.showToast(error.message || 'Falha ao excluir biometria.', 'error');
          }
        }
      }
    });

    if (supportsHover && tableNode) {
      tableNode.addEventListener('mouseover', (event) => {
        const previewButton = event.target.closest('.js-open-biometric');
        if (!previewButton) {
          return;
        }
        const from = event.relatedTarget;
        if (from && previewButton.contains(from)) {
          return;
        }
        showHoverPreview(previewButton);
      });

      tableNode.addEventListener('mouseout', (event) => {
        const previewButton = event.target.closest('.js-open-biometric');
        if (!previewButton) {
          return;
        }
        const to = event.relatedTarget;
        if (to && (previewButton.contains(to) || hoverCard?.contains(to))) {
          return;
        }
        scheduleHideHoverCard();
      });

      hoverCard?.addEventListener('mouseenter', () => {
        clearTimeout(hideTimer);
      });

      hoverCard?.addEventListener('mouseleave', () => {
        scheduleHideHoverCard();
      });

      window.addEventListener('scroll', hideHoverCard, { passive: true });
      window.addEventListener('resize', hideHoverCard);
    }

    updateVisibleCount();
  };

  window.addEventListener('load', init, { once: true });
})();
</script>
