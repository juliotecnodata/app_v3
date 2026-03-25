(() => {
  const base = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
  const csrf = document.querySelector('meta[name="csrf"]')?.getAttribute('content') || '';

  const parseResponse = async (res) => {
    const raw = await res.text();
    let json = null;
    if (raw) {
      try {
        json = JSON.parse(raw);
      } catch (_error) {
        json = null;
      }
    }
    if (!res.ok || !json || json.ok === false) {
      const fallbackText = String(raw || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
      const msg = (json && json.error)
        ? json.error
        : (fallbackText !== '' ? fallbackText.slice(0, 300) : 'Falha na requisição.');
      const error = new Error(msg);
      error.status = res.status;
      error.payload = json || null;
      error.raw = raw || '';
      throw error;
    }
    return json;
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const post = async (path, data) => {
    const fd = new FormData();
    for (const [k, v] of Object.entries(data || {})) fd.append(k, v ?? '');
    if (!fd.has('csrf')) fd.append('csrf', csrf);
    const res = await fetch(base + path, { method: 'POST', body: fd, credentials: 'same-origin' });
    return parseResponse(res);
  };

  const get = async (path, params = {}) => {
    const url = new URL(base + path, window.location.origin);
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || typeof value === 'undefined' || value === '') return;
      url.searchParams.set(key, value);
    });
    const res = await fetch(url.toString(), { method: 'GET', credentials: 'same-origin' });
    return parseResponse(res);
  };

  // Toast helper
  window.showToast = function(msg, type = 'info') {
    if (typeof Toastify !== 'undefined') {
      Toastify({
        text: msg,
        duration: 4000,
        gravity: 'top',
        position: 'right',
        backgroundColor: type === 'success' ? '#2f7a44' : (type === 'error' ? '#a9210f' : '#ed462f'),
        stopOnFocus: true,
        close: true
      }).showToast();
      return;
    }
    if (typeof Swal !== 'undefined') {
      Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'), title: msg, customClass: { popup: 'app-swal-toast', title: 'app-swal-toast__title' } });
      return;
    }
    console.log(type.toUpperCase() + ':', msg);
  };

  window.APP_HTTP = { post, get, base, csrf };

  const dataTableLanguage = {
    url: 'https://cdn.datatables.net/plug-ins/2.1.8/i18n/pt-BR.json'
  };

  const dataTableDefaults = {
    pageLength: 10,
    lengthMenu: [[5, 10, 20, 30, 50, 100], [5, 10, 20, 30, 50, 100]],
    order: [[0, 'desc']],
    autoWidth: false,
    orderClasses: false,
    language: dataTableLanguage
  };

  const dataTableApi = {
    defaults: dataTableDefaults,
    language: dataTableLanguage,
    init(target, options = {}) {
      if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') {
        return null;
      }

      const node = typeof target === 'string' ? document.querySelector(target) : target;
      if (!node) {
        return null;
      }

      const bodyRows = Array.from(node.querySelectorAll('tbody > tr'));
      if (bodyRows.length === 1) {
        const cells = bodyRows[0].querySelectorAll('td');
        if (cells.length === 1 && Number(cells[0].getAttribute('colspan') || 0) > 1) {
          return null;
        }
      }

      if (typeof window.jQuery.fn.DataTable.isDataTable === 'function' && window.jQuery.fn.DataTable.isDataTable(node)) {
        return window.jQuery(node).DataTable();
      }

      const merged = {
        ...dataTableDefaults,
        ...(options || {})
      };

      if (!merged.language) {
        merged.language = dataTableLanguage;
      }

      return window.jQuery(node).DataTable(merged);
    }
  };

  window.APP_DATA_TABLE = dataTableApi;

  const courseBlockDefaults = {
    title: 'Acesso bloqueado',
    message: 'Seu acesso a este curso foi bloqueado por descumprimento das regras de validacao. Entre em contato com a equipe administrativa para regularizacao.'
  };

  const courseBlockApi = {
    async block(context = {}) {
      const courseId = Number(context.courseId || 0);
      const userId = Number(context.userId || 0);
      if (courseId <= 0 || userId <= 0) {
        throw new Error('Curso ou aluno invalido para bloqueio.');
      }

      const initialTitle = String(context.currentTitle || context.title || courseBlockDefaults.title).trim() || courseBlockDefaults.title;
      const initialMessage = String(context.currentMessage || context.message || courseBlockDefaults.message).trim() || courseBlockDefaults.message;

      let title = initialTitle;
      let message = initialMessage;

      if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
          title: 'Bloquear acesso ao curso',
          width: 720,
          showCancelButton: true,
          confirmButtonText: 'Bloquear aluno',
          cancelButtonText: 'Cancelar',
          reverseButtons: true,
          customClass: {
            popup: 'app-swal app-swal--wide'
          },
          html:
            `<div class="text-start">` +
              `<div class="small text-muted mb-3">Aluno: <strong>${escapeHtml(String(context.userName || 'Aluno'))}</strong>${context.courseTitle ? ` &middot; Curso: <strong>${escapeHtml(String(context.courseTitle))}</strong>` : ''}</div>` +
              `<div class="mb-3">` +
                `<label class="form-label fw-semibold" for="swal-course-block-title">Titulo</label>` +
                `<input id="swal-course-block-title" class="form-control" maxlength="160" value="${escapeHtml(initialTitle)}">` +
              `</div>` +
              `<div>` +
                `<label class="form-label fw-semibold" for="swal-course-block-message">Mensagem</label>` +
                `<textarea id="swal-course-block-message" class="form-control" rows="5" maxlength="4000">${escapeHtml(initialMessage)}</textarea>` +
              `</div>` +
            `</div>`,
          preConfirm: () => {
            const titleField = document.getElementById('swal-course-block-title');
            const messageField = document.getElementById('swal-course-block-message');
            const nextTitle = String(titleField?.value || '').trim() || courseBlockDefaults.title;
            const nextMessage = String(messageField?.value || '').trim() || courseBlockDefaults.message;
            if (nextMessage.length < 10) {
              Swal.showValidationMessage('Informe uma mensagem mais clara para o aluno.');
              return false;
            }
            return { title: nextTitle, message: nextMessage };
          }
        });

        if (!result.isConfirmed || !result.value) {
          return null;
        }

        title = String(result.value.title || '').trim() || courseBlockDefaults.title;
        message = String(result.value.message || '').trim() || courseBlockDefaults.message;
      } else {
        const typed = window.prompt('Mensagem de bloqueio para o aluno:', initialMessage);
        if (typed === null) {
          return null;
        }
        message = String(typed || '').trim() || courseBlockDefaults.message;
      }

      return post('/api/admin/course-access/block', {
        course_id: courseId,
        user_id: userId,
        title,
        message,
        reason_code: 'manual'
      });
    },

    async unblock(context = {}) {
      const courseId = Number(context.courseId || 0);
      const userId = Number(context.userId || 0);
      if (courseId <= 0 || userId <= 0) {
        throw new Error('Curso ou aluno invalido para desbloqueio.');
      }

      if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
          icon: 'question',
          title: 'Desbloquear acesso do aluno?',
          html: `<div class="small text-muted">Aluno: <strong>${escapeHtml(String(context.userName || 'Aluno'))}</strong>${context.courseTitle ? ` &middot; Curso: <strong>${escapeHtml(String(context.courseTitle))}</strong>` : ''}</div>`,
          showCancelButton: true,
          confirmButtonText: 'Desbloquear',
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        if (!result.isConfirmed) {
          return null;
        }
      } else if (!window.confirm('Deseja desbloquear o acesso deste aluno ao curso?')) {
        return null;
      }

      return post('/api/admin/course-access/unblock', {
        course_id: courseId,
        user_id: userId
      });
    }
  };

  window.APP_COURSE_BLOCK = courseBlockApi;

  // Modal helper/fallback:
  // - Use Bootstrap modal API when available.
  // - If Bootstrap JS is unavailable, provide a lightweight fallback so
  //   data-bs-toggle/data-bs-dismiss keep working.
  const hasBootstrapModal = !!(window.bootstrap && window.bootstrap.Modal);
  const fallbackBackdrops = new Map();

  const dispatchModalEvent = (modal, type) => {
    if (!modal) return;
    modal.dispatchEvent(new Event(type, { bubbles: true }));
  };

  const fallbackOpenModal = (modal) => {
    if (!modal) return;
    modal.style.display = 'block';
    modal.classList.add('show');
    modal.removeAttribute('aria-hidden');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('role', 'dialog');
    document.body.classList.add('modal-open');

    if (!fallbackBackdrops.has(modal)) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
      fallbackBackdrops.set(modal, backdrop);
    }

    dispatchModalEvent(modal, 'shown.bs.modal');
  };

  const fallbackCloseModal = (modal) => {
    if (!modal) return;
    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');

    const backdrop = fallbackBackdrops.get(modal);
    if (backdrop && backdrop.parentNode) {
      backdrop.parentNode.removeChild(backdrop);
    }
    fallbackBackdrops.delete(modal);

    if (fallbackBackdrops.size === 0) {
      document.body.classList.remove('modal-open');
    }

    dispatchModalEvent(modal, 'hidden.bs.modal');
  };

  window.APP_MODAL = {
    open(modal) {
      if (!modal) return;
      if (hasBootstrapModal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        return;
      }
      fallbackOpenModal(modal);
    },
    close(modal) {
      if (!modal) return;
      if (hasBootstrapModal) {
        const instance = window.bootstrap.Modal.getInstance(modal);
        if (instance) {
          instance.hide();
        }
        return;
      }
      fallbackCloseModal(modal);
    },
  };

  if (!hasBootstrapModal) {
    document.addEventListener('click', (event) => {
      const openTrigger = event.target.closest('[data-bs-toggle="modal"][data-bs-target]');
      if (openTrigger) {
        const target = openTrigger.getAttribute('data-bs-target');
        const modal = target ? document.querySelector(target) : null;
        if (modal) {
          event.preventDefault();
          fallbackOpenModal(modal);
          return;
        }
      }

      const closeTrigger = event.target.closest('[data-bs-dismiss="modal"]');
      if (closeTrigger) {
        event.preventDefault();
        const modal = closeTrigger.closest('.modal');
        fallbackCloseModal(modal);
        return;
      }

      const modalRoot = event.target.classList && event.target.classList.contains('modal')
        ? event.target
        : null;
      if (modalRoot && modalRoot.classList.contains('show')) {
        fallbackCloseModal(modalRoot);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const opened = Array.from(document.querySelectorAll('.modal.show'));
      if (!opened.length) return;
      fallbackCloseModal(opened[opened.length - 1]);
    });
  }

  // Global select2 init (optional)
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof $ === 'undefined') return;
    document.querySelectorAll('select.select2').forEach(el => $(el).select2({ width: '100%' }));
  });

  const initStudentAlerts = () => {
    const body = document.body;
    if (!body || body.classList.contains('admin-shell')) {
      return;
    }

    const alertPage = (document.querySelector('meta[name="app-alert-page"]')?.getAttribute('content') || '').trim();
    if (!alertPage || alertPage === 'login') {
      return;
    }

    const courseId = Number(document.querySelector('meta[name="app-course-id"]')?.getAttribute('content') || 0);
    const shownIds = new Set();
    let visibleAlertId = 0;

    const iconForSeverity = (severity) => {
      switch (String(severity || '').toLowerCase()) {
        case 'danger':
          return 'warning';
        case 'info':
          return 'info';
        default:
          return 'warning';
      }
    };

    const acknowledge = async (alertId) => {
      try {
        await post('/api/user-alert/ack', {
          alert_id: alertId,
          page: alertPage,
          course_id: courseId > 0 ? courseId : '',
        });
      } catch (_error) {
        // Mantem o fluxo do aluno mesmo se a confirmacao falhar.
      }
    };

    const showAlert = async (alert) => {
      const alertId = Number(alert?.id || 0);
      if (alertId <= 0 || shownIds.has(alertId) || visibleAlertId === alertId) {
        return;
      }

      shownIds.add(alertId);
      visibleAlertId = alertId;

      if (typeof Swal === 'undefined') {
        window.alert(`${alert.title}\n\n${alert.message}`);
        if (alert.require_ack || alert.is_blocking) {
          await acknowledge(alertId);
        }
        visibleAlertId = 0;
        return;
      }

      const footerParts = [];
      if (alert.created_at_label) {
        footerParts.push(`Emitido em ${alert.created_at_label}`);
      }
      if (alert.expires_at_label) {
        footerParts.push(`Valido ate ${alert.expires_at_label}`);
      }

      await Swal.fire({
        icon: iconForSeverity(alert.severity),
        title: String(alert.title || 'Aviso importante'),
        html: `<div class="app-user-alert__message">${String(alert.message || '').replace(/\n/g, '<br>')}</div>`,
        footer: footerParts.length ? footerParts.join(' &middot; ') : '',
        confirmButtonText: alert.is_blocking ? 'Li e entendi' : 'Ok',
        allowOutsideClick: !alert.is_blocking,
        allowEscapeKey: !alert.is_blocking,
        customClass: {
          popup: 'app-user-alert',
          title: 'app-user-alert__title',
          confirmButton: 'app-user-alert__confirm'
        }
      });

      if (alert.require_ack || alert.is_blocking) {
        await acknowledge(alertId);
      }

      visibleAlertId = 0;
    };

    const manualCheck = async () => {
      try {
        const response = await get('/api/user-alert/current', {
          page: alertPage,
          course_id: courseId > 0 ? courseId : '',
        });
        if (response && response.alert) {
          await showAlert(response.alert);
        }
      } catch (_error) {
        // Sem impacto para a pagina atual.
      }
    };

    window.APP_USER_ALERT = {
      check: manualCheck
    };

    if (window.APP_BOOTSTRAP_ALERT) {
      showAlert(window.APP_BOOTSTRAP_ALERT);
    }
  };

  document.addEventListener('DOMContentLoaded', initStudentAlerts, { once: true });
})();
