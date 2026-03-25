(() => {
  const http = window.APP_HTTP || null;
  const post = http ? http.post : null;

  const shell = document.querySelector('.study-shell');
  const btnMenu = document.getElementById('btnToggleMenu');
  const offcanvasEl = document.getElementById('studyOffcanvas');

  const syncHeaderOffset = () => {
    if (!shell) return;
    const header = document.querySelector('.study-header');
    if (!header) return;
    const height = Math.ceil(header.getBoundingClientRect().height);
    if (height > 0) {
      shell.style.setProperty('--study-header-height', `${height + 6}px`);
    }
  };

  syncHeaderOffset();
  window.addEventListener('resize', syncHeaderOffset);
  window.setTimeout(syncHeaderOffset, 80);

  if (shell && btnMenu) {
    const desktopQuery = window.matchMedia('(min-width: 768px)');
    const toggleAttr = btnMenu.getAttribute('data-bs-toggle');
    const targetAttr = btnMenu.getAttribute('data-bs-target');

    const setCollapsed = (collapsed) => {
      shell.classList.toggle('is-collapsed', collapsed);
      btnMenu.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
      if (collapsed) {
        localStorage.setItem('studySidebarCollapsed', '1');
      } else {
        localStorage.removeItem('studySidebarCollapsed');
      }
    };

    const applyViewportMode = (desktop) => {
      if (desktop) {
        btnMenu.removeAttribute('data-bs-toggle');
        btnMenu.removeAttribute('data-bs-target');

        if (offcanvasEl && window.bootstrap?.Offcanvas) {
          const instance = window.bootstrap.Offcanvas.getInstance(offcanvasEl);
          if (instance) instance.hide();
        }

        document.querySelectorAll('.offcanvas-backdrop').forEach((node) => node.remove());
        document.body.classList.remove('offcanvas-open');
      } else {
        if (toggleAttr) btnMenu.setAttribute('data-bs-toggle', toggleAttr);
        if (targetAttr) btnMenu.setAttribute('data-bs-target', targetAttr);
      }
    };

    if (desktopQuery.matches && localStorage.getItem('studySidebarCollapsed') === '1') {
      setCollapsed(true);
    }

    applyViewportMode(desktopQuery.matches);

    btnMenu.addEventListener('click', (event) => {
      if (!desktopQuery.matches) return;
      event.preventDefault();
      event.stopPropagation();
      setCollapsed(!shell.classList.contains('is-collapsed'));
    });

    desktopQuery.addEventListener('change', () => {
      applyViewportMode(desktopQuery.matches);
      if (!desktopQuery.matches) {
        setCollapsed(false);
      }
    });
  }

  if (offcanvasEl) {
    offcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
      document.querySelectorAll('.offcanvas-backdrop').forEach((node) => node.remove());
      document.body.classList.remove('offcanvas-open');
    });
  }

  const setupTrailSearch = () => {
    const searchInputs = [
      document.getElementById('trailSearchDesktop'),
      document.getElementById('trailSearchMobile')
    ].filter(Boolean);
    const expandButtons = Array.from(document.querySelectorAll('.js-trail-expand-all'));
    const collapseButtons = Array.from(document.querySelectorAll('.js-trail-collapse-all'));
    const desktopRail = document.getElementById('railListDesktop');
    const mobileRail = document.getElementById('railListMobile');
    const rails = [desktopRail, mobileRail].filter(Boolean);
    const viewportQuery = window.matchMedia('(min-width: 768px)');

    if (!rails.length) return;

    const buildInitialCollapsedSet = () => {
      const byNode = new Map();
      document.querySelectorAll('.trail-container[data-collapsible="1"]').forEach((container) => {
        const nodeId = String(container.dataset.nodeId || '').trim();
        if (nodeId === '') return;
        const isActivePath = container.classList.contains('is-active-path');
        if (!byNode.has(nodeId)) {
          byNode.set(nodeId, isActivePath);
        } else if (isActivePath) {
          byNode.set(nodeId, true);
        }
      });

      const initial = new Set();
      byNode.forEach((isActivePath, nodeId) => {
        if (!isActivePath) {
          initial.add(nodeId);
        }
      });
      return initial;
    };

    let collapsedState = buildInitialCollapsedSet();

    const getCollapsedSet = () => {
      return new Set(collapsedState);
    };

    const saveCollapsedSet = (set) => {
      collapsedState = new Set(Array.from(set).map((value) => String(value)));
    };

    const applyCollapsedVisual = (container, collapsed) => {
      container.classList.toggle('is-collapsed', collapsed);
      container.classList.toggle('is-open', !collapsed);
      const toggle = container.querySelector(':scope > .trail-group .trail-group-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      }
    };

    const applyCollapsedStateByNode = (nodeId, collapsed) => {
      document.querySelectorAll(`.trail-container[data-node-id="${nodeId}"][data-collapsible="1"]`).forEach((container) => {
        applyCollapsedVisual(container, collapsed);
      });
    };

    const getAllCollapsibleNodeIds = () => {
      const unique = new Set();
      document.querySelectorAll('.trail-container[data-collapsible="1"]').forEach((container) => {
        const nodeId = String(container.dataset.nodeId || '');
        if (nodeId !== '') unique.add(nodeId);
      });
      return unique;
    };

    const restoreCollapsedState = () => {
      const collapsed = getCollapsedSet();
      let collapsedActivePath = false;
      document.querySelectorAll('.trail-container[data-collapsible="1"]').forEach((container) => {
        const nodeId = String(container.dataset.nodeId || '');
        const isCollapsed = nodeId !== '' && collapsed.has(nodeId);
        if (isCollapsed && container.classList.contains('is-active-path')) {
          collapsedActivePath = true;
        }
        applyCollapsedVisual(container, isCollapsed);
      });
      focusActiveTrailItem('auto', !collapsedActivePath);
    };

    const setAllCollapsed = (collapsed) => {
      const nodeIds = getAllCollapsibleNodeIds();
      nodeIds.forEach((nodeId) => {
        applyCollapsedStateByNode(nodeId, collapsed);
      });
      saveCollapsedSet(collapsed ? nodeIds : new Set());
      focusActiveTrailItem(collapsed ? 'auto' : 'smooth', !collapsed);
    };

    const ensureSearchCache = (rail) => {
      rail.querySelectorAll('.trail-item').forEach((item) => {
        if (!item.dataset.searchText) {
          item.dataset.searchText = String(item.textContent || '').toLowerCase();
        }
      });
    };
    rails.forEach(ensureSearchCache);

    const childGroups = (container) => {
      const wrap = container.querySelector(':scope > .trail-container-children');
      if (!wrap) return { containers: [], items: [] };
      const containers = [];
      const items = [];
      Array.from(wrap.children).forEach((child) => {
        if (child.classList.contains('trail-container')) {
          containers.push(child);
        } else if (child.classList.contains('trail-item')) {
          items.push(child);
        }
      });
      return { containers, items };
    };

    const getActiveRails = () => {
      return viewportQuery.matches
        ? [desktopRail].filter(Boolean)
        : [mobileRail].filter(Boolean);
    };

    const ensureActivePathExpanded = () => {
      document.querySelectorAll('.trail-container.is-active-path[data-collapsible="1"]').forEach((container) => {
        applyCollapsedVisual(container, false);
      });
    };

    const scrollRailToItem = (rail, item, behavior = 'smooth') => {
      if (!rail || !item) return;
      if (item.offsetParent === null) return;

      const railRect = rail.getBoundingClientRect();
      const itemRect = item.getBoundingClientRect();
      const itemTopInRail = (itemRect.top - railRect.top) + rail.scrollTop;
      const targetTop = itemTopInRail - Math.max(18, Math.floor(rail.clientHeight * 0.28));

      rail.scrollTo({
        top: Math.max(0, targetTop),
        behavior
      });
    };

    const focusActiveTrailItem = (behavior = 'smooth', expandActivePath = true) => {
      if (expandActivePath) {
        ensureActivePathExpanded();
      }
      getActiveRails().forEach((rail) => {
        const active = rail.querySelector('.trail-item.is-active');
        if (!active) return;
        scrollRailToItem(rail, active, behavior);
      });
    };

    const resetRailVisibility = (rail) => {
      rail.querySelectorAll('.trail-item').forEach((item) => {
        item.style.display = '';
      });
      rail.querySelectorAll('.trail-container').forEach((container) => {
        container.style.display = '';
      });
    };

    const applyFilterOnRail = (rail, normalizedQuery) => {
      rail.querySelectorAll('.trail-item').forEach((item) => {
        const text = String(item.dataset.searchText || '');
        item.style.display = !normalizedQuery || text.includes(normalizedQuery) ? '' : 'none';
      });

      const walkContainer = (container) => {
        const header = String(container.dataset.search || '').toLowerCase();
        const groups = childGroups(container);
        let hasVisibleChild = false;
        for (let i = 0; i < groups.containers.length; i++) {
          if (walkContainer(groups.containers[i])) hasVisibleChild = true;
        }

        let hasVisibleItem = false;
        for (let i = 0; i < groups.items.length; i++) {
          if (groups.items[i].style.display !== 'none') {
            hasVisibleItem = true;
            break;
          }
        }

        const headerMatch = header.includes(normalizedQuery);
        const shouldShow = headerMatch || hasVisibleChild || hasVisibleItem;
        container.style.display = shouldShow ? '' : 'none';
        if (shouldShow && container.dataset.collapsible === '1') {
          applyCollapsedVisual(container, false);
        }
        return shouldShow;
      };

      Array.from(rail.children)
        .filter((child) => child.classList.contains('trail-container'))
        .forEach((container) => {
          walkContainer(container);
        });
    };

    let lastQuery = '';
    const applyFilter = (query) => {
      lastQuery = String(query || '');
      const normalized = lastQuery.trim().toLowerCase();
      const activeRails = getActiveRails();

      if (!normalized) {
        activeRails.forEach(resetRailVisibility);
        restoreCollapsedState();
        return;
      }

      activeRails.forEach((rail) => applyFilterOnRail(rail, normalized));
      focusActiveTrailItem('smooth');
    };

    let filterTimer = 0;
    const scheduleFilter = (query) => {
      window.clearTimeout(filterTimer);
      filterTimer = window.setTimeout(() => {
        applyFilter(query);
      }, 120);
    };

    document.addEventListener('click', (event) => {
      const toggle = event.target.closest('.trail-group-toggle');
      if (!toggle) return;

      const container = toggle.closest('.trail-container[data-collapsible="1"]');
      if (!container) return;

      event.preventDefault();
      event.stopPropagation();

      const nodeId = String(container.dataset.nodeId || '');
      if (!nodeId) return;

      const shouldCollapse = !container.classList.contains('is-collapsed');
      applyCollapsedStateByNode(nodeId, shouldCollapse);

      const collapsed = getCollapsedSet();
      if (shouldCollapse) {
        collapsed.add(nodeId);
      } else {
        collapsed.delete(nodeId);
      }
      saveCollapsedSet(collapsed);
    });

    restoreCollapsedState();

    searchInputs.forEach((input) => {
      input.addEventListener('input', () => {
        const query = String(input.value || '');
        searchInputs.forEach((other) => {
          if (other === input) return;
          other.value = query;
        });
        scheduleFilter(query);
      });
    });

    expandButtons.forEach((button) => {
      button.addEventListener('click', () => {
        setAllCollapsed(false);
      });
    });

    collapseButtons.forEach((button) => {
      button.addEventListener('click', () => {
        setAllCollapsed(true);
      });
    });

    viewportQuery.addEventListener('change', () => {
      // Reaplica o estado no rail ativo ao trocar entre desktop/mobile.
      applyFilter(lastQuery);
      focusActiveTrailItem('auto');
    });

    window.setTimeout(() => {
      focusActiveTrailItem('auto');
    }, 60);
  };

  setupTrailSearch();

  const studyMain = document.querySelector('.study-main');
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a.js-study-link');
    if (!link) return;
    if (event.defaultPrevented) return;
    if (event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target && link.target !== '_self') return;

    let destination;
    try {
      destination = new URL(link.href, window.location.href);
    } catch (error) {
      return;
    }

    const current = new URL(window.location.href);
    if (destination.origin !== current.origin) return;

    event.preventDefault();
    if (studyMain) studyMain.classList.add('is-transitioning');
    setTimeout(() => {
      window.location.href = destination.href;
    }, 120);
  });

  const progressBar = document.getElementById('studyProgressBar');
  const progressLabel = document.getElementById('studyProgressLabel');
  const totalItemsInput = document.getElementById('studyTotalItems');
  const doneItemsInput = document.getElementById('studyDoneItems');
  const selectedDoneInput = document.getElementById('studySelectedDone');
  const doneCountLabel = document.getElementById('studyDoneCount');

  const applyTopbarProgress = () => {
    const total = parseInt(String(totalItemsInput?.value || '0'), 10) || 0;
    const done = parseInt(String(doneItemsInput?.value || '0'), 10) || 0;
    const percent = total > 0 ? Math.floor((Math.min(done, total) * 100) / total) : 0;

    if (progressBar) {
      progressBar.style.width = `${percent}%`;
      const progressRoot = progressBar.closest('.progress');
      if (progressRoot) progressRoot.setAttribute('aria-valuenow', String(percent));
    }

    if (progressLabel) {
      progressLabel.textContent = `${percent}%`;
    }

    if (doneCountLabel) {
      doneCountLabel.textContent = String(Math.min(done, total));
    }
  };

  const markCurrentAsCompletedInTopbar = () => {
    if (!totalItemsInput || !doneItemsInput || !selectedDoneInput) return;

    const alreadyCompleted = selectedDoneInput.value === '1';
    if (alreadyCompleted) {
      applyTopbarProgress();
      return;
    }

    const total = parseInt(String(totalItemsInput.value || '0'), 10) || 0;
    const done = parseInt(String(doneItemsInput.value || '0'), 10) || 0;
    doneItemsInput.value = String(Math.min(total, done + 1));
    selectedDoneInput.value = '1';
    applyTopbarProgress();
  };

  applyTopbarProgress();

  const nextLockMessage = document.getElementById('studyNextLockMessage');
  const markDoneButton = document.getElementById('btnMarkDone');
  const completionHint = document.getElementById('studyCompletionHint');
  const manualCompletionProgress = {
    percent: Math.max(0, Math.min(100, parseInt(String(markDoneButton?.getAttribute('data-current-percent') || '0'), 10) || 0)),
    position: Math.max(0, parseInt(String(markDoneButton?.getAttribute('data-current-position') || '0'), 10) || 0),
    seconds: 0
  };

  const rememberManualCompletionProgress = (percentValue, positionValue, secondsValue = 0) => {
    const percent = Math.max(0, Math.min(100, parseInt(String(percentValue ?? '0'), 10) || 0));
    const position = Math.max(0, parseInt(String(positionValue ?? '0'), 10) || 0);
    const seconds = Math.max(0, parseInt(String(secondsValue ?? '0'), 10) || 0);

    manualCompletionProgress.percent = Math.max(manualCompletionProgress.percent, percent);
    manualCompletionProgress.position = Math.max(manualCompletionProgress.position, position);
    manualCompletionProgress.seconds = Math.max(manualCompletionProgress.seconds, seconds);

    if (markDoneButton) {
      markDoneButton.setAttribute('data-current-percent', String(manualCompletionProgress.percent));
      markDoneButton.setAttribute('data-current-position', String(manualCompletionProgress.position));
    }
  };

  const setMarkDoneAvailability = (available, hintText = '') => {
    if (!markDoneButton) return;
    const enabled = !!available;
    const mode = String(markDoneButton.getAttribute('data-mode') || 'disabled');
    if (mode === 'complete') {
      markDoneButton.disabled = !enabled;
    }
    markDoneButton.setAttribute('data-unlocked', enabled ? '1' : '0');

    if (completionHint) {
      completionHint.textContent = String(hintText || '').trim();
      completionHint.classList.toggle('d-none', enabled || String(hintText || '').trim() === '');
    }
  };

  if (markDoneButton) {
    const unlocked = String(markDoneButton.getAttribute('data-unlocked') || '0') === '1';
    setMarkDoneAvailability(unlocked, unlocked ? '' : String(completionHint?.textContent || '').trim());
  }

  const refreshModuleCompletionState = () => {
    document.querySelectorAll('.trail-container[data-module="1"]').forEach((container) => {
      const items = Array.from(container.querySelectorAll('.trail-item'));
      const total = items.length;
      const done = items.filter((item) => item.classList.contains('is-done')).length;
      const isDone = total > 0 && done >= total;

      container.classList.toggle('is-module-done', isDone);
      container.setAttribute('data-module-total', String(total));
      container.setAttribute('data-module-done', String(done));

      const status = container.querySelector(':scope > .trail-group [data-module-status]');
      if (!status) return;
      if (isDone) {
        status.classList.add('is-done');
        status.innerHTML = '<i class="fa-solid fa-circle-check"></i> Concluido';
        status.removeAttribute('hidden');
        return;
      }
      status.classList.remove('is-done');
      status.innerHTML = '';
      status.setAttribute('hidden', 'hidden');
    });
  };

  refreshModuleCompletionState();

  const unlockNextButtonIfPossible = () => {
    if (!markDoneButton) return;
    const nextUrl = String(markDoneButton.getAttribute('data-next-url') || '').trim();
    if (nextUrl === '') return;
    markDoneButton.setAttribute('data-mode', 'next');
    markDoneButton.disabled = false;
    markDoneButton.classList.remove('study-footer-btn--complete', 'study-footer-btn--done', 'study-footer-btn--muted');
    markDoneButton.classList.add('study-footer-btn--next');
    markDoneButton.innerHTML = 'Proximo <i class="fa-solid fa-arrow-right ms-1"></i>';
    if (nextLockMessage) {
      nextLockMessage.classList.add('d-none');
    }
  };

  const setCurrentItemAsCompletedUI = () => {
    markCurrentAsCompletedInTopbar();

    const statusChip = document.querySelector('.study-context__status');
    if (statusChip) {
      statusChip.classList.remove('is-open');
      statusChip.classList.add('is-done');
      statusChip.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i>Item concluido';
    }

    document.querySelectorAll('.trail-item.is-active').forEach((activeTrailItem) => {
      activeTrailItem.classList.add('is-done');
      const dot = activeTrailItem.querySelector('.trail-dot');
      if (dot) {
        dot.innerHTML = '<i class="fa-solid fa-check"></i>';
      }
    });

    const unlockNextTrailItems = () => {
      const unlockItem = (item) => {
        item.classList.remove('is-locked');
        item.removeAttribute('aria-disabled');
        item.removeAttribute('tabindex');
        item.classList.add('is-unlocking');

        const number = String(item.getAttribute('data-node-number') || '').trim();
        const dot = item.querySelector('.trail-dot');
        if (dot) {
          if (number !== '') {
            dot.textContent = number;
          } else {
            dot.innerHTML = '<i class="fa-solid fa-circle" style="font-size:7px;"></i>';
          }
        }

        const lockRight = item.querySelector('i.fa-lock.text-muted');
        if (lockRight) {
          lockRight.className = 'fa-solid fa-chevron-right text-muted';
        }

        window.setTimeout(() => {
          item.classList.remove('is-unlocking');
        }, 760);
      };

      document.querySelectorAll('.rail-list').forEach((rail) => {
        const items = Array.from(rail.querySelectorAll('.trail-item'));
        const currentIndex = items.findIndex((item) => item.classList.contains('is-active'));
        if (currentIndex < 0) return;

        for (let i = currentIndex + 1; i < items.length; i++) {
          const candidate = items[i];
          if (!candidate.classList.contains('is-locked')) continue;
          unlockItem(candidate);
          break;
        }
      });
    };

    unlockNextTrailItems();
    refreshModuleCompletionState();

    if (markDoneButton) {
      const nextUrl = String(markDoneButton.getAttribute('data-next-url') || '').trim();
      if (nextUrl !== '') {
        unlockNextButtonIfPossible();
      } else {
        markDoneButton.disabled = true;
        markDoneButton.setAttribute('data-mode', 'done');
        markDoneButton.classList.remove('study-footer-btn--complete', 'study-footer-btn--next', 'study-footer-btn--muted');
        markDoneButton.classList.add('study-footer-btn--done');
        markDoneButton.innerHTML = '<i class="fa-solid fa-check me-1"></i> Aula concluida';
      }
    }

    if (completionHint) {
      completionHint.textContent = 'Voce ja pode seguir para o proximo conteudo.';
      completionHint.classList.remove('d-none');
    }

    unlockNextButtonIfPossible();
  };

  const showStudyDoneAlert = (title = 'Aula concluida', text = 'Item marcado como concluido.') => {
    if (!window.Swal) return;
    Swal.fire({
      toast: true,
      icon: 'success',
      position: 'top-end',
      title: String(title || 'Concluido'),
      text: String(text || ''),
      showConfirmButton: false,
      timer: 1400,
      timerProgressBar: true,
      customClass: {
        popup: 'study-swal-toast',
        title: 'study-swal-toast__title',
        htmlContainer: 'study-swal-toast__text'
      }
    });
  };

  if (!post) return;

  if (markDoneButton) {
    markDoneButton.addEventListener('click', async () => {
      const mode = String(markDoneButton.getAttribute('data-mode') || 'complete');
      if (mode === 'next') {
        const nextUrl = String(markDoneButton.getAttribute('data-next-url') || '').trim();
        if (nextUrl !== '') {
          window.location.href = nextUrl;
        }
        return;
      }
      if (mode !== 'complete') {
        return;
      }

      const courseId = markDoneButton.getAttribute('data-course');
      const nodeId = markDoneButton.getAttribute('data-node');
      const originalHtml = markDoneButton.innerHTML;
      const unlocked = String(markDoneButton.getAttribute('data-unlocked') || '0') === '1';
      const currentPercent = Math.max(0, Math.min(100, manualCompletionProgress.percent || 0));
      const currentPosition = Math.max(0, manualCompletionProgress.position || 0);
      const currentSeconds = Math.max(0, manualCompletionProgress.seconds || 0);

      try {
        markDoneButton.disabled = true;
        markDoneButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';

        if (currentPercent > 0 || currentPosition > 0 || currentSeconds > 0) {
          await post('/api/progress/update', {
            course_id: courseId,
            node_id: nodeId,
            status: 'in_progress',
            percent: currentPercent,
            seconds: currentSeconds,
            position: currentPosition
          });
        }

        await post('/api/progress/update', {
          course_id: courseId,
          node_id: nodeId,
          status: 'completed',
          percent: currentPercent > 0 ? currentPercent : 100,
          seconds: 0,
          position: currentPosition
        });

        setCurrentItemAsCompletedUI();

        showStudyDoneAlert('Aula concluida', 'Item marcado como concluido.');
      } catch (error) {
        markDoneButton.disabled = !unlocked;
        markDoneButton.innerHTML = originalHtml;
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Erro', text: String(error.message || error) });
        }
      }
    });
  }

  document.querySelectorAll('.js-study-pdf-touch').forEach((link) => {
    link.addEventListener('click', () => {
      const courseId = String(link.getAttribute('data-course') || '').trim();
      const nodeId = String(link.getAttribute('data-node') || '').trim();
      if (!courseId || !nodeId) return;

      setMarkDoneAvailability(true, '');
      rememberManualCompletionProgress(1, 1, 0);

      post('/api/progress/update', {
        course_id: courseId,
        node_id: nodeId,
        status: 'in_progress',
        percent: 1,
        seconds: 0,
        position: 1
      }).catch(() => {});
    });
  });

  document.querySelectorAll('.js-study-manual-touch').forEach((link) => {
    link.addEventListener('click', () => {
      const courseId = String(link.getAttribute('data-course') || '').trim();
      const nodeId = String(link.getAttribute('data-node') || '').trim();
      const percent = Math.max(1, parseInt(String(link.getAttribute('data-percent') || '1'), 10) || 1);
      const position = Math.max(1, parseInt(String(link.getAttribute('data-position') || '1'), 10) || 1);
      if (!courseId || !nodeId) return;

      setMarkDoneAvailability(true, '');
      rememberManualCompletionProgress(percent, position, 0);

      post('/api/progress/update', {
        course_id: courseId,
        node_id: nodeId,
        status: 'in_progress',
        percent,
        seconds: 0,
        position
      }).catch(() => {});
    });
  });

  const certificateButton = document.getElementById('btnGenerateCertificate');
  if (certificateButton) {
    certificateButton.addEventListener('click', async () => {
      const courseId = String(certificateButton.getAttribute('data-course') || '').trim();
      const nodeId = String(certificateButton.getAttribute('data-node') || '').trim();
      const certificateUrlRaw = String(certificateButton.getAttribute('data-url') || '').trim();
      const certificateUrl = certificateUrlRaw
        .replace(/&amp;/gi, '&')
        .replace(/&#38;/g, '&');
      if (!courseId || !nodeId || !certificateUrl) return;

      certificateButton.disabled = true;
      const originalHtml = certificateButton.innerHTML;
      certificateButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Gerando...';

      try {
        await post('/api/progress/update', {
          course_id: courseId,
          node_id: nodeId,
          status: 'completed',
          percent: 100,
          seconds: 0,
          position: 0
        });

        setCurrentItemAsCompletedUI();

        window.open(certificateUrl, '_blank', 'noopener');
        certificateButton.disabled = false;
        certificateButton.innerHTML = originalHtml;

        showStudyDoneAlert('Certificado liberado', 'Seu certificado foi gerado e o item foi concluido.');
      } catch (error) {
        certificateButton.disabled = false;
        certificateButton.innerHTML = originalHtml;
        if (window.Swal) {
          Swal.fire({ icon: 'error', title: 'Erro', text: String(error.message || error) });
        }
      }
    });
  }

  const mobileVideoToggleBtn = document.getElementById('btnVideoPlayPause');
  const setMobileVideoToggleEnabled = (enabled) => {
    if (!mobileVideoToggleBtn) return;
    mobileVideoToggleBtn.disabled = !enabled;
  };
  const setMobileVideoToggleState = (isPaused) => {
    if (!mobileVideoToggleBtn) return;
    const paused = !!isPaused;
    mobileVideoToggleBtn.dataset.paused = paused ? '1' : '0';
    mobileVideoToggleBtn.classList.toggle('is-paused', paused);
    mobileVideoToggleBtn.classList.toggle('is-playing', !paused);
    const icon = mobileVideoToggleBtn.querySelector('[data-icon]');
    const label = mobileVideoToggleBtn.querySelector('[data-label]');
    if (icon) {
      icon.className = `fa-solid ${paused ? 'fa-play' : 'fa-pause'} me-1`;
    }
    if (label) {
      label.textContent = paused ? 'Continuar video' : 'Pausar video';
    }
  };
  const bindMobileVideoToggle = (handler) => {
    if (!mobileVideoToggleBtn || typeof handler !== 'function') return;
    mobileVideoToggleBtn.addEventListener('click', (event) => {
      event.preventDefault();
      handler();
      mobileVideoToggleBtn.blur();
    });
  };

  const video = document.getElementById('player');
  if (video) {
    setMobileVideoToggleEnabled(true);
    setMobileVideoToggleState(video.paused);

    const courseId = video.getAttribute('data-course');
    const nodeId = video.getAttribute('data-node');
    const resumeAt = parseInt(String(video.getAttribute('data-lastpos') || '0'), 10) || 0;
    const minPercentRaw = parseInt(String(video.getAttribute('data-minpercent') || '100'), 10) || 100;
    const minPercent = Math.max(1, Math.min(100, minPercentRaw));
    // Modo economico:
    // - Nao envia ping continuo.
    // - Envia "in_progress" apenas em 1 checkpoint de retomada.
    // - Envia "completed" no limite minimo/configurado.
    const resumeCheckpointPercent = minPercent >= 90 ? 70 : (minPercent >= 70 ? 50 : 0);

    let lastTime = 0;
    let completionHandled = selectedDoneInput?.value === '1';
    let criteriaUnlocked = completionHandled || (String(markDoneButton?.getAttribute('data-unlocked') || '0') === '1');
    let checkpointSent = resumeCheckpointPercent <= 0;
    let syncInFlight = false;

    video.addEventListener('loadedmetadata', () => {
      if (resumeAt > 5 && resumeAt < ((video.duration || 0) - 5)) {
        video.currentTime = resumeAt;
      }
    });

    const sendProgress = async (forceComplete = false) => {
      if (completionHandled && !forceComplete) return;
      if (syncInFlight) return;

      const duration = video.duration || 0;
      const current = video.currentTime || 0;
      const percent = duration > 0 ? Math.min(100, Math.floor((current / duration) * 100)) : 0;
      const reachedMinimum = percent >= minPercent;
      const shouldUnlock = !completionHandled && !criteriaUnlocked && reachedMinimum;
      const shouldCheckpoint = !shouldUnlock
        && !checkpointSent
        && resumeCheckpointPercent > 0
        && percent >= resumeCheckpointPercent;
      if (!forceComplete && !shouldUnlock && !shouldCheckpoint) return;
      if (shouldCheckpoint) checkpointSent = true;
      if (shouldUnlock) {
        criteriaUnlocked = true;
        setMarkDoneAvailability(true, '');
      }

      const deltaSeconds = Math.max(0, Math.floor(current - lastTime));
      const currentPosition = Math.floor(current);
      rememberManualCompletionProgress(percent, currentPosition, deltaSeconds);

      lastTime = current;

      syncInFlight = true;
      try {
        await post('/api/progress/update', {
          course_id: courseId,
          node_id: nodeId,
          status: 'in_progress',
          percent: percent,
          seconds: deltaSeconds,
          position: currentPosition
        });
      } finally {
        syncInFlight = false;
      }
    };

    video.addEventListener('timeupdate', () => {
      sendProgress(false).catch(() => {});
    });

    video.addEventListener('play', () => {
      setMobileVideoToggleState(false);
    });

    video.addEventListener('pause', () => {
      setMobileVideoToggleState(true);
    });

    video.addEventListener('ended', () => {
      setMobileVideoToggleState(true);
      sendProgress(true)
        .catch((error) => {
          if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Erro ao salvar progresso', text: String(error.message || error) });
          }
        });
    });

    bindMobileVideoToggle(() => {
      if (video.paused) {
        const playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(() => {
            if (window.showToast) {
              window.showToast('Toque no video para continuar a reproducao.', 'info');
            }
          });
        }
      } else {
        video.pause();
      }
    });
  }

  const videoIframe = document.getElementById('playerIframe');
  if (videoIframe) {
    setMobileVideoToggleEnabled(true);
    setMobileVideoToggleState(false);

    const provider = String(videoIframe.getAttribute('data-provider') || '').toLowerCase();
    const iframeSrc = String(videoIframe.getAttribute('src') || '');
    const isYoutube = provider === 'youtube'
      || /(?:youtube\.com|youtube-nocookie\.com|youtu\.be)/i.test(iframeSrc);

    const buildYoutubeEmbedUrl = (rawUrl) => {
      const source = String(rawUrl || '').trim();
      if (source === '') return source;
      try {
        const parsed = new URL(source, window.location.origin);
        let videoId = '';

        if (/youtu\.be$/i.test(parsed.hostname)) {
          videoId = parsed.pathname.replace(/^\/+/, '').split('/')[0] || '';
        } else if (/youtube(?:-nocookie)?\.com$/i.test(parsed.hostname)) {
          if (parsed.pathname.startsWith('/embed/')) {
            videoId = parsed.pathname.replace('/embed/', '').split('/')[0] || '';
          } else {
            videoId = parsed.searchParams.get('v') || '';
          }
        }

        const target = videoId !== ''
          ? new URL(`https://www.youtube.com/embed/${videoId}`)
          : new URL(source, window.location.origin);
        if (/youtube(?:-nocookie)?\.com$/i.test(target.hostname)) {
          target.searchParams.set('enablejsapi', '1');
          target.searchParams.set('controls', '1');
          target.searchParams.set('playsinline', '1');
          target.searchParams.set('rel', '0');
          if (window.location.origin) {
            target.searchParams.set('origin', window.location.origin);
          }
        }
        return target.href;
      } catch (_error) {
        return source;
      }
    };

    if (provider === 'videofront') {
      const courseId = videoIframe.getAttribute('data-course');
      const nodeId = videoIframe.getAttribute('data-node');
      const minPercentRaw = parseInt(String(videoIframe.getAttribute('data-minpercent') || '100'), 10) || 100;
      const minPercent = Math.max(1, Math.min(100, minPercentRaw));
      const resumeCheckpointPercent = minPercent >= 90 ? 70 : (minPercent >= 70 ? 50 : 0);
      let lastPosition = parseInt(String(videoIframe.getAttribute('data-lastpos') || '0'), 10) || 0;
      let completionHandled = selectedDoneInput?.value === '1';
      let criteriaUnlocked = completionHandled || (String(markDoneButton?.getAttribute('data-unlocked') || '0') === '1');
      let checkpointSent = resumeCheckpointPercent <= 0;
      let syncInFlight = false;

      const postVfCommand = (command) => {
        const target = videoIframe.contentWindow;
        if (!target) return;
        const cmd = String(command || '').trim().toLowerCase();
        if (cmd === '') return;
        const payloads = [
          { localMensagem: 'vfplayer', nomeMensagem: cmd },
          { type: cmd },
          { action: cmd },
          { command: cmd }
        ];
        payloads.forEach((payload) => {
          try {
            target.postMessage(payload, '*');
            target.postMessage(JSON.stringify(payload), '*');
          } catch (_error) {
            // Ignora falha de comunicacao entre janelas.
          }
        });
      };

      const toInt = (value) => {
        const parsed = parseInt(String(value ?? '0'), 10);
        if (!Number.isFinite(parsed)) return 0;
        return parsed;
      };

      const handleVfMessage = (event) => {
        let data = event.data;
        if (!data) return;

        if (typeof data === 'string') {
          try {
            data = JSON.parse(data);
          } catch (_error) {
            return;
          }
        }

        if (!data || typeof data !== 'object') return;
        const origin = String(event.origin || '').toLowerCase();
        const isVideotecaOrigin = origin.includes('videotecaead.com.br');
        const isWrapperProgress = String(data.localMensagem || '') === 'vfplayer'
          && String(data.nomeMensagem || '') === 'progress';
        const isDirectTimeupdate = isVideotecaOrigin
          && String(data.type || '') === 'timeupdate';

        if (!isWrapperProgress && !isDirectTimeupdate) return;
        setMobileVideoToggleState(false);
        if (completionHandled) return;
        if (syncInFlight) return;

        const currentTime = Math.max(0, toInt(data.currentTime));
        let progress = Math.max(0, Math.min(100, toInt(data.progress)));
        if (!isWrapperProgress) {
          const duration = Math.max(0, toInt(data.duration));
          if (duration > 0) {
            progress = Math.max(0, Math.min(100, Math.round((currentTime * 100) / duration)));
          }
        }
        const reachedMinimum = progress >= minPercent;
        const shouldUnlock = !completionHandled && !criteriaUnlocked && reachedMinimum;
        const shouldCheckpoint = !shouldUnlock
          && !checkpointSent
          && resumeCheckpointPercent > 0
          && progress >= resumeCheckpointPercent;
        if (!shouldUnlock && !shouldCheckpoint) return;
        if (shouldCheckpoint) checkpointSent = true;
        if (shouldUnlock) {
          criteriaUnlocked = true;
          setMarkDoneAvailability(true, '');
        }

        const deltaSeconds = Math.max(0, currentTime - lastPosition);
        rememberManualCompletionProgress(progress, currentTime, deltaSeconds);
        lastPosition = currentTime;

        syncInFlight = true;
        post('/api/progress/update', {
          course_id: courseId,
          node_id: nodeId,
          status: 'in_progress',
          percent: progress,
          seconds: deltaSeconds,
          position: currentTime
        }).catch(() => {}).finally(() => {
          syncInFlight = false;
        });
      };

      window.addEventListener('message', handleVfMessage);
      window.addEventListener('beforeunload', () => {
        window.removeEventListener('message', handleVfMessage);
      }, { once: true });

      bindMobileVideoToggle(() => {
        const currentlyPaused = String(mobileVideoToggleBtn?.dataset.paused || '0') === '1';
        if (currentlyPaused) {
          postVfCommand('play');
          setMobileVideoToggleState(false);
        } else {
          postVfCommand('pause');
          setMobileVideoToggleState(true);
        }
      });
    } else if (isYoutube) {
      const courseId = videoIframe.getAttribute('data-course');
      const nodeId = videoIframe.getAttribute('data-node');
      const resumeAt = parseInt(String(videoIframe.getAttribute('data-lastpos') || '0'), 10) || 0;
      const minPercentRaw = parseInt(String(videoIframe.getAttribute('data-minpercent') || '100'), 10) || 100;
      const minPercent = Math.max(1, Math.min(100, minPercentRaw));
      const resumeCheckpointPercent = minPercent >= 90 ? 70 : (minPercent >= 70 ? 50 : 0);

      let completionHandled = selectedDoneInput?.value === '1';
      let criteriaUnlocked = completionHandled || (String(markDoneButton?.getAttribute('data-unlocked') || '0') === '1');
      let lastPosition = Math.max(0, resumeAt);
      let checkpointSent = resumeCheckpointPercent <= 0;
      let progressTimer = 0;
      let syncInFlight = false;
      let ytPlayer = null;

      const normalizedYoutubeSrc = buildYoutubeEmbedUrl(iframeSrc);
      if (normalizedYoutubeSrc !== '' && normalizedYoutubeSrc !== iframeSrc) {
        videoIframe.setAttribute('src', normalizedYoutubeSrc);
      }

      const stopProgressTimer = () => {
        if (progressTimer > 0) {
          window.clearInterval(progressTimer);
          progressTimer = 0;
        }
      };

      const startProgressTimer = () => {
        if (progressTimer > 0) return;
        progressTimer = window.setInterval(() => {
          sendYoutubeProgress(false).catch(() => {});
        }, 30000);
      };

      const sendYoutubeProgress = async (forceComplete = false) => {
        if (completionHandled && !forceComplete) return;
        if (syncInFlight) return;
        const currentRaw = ytPlayer && typeof ytPlayer.getCurrentTime === 'function'
          ? Number(ytPlayer.getCurrentTime())
          : 0;
        const durationRaw = ytPlayer && typeof ytPlayer.getDuration === 'function'
          ? Number(ytPlayer.getDuration())
          : 0;

        const current = Number.isFinite(currentRaw) ? Math.max(0, currentRaw) : 0;
        const duration = Number.isFinite(durationRaw) ? Math.max(0, durationRaw) : 0;
        const percent = duration > 0 ? Math.min(100, Math.floor((current / duration) * 100)) : 0;
        const reachedMinimum = percent >= minPercent;
        const shouldUnlock = !completionHandled && !criteriaUnlocked && reachedMinimum;
        const shouldCheckpoint = !shouldUnlock
          && !checkpointSent
          && resumeCheckpointPercent > 0
          && percent >= resumeCheckpointPercent;
        if (!forceComplete && !shouldUnlock && !shouldCheckpoint) return;
        if (shouldCheckpoint) checkpointSent = true;
        if (shouldUnlock) {
          criteriaUnlocked = true;
          setMarkDoneAvailability(true, '');
        }

        const currentSeconds = Math.floor(current);
        const deltaSeconds = Math.max(0, currentSeconds - lastPosition);
        rememberManualCompletionProgress(percent, currentSeconds, deltaSeconds);
        lastPosition = currentSeconds;

        syncInFlight = true;
        try {
          await post('/api/progress/update', {
            course_id: courseId,
            node_id: nodeId,
            status: 'in_progress',
            percent: percent,
            seconds: deltaSeconds,
            position: currentSeconds
          });
        } finally {
          syncInFlight = false;
        }
      };

      const onYoutubeStateChange = (event) => {
        const state = Number(event?.data ?? -1);
        const PLAYER_STATE = window.YT?.PlayerState || {};

        if (state === PLAYER_STATE.PLAYING) {
          setMobileVideoToggleState(false);
          startProgressTimer();
          return;
        }

        if (state === PLAYER_STATE.PAUSED || state === PLAYER_STATE.BUFFERING) {
          setMobileVideoToggleState(true);
          sendYoutubeProgress(false).catch(() => {});
          stopProgressTimer();
          return;
        }

        if (state === PLAYER_STATE.ENDED) {
          setMobileVideoToggleState(true);
          stopProgressTimer();
          sendYoutubeProgress(true)
            .catch((error) => {
              if (window.Swal) {
                Swal.fire({ icon: 'error', title: 'Erro ao salvar progresso', text: String(error.message || error) });
              }
            });
          return;
        }

        if (state === PLAYER_STATE.UNSTARTED || state === PLAYER_STATE.CUED) {
          setMobileVideoToggleState(true);
          stopProgressTimer();
        }
      };

      const initYoutubePlayer = () => {
        if (!window.YT || typeof window.YT.Player !== 'function') return;
        if (ytPlayer) return;

        ytPlayer = new window.YT.Player(videoIframe, {
          events: {
            onReady: () => {
              try {
                const duration = Number(ytPlayer.getDuration?.() || 0);
                if (resumeAt > 5 && duration > 10 && resumeAt < (duration - 5)) {
                  ytPlayer.seekTo(resumeAt, true);
                }
              } catch (_error) {
                // Ignora falha de seek sem bloquear o player.
              }
            },
            onStateChange: onYoutubeStateChange
          }
        });
      };

      if (window.YT && typeof window.YT.Player === 'function') {
        initYoutubePlayer();
      } else {
        const queueKey = '__appV3YoutubeReadyQueue';
        const loadedKey = '__appV3YoutubeApiRequested';
        const readyQueue = Array.isArray(window[queueKey]) ? window[queueKey] : [];
        readyQueue.push(initYoutubePlayer);
        window[queueKey] = readyQueue;

        if (!window[loadedKey]) {
          window[loadedKey] = true;
          const previousReady = window.onYouTubeIframeAPIReady;
          window.onYouTubeIframeAPIReady = () => {
            if (typeof previousReady === 'function') {
              try {
                previousReady();
              } catch (_error) {
                // Ignora erros de handlers antigos.
              }
            }
            const callbacks = Array.isArray(window[queueKey]) ? window[queueKey] : [];
            while (callbacks.length > 0) {
              const callback = callbacks.shift();
              if (typeof callback === 'function') {
                try {
                  callback();
                } catch (_error) {
                  // Ignora callback com erro para nao quebrar a fila.
                }
              }
            }
          };

          const script = document.createElement('script');
          script.src = 'https://www.youtube.com/iframe_api';
          script.async = true;
          script.defer = true;
          document.head.appendChild(script);
        }
      }

      window.addEventListener('beforeunload', () => {
        sendYoutubeProgress(false).catch(() => {});
        stopProgressTimer();
      }, { once: true });

      bindMobileVideoToggle(() => {
        if (!ytPlayer) {
          if (window.showToast) {
            window.showToast('Carregando player, tente novamente em alguns segundos.', 'info');
          }
          return;
        }
        const isPaused = String(mobileVideoToggleBtn?.dataset.paused || '1') === '1';
        try {
          if (isPaused && typeof ytPlayer.playVideo === 'function') {
            ytPlayer.playVideo();
            setMobileVideoToggleState(false);
          } else if (!isPaused && typeof ytPlayer.pauseVideo === 'function') {
            ytPlayer.pauseVideo();
            setMobileVideoToggleState(true);
          }
        } catch (_error) {
          if (window.showToast) {
            window.showToast('Nao foi possivel controlar o video agora.', 'error');
          }
        }
      });
    } else {
      bindMobileVideoToggle(() => {
        const target = videoIframe.contentWindow;
        const isPaused = String(mobileVideoToggleBtn?.dataset.paused || '1') === '1';
        const command = isPaused ? 'play' : 'pause';
        if (target) {
          try {
            target.postMessage({ type: command }, '*');
            target.postMessage({ action: command }, '*');
            target.postMessage({ event: 'command', func: isPaused ? 'playVideo' : 'pauseVideo', args: '' }, '*');
          } catch (_error) {
            // Sem efeito para providers sem suporte.
          }
        }
        setMobileVideoToggleState(!isPaused);
      });
    }
  }

  const appLessonNative = document.getElementById('appLessonNative');
  if (appLessonNative && post) {
    const pageLabel = document.getElementById('appLessonPageLabel');
    const progressLabel = document.getElementById('appLessonProgressLabel');
    const alertBox = document.getElementById('appLessonAlert');
    const contentWrap = document.getElementById('appLessonContent');

    const courseId = parseInt(String(appLessonNative.getAttribute('data-course-id') || '0'), 10) || 0;
    const nodeId = parseInt(String(appLessonNative.getAttribute('data-node-id') || '0'), 10) || 0;
    const cmid = parseInt(String(appLessonNative.getAttribute('data-cmid') || '0'), 10) || 0;

    let lessonId = 0;
    let currentPageId = 0;
    let lessonPassword = '';
    let loading = false;
    let finishing = false;
    let lessonEntry = null;
    let lastProcessMeta = null;

    const escapeHtml = (value) => {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    };

    const syncLessonProgress = async (status, percent) => {
      const safeStatus = String(status || '').trim();
      if (safeStatus === '') return;
      const safePercent = Math.max(0, Math.min(100, parseInt(String(percent || '0'), 10) || 0));
      try {
        await post('/api/progress/update', {
          course_id: courseId,
          node_id: nodeId,
          status: safeStatus,
          percent: safePercent,
          seconds: 0,
          position: Math.max(0, currentPageId)
        });
      } catch (_error) {
        // Nao bloqueia a navegacao da licao em caso de erro de telemetria.
      }
    };

    const setAlert = (type, message) => {
      if (!alertBox) return;
      const text = String(message || '').trim();
      if (text === '') {
        alertBox.innerHTML = '';
        return;
      }
      const klass = type === 'error'
        ? 'alert-danger'
        : (type === 'success' ? 'alert-success' : 'alert-info');
      alertBox.innerHTML = `<div class="alert ${klass} py-2 mb-2">${text}</div>`;
    };

    const setLoading = (value) => {
      loading = !!value;
      appLessonNative.classList.toggle('is-loading', loading);
      if (!contentWrap) return;
      contentWrap.querySelectorAll('button, input, select, textarea').forEach((el) => {
        el.disabled = loading;
      });
    };

    const sanitizeLessonHtml = (html) => {
      return String(html || '').replace(/<script[\s\S]*?<\/script>/gi, '');
    };

    const requestLessonPassword = async () => {
      if (window.Swal) {
        const result = await Swal.fire({
          title: 'Senha da licao',
          text: 'Esta licao exige validacao por senha.',
          input: 'password',
          inputPlaceholder: 'Digite a senha',
          inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off',
            autocomplete: 'current-password'
          },
          confirmButtonText: 'Continuar',
          showCancelButton: true,
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        return result.isConfirmed ? String(result.value || '').trim() : '';
      }
      return String(window.prompt('Digite a senha da licao:') || '').trim();
    };

    const collectLessonData = (form, submitter) => {
      let fd;
      try {
        fd = submitter ? new FormData(form, submitter) : new FormData(form);
      } catch (_error) {
        fd = new FormData(form);
      }
      if (submitter && submitter.name && !fd.has(submitter.name)) {
        fd.append(submitter.name, submitter.value || '1');
      }
      const data = [];
      for (const [name, value] of fd.entries()) {
        if (!name) continue;
        if (value instanceof File) continue;
        data.push({ name, value: String(value ?? '') });
      }
      return data;
    };

    const isControlVisible = (el) => {
      if (!el) return false;
      if ('disabled' in el && el.disabled) return false;
      const style = window.getComputedStyle ? window.getComputedStyle(el) : null;
      if (style && (style.display === 'none' || style.visibility === 'hidden')) return false;
      if (typeof HTMLInputElement !== 'undefined' && el instanceof HTMLInputElement && el.type === 'hidden') return false;
      if (el.offsetParent !== null) return true;
      return !!(style && style.position === 'fixed');
    };

    const lessonHtmlHasSubmitControl = (html) => {
      const source = String(html || '');
      if (source === '') return false;
      return /<button\b[^>]*(?:type\s*=\s*["']?submit["']?|name\s*=\s*["']submitbutton["'])/i.test(source)
        || /<input\b[^>]*(?:type\s*=\s*["']?submit["']?|name\s*=\s*["']submitbutton["'])/i.test(source)
        || /<button\b(?![^>]*\btype\s*=)[^>]*>/i.test(source);
    };

    const lessonHtmlHasNavigationLink = (html) => {
      const source = String(html || '');
      if (source === '') return false;
      return /<a\b[^>]*href=["'][^"']*(?:pageid=|\/mod\/lesson\/)/i.test(source);
    };

    const resolveLessonAutoAction = (processMeta, payload, activePageId) => {
      if (!processMeta || typeof processMeta !== 'object') return null;
      if (!payload || typeof payload !== 'object') return null;

      const attemptsRemainingRaw = Number(processMeta.attemptsremaining);
      const hasAttemptsInfo = Number.isFinite(attemptsRemainingRaw);
      const attemptsRemaining = hasAttemptsInfo ? Math.max(0, parseInt(String(attemptsRemainingRaw), 10) || 0) : null;
      const terminalPageResult = attemptsRemaining === 0 && (processMeta.correctanswer || processMeta.maxattemptsreached);
      if (!terminalPageResult) return null;

      const payloadPageId = parseInt(String(payload.page_id ?? 0), 10) || 0;
      const payloadNextPageId = parseInt(String(payload.next_page_id ?? 0), 10) || 0;
      const payloadHtml = String(payload.page_html || '');
      const hasNativeControls = lessonHtmlHasSubmitControl(payloadHtml) || lessonHtmlHasNavigationLink(payloadHtml);
      if (hasNativeControls) return null;

      if (payloadNextPageId > 0 && payloadNextPageId !== payloadPageId) {
        return { type: 'next', pageId: payloadNextPageId };
      }
      if (payloadPageId > 0 && payloadPageId !== activePageId) {
        return { type: 'next', pageId: payloadPageId };
      }

      return { type: 'finish' };
    };

    const renderEntry = (entry) => {
      if (!contentWrap) return;
      lessonEntry = entry && typeof entry === 'object' ? entry : {};
      lastProcessMeta = null;
      const attempts = parseInt(String(entry?.attempts_count ?? 0), 10) || 0;
      const lastPage = parseInt(String(entry?.last_page_seen ?? 0), 10) || 0;
      const firstPage = parseInt(String(entry?.first_page_id ?? 0), 10) || 0;
      const leftTimed = !!entry?.left_during_timed_session;
      const requiresPassword = !!entry?.requires_password;
      const reviewMode = !!entry?.review_mode;
      const reasons = Array.isArray(entry?.prevent_reasons) ? entry.prevent_reasons : [];
      const hint = reasons.length > 0 ? String(reasons[0]) : '';

      if (pageLabel) pageLabel.textContent = 'Licao';
      if (progressLabel) progressLabel.textContent = '--';

      const canContinue = leftTimed && lastPage > 0;
      const startLabel = canContinue ? 'Continuar de onde parou' : 'Iniciar licao';
      const helpText = canContinue
        ? `Ultima pagina registrada: ${lastPage}`
        : (firstPage > 0 ? `Primeira pagina: ${firstPage}` : 'Licao pronta para iniciar');
      const blockedByRules = reasons.length > 0 && !requiresPassword;

      contentWrap.innerHTML = `
        <section class="lesson-native__entry">
          <h5 class="lesson-native__entry-title">Exercicio interativo</h5>
          <div class="lesson-native__chips">
            <span class="lesson-native__chip"><i class="fa-solid fa-list-check"></i> ${attempts} tentativas</span>
            ${canContinue ? '<span class="lesson-native__chip is-live"><i class="fa-solid fa-clock-rotate-left"></i> Em andamento</span>' : ''}
            ${requiresPassword ? '<span class="lesson-native__chip"><i class="fa-solid fa-lock"></i> Com senha</span>' : ''}
            ${reviewMode ? '<span class="lesson-native__chip"><i class="fa-solid fa-arrows-rotate"></i> Modo revisao</span>' : ''}
          </div>
          <div class="lesson-native__entry-text">${helpText}</div>
          ${hint !== '' ? `<div class="lesson-native__entry-warning">${hint}</div>` : ''}
          <div class="lesson-native__entry-actions">
            <button type="button" class="btn btn-primary" id="appLessonStart" ${blockedByRules ? 'disabled' : ''}>${startLabel}</button>
          </div>
        </section>
      `;

      const startButton = document.getElementById('appLessonStart');
      startButton?.addEventListener('click', () => {
        if (loading) return;
        loadLessonSession({ autostart: true });
      });
    };

    const renderLessonPage = async (payload) => {
      if (!contentWrap) return;
      const pageId = parseInt(String(payload?.page_id ?? 0), 10) || 0;
      const progress = parseInt(String(payload?.progress ?? 0), 10) || 0;
      const title = String(payload?.page_title || '').trim();
      const type = String(payload?.page_type || '').trim();
      const html = sanitizeLessonHtml(payload?.page_html || '');
      const isEol = !!payload?.is_eol;
      const prevPageId = parseInt(String(payload?.prev_page_id ?? 0), 10) || 0;
      const nextPageId = parseInt(String(payload?.next_page_id ?? 0), 10) || 0;

      currentPageId = pageId;
      if (pageLabel) {
        const label = title !== '' ? title : (pageId > 0 ? `Pagina ${pageId}` : 'Licao');
        pageLabel.textContent = type !== '' ? `${label} (${type})` : label;
      }
      if (progressLabel) progressLabel.textContent = `${Math.max(0, Math.min(100, progress))}%`;

      if (isEol) {
        contentWrap.innerHTML = '<div class="quiz-app-loading">Finalizando tentativa...</div>';
        await finishLesson();
        return;
      }

      const score = String(payload?.ongoing_score || '').trim();
      const answerCount = parseInt(String(payload?.answer_count ?? 0), 10) || 0;
      const pageMessages = Array.isArray(payload?.messages) ? payload.messages : [];
      const displayMenu = !!payload?.displaymenu;
      const scoreHtml = score !== '' ? `<div class="lesson-native__score">${score}</div>` : '';
      const pageMetaChips = [];
      if (type !== '') {
        pageMetaChips.push(`<span class="lesson-native__page-chip"><i class="fa-solid fa-tag"></i>${escapeHtml(type)}</span>`);
      }
      if (answerCount > 0) {
        pageMetaChips.push(`<span class="lesson-native__page-chip"><i class="fa-regular fa-circle-dot"></i>${answerCount} respostas</span>`);
      }
      if (lessonEntry && parseInt(String(lessonEntry.attempts_count ?? 0), 10) > 0) {
        pageMetaChips.push(`<span class="lesson-native__page-chip"><i class="fa-solid fa-list-check"></i>${parseInt(String(lessonEntry.attempts_count ?? 0), 10)} tentativas</span>`);
      }
      if (displayMenu) {
        pageMetaChips.push('<span class="lesson-native__page-chip"><i class="fa-solid fa-bars"></i>Menu habilitado</span>');
      }
      const pageMetaHtml = pageMetaChips.length > 0
        ? `<div class="lesson-native__page-meta">${pageMetaChips.join('')}</div>`
        : '';

      const processChips = [];
      if (lastProcessMeta && typeof lastProcessMeta === 'object') {
        if (lastProcessMeta.correctanswer) {
          processChips.push('<span class="lesson-native__process-chip is-ok"><i class="fa-solid fa-check"></i>Resposta correta</span>');
        } else if (lastProcessMeta.noanswer) {
          processChips.push('<span class="lesson-native__process-chip is-warn"><i class="fa-solid fa-circle-exclamation"></i>Selecione uma resposta</span>');
        } else if (lastProcessMeta.maxattemptsreached) {
          processChips.push('<span class="lesson-native__process-chip is-warn"><i class="fa-solid fa-triangle-exclamation"></i>Limite de tentativas atingido</span>');
        }

        const attemptsRemaining = Number(lastProcessMeta.attemptsremaining);
        if (Number.isFinite(attemptsRemaining) && attemptsRemaining >= 0) {
          processChips.push(`<span class="lesson-native__process-chip"><i class="fa-solid fa-repeat"></i>Tentativas restantes: ${attemptsRemaining}</span>`);
        }
      }
      const processMetaHtml = processChips.length > 0
        ? `<div class="lesson-native__process">${processChips.join('')}</div>`
        : '';

      contentWrap.innerHTML = `
        <section class="lesson-native__page">
          ${pageMetaHtml}
          ${processMetaHtml}
          ${scoreHtml}
          <div class="lesson-native__page-html">${html || '<div class="alert alert-warning mb-0">Pagina sem conteudo.</div>'}</div>
          <div class="lesson-native__actionbar">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="appLessonPrev" ${prevPageId > 0 ? '' : 'disabled'}>
              <i class="fa-solid fa-arrow-left me-1"></i> Anterior
            </button>
            <button type="button" class="btn btn-primary btn-sm" id="appLessonPrimary">
              Continuar <i class="fa-solid fa-arrow-right ms-1"></i>
            </button>
          </div>
        </section>
      `;

      contentWrap.querySelectorAll('form').forEach((form) => {
        form.setAttribute('action', '#');
        form.removeAttribute('target');
      });

      const htmlRoot = contentWrap.querySelector('.lesson-native__page-html');
      const firstForm = htmlRoot ? htmlRoot.querySelector('form') : null;
      const hasForm = !!firstForm;
      const submitCandidates = firstForm
        ? Array.from(firstForm.querySelectorAll(
            'button[type="submit"], input[type="submit"], button:not([type]), button[name="submitbutton"], input[name="submitbutton"]'
          ))
        : [];
      const hasNativeSubmit = submitCandidates.some((candidate) => isControlVisible(candidate));
      const navCandidates = htmlRoot
        ? Array.from(htmlRoot.querySelectorAll('a[href*="pageid="], a[href*="/mod/lesson/"]'))
        : [];
      const hasLessonNavLinks = navCandidates.some((candidate) => isControlVisible(candidate));
      const needsFallbackPrimary = !hasNativeSubmit && !hasLessonNavLinks;
      const actionBar = contentWrap.querySelector('.lesson-native__actionbar');

      const primaryBtn = document.getElementById('appLessonPrimary');
      if (primaryBtn) {
        if (!needsFallbackPrimary) {
          primaryBtn.classList.add('d-none');
        } else {
          primaryBtn.classList.remove('d-none');
          primaryBtn.innerHTML = hasForm
            ? 'Responder e avancar <i class="fa-solid fa-arrow-right ms-1"></i>'
            : 'Avancar <i class="fa-solid fa-arrow-right ms-1"></i>';
        }
      }

      if (actionBar && prevPageId <= 0 && !needsFallbackPrimary) {
        actionBar.classList.add('d-none');
      } else if (actionBar) {
        actionBar.classList.remove('d-none');
      }

      if (needsFallbackPrimary) {
        primaryBtn?.addEventListener('click', () => {
          if (loading || currentPageId <= 0) return;
          if (firstForm) {
            if (typeof firstForm.requestSubmit === 'function') {
              firstForm.requestSubmit();
            } else {
              firstForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
            return;
          }
          if (nextPageId > 0 && nextPageId !== currentPageId) {
            loadLessonPage(nextPageId);
            return;
          }
          const attemptsRemaining = Number(lastProcessMeta?.attemptsremaining);
          const canForceFinish = Number.isFinite(attemptsRemaining)
            && attemptsRemaining <= 0
            && (lastProcessMeta?.correctanswer || lastProcessMeta?.maxattemptsreached);
          if (canForceFinish) {
            finishLesson();
            return;
          }
          processLessonPage([]);
        });
      }

      const prevBtn = document.getElementById('appLessonPrev');
      prevBtn?.addEventListener('click', () => {
        if (loading || prevPageId <= 0) return;
        loadLessonPage(prevPageId);
      });

      if (pageMessages.length > 0 && !lastProcessMeta) {
        const topPageMessage = String(pageMessages[0] || '').trim();
        if (topPageMessage !== '') {
          setAlert('info', topPageMessage);
        }
      }

      await syncLessonProgress('in_progress', progress);
    };

    const finishLesson = async () => {
      if (finishing || lessonId <= 0) return;
      finishing = true;
      setLoading(true);
      try {
        const json = await post('/api/lesson/finish', {
          course_id: courseId,
          node_id: nodeId,
          cmid,
          lesson_id: lessonId,
          lesson_password: lessonPassword
        });

        const lesson = json && typeof json === 'object' ? (json.lesson || {}) : {};
        const completion = lesson && typeof lesson === 'object' && lesson.completion && typeof lesson.completion === 'object'
          ? lesson.completion
          : {};
        const completionMet = !!completion.met;

        if (completionMet) {
          try {
            await post('/api/progress/update', {
              course_id: courseId,
              node_id: nodeId,
              status: 'completed',
              percent: 100,
              seconds: 0,
              position: 0
            });
            setCurrentItemAsCompletedUI();
          } catch (_progressError) {
            // Mantem feedback da licao mesmo se o sync local falhar.
          }
        }

        const gradeValue = lesson.grade;
        const hasGrade = gradeValue !== null && gradeValue !== undefined && Number.isFinite(Number(gradeValue));
        const gradeText = hasGrade
          ? Number(gradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : '';

        if (pageLabel) pageLabel.textContent = completionMet ? 'Atividade concluida' : 'Resultado da licao';
        if (progressLabel) progressLabel.textContent = completionMet ? '100%' : progressLabel.textContent;

        const resultTitle = completionMet ? 'Voce terminou esta atividade' : 'Tentativa finalizada';
        const resultText = completionMet
          ? 'Voce terminou esta atividade. O exercicio foi concluido.'
          : 'A tentativa foi finalizada, mas os criterios de conclusao do Moodle ainda nao foram atendidos.';
        const retryLabel = completionMet ? 'Abrir novamente' : 'Tentar novamente';

        if (contentWrap) {
          contentWrap.innerHTML = `
            <section class="lesson-native__result ${completionMet ? 'is-success' : 'is-info'}">
              <h5>${resultTitle}</h5>
              ${hasGrade ? `<div class="lesson-native__result-grade">Nota: <strong>${gradeText}</strong></div>` : ''}
              <div class="lesson-native__result-text">
                ${resultText}
              </div>
              <div class="lesson-native__entry-actions">
                <button type="button" class="btn btn-outline-secondary" id="appLessonRestart">${retryLabel}</button>
                <button type="button" class="btn btn-primary" id="appLessonBackToCourse">Voltar ao conteudo</button>
              </div>
            </section>
          `;
        }

        setAlert(completionMet ? 'success' : 'info', completionMet
          ? 'Voce terminou esta atividade. O exercicio foi concluido.'
          : 'Exercicio finalizado. Voce pode tentar novamente.');

        const restartBtn = document.getElementById('appLessonRestart');
        restartBtn?.addEventListener('click', () => {
          if (loading) return;
          loadLessonSession({ autostart: false });
        });
        const backBtn = document.getElementById('appLessonBackToCourse');
        backBtn?.addEventListener('click', () => {
          window.location.href = window.location.href;
        });
      } catch (error) {
        setAlert('error', String(error.message || error));
      } finally {
        finishing = false;
        setLoading(false);
      }
    };

    const processLessonPage = async (data) => {
      if (loading || lessonId <= 0 || currentPageId <= 0) return;
      setLoading(true);
      try {
        const json = await post('/api/lesson/page', {
          lesson_id: lessonId,
          page_id: currentPageId,
          data: JSON.stringify(Array.isArray(data) ? data : []),
          lesson_password: lessonPassword
        });

        const process = json && typeof json === 'object' ? (json.process || {}) : {};
        const processMessages = Array.isArray(process.messages) ? process.messages : [];
        const feedback = String(process.feedback || '').trim();
        const attemptsRemaining = Number.isFinite(Number(process.attemptsremaining))
          ? Math.max(0, parseInt(String(process.attemptsremaining), 10) || 0)
          : null;
        lastProcessMeta = {
          correctanswer: !!process.correctanswer,
          noanswer: !!process.noanswer,
          maxattemptsreached: !!process.maxattemptsreached,
          attemptsremaining: attemptsRemaining
        };

        const messageParts = [];
        if (feedback !== '') {
          messageParts.push(feedback);
        } else if (processMessages.length > 0) {
          messageParts.push(String(processMessages[0] || '').trim());
        }
        if (attemptsRemaining !== null) {
          messageParts.push(`Tentativas restantes nesta pagina: ${attemptsRemaining}.`);
        }
        if (!!process.maxattemptsreached) {
          messageParts.push('Voce atingiu o limite de tentativas nesta pagina.');
        }
        const topMessage = messageParts.join(' ').trim();
        setAlert(topMessage ? 'info' : '', topMessage);

        if (json.finished) {
          await finishLesson();
          return;
        }

        const payload = json && typeof json === 'object' ? json.lesson : null;
        if (!payload || typeof payload !== 'object') {
          throw new Error('Nao foi possivel carregar a proxima pagina da licao.');
        }
        const autoAction = resolveLessonAutoAction(lastProcessMeta, payload, currentPageId);
        await renderLessonPage(payload);
        if (autoAction) {
          const runAutoAction = () => {
            if (loading) {
              window.setTimeout(runAutoAction, 80);
              return;
            }
            if (autoAction.type === 'next' && autoAction.pageId > 0) {
              loadLessonPage(autoAction.pageId);
              return;
            }
            finishLesson();
          };
          window.setTimeout(runAutoAction, 80);
        }
      } catch (error) {
        setAlert('error', String(error.message || error));
      } finally {
        setLoading(false);
      }
    };

    const loadLessonPage = async (pageId) => {
      const safePageId = parseInt(String(pageId || '0'), 10) || 0;
      if (loading || lessonId <= 0 || safePageId <= 0) return;
      lastProcessMeta = null;
      setLoading(true);
      try {
        const json = await post('/api/lesson/page/view', {
          lesson_id: lessonId,
          page_id: safePageId,
          lesson_password: lessonPassword
        });
        const payload = json && typeof json === 'object' ? json.lesson : null;
        if (!payload || typeof payload !== 'object') {
          throw new Error('Nao foi possivel carregar a pagina solicitada da licao.');
        }
        await renderLessonPage(payload);
      } catch (error) {
        setAlert('error', String(error.message || error));
      } finally {
        setLoading(false);
      }
    };

    contentWrap?.addEventListener('submit', (event) => {
      const form = event.target.closest('form');
      if (!form) return;
      event.preventDefault();
      if (loading) return;
      const submitter = event.submitter || document.activeElement;
      const data = collectLessonData(form, submitter);
      processLessonPage(data);
    });

    contentWrap?.addEventListener('click', (event) => {
      const anchor = event.target.closest('a[href]');
      if (!anchor) return;
      const href = String(anchor.getAttribute('href') || '').trim();
      if (href === '' || href.startsWith('#')) return;

      let url;
      try {
        url = new URL(href, window.location.href);
      } catch (_error) {
        return;
      }

      const isLessonUrl = url.pathname.includes('/mod/lesson/');
      const pageIdParam = parseInt(String(url.searchParams.get('pageid') || '0'), 10) || 0;
      if (!isLessonUrl || pageIdParam <= 0) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener');
        return;
      }

      event.preventDefault();
      if (loading) return;
      loadLessonPage(pageIdParam);
    });

    const loadLessonSession = async (options = {}) => {
      const autostart = !!options.autostart;
      if (typeof options.password === 'string') {
        lessonPassword = options.password.trim();
      }
      lastProcessMeta = null;
      setLoading(true);
      setAlert('', '');
      if (contentWrap) {
        contentWrap.innerHTML = '<div class="quiz-app-loading">Carregando exercicio...</div>';
      }
      try {
        const json = await post('/api/lesson/session', {
          course_id: courseId,
          node_id: nodeId,
          cmid,
          lesson_password: lessonPassword,
          autostart: autostart ? 1 : 0
        });
        const entry = json && typeof json === 'object' ? (json.entry || {}) : {};
        lessonEntry = entry && typeof entry === 'object' ? entry : null;
        lessonId = parseInt(String(entry.lesson_id || (json.lesson && json.lesson.lesson_id) || '0'), 10) || 0;

        if (!autostart) {
          renderEntry(entry);
          return;
        }

        const payload = json && typeof json === 'object' ? json.lesson : null;
        if (!payload || typeof payload !== 'object') {
          throw new Error('A licao nao retornou conteudo para iniciar.');
        }
        await renderLessonPage(payload);
      } catch (error) {
        const payload = error && typeof error === 'object' ? error.payload : null;
        if (payload && payload.code === 'lesson_preflight_required') {
          setLoading(false);
          const informedPassword = await requestLessonPassword();
          if (informedPassword !== '') {
            await loadLessonSession({ autostart: true, password: informedPassword });
            return;
          }
        }
        if (contentWrap) contentWrap.innerHTML = '';
        setAlert('error', String(error.message || error));
      } finally {
        setLoading(false);
      }
    };

    loadLessonSession({ autostart: false });
  }

  const appLessonEmbed = document.getElementById('appLessonEmbed');
  if (appLessonEmbed && post) {
    const courseId = parseInt(String(appLessonEmbed.getAttribute('data-course-id') || '0'), 10) || 0;
    const nodeId = parseInt(String(appLessonEmbed.getAttribute('data-node-id') || '0'), 10) || 0;
    const cmid = parseInt(String(appLessonEmbed.getAttribute('data-cmid') || '0'), 10) || 0;
    const initiallyCompleted = String(appLessonEmbed.getAttribute('data-completed') || '0') === '1';
    const statusEl = document.getElementById('appLessonEmbedStatus');
    const alertEl = document.getElementById('appLessonEmbedAlert');
    const frameEl = document.getElementById('appLessonEmbedFrame');

    let pollTimer = 0;
    let shellTimer = 0;
    let inFlight = false;
    let completed = initiallyCompleted;

    const sanitizeLessonIframeShell = () => {
      if (!frameEl) return;
      try {
        const doc = frameEl.contentDocument || (frameEl.contentWindow ? frameEl.contentWindow.document : null);
        if (!doc || !doc.body) return;

        const hideSelectors = [
          '#page-header',
          '#header',
          '#topofscroll',
          '.navbar',
          '#nav-drawer',
          '.drawer',
          '.drawer-toggles',
          '.secondary-navigation',
          '.header-actions-container',
          '#region-main-settings-menu',
          '#page-footer'
        ];
        hideSelectors.forEach((selector) => {
          doc.querySelectorAll(selector).forEach((el) => {
            el.style.display = 'none';
          });
        });

        const main = doc.querySelector('#region-main') || doc.querySelector('[role="main"]') || doc.querySelector('main');
        if (main) {
          let node = main;
          while (node && node.parentElement) {
            const parent = node.parentElement;
            Array.from(parent.children).forEach((sibling) => {
              if (sibling !== node && sibling.tagName !== 'SCRIPT' && sibling.tagName !== 'STYLE' && sibling.tagName !== 'NOSCRIPT') {
                sibling.style.display = 'none';
              }
            });
            if (parent === doc.body || parent === doc.documentElement) {
              break;
            }
            node = parent;
          }
          main.style.maxWidth = '100%';
          main.style.margin = '0';
          main.style.padding = '0';
        }

        doc.body.style.margin = '0';
        doc.body.style.padding = '0';
        doc.body.style.background = '#fff';
      } catch (_error) {
        // Em cross-domain o browser bloqueia acesso ao iframe.
      }
    };

    const setStatus = (isDone, text = '') => {
      if (!statusEl) return;
      statusEl.classList.toggle('is-open', !isDone);
      statusEl.classList.toggle('is-done', !!isDone);
      if (text !== '') {
        statusEl.textContent = text;
        return;
      }
      statusEl.textContent = isDone ? 'Concluido' : 'Em andamento';
    };

    const setAlert = (type, message) => {
      if (!alertEl) return;
      const text = String(message || '').trim();
      if (text === '') {
        alertEl.innerHTML = '';
        return;
      }
      const klass = type === 'error'
        ? 'alert-danger'
        : (type === 'success' ? 'alert-success' : 'alert-info');
      alertEl.innerHTML = `<div class="alert ${klass} py-2 mb-2">${text}</div>`;
    };

    const syncLessonCompletion = async (showError = false) => {
      if (inFlight || courseId <= 0 || nodeId <= 0 || cmid <= 0) return;
      inFlight = true;
      try {
        const json = await post('/api/lesson/status', {
          course_id: courseId,
          node_id: nodeId,
          cmid
        });

        const isDone = !!(json && json.completed);
        const status = String(json?.status || '');
        if (isDone) {
          if (!completed) {
            completed = true;
            setCurrentItemAsCompletedUI();
            setAlert('success', 'Exercicio concluido com sucesso.');
          }
          setStatus(true, 'Concluido');
          return;
        }

        if (status === 'in_progress') {
          setStatus(false, 'Em andamento');
        } else {
          setStatus(false, 'Nao concluido');
        }
      } catch (error) {
        if (showError) {
          setAlert('error', String(error.message || error));
        }
      } finally {
        inFlight = false;
      }
    };

    const startPolling = () => {
      if (pollTimer > 0) return;
      pollTimer = window.setInterval(() => {
        syncLessonCompletion(false);
      }, 90000);
    };

    const startShellSanitizer = () => {
      if (shellTimer > 0) return;
      shellTimer = window.setInterval(() => {
        sanitizeLessonIframeShell();
      }, 1500);
    };

    const stopPolling = () => {
      if (pollTimer > 0) {
        window.clearInterval(pollTimer);
        pollTimer = 0;
      }
      if (shellTimer > 0) {
        window.clearInterval(shellTimer);
        shellTimer = 0;
      }
    };

    if (completed) {
      setStatus(true, 'Concluido');
    } else {
      setStatus(false, 'Em andamento');
    }

    frameEl?.addEventListener('load', () => {
      sanitizeLessonIframeShell();
      syncLessonCompletion(false);
    });

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        syncLessonCompletion(false);
      }
    });

    window.addEventListener('focus', () => {
      syncLessonCompletion(false);
    });

    window.addEventListener('beforeunload', () => {
      stopPolling();
    }, { once: true });

    syncLessonCompletion(false);
    startPolling();
    startShellSanitizer();
  }

  const appQuiz = document.getElementById('appQuiz');
  if (appQuiz) {
    const pageLabel = document.getElementById('appQuizPageLabel');
    const timerLabel = document.getElementById('appQuizTimer');
    const alertBox = document.getElementById('appQuizAlert');
    const questionsWrap = document.getElementById('appQuizQuestions');
    const form = document.getElementById('appQuizForm');
    const btnPrev = document.getElementById('appQuizPrev');
    const btnNext = document.getElementById('appQuizNext');
    const btnFinish = document.getElementById('appQuizFinish');
    const btnRetry = document.getElementById('appQuizRetry');
    const markDoneBtn = document.getElementById('btnMarkDone');

    const courseId = parseInt(String(appQuiz.getAttribute('data-course-id') || '0'), 10) || 0;
    const nodeId = parseInt(String(appQuiz.getAttribute('data-node-id') || '0'), 10) || 0;
    const cmid = parseInt(String(appQuiz.getAttribute('data-cmid') || '0'), 10) || 0;

    let attemptId = 0;
    let quizId = 0;
    let currentPage = 0;
    let nextPage = -1;
    let totalPages = 1;
    let endTime = 0;
    let timerRef = null;
    let loading = false;
    let quizPassword = '';
    let lastRenderedPage = -1;
    let sessionEntry = null;

    const setAlert = (type, message) => {
      if (!alertBox) return;
      const text = String(message || '').trim();
      if (text === '') {
        alertBox.innerHTML = '';
        return;
      }
      const klass = type === 'error'
        ? 'alert-danger'
        : (type === 'success' ? 'alert-success' : 'alert-info');
      alertBox.innerHTML = `<div class="alert ${klass} py-2 mb-2">${text}</div>`;
    };

    const updateTimer = () => {
      if (!timerLabel || endTime <= 0) return;
      const now = Math.floor(Date.now() / 1000);
      const remaining = Math.max(0, endTime - now);
      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;
      timerLabel.classList.remove('d-none');
      timerLabel.textContent = `Tempo restante: ${minutes}:${String(seconds).padStart(2, '0')}`;
      if (remaining <= 0 && btnFinish && !btnFinish.disabled) {
        btnFinish.click();
      }
    };

    const restartTimer = () => {
      if (timerRef) {
        window.clearInterval(timerRef);
        timerRef = null;
      }
      if (endTime > 0) {
        updateTimer();
        timerRef = window.setInterval(updateTimer, 1000);
      } else if (timerLabel) {
        timerLabel.classList.add('d-none');
      }
    };

    const collectAttemptData = () => {
      if (!form) return [];
      const formData = new FormData(form);
      const payload = [];

      for (const [name, value] of formData.entries()) {
        if (!name) continue;
        if (value instanceof File) continue;
        payload.push({ name, value: String(value ?? '') });
      }
      return payload;
    };

    const setLoading = (value) => {
      loading = value;
      const disabled = !!value;
      if (btnPrev) btnPrev.disabled = disabled || currentPage <= 0;
      if (btnNext) btnNext.disabled = disabled || nextPage < 0;
      if (btnFinish) btnFinish.disabled = disabled || nextPage >= 0;
      if (btnRetry) btnRetry.disabled = disabled;
      if (questionsWrap) questionsWrap.classList.toggle('is-loading', disabled);
    };

    const setRetryButton = (visible, disabled = false) => {
      if (!btnRetry) return;
      btnRetry.classList.toggle('d-none', !visible);
      btnRetry.disabled = !!disabled;
    };

    const renderQuiz = (quiz) => {
      attemptId = parseInt(String(quiz.attempt_id || '0'), 10) || 0;
      quizId = parseInt(String(quiz.quiz_id || '0'), 10) || 0;
      currentPage = parseInt(String(quiz.currentpage ?? quiz.page ?? 0), 10) || 0;
      nextPage = parseInt(String(quiz.nextpage ?? -1), 10);
      totalPages = parseInt(String(quiz.totalpages || 1), 10) || 1;
      endTime = parseInt(String(quiz.endtime || 0), 10) || 0;

      if (pageLabel) {
        const humanPage = Math.max(1, currentPage + 1);
        pageLabel.textContent = `Pagina ${humanPage} de ${Math.max(1, totalPages)}`;
      }

      if (btnNext) btnNext.classList.toggle('d-none', nextPage < 0);
      if (btnFinish) btnFinish.classList.toggle('d-none', nextPage >= 0);
      if (btnPrev) btnPrev.classList.remove('d-none');
      if (btnNext) btnNext.classList.remove('d-none');
      setRetryButton(false, true);

      const questions = Array.isArray(quiz.questions) ? quiz.questions : [];
      if (!questionsWrap) return;
      questionsWrap.classList.remove('is-review');

      if (!questions.length) {
        questionsWrap.innerHTML = '<div class="alert alert-warning mb-0">Nenhuma questao disponivel nesta pagina.</div>';
      } else {
        questionsWrap.innerHTML = questions.map((question) => {
          const html = String(question.html || '');
          return `<article class="quiz-app-question">${html}</article>`;
        }).join('');
      }

      if (lastRenderedPage !== currentPage) {
        lastRenderedPage = currentPage;
        if (questionsWrap) {
          questionsWrap.scrollTop = 0;
        }
        if (appQuiz && typeof appQuiz.scrollIntoView === 'function') {
          appQuiz.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      const messages = Array.isArray(quiz.messages) ? quiz.messages : [];
      const warnings = Array.isArray(quiz.warnings) ? quiz.warnings : [];
      const firstMessage = String(messages[0] || warnings[0]?.message || '').trim();
      setAlert(firstMessage ? 'info' : '', firstMessage);

      restartTimer();
      setLoading(false);
    };

    const renderReview = (review) => {
      if (!questionsWrap) return;

      const questions = Array.isArray(review?.questions) ? review.questions : [];
      const additional = Array.isArray(review?.additionaldata) ? review.additionaldata : [];
      const reviewGrade = review && review.grade !== undefined && review.grade !== null
        ? Number(review.grade)
        : null;

      if (pageLabel) {
        pageLabel.textContent = 'Resultado da tentativa';
      }

      if (timerRef) {
        window.clearInterval(timerRef);
        timerRef = null;
      }
      if (timerLabel) {
        timerLabel.classList.add('d-none');
      }

      const feedbackHtml = additional.map((item) => {
        const title = String(item?.title || '').trim();
        const content = String(item?.content || '').trim();
        if (!title && !content) return '';
        return `<article class="quiz-app-feedback">
          ${title ? `<h6 class="quiz-app-feedback__title">${title}</h6>` : ''}
          <div class="quiz-app-feedback__content">${content}</div>
        </article>`;
      }).join('');

      const questionsHtml = questions.map((question) => {
        const html = String(question?.html || '');
        return `<article class="quiz-app-question quiz-app-question--review">${html}</article>`;
      }).join('');

      const gradeHtml = reviewGrade !== null && Number.isFinite(reviewGrade)
        ? `<article class="quiz-app-feedback quiz-app-feedback--grade">
            <h6 class="quiz-app-feedback__title">Nota final</h6>
            <div class="quiz-app-feedback__content"><strong>${reviewGrade.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></div>
          </article>`
        : '';
      const closeButtonHtml = `
        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="appQuizReviewClose">
            <i class="fa-solid fa-xmark me-1"></i> Fechar
          </button>
        </div>
      `;

      questionsWrap.classList.add('is-review');
      questionsWrap.innerHTML = (`${gradeHtml}${feedbackHtml}${questionsHtml}` || '<div class="alert alert-info mb-0">Tentativa finalizada. O Moodle nao liberou revisao detalhada para este questionario.</div>') + closeButtonHtml;

      if (btnPrev) {
        btnPrev.disabled = true;
        btnPrev.classList.add('d-none');
      }
      if (btnNext) {
        btnNext.disabled = true;
        btnNext.classList.add('d-none');
      }
      if (btnFinish) {
        btnFinish.disabled = true;
        btnFinish.classList.add('d-none');
      }
      document.getElementById('appQuizReviewClose')?.addEventListener('click', () => {
        if (loading) return;
        loadSession({ autostart: false });
      });
      setLoading(false);
    };

    const renderReviewUnavailable = (message, gradeValue = null) => {
      if (!questionsWrap) return;

      if (pageLabel) {
        pageLabel.textContent = 'Tentativa concluida';
      }

      if (timerRef) {
        window.clearInterval(timerRef);
        timerRef = null;
      }
      if (timerLabel) {
        timerLabel.classList.add('d-none');
      }

      const hasGrade = gradeValue !== null && gradeValue !== undefined && Number.isFinite(Number(gradeValue));
      const gradeHtml = hasGrade
        ? `<article class="quiz-app-feedback quiz-app-feedback--grade">
            <h6 class="quiz-app-feedback__title">Nota final</h6>
            <div class="quiz-app-feedback__content"><strong>${Number(gradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></div>
          </article>`
        : '';

      const safeMessage = String(message || '').trim() || 'A revisao desta tentativa nao esta disponivel no Moodle para este questionario.';
      const closeButtonHtml = `
        <div class="d-flex justify-content-end mt-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="appQuizReviewClose">
            <i class="fa-solid fa-xmark me-1"></i> Fechar
          </button>
        </div>
      `;

      questionsWrap.classList.add('is-review');
      questionsWrap.innerHTML = `
        ${gradeHtml}
        <article class="quiz-app-feedback">
          <h6 class="quiz-app-feedback__title">Tentativa finalizada</h6>
          <div class="quiz-app-feedback__content">${safeMessage}</div>
        </article>
        ${closeButtonHtml}
      `;

      if (btnPrev) {
        btnPrev.disabled = true;
        btnPrev.classList.add('d-none');
      }
      if (btnNext) {
        btnNext.disabled = true;
        btnNext.classList.add('d-none');
      }
      if (btnFinish) {
        btnFinish.disabled = true;
        btnFinish.classList.add('d-none');
      }
      document.getElementById('appQuizReviewClose')?.addEventListener('click', () => {
        if (loading) return;
        loadSession({ autostart: false });
      });
      setLoading(false);
    };

    const showQuizResultDialog = async (options = {}) => {
      const completionMet = !!options.completionMet;
      const canRetry = !!options.canRetry;
      const passRequired = !!options.passRequired;
      const gradeText = options.gradeText !== null && options.gradeText !== undefined ? String(options.gradeText) : '';
      const passText = options.passText !== null && options.passText !== undefined ? String(options.passText) : '';
      const summaryText = String(options.summaryText || '').trim();
      const reviewEnabled = !!options.reviewEnabled;
      const reviewLabel = String(options.reviewLabel || 'Revisar agora').trim() || 'Revisar agora';

      if (!window.Swal) {
        return 'review';
      }

      const badgeLabel = String(options.statusLabel || (completionMet ? 'Concluido' : 'Pendente')).trim() || (completionMet ? 'Concluido' : 'Pendente');
      const badgeClass = String(options.statusClass || (completionMet ? 'is-success' : 'is-pending')).trim() || (completionMet ? 'is-success' : 'is-pending');
      const title = completionMet ? 'Resultado da avaliacao' : 'Resultado da tentativa';
      const gradeHtml = gradeText
        ? `<div class="study-swal-result__metric"><span class="study-swal-result__metric-label">Nota desta tentativa</span><strong class="study-swal-result__metric-value">${gradeText}</strong></div>`
        : '';
      const passHtml = passRequired && passText
        ? `<div class="study-swal-result__metric"><span class="study-swal-result__metric-label">Minima exigida</span><strong class="study-swal-result__metric-value">${passText}</strong></div>`
        : '';

      const result = await Swal.fire({
        icon: completionMet ? 'success' : 'info',
        title,
        html: `
          <div class="study-swal-result__panel">
            <span class="study-swal-result__badge ${badgeClass}">${badgeLabel}</span>
            <p class="study-swal-result__summary">${summaryText}</p>
            ${(gradeHtml || passHtml) ? `<div class="study-swal-result__metrics">${gradeHtml}${passHtml}</div>` : ''}
          </div>
        `,
        showConfirmButton: reviewEnabled,
        confirmButtonText: reviewLabel,
        showDenyButton: canRetry,
        denyButtonText: 'Tentar novamente',
        showCancelButton: true,
        cancelButtonText: 'Fechar',
        reverseButtons: true,
        buttonsStyling: false,
        allowOutsideClick: false,
        customClass: {
          popup: 'study-swal-result',
          icon: 'study-swal-result__icon',
          title: 'study-swal-result__title',
          htmlContainer: 'study-swal-result__text',
          actions: 'study-swal-result__actions',
          confirmButton: 'study-swal-result__btn study-swal-result__btn--primary',
          denyButton: 'study-swal-result__btn study-swal-result__btn--neutral',
          cancelButton: 'study-swal-result__btn study-swal-result__btn--ghost'
        }
      });

      if (result.isConfirmed && reviewEnabled) return 'review';
      if (result.isDenied && canRetry) return 'retry';
      return 'close';
    };

    const renderEntry = (entry) => {
      sessionEntry = entry && typeof entry === 'object' ? entry : {};
      const attemptsTotal = parseInt(String(sessionEntry.attempts_total ?? 0), 10) || 0;
      const finishedCount = parseInt(String(sessionEntry.finished_count ?? 0), 10) || 0;
      const unfinishedCount = parseInt(String(sessionEntry.unfinished_count ?? 0), 10) || 0;
      const lastFinishedAttemptId = parseInt(String(sessionEntry.last_finished_attempt_id ?? 0), 10) || 0;
      const maxAttemptsRaw = parseInt(String(sessionEntry.max_attempts ?? 0), 10) || 0;
      const hasLimit = maxAttemptsRaw > 0;
      const hasUnfinished = !!sessionEntry.has_unfinished;
      const canStart = !!sessionEntry.can_start;
      const passRequired = !!sessionEntry.pass_required;
      const completionMet = !!sessionEntry.completion_met;
      const lastAttemptGradeValue = sessionEntry.grade;
      const overallGradeValue = sessionEntry.overall_grade !== undefined ? sessionEntry.overall_grade : lastAttemptGradeValue;
      const gradeValue = completionMet && overallGradeValue !== null && overallGradeValue !== undefined
        ? overallGradeValue
        : lastAttemptGradeValue;
      const passGradeValue = sessionEntry.pass_grade;
      const preventReasons = Array.isArray(sessionEntry.prevent_reasons) ? sessionEntry.prevent_reasons : [];
      const quizName = String(sessionEntry.quiz_name || '').trim();
      const hasGrade = gradeValue !== null && gradeValue !== undefined && Number.isFinite(Number(gradeValue));
      const hasPassGrade = passGradeValue !== null && passGradeValue !== undefined && Number.isFinite(Number(passGradeValue));
      const gradeText = hasGrade
        ? Number(gradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : null;
      const passText = hasPassGrade
        ? Number(passGradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : null;

      if (pageLabel) {
        pageLabel.textContent = 'Questionario';
      }
      if (timerLabel) {
        timerLabel.classList.add('d-none');
      }
      if (btnPrev) btnPrev.classList.add('d-none');
      if (btnNext) btnNext.classList.add('d-none');
      if (btnFinish) btnFinish.classList.add('d-none');
      setRetryButton(false, true);

      const limitText = hasLimit
        ? `${finishedCount} de ${maxAttemptsRaw} tentativas concluidas`
        : `${finishedCount} tentativas concluidas`;
      const actionButtons = [];
      if (hasUnfinished) {
        actionButtons.push('<button type="button" class="btn btn-primary" id="appQuizContinueAttempt"><i class="fa-solid fa-play me-1"></i> Continuar de onde parou</button>');
      }
      if (lastFinishedAttemptId > 0) {
        actionButtons.push('<button type="button" class="btn btn-outline-primary" id="appQuizReviewAttempt"><i class="fa-solid fa-clipboard-check me-1"></i> Revisar ultima tentativa</button>');
      }
      if (canStart) {
        const startLabel = finishedCount > 0 ? 'Responder novamente' : 'Iniciar tentativa';
        const startIcon = finishedCount > 0 ? 'fa-rotate-right' : 'fa-circle-play';
        actionButtons.push(`<button type="button" class="btn btn-primary" id="appQuizStartAttempt"><i class="fa-solid ${startIcon} me-1"></i> ${startLabel}</button>`);
      }
      if (!actionButtons.length) {
        actionButtons.push('<button type="button" class="btn btn-outline-secondary" disabled><i class="fa-solid fa-lock me-1"></i> Tentativas bloqueadas</button>');
      }

      const reasonHtml = (!canStart && preventReasons.length > 0)
        ? `<div class="quiz-app-entry__reason">${preventReasons[0]}</div>`
        : '';
      const gradingHtml = passRequired && passText !== null
        ? `<div class="quiz-app-entry__reason">
            <i class="fa-solid fa-shield-halved me-1"></i>
            Este item so conclui ao atingir a nota minima de <strong>${passText}</strong>.
            ${gradeText !== null
              ? (completionMet
                ? ` Sua nota valida no Moodle e <strong>${gradeText}</strong>.`
                : ` Sua ultima nota foi <strong>${gradeText}</strong>.`)
              : ''}
          </div>`
        : '';

      if (questionsWrap) {
        questionsWrap.classList.remove('is-review');
        questionsWrap.innerHTML = `
          <section class="quiz-app-entry">
            ${quizName !== '' ? `<h5 class="quiz-app-entry__title">${quizName}</h5>` : ''}
            <div class="quiz-app-entry__meta">
              <span class="quiz-app-entry__chip"><i class="fa-solid fa-list-check"></i> ${attemptsTotal} tentativas registradas</span>
              <span class="quiz-app-entry__chip"><i class="fa-solid fa-flag-checkered"></i> ${limitText}</span>
              ${unfinishedCount > 0 ? `<span class="quiz-app-entry__chip is-live"><i class="fa-solid fa-hourglass-half"></i> ${unfinishedCount} em andamento</span>` : ''}
              ${finishedCount > 0 ? `<span class="quiz-app-entry__chip"><i class="fa-solid fa-circle-check"></i> ${finishedCount} concluida(s)</span>` : ''}
            </div>
            <div class="quiz-app-entry__actions">${actionButtons.join('')}</div>
            ${gradingHtml}
            ${reasonHtml}
          </section>
        `;
      }

      const startButton = document.getElementById('appQuizStartAttempt');
      if (startButton) {
        startButton.addEventListener('click', () => {
          if (loading) return;
          loadSession({ autostart: true });
        });
      }

      const continueButton = document.getElementById('appQuizContinueAttempt');
      if (continueButton) {
        continueButton.addEventListener('click', () => {
          if (loading) return;
          loadSession({ autostart: true });
        });
      }

      const reviewButton = document.getElementById('appQuizReviewAttempt');
      if (reviewButton) {
        reviewButton.addEventListener('click', async () => {
          if (loading || lastFinishedAttemptId <= 0) return;
          setLoading(true);
          setAlert('', '');
          try {
            const reviewJson = await quizPost('/api/quiz/review', {
              attempt_id: lastFinishedAttemptId,
              page: -1
            });
            if (reviewJson && reviewJson.review_available === false) {
              renderReviewUnavailable(reviewJson.message || '', reviewJson.review?.grade);
            } else {
              renderReview(reviewJson.review || {});
            }
          } catch (error) {
            setLoading(false);
            setAlert('error', String(error.message || error));
          }
        });
      }

      setLoading(false);
    };

    const quizPost = async (path, payload) => {
      const data = Object.assign({}, payload || {});
      if (Array.isArray(data.data)) {
        data.data = JSON.stringify(data.data);
      }
      const timeoutMs = 20000;
      return await Promise.race([
        post(path, data),
        new Promise((_, reject) => {
          window.setTimeout(() => {
            reject(new Error('A requisicao do questionario demorou demais. Tente novamente.'));
          }, timeoutMs);
        })
      ]);
    };

    const requestQuizPassword = async () => {
      if (window.Swal) {
        const result = await Swal.fire({
          title: 'Senha do questionario',
          text: 'Este questionario exige validacao por senha.',
          input: 'password',
          inputPlaceholder: 'Digite a senha',
          inputAttributes: {
            autocapitalize: 'off',
            autocorrect: 'off',
            autocomplete: 'current-password'
          },
          confirmButtonText: 'Continuar',
          showCancelButton: true,
          cancelButtonText: 'Cancelar',
          reverseButtons: true
        });
        return result.isConfirmed ? String(result.value || '').trim() : '';
      }
      return String(window.prompt('Digite a senha do questionario:') || '').trim();
    };

    const loadSession = async (options = {}) => {
      const autostart = !!options.autostart;
      if (typeof options.password === 'string') {
        quizPassword = options.password.trim();
      }
      setLoading(true);
      setRetryButton(false, true);
      setAlert('', '');
      if (questionsWrap) {
        questionsWrap.innerHTML = '<div class="quiz-app-loading">Carregando questionario...</div>';
      }
      try {
        const json = await quizPost('/api/quiz/session', {
          course_id: courseId,
          node_id: nodeId,
          cmid,
          quiz_password: quizPassword,
          autostart: autostart ? 1 : 0
        });
        const entry = json && typeof json === 'object' ? json.entry : null;
        if (!autostart && entry) {
          renderEntry(entry);
          return;
        }
        renderQuiz(json.quiz || {});
      } catch (error) {
        const payload = error && typeof error === 'object' ? error.payload : null;
        if (error && typeof console !== 'undefined' && typeof console.error === 'function') {
          console.error('[app_v3][quiz_session]', {
            status: error.status || null,
            message: error.message || '',
            payload: payload || null
          });
        }
        if (payload && payload.code === 'quiz_preflight_required') {
          setLoading(false);
          const informedPassword = await requestQuizPassword();
          if (informedPassword !== '') {
            await loadSession({ password: informedPassword, autostart: true });
            return;
          }
        }
        if (payload && payload.entry) {
          renderEntry(payload.entry);
          setAlert('info', String(error.message || error));
          return;
        }
        if (questionsWrap) questionsWrap.innerHTML = '';
        setLoading(false);
        setAlert('error', String(error.message || error));
      }
    };

    const goToPage = async (targetPage) => {
      if (loading || attemptId <= 0 || quizId <= 0) return;
      const safePage = Math.max(0, parseInt(String(targetPage || 0), 10) || 0);
      setLoading(true);
      setAlert('', '');
      try {
        const json = await quizPost('/api/quiz/page', {
          attempt_id: attemptId,
          page: safePage,
          data: collectAttemptData(),
          quiz_password: quizPassword
        });
        renderQuiz(json.quiz || {});
      } catch (error) {
        setLoading(false);
        setAlert('error', String(error.message || error));
      }
    };

    const finishAttempt = async () => {
      if (loading || attemptId <= 0 || quizId <= 0) return;
      setLoading(true);
      setAlert('info', 'Enviando respostas...');
      try {
        const json = await quizPost('/api/quiz/finish', {
          attempt_id: attemptId,
          course_id: courseId,
          node_id: nodeId,
          cmid,
          data: collectAttemptData(),
          quiz_password: quizPassword
        });
        const result = json.quiz || {};
        const state = String(result.state || '');
        if (state !== 'finished') {
          setLoading(false);
          setAlert('error', 'A tentativa ainda nao foi finalizada. Revise as respostas e tente novamente.');
          return;
        }

        const completion = (result && typeof result === 'object' && result.completion && typeof result.completion === 'object')
          ? result.completion
          : {};
        const completionMet = !!completion.met;
        const passRequired = !!completion.pass_required;
        const canRetry = !!completion.can_retry;
        const retryReasons = Array.isArray(completion.retry_reasons) ? completion.retry_reasons : [];
        const overallGradeValue = completion.overall_grade !== undefined
          ? completion.overall_grade
          : (result.overall_grade !== undefined ? result.overall_grade : (completion.grade !== undefined ? completion.grade : result.grade));
        const gradeValue = completion.attempt_grade !== undefined
          ? completion.attempt_grade
          : (result.attempt_grade !== undefined ? result.attempt_grade : overallGradeValue);
        const passGradeValue = completion.pass_grade;
        const hasGrade = gradeValue !== null && gradeValue !== undefined && Number.isFinite(Number(gradeValue));
        const hasPassGrade = passGradeValue !== null && passGradeValue !== undefined && Number.isFinite(Number(passGradeValue));
        const gradeText = hasGrade
          ? Number(gradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : null;
        const passText = hasPassGrade
          ? Number(passGradeValue).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : null;

        const attemptMeetsCriteria = passRequired && hasPassGrade
          ? (hasGrade && (Number(gradeValue) + 0.00001) >= Number(passGradeValue))
          : completionMet;
        const itemAlreadyCompleted = !!completionMet;
        const completedInPreviousAttempt = itemAlreadyCompleted && !attemptMeetsCriteria;
        let dialogStatusLabel = attemptMeetsCriteria ? 'Aprovado' : 'Pendente';
        let dialogStatusClass = attemptMeetsCriteria ? 'is-success' : 'is-pending';

        let resultTone = 'success';
        let resultMessage = gradeText
          ? `Tentativa finalizada com sucesso. Nota desta tentativa: ${gradeText}. Item concluido.`
          : 'Tentativa finalizada com sucesso. Item concluido.';

        if (attemptMeetsCriteria) {
          if (itemAlreadyCompleted) {
            try {
              await post('/api/progress/update', {
                course_id: courseId,
                node_id: nodeId,
                status: 'completed',
                percent: 100,
                seconds: 0,
                position: 0
              });
              setCurrentItemAsCompletedUI();
            } catch (_progressError) {
              // Mantem feedback de quiz finalizado mesmo que progresso local falhe.
            }
          } else if (passRequired && passText !== null) {
            resultTone = 'info';
            dialogStatusLabel = 'Aguardando sincronizacao';
            dialogStatusClass = 'is-pending';
            resultMessage = `Tentativa enviada. Nota desta tentativa: ${gradeText}. A nota minima foi atingida, mas o Moodle ainda nao confirmou a conclusao deste item.`;
          }
        } else {
          resultTone = 'info';
          resultMessage = 'Tentativa enviada, mas este item ainda nao foi concluido.';
          if (passRequired && passText !== null) {
            const current = gradeText !== null ? gradeText : '0,00';
            resultMessage = `Tentativa enviada. Nota desta tentativa: ${current}. Minima exigida para concluir: ${passText}.`;
          }
          if (completedInPreviousAttempt) {
            dialogStatusLabel = 'Abaixo da minima';
            resultMessage += ' O item permanece concluido porque voce ja atingiu a nota minima em uma tentativa anterior.';
          }
          if (!canRetry && retryReasons.length > 0) {
            resultMessage += ` ${retryReasons[0]}`;
          } else if (canRetry) {
            resultMessage += ' Use "Tentar novamente" para fazer outra tentativa.';
          }
        }

        let reviewKind = 'full';
        let reviewPayload = null;
        let reviewLabel = 'Revisar agora';

        try {
          const reviewJson = await quizPost('/api/quiz/review', {
            attempt_id: attemptId,
            page: -1
          });
          if (reviewJson && reviewJson.review_available === false) {
            reviewKind = 'unavailable';
            reviewLabel = 'Ver resultado';
            reviewPayload = {
              message: reviewJson.message || '',
              grade: reviewJson.review?.grade
            };
          } else {
            reviewPayload = reviewJson.review || {};
          }
        } catch (_reviewError) {
          reviewKind = 'unavailable';
          reviewLabel = 'Ver resultado';
          reviewPayload = {
            message: 'Tentativa finalizada. A revisao detalhada nao pode ser exibida agora.',
            grade: completion.attempt_grade ?? result.attempt_grade ?? overallGradeValue
          };
        }

        if (!window.Swal) {
          setAlert(resultTone, resultMessage);
          if (reviewKind === 'full') {
            renderReview(reviewPayload || {});
          } else {
            renderReviewUnavailable(reviewPayload?.message || '', reviewPayload?.grade);
          }
          if (!completionMet && canRetry) {
            setRetryButton(true, false);
          } else {
            setRetryButton(false, true);
          }
          if (completionMet && markDoneBtn) markDoneBtn.disabled = true;
          return;
        }

        setAlert('', '');
        setLoading(false);

        const dialogAction = await showQuizResultDialog({
          completionMet: attemptMeetsCriteria,
          canRetry,
          passRequired,
          gradeText,
          passText,
          statusLabel: dialogStatusLabel,
          statusClass: dialogStatusClass,
          summaryText: resultMessage,
          reviewEnabled: true,
          reviewLabel
        });

        if (dialogAction === 'retry') {
          setRetryButton(false, true);
          await loadSession({ autostart: true });
          return;
        }

        if (dialogAction === 'close') {
          setRetryButton(false, true);
          await loadSession({ autostart: false });
          return;
        }

        if (reviewKind === 'full') {
          renderReview(reviewPayload || {});
        } else {
          renderReviewUnavailable(reviewPayload?.message || '', reviewPayload?.grade);
        }
        if (!completionMet && canRetry) {
          setRetryButton(true, false);
        } else {
          setRetryButton(false, true);
        }
        if (completionMet && markDoneBtn) markDoneBtn.disabled = true;
      } catch (error) {
        setLoading(false);
        setAlert('error', String(error.message || error));
      }
    };

    btnPrev?.addEventListener('click', () => {
      if (currentPage <= 0) return;
      goToPage(currentPage - 1);
    });

    btnNext?.addEventListener('click', () => {
      if (nextPage < 0) return;
      goToPage(nextPage);
    });

    btnFinish?.addEventListener('click', () => {
      finishAttempt();
    });

    btnRetry?.addEventListener('click', () => {
      if (loading) return;
      setRetryButton(true, true);
      lastRenderedPage = -1;
      setAlert('info', 'Iniciando nova tentativa...');
      loadSession({ autostart: true });
    });

    loadSession({ autostart: false });
    window.addEventListener('beforeunload', () => {
      if (timerRef) {
        window.clearInterval(timerRef);
      }
    }, { once: true });
  }
})();



