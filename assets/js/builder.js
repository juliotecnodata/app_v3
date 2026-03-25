(() => {
  const boot = window.BUILDER_BOOT;
  if (!boot) return;

  const base = boot.base || '';
  const csrf = boot.csrf || '';
  const courseId = parseInt(String(boot.courseId || '0'), 10) || 0;

  const q = (selector, scope = document) => scope.querySelector(selector);
  const qa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));
  const safe = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => {
    return {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char] || char;
  });

  const treeWrap = q('#treeWrap');
  const modalAddEl = q('#modalAddNode');
  const modalNodeEl = q('#modalNodeEditor');
  const modalTemplateEl = q('#modalTemplate');

  const modalAdd = modalAddEl ? new bootstrap.Modal(modalAddEl) : null;
  const modalNode = modalNodeEl ? new bootstrap.Modal(modalNodeEl) : null;
  const modalTemplate = modalTemplateEl ? new bootstrap.Modal(modalTemplateEl) : null;

  const frmAdd = q('#frmAddNode');
  const frmNode = q('#frmNode');
  const frmCourse = q('#frmCourse');
  const frmExam = q('#frmExam');
  const btnCreateNode = q('#btnCreateNode');
  const btnCreateNodeAndContinue = q('#btnCreateNodeAndContinue');
  const btnCreateNodeAndChild = q('#btnCreateNodeAndChild');
  const btnSaveNode = q('#btnSaveNode');
  const btnSaveNodeAndCreateChild = q('#btnSaveNodeAndCreateChild');
  const btnCompleteNodeForEnrolled = q('#btnCompleteNodeForEnrolled');
  const btnCleanupNodeForNeverStarted = q('#btnCleanupNodeForNeverStarted');

  const nodeEmpty = q('#nodeEmpty');
  const nodeEditor = q('#nodeEditor');

  const nodeIdInput = q('input[name="node_id"]', frmNode || document);
  const nodeTitleInput = q('#nodeTitle', frmNode || document);
  const nodeKindSelect = q('#nodeKind', frmNode || document);
  const nodeSubtypeSelect = q('#nodeSubtype', frmNode || document);
  const nodePublishedSelect = q('#nodePublished', frmNode || document);
  const nodeCountInProgressSelect = q('#nodeCountInProgress', frmNode || document);

  const nodeKindBadge = q('#nodeKindBadge');
  const nodeSubtypeBadge = q('#nodeSubtypeBadge');
  const nodeNumberBadge = q('#nodeNumberBadge');

  const contentRaw = q('#contentRaw');
  const rulesRaw = q('#rulesRaw');
  const jsonPreview = q('#jsonPreview');

  const boxVideo = q('#boxVideo');
  const boxPdf = q('#boxPdf');
  const boxText = q('#boxText');
  const boxLink = q('#boxLink');
  const boxMoodleMap = q('#boxMoodleMap');

  const videoUrlInput = q('#videoUrl');
  const videoProviderSelect = q('#videoProvider');
  const videoMinInput = q('#videoMinPercent');
  const pdfPathInput = q('#pdfPath');
  const textHtmlInput = q('#textHtml');
  const linkUrlInput = q('#linkUrl');
  const linkLabelInput = q('#linkLabel');
  const moodleCmidInput = q('#moodleCmid');
  const moodleViewUrlInput = q('#moodleViewUrl');

  const ruleSequential = q('#ruleSequential');
  const ruleRequireVideo = q('#ruleRequireVideo');
  const ruleRequirePdf = q('#ruleRequirePdf');
  const ruleLibraryOnly = q('#ruleLibraryOnly');

  const toggleAdvanced = q('#toggleAdvanced');
  const nodeAdvanced = q('#nodeAdvanced');

  const uploadInput = q('#uploadFile');
  const selectionEmpty = q('#builderSelectionEmpty');
  const selectionState = q('#builderSelectionState');
  const selectionTitle = q('#builderSelectionTitle');
  const selectionPath = q('#builderSelectionPath');
  const selectionType = q('#builderSelectionType');
  const selectionPublished = q('#builderSelectionPublished');
  const selectionPercent = q('#builderSelectionPercent');
  const selectionNumber = q('#builderSelectionNumber');
  const btnEditSelectedNode = q('#btnEditSelectedNode');
  const btnCreateChildSelected = q('#btnCreateChildSelected');

  const state = {
    selectedNode: null,
    selectedEl: null,
  };

  const kindLabels = {
    container: 'Container',
    content: 'Conteudo',
    action: 'Acao',
  };

  const subtypeLabels = {
    root: 'Root',
    module: 'Modulo',
    topic: 'Topico',
    section: 'Secao',
    subsection: 'Subsecao',
    video: 'Video',
    pdf: 'PDF',
    text: 'Texto',
    download: 'Download',
    link: 'Link externo / E-book',
    final_exam: 'Prova final',
    certificate: 'Certificado',
  };

  const showAlert = (icon, title, text) => {
    if (window.Swal) {
      return Swal.fire({ icon, title, text });
    }
    alert(`${title}: ${text}`);
    return Promise.resolve();
  };

  const toast = (message, type = 'info') => {
    if (window.showToast) {
      window.showToast(message, type);
      return;
    }
    if (window.Swal) {
      Swal.fire({ toast: true, position: 'top-end', timer: 1800, showConfirmButton: false, icon: type, title: message });
    }
  };

  const toFormData = (payload) => {
    if (payload instanceof FormData) {
      if (!payload.has('csrf')) payload.append('csrf', csrf);
      return payload;
    }

    const fd = new FormData();
    Object.keys(payload || {}).forEach((key) => {
      fd.append(key, payload[key] ?? '');
    });
    if (!fd.has('csrf')) fd.append('csrf', csrf);
    return fd;
  };

  const apiPost = async (path, payload) => {
    const res = await fetch(base + path, {
      method: 'POST',
      body: toFormData(payload),
      credentials: 'same-origin',
    });

    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok === false) {
      throw new Error(json && json.error ? json.error : `Falha na requisicao (${res.status}).`);
    }

    return json;
  };

  const apiGet = async (path) => {
    const res = await fetch(base + path, { credentials: 'same-origin' });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok === false) {
      throw new Error(json && json.error ? json.error : `Falha na requisicao (${res.status}).`);
    }
    return json;
  };

  const safeJsonParse = (value) => {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      return value;
    }

    const text = String(value || '').trim();
    if (!text) return null;

    try {
      const parsed = JSON.parse(text);
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return parsed;
      }
      if (typeof parsed === 'string') {
        try {
          const parsedTwice = JSON.parse(parsed);
          if (parsedTwice && typeof parsedTwice === 'object' && !Array.isArray(parsedTwice)) {
            return parsedTwice;
          }
        } catch (_err) {
          // ignore
        }
      }
      return null;
    } catch (error) {
      return null;
    }
  };

  const pickFirst = (...values) => {
    for (let i = 0; i < values.length; i++) {
      const value = String(values[i] ?? '').trim();
      if (value !== '') return value;
    }
    return '';
  };

  const renderJsonPreview = () => {
    if (!jsonPreview) return;

    const content = safeJsonParse(contentRaw?.value) || {};
    const rules = safeJsonParse(rulesRaw?.value) || {};

    jsonPreview.innerHTML = `
      <div class="small text-muted mb-1">content_json</div>
      <pre>${safe(JSON.stringify(content, null, 2))}</pre>
      <div class="small text-muted mb-1">rules_json</div>
      <pre>${safe(JSON.stringify(rules, null, 2))}</pre>
    `;
  };

  const setSubtypeOptions = (kind, selectedSubtype, config = {}) => {
    if (!nodeSubtypeSelect) return;
    const allowRoot = !!config.allowRoot;

    let subtypeOptions = [];
    if (kind === 'container') {
      subtypeOptions = allowRoot
        ? ['root', 'module', 'topic', 'section', 'subsection']
        : ['module', 'topic', 'section', 'subsection'];
    } else if (kind === 'action') {
      subtypeOptions = ['final_exam', 'certificate'];
    } else {
      subtypeOptions = ['video', 'pdf', 'text', 'download', 'link'];
    }

    nodeSubtypeSelect.innerHTML = '';
    subtypeOptions.forEach((option) => {
      const element = document.createElement('option');
      element.value = option;
      element.textContent = option;
      nodeSubtypeSelect.appendChild(element);
    });

    if (subtypeOptions.includes(selectedSubtype)) {
      nodeSubtypeSelect.value = selectedSubtype;
    } else {
      nodeSubtypeSelect.value = subtypeOptions[0] || '';
    }
  };

  const iconForNode = (kind, subtype) => {
    if (kind === 'container') return 'fa-regular fa-folder-open';
    if (kind === 'action') return 'fa-solid fa-bolt';
    if (subtype === 'video') return 'fa-solid fa-circle-play';
    if (subtype === 'pdf') return 'fa-solid fa-file-pdf';
    if (subtype === 'text') return 'fa-solid fa-align-left';
    if (subtype === 'download') return 'fa-solid fa-download';
    if (subtype === 'link') return 'fa-solid fa-up-right-from-square';
    return 'fa-solid fa-file-lines';
  };

  const showSubtypeBoxes = (subtype) => {
    [boxVideo, boxPdf, boxText, boxLink].forEach((box) => {
      if (box) box.classList.add('d-none');
    });

    if (subtype === 'video' && boxVideo) boxVideo.classList.remove('d-none');
    if (subtype === 'pdf' && boxPdf) boxPdf.classList.remove('d-none');
    if (subtype === 'text' && boxText) boxText.classList.remove('d-none');
    if ((subtype === 'download' || subtype === 'link') && boxLink) boxLink.classList.remove('d-none');
  };

  const showMoodleMapBox = (kind) => {
    if (!boxMoodleMap) return;
    const canMapToMoodle = kind === 'content' || kind === 'action';
    boxMoodleMap.classList.toggle('d-none', !canMapToMoodle);
  };

  const setTreeSelection = (element) => {
    qa('.builder-item.is-selected', treeWrap || document).forEach((item) => item.classList.remove('is-selected'));
    if (element) element.classList.add('is-selected');
    state.selectedEl = element;
  };

  const renderSelectionSummary = () => {
    if (!selectionEmpty || !selectionState) return;

    const node = state.selectedNode;
    const itemEl = state.selectedEl;
    if (!node || !itemEl) {
      selectionEmpty.classList.remove('d-none');
      selectionState.classList.add('d-none');
      if (btnCreateChildSelected) btnCreateChildSelected.disabled = true;
      if (btnSaveNodeAndCreateChild) btnSaveNodeAndCreateChild.disabled = true;
      if (btnCompleteNodeForEnrolled) btnCompleteNodeForEnrolled.disabled = true;
      if (btnCleanupNodeForNeverStarted) btnCleanupNodeForNeverStarted.disabled = true;
      return;
    }

    const kind = String(node.kind || '');
    const subtype = String(node.subtype || '');
    const title = String(node.title || 'Sem titulo');
    const number = String(itemEl.dataset.number || '--');
    const trail = [];
    let parentItem = itemEl.parentElement?.closest('.builder-item') || null;
    while (parentItem) {
      const parentTitle = q(':scope > .builder-row .text-truncate', parentItem)?.textContent?.trim();
      if (parentTitle) trail.unshift(parentTitle);
      parentItem = parentItem.parentElement?.closest('.builder-item') || null;
    }

    selectionEmpty.classList.add('d-none');
    selectionState.classList.remove('d-none');

    if (selectionTitle) selectionTitle.textContent = title;
    if (selectionPath) selectionPath.textContent = trail.length ? trail.join(' > ') : 'Nivel raiz do curso';
    if (selectionType) selectionType.textContent = `${kindLabels[kind] || kind} / ${subtypeLabels[subtype] || subtype}`;
    if (selectionPublished) selectionPublished.textContent = String(node.is_published || '1') === '1' ? 'Sim' : 'Nao';
    if (selectionPercent) {
      const raw = String(node.count_in_progress_percent ?? '').trim();
      selectionPercent.textContent = raw === '' ? (subtype === 'certificate' ? 'Nao' : 'Sim') : (raw === '1' ? 'Sim' : 'Nao');
    }
    if (selectionNumber) selectionNumber.textContent = number;
    if (btnCreateChildSelected) btnCreateChildSelected.disabled = kind !== 'container';
    if (btnSaveNodeAndCreateChild) btnSaveNodeAndCreateChild.disabled = kind !== 'container';
    if (btnCompleteNodeForEnrolled) {
      const isPublished = String(node.is_published || '1') === '1';
      const isRootContainer = kind === 'container' && subtype === 'root';
      const canMassComplete = isPublished && !isRootContainer && (kind === 'container' || kind === 'content' || kind === 'action');
      btnCompleteNodeForEnrolled.disabled = !canMassComplete;
      btnCompleteNodeForEnrolled.innerHTML = kind === 'container'
        ? '<i class="fa-solid fa-user-check me-1"></i> Concluir bloco para inscritos'
        : '<i class="fa-solid fa-user-check me-1"></i> Concluir item para inscritos';
    }
    if (btnCleanupNodeForNeverStarted) {
      const isPublished = String(node.is_published || '1') === '1';
      const isRootContainer = kind === 'container' && subtype === 'root';
      const canCleanup = isPublished && !isRootContainer && (kind === 'container' || kind === 'content' || kind === 'action');
      btnCleanupNodeForNeverStarted.disabled = !canCleanup;
      btnCleanupNodeForNeverStarted.innerHTML = kind === 'container'
        ? '<i class="fa-solid fa-eraser me-1"></i> Corrigir bloco sem acesso'
        : '<i class="fa-solid fa-eraser me-1"></i> Corrigir item sem acesso';
    }
  };

  const updateNumbering = () => {
    if (!treeWrap) return;
    const root = q(':scope > ul.builder-list', treeWrap);
    if (!root) return;

    const walk = (list, prefix) => {
      const items = Array.from(list.children).filter((node) => node.classList.contains('builder-item'));
      items.forEach((item, index) => {
        const value = prefix ? `${prefix}.${index + 1}` : `${index + 1}`;
        item.dataset.number = value;
        const bubble = q('.builder-num', item);
        if (bubble) bubble.textContent = value;

        const childList = q(':scope > ul.builder-list', item);
        if (childList) walk(childList, value);
      });
    };

    walk(root, '');
    renderSelectionSummary();
  };

  const initSortableList = (list) => {
    if (!list || !window.Sortable || list.dataset.sortableBound === '1') {
      return;
    }

    new Sortable(list, {
      group: 'builder-tree',
      animation: 150,
      handle: '.builder-handle',
      draggable: '.builder-item',
      swapThreshold: 0.65,
      onEnd: updateNumbering,
    });
    list.dataset.sortableBound = '1';
  };

  const ensureRootList = () => {
    if (!treeWrap) return null;
    let rootList = q(':scope > ul.builder-list', treeWrap);
    if (!rootList) {
      rootList = document.createElement('ul');
      rootList.className = 'builder-list';
      treeWrap.appendChild(rootList);
    }
    initSortableList(rootList);
    return rootList;
  };

  const createTreeItemElement = (node) => {
    const item = document.createElement('li');
    item.className = 'builder-item';
    item.dataset.id = String(node.id || '');
    item.dataset.kind = String(node.kind || 'content');
    item.dataset.subtype = String(node.subtype || 'text');
    item.innerHTML = `
      <div class="builder-row">
        <span class="builder-handle" title="Arrastar"><i class="fa-solid fa-grip-vertical"></i></span>
        <span class="builder-title">
          <i class="${iconForNode(String(node.kind || 'content'), String(node.subtype || 'text'))}"></i>
          <span class="builder-num"></span>
          <span class="text-truncate">${safe(String(node.title || 'Sem titulo'))}</span>
          <span class="badge text-bg-light border ms-1">${safe(String(node.kind || 'content'))}</span>
          <span class="badge text-bg-light border">${safe(String(node.subtype || 'text'))}</span>
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary btnSelectNode" title="Editar item">
          <i class="fa-solid fa-sliders"></i>
        </button>
      </div>
    `;
    return item;
  };

  const appendNodeToTree = (node) => {
    if (!node || !treeWrap) return null;

    let targetList = ensureRootList();
    const parentId = parseInt(String(node.parent_id || '0'), 10) || 0;
    if (parentId > 0) {
      const parentItem = q(`.builder-item[data-id="${parentId}"]`, treeWrap);
      if (parentItem) {
        let childList = q(':scope > ul.builder-list', parentItem);
        if (!childList) {
          childList = document.createElement('ul');
          childList.className = 'builder-list';
          parentItem.appendChild(childList);
        }
        initSortableList(childList);
        targetList = childList;
      }
    }

    if (!targetList) return null;

    const item = createTreeItemElement(node);
    targetList.appendChild(item);
    updateNumbering();
    return item;
  };

  const selectedParentForAdd = () => {
    if (!frmAdd) return;

    const parentIdInput = q('input[name="parent_id"]', frmAdd);
    const parentTitleInput = q('input[name="parent_title"]', frmAdd);

    if (!parentIdInput || !parentTitleInput) return;

    if (state.selectedNode && state.selectedNode.kind === 'container') {
      parentIdInput.value = String(state.selectedNode.id || '');
      parentTitleInput.value = String(state.selectedNode.title || '');
    } else {
      parentIdInput.value = '';
      parentTitleInput.value = '';
    }
  };

  const setAddParentNode = (node) => {
    if (!frmAdd) return;

    const parentIdInput = q('input[name="parent_id"]', frmAdd);
    const parentTitleInput = q('input[name="parent_title"]', frmAdd);
    if (!parentIdInput || !parentTitleInput) return;

    if (node && String(node.kind || '') === 'container') {
      parentIdInput.value = String(node.id || '');
      parentTitleInput.value = String(node.title || '');
      return;
    }

    parentIdInput.value = '';
    parentTitleInput.value = '';
  };

  const buildContentFromInputs = () => {
    const subtype = String(nodeSubtypeSelect?.value || '');
    const kind = String(nodeKindSelect?.value || state.selectedNode?.kind || '');

    let payload = {};
    if (subtype === 'video') {
      const url = String(videoUrlInput?.value || '').trim();
      const provider = String(videoProviderSelect?.value || 'mp4').trim();
      const minPercent = parseInt(String(videoMinInput?.value || '0'), 10) || 0;

      if (url) payload.url = url;
      if (provider) payload.provider = provider;
      if (minPercent > 0) payload.min_video_percent = Math.max(1, Math.min(100, minPercent));
    } else if (subtype === 'pdf') {
      const path = String(pdfPathInput?.value || '').trim();
      payload = path ? { file_path: path } : {};
    } else if (subtype === 'text') {
      payload = { html: String(textHtmlInput?.value || '') };
    } else if (subtype === 'download' || subtype === 'link') {
      const url = String(linkUrlInput?.value || '').trim();
      const label = String(linkLabelInput?.value || '').trim();
      if (url) payload.url = url;
      if (label) payload.label = label;
    }

    if (!(kind === 'content' || kind === 'action')) {
      return payload;
    }

    return applyMoodleMapping(payload, kind);
  };

  const applyMoodleMapping = (content, currentKind = '') => {
    const kind = String(currentKind || nodeKindSelect?.value || state.selectedNode?.kind || '');
    const payload = (content && typeof content === 'object' && !Array.isArray(content)) ? content : {};

    if (!(kind === 'content' || kind === 'action')) {
      return payload;
    }

    const cmid = parseInt(String(moodleCmidInput?.value || '0'), 10) || 0;

    if (cmid > 0) {
      payload.cmid = cmid;
      payload.moodle_cmid = cmid;
      if (!payload.moodle || typeof payload.moodle !== 'object' || Array.isArray(payload.moodle)) {
        payload.moodle = {};
      }
      payload.moodle.cmid = cmid;
    }

    return payload;
  };

  const buildRulesFromInputs = () => {
    const payload = {};
    if (ruleSequential) payload.sequential = !!ruleSequential.checked;
    if (ruleRequireVideo?.checked) payload.require_video = true;
    if (ruleRequirePdf?.checked) payload.require_pdf = true;
    if (String(nodeSubtypeSelect?.value || '') === 'link' && ruleLibraryOnly?.checked) payload.library_only = true;

    const minPercent = parseInt(String(videoMinInput?.value || '0'), 10) || 0;
    if (minPercent > 0) payload.min_video_percent = Math.max(1, Math.min(100, minPercent));

    return payload;
  };

  const syncRawEditorsFromInputs = () => {
    if (contentRaw) contentRaw.value = JSON.stringify(buildContentFromInputs(), null, 2);
    if (rulesRaw) rulesRaw.value = JSON.stringify(buildRulesFromInputs(), null, 2);
    renderJsonPreview();
  };

  const populateInputsFromNode = (node) => {
    const content = safeJsonParse(node.content_json) || {};
    const rules = safeJsonParse(node.rules_json) || {};
    const mappingUrl = pickFirst(content?.moodle?.url, content.moodle_url, node.moodle_url);
    const fallbackCmid = parseInt(String(node.moodle_cmid || '0'), 10) || 0;

    if (videoUrlInput) videoUrlInput.value = pickFirst(content.url, mappingUrl);
    if (videoProviderSelect) videoProviderSelect.value = String(content.provider || 'mp4');
    if (videoMinInput) {
      const min = parseInt(String(content.min_video_percent ?? rules.min_video_percent ?? ''), 10);
      videoMinInput.value = Number.isFinite(min) && min > 0 ? String(min) : '';
    }

    if (pdfPathInput) pdfPathInput.value = pickFirst(content.file_path, content.url, mappingUrl);
    if (textHtmlInput) textHtmlInput.value = String(content.html || '');
    if (linkUrlInput) linkUrlInput.value = pickFirst(content.url, content.file_path, content.source_url, mappingUrl);
    if (linkLabelInput) linkLabelInput.value = String(content.label || '');
    if (moodleCmidInput) {
      const cmid = parseInt(String(content?.moodle?.cmid ?? content.cmid ?? content.moodle_cmid ?? fallbackCmid ?? '0'), 10) || 0;
      moodleCmidInput.value = cmid > 0 ? String(cmid) : '';
    }
    if (moodleViewUrlInput) {
      moodleViewUrlInput.value = mappingUrl;
    }

    if (ruleSequential) {
      if (Object.prototype.hasOwnProperty.call(rules, 'sequential')) {
        ruleSequential.checked = !(rules.sequential === false || rules.sequential === 0 || rules.sequential === '0' || rules.sequential === 'false');
      } else {
        ruleSequential.checked = true;
      }
    }
    if (ruleRequireVideo) ruleRequireVideo.checked = !!rules.require_video;
    if (ruleRequirePdf) ruleRequirePdf.checked = !!rules.require_pdf;
    if (ruleLibraryOnly) ruleLibraryOnly.checked = !!rules.library_only;

    if (contentRaw) contentRaw.value = node.content_json ? JSON.stringify(content, null, 2) : '';
    if (rulesRaw) rulesRaw.value = node.rules_json ? JSON.stringify(rules, null, 2) : '';

    renderJsonPreview();
  };

  const openNodeEditor = (node, nodeElement) => {
    state.selectedNode = node;
    setTreeSelection(nodeElement || state.selectedEl);

    if (nodeEmpty) nodeEmpty.classList.add('d-none');
    if (nodeEditor) nodeEditor.classList.remove('d-none');

    if (nodeIdInput) nodeIdInput.value = String(node.id || '');
    if (nodeTitleInput) nodeTitleInput.value = String(node.title || '');
    if (nodePublishedSelect) nodePublishedSelect.value = String(node.is_published ?? 1);
    if (nodeCountInProgressSelect) {
      const raw = String(node.count_in_progress_percent ?? '').trim();
      if (raw === '0' || raw === '1') {
        nodeCountInProgressSelect.value = raw;
      } else {
        nodeCountInProgressSelect.value = String(node.subtype || '') === 'certificate' ? '0' : '1';
      }
    }
    if (nodeKindSelect) {
      nodeKindSelect.value = String(node.kind || 'content');
      const isRootNode = !node.parent_id && String(node.subtype || '') === 'root';
      nodeKindSelect.disabled = isRootNode;
    }

    const isRootNode = !node.parent_id && String(node.subtype || '') === 'root';
    const currentKind = String(nodeKindSelect?.value || node.kind || 'content');
    setSubtypeOptions(currentKind, String(node.subtype || 'video'), { allowRoot: isRootNode });
    showMoodleMapBox(currentKind);
    showSubtypeBoxes(String(nodeSubtypeSelect?.value || ''));

    if (nodeKindBadge) nodeKindBadge.textContent = String(node.kind || '-');
    if (nodeSubtypeBadge) nodeSubtypeBadge.textContent = String(nodeSubtypeSelect?.value || '-');
    if (nodeNumberBadge) nodeNumberBadge.textContent = String(state.selectedEl?.dataset.number || '--');

    populateInputsFromNode(node);
    selectedParentForAdd();
    renderSelectionSummary();

    modalNode?.show();
  };

  const readNode = async (nodeId) => {
    const json = await apiGet(`/api/admin/node/read?node_id=${encodeURIComponent(nodeId)}`);
    return json.node || null;
  };

  treeWrap?.addEventListener('click', async (event) => {
    const button = event.target.closest('.btnSelectNode');
    const row = event.target.closest('.builder-row');
    if (!row) return;

    const item = row.closest('.builder-item');
    if (!item) return;

    if (event.target.closest('.builder-handle')) return;

    const nodeId = parseInt(String(item.dataset.id || '0'), 10);
    if (!nodeId) return;

    try {
      const node = await readNode(nodeId);
      if (!node) {
        showAlert('warning', 'Item nao encontrado', 'Nao foi possivel carregar o item selecionado.');
        return;
      }
      openNodeEditor(node, item);
      if (!button) toast('Item carregado para edicao.', 'info');
    } catch (error) {
      showAlert('error', 'Erro ao carregar item', String(error.message || error));
    }
  });

  q('#btnAddNode')?.addEventListener('click', () => {
    selectedParentForAdd();
    syncAddFlowButtons();
    modalAdd?.show();
    focusAddTitle();
  });

  q('#btnClearParent')?.addEventListener('click', () => {
    if (!frmAdd) return;
    const parentIdInput = q('input[name="parent_id"]', frmAdd);
    const parentTitleInput = q('input[name="parent_title"]', frmAdd);
    if (parentIdInput) parentIdInput.value = '';
    if (parentTitleInput) parentTitleInput.value = '';
    toast('Novo item sera criado no root.', 'info');
  });

  const addKindSelect = q('select[name="kind"]', frmAdd || document);
  const addSubtypeSelect = q('select[name="subtype"]', frmAdd || document);
  const addTitleInput = q('input[name="title"]', frmAdd || document);
  const syncAddSubtypeOptions = () => {
    if (!addKindSelect || !addSubtypeSelect) return;

    const kind = String(addKindSelect.value || 'content');
    const current = String(addSubtypeSelect.value || '');
    let options = [];

    if (kind === 'container') {
      options = ['module', 'topic', 'section', 'subsection'];
    } else if (kind === 'action') {
      options = ['final_exam', 'certificate'];
    } else {
      options = ['video', 'pdf', 'text', 'download', 'link'];
    }

    addSubtypeSelect.innerHTML = '';
    options.forEach((option) => {
      const element = document.createElement('option');
      element.value = option;
      element.textContent = option;
      addSubtypeSelect.appendChild(element);
    });

    addSubtypeSelect.value = options.includes(current) ? current : options[0];
  };

  const syncAddFlowButtons = () => {
    if (btnCreateNodeAndChild) {
      btnCreateNodeAndChild.disabled = String(addKindSelect?.value || '') !== 'container';
    }
  };

  const focusAddTitle = () => {
    if (!addTitleInput) return;
    setTimeout(() => {
      addTitleInput.focus();
      addTitleInput.select();
    }, 80);
  };

  const setAddKindAndSubtype = (kind, subtype) => {
    if (!addKindSelect || !addSubtypeSelect) return;
    addKindSelect.value = kind;
    syncAddSubtypeOptions();
    addSubtypeSelect.value = subtype;
    syncAddFlowButtons();
  };

  addKindSelect?.addEventListener('change', () => {
    syncAddSubtypeOptions();
    syncAddFlowButtons();
  });
  syncAddSubtypeOptions();
  syncAddFlowButtons();

  qa('[data-quick-add]', frmAdd || document).forEach((button) => {
    button.addEventListener('click', () => {
      if (!frmAdd) return;
      const type = String(button.getAttribute('data-quick-add') || '');
      const kindSelect = q('select[name="kind"]', frmAdd);
      const subtypeSelect = q('select[name="subtype"]', frmAdd);
      const titleInput = q('input[name="title"]', frmAdd);

      if (!kindSelect || !subtypeSelect || !titleInput) return;
      let targetKind = kindSelect.value || 'content';
      let targetSubtype = subtypeSelect.value || 'video';

      if (type === 'module') {
        targetKind = 'container';
        targetSubtype = 'module';
        titleInput.value = 'Modulo '; 
      } else if (type === 'video') {
        targetKind = 'content';
        targetSubtype = 'video';
        titleInput.value = 'Aula em video - ';
      } else if (type === 'pdf') {
        targetKind = 'content';
        targetSubtype = 'pdf';
        titleInput.value = 'Material PDF - ';
      } else if (type === 'text') {
        targetKind = 'content';
        targetSubtype = 'text';
        titleInput.value = 'Leitura - ';
      } else if (type === 'exercise') {
        targetKind = 'content';
        targetSubtype = 'text';
        titleInput.value = 'Exercicio - ';
      } else if (type === 'ebook') {
        targetKind = 'content';
        targetSubtype = 'link';
        titleInput.value = 'E-book - ';
      } else if (type === 'link') {
        targetKind = 'content';
        targetSubtype = 'link';
        titleInput.value = 'Link externo - ';
      }

      kindSelect.value = targetKind;
      syncAddSubtypeOptions();
      subtypeSelect.value = targetSubtype;

      syncAddFlowButtons();
      modalAdd?.show();
      focusAddTitle();
    });
  });

  const afterCreateNode = (json, flow = 'close') => {
    const node = json && json.node ? json.node : null;
    if (node) {
      const newItem = appendNodeToTree(node);
      if (newItem) {
        setTreeSelection(newItem);
      }
      state.selectedNode = node;
      renderSelectionSummary();
    }

    toast('Item criado com sucesso.', 'success');
    if (Number(json?.auto_completed_users || 0) > 0) {
      toast(`${Number(json.auto_completed_users || 0)} aluno(s) com 100% foram preservados automaticamente.`, 'info');
    }

    if (flow === 'continue') {
      if (addTitleInput) addTitleInput.value = '';
      focusAddTitle();
      return;
    }

    if (flow === 'child') {
      if (node && String(node.kind || '') === 'container') {
        setAddParentNode(node);
        setAddKindAndSubtype('content', 'video');
        if (addTitleInput) addTitleInput.value = '';
        toast('Modulo criado. Agora adicione os conteudos dentro dele.', 'info');
        focusAddTitle();
        return;
      }

      if (addTitleInput) addTitleInput.value = '';
      focusAddTitle();
      return;
    }

    modalAdd?.hide();
  };

  const createNodeFromAddForm = async (flow = 'close') => {
    if (!frmAdd) return;

    if (!addTitleInput?.value.trim()) {
      addTitleInput?.focus();
      showAlert('warning', 'Titulo obrigatorio', 'Informe um titulo para criar o item.');
      return;
    }

    if (flow === 'child' && String(addKindSelect?.value || '') !== 'container') {
      showAlert('info', 'Crie um container primeiro', 'Use "Criar e adicionar conteudo" quando estiver criando modulo, topico, secao ou subsecao.');
      return;
    }

    const payload = Object.fromEntries(new FormData(frmAdd).entries());
    payload.content = '{}';
    payload.rules = '{}';

    const actionButton = flow === 'child'
      ? btnCreateNodeAndChild
      : flow === 'continue'
        ? btnCreateNodeAndContinue
        : btnCreateNode;
    const originalHtml = actionButton ? actionButton.innerHTML : '';
    if (actionButton) {
      actionButton.disabled = true;
      actionButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processando...';
    }

    try {
      const json = await apiPost('/api/admin/node/create', payload);
      afterCreateNode(json, flow);
    } catch (error) {
      showAlert('error', 'Erro ao criar item', String(error.message || error));
    } finally {
      if (actionButton) {
        actionButton.disabled = false;
        actionButton.innerHTML = originalHtml;
      }
      syncAddFlowButtons();
    }
  };

  btnCreateNode?.addEventListener('click', () => createNodeFromAddForm('close'));
  btnCreateNodeAndContinue?.addEventListener('click', () => createNodeFromAddForm('continue'));
  btnCreateNodeAndChild?.addEventListener('click', () => createNodeFromAddForm('child'));

  nodeSubtypeSelect?.addEventListener('change', () => {
    showSubtypeBoxes(String(nodeSubtypeSelect.value || ''));
    if (nodeCountInProgressSelect && String(nodeSubtypeSelect.value || '') === 'certificate') {
      nodeCountInProgressSelect.value = '0';
    }
    if (ruleLibraryOnly && String(nodeSubtypeSelect.value || '') !== 'link') {
      ruleLibraryOnly.checked = false;
    }
    if (nodeSubtypeBadge) nodeSubtypeBadge.textContent = String(nodeSubtypeSelect.value || '');
    if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();
  });

  nodeKindSelect?.addEventListener('change', () => {
    const kind = String(nodeKindSelect.value || 'content');
    const prevSubtype = String(nodeSubtypeSelect?.value || '');
    const isRootNode = !!(state.selectedNode && !state.selectedNode.parent_id && String(state.selectedNode.subtype || '') === 'root');
    setSubtypeOptions(kind, prevSubtype, { allowRoot: isRootNode });
    showMoodleMapBox(kind);
    showSubtypeBoxes(String(nodeSubtypeSelect?.value || ''));
    if (nodeKindBadge) nodeKindBadge.textContent = kind;
    if (nodeSubtypeBadge) nodeSubtypeBadge.textContent = String(nodeSubtypeSelect?.value || '');
    if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();
  });

  [videoUrlInput, videoProviderSelect, videoMinInput, pdfPathInput, textHtmlInput, linkUrlInput, linkLabelInput, moodleCmidInput, ruleSequential, ruleRequireVideo, ruleRequirePdf, ruleLibraryOnly]
    .filter(Boolean)
    .forEach((input) => {
      input.addEventListener('input', () => {
        if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();
      });
      input.addEventListener('change', () => {
        if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();
      });
    });

  ruleLibraryOnly?.addEventListener('change', () => {
    if (!nodeCountInProgressSelect) return;
    if (ruleLibraryOnly.checked) {
      nodeCountInProgressSelect.value = '0';
    }
    if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();
  });

  moodleCmidInput?.addEventListener('input', () => {
    if (moodleViewUrlInput) moodleViewUrlInput.value = '';
  });

  q('#btnExampleVideofront')?.addEventListener('click', () => {
    if (videoProviderSelect) videoProviderSelect.value = 'videofront';
    if (videoUrlInput) videoUrlInput.value = 'https://videofront.example.com/embed/SEU_VIDEO';
    syncRawEditorsFromInputs();
    toast('Exemplo preenchido.', 'info');
  });

  toggleAdvanced?.addEventListener('change', () => {
    if (!nodeAdvanced) return;
    nodeAdvanced.classList.toggle('d-none', !toggleAdvanced.checked);
    if (!toggleAdvanced.checked) syncRawEditorsFromInputs();
  });

  q('#btnRebuildJson')?.addEventListener('click', () => {
    syncRawEditorsFromInputs();
  });

  contentRaw?.addEventListener('input', renderJsonPreview);
  rulesRaw?.addEventListener('input', renderJsonPreview);

  const saveCurrentNode = async () => {
    const nodeId = parseInt(String(nodeIdInput?.value || '0'), 10);
    if (!nodeId) {
      showAlert('info', 'Selecione um item', 'Clique em um item da arvore para editar.');
      return null;
    }

    const title = String(nodeTitleInput?.value || '').trim();
    const kind = String(nodeKindSelect?.value || state.selectedNode?.kind || 'content').trim();
    const subtype = String(nodeSubtypeSelect?.value || '').trim();
    const isPublished = String(nodePublishedSelect?.value || '1');
    const countInProgressPercent = String(nodeCountInProgressSelect?.value || '1');

    if (!title) {
      nodeTitleInput?.focus();
      showAlert('warning', 'Titulo obrigatorio', 'Informe o titulo do item.');
      return null;
    }

    if (!subtype) {
      showAlert('warning', 'Subtype obrigatorio', 'Escolha um subtype para o item.');
      return null;
    }

    let contentValue = '';
    let rulesValue = '';

    if (toggleAdvanced?.checked) {
      if (contentRaw?.value.trim()) {
        if (!safeJsonParse(contentRaw.value)) {
          showAlert('error', 'content_json invalido', 'Corrija o JSON antes de salvar.');
          return null;
        }
      }
      if (rulesRaw?.value.trim()) {
        if (!safeJsonParse(rulesRaw.value)) {
          showAlert('error', 'rules_json invalido', 'Corrija o JSON antes de salvar.');
          return null;
        }
      }

      const advancedContent = safeJsonParse(String(contentRaw?.value || '').trim()) || {};
      contentValue = JSON.stringify(applyMoodleMapping(advancedContent, kind));
      rulesValue = String(rulesRaw?.value || '').trim();
    } else {
      contentValue = JSON.stringify(buildContentFromInputs());
      rulesValue = JSON.stringify(buildRulesFromInputs());
      if (contentRaw) contentRaw.value = JSON.stringify(JSON.parse(contentValue || '{}'), null, 2);
      if (rulesRaw) rulesRaw.value = JSON.stringify(JSON.parse(rulesValue || '{}'), null, 2);
      renderJsonPreview();
    }

    const json = await apiPost('/api/admin/node/update', {
      node_id: nodeId,
      title,
      kind,
      subtype,
      is_published: isPublished,
      count_in_progress_percent: countInProgressPercent,
      content: contentValue,
      rules: rulesValue,
    });

    if (state.selectedEl) {
      const titleTarget = q('.text-truncate', state.selectedEl);
      if (titleTarget) titleTarget.textContent = title;

      const badges = qa('.badge', state.selectedEl);
      if (badges.length >= 1) {
        badges[0].textContent = kind;
      }
      if (badges.length >= 2) {
        badges[1].textContent = subtype;
      }

      const icon = q('.builder-title > i', state.selectedEl);
      if (icon) {
        icon.className = iconForNode(kind, subtype);
      }

      state.selectedEl.dataset.kind = kind;
      state.selectedEl.dataset.subtype = subtype;
    }

    if (state.selectedNode) {
      state.selectedNode.title = title;
      state.selectedNode.kind = kind;
      state.selectedNode.subtype = subtype;
      state.selectedNode.is_published = isPublished === '1' ? 1 : 0;
      state.selectedNode.count_in_progress_percent = countInProgressPercent === '1' ? 1 : 0;
    }

    renderSelectionSummary();

    toast('Item salvo com sucesso.', 'success');
    if (Number(json.auto_completed_users || 0) > 0) {
      toast(`${Number(json.auto_completed_users || 0)} aluno(s) com 100% foram preservados automaticamente.`, 'info');
    }

    return json;
  };

  btnSaveNode?.addEventListener('click', async () => {
    try {
      await saveCurrentNode();
    } catch (error) {
      showAlert('error', 'Erro ao salvar item', String(error.message || error));
    }
  });

  btnSaveNodeAndCreateChild?.addEventListener('click', async () => {
    if (!state.selectedNode || String(state.selectedNode.kind || '') !== 'container') {
      showAlert('info', 'Selecione um container', 'Esse atalho funciona para modulo, topico, secao ou subsecao.');
      return;
    }

    const originalHtml = btnSaveNodeAndCreateChild.innerHTML;
    btnSaveNodeAndCreateChild.disabled = true;
    btnSaveNodeAndCreateChild.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processando...';

    try {
      const json = await saveCurrentNode();
      if (!json) return;
      modalNode?.hide();
      setAddParentNode(state.selectedNode);
      setAddKindAndSubtype('content', 'video');
      if (addTitleInput) addTitleInput.value = '';
      modalAdd?.show();
      toast('Agora adicione o conteudo dentro deste container.', 'info');
      focusAddTitle();
    } catch (error) {
      showAlert('error', 'Erro ao salvar item', String(error.message || error));
    } finally {
      btnSaveNodeAndCreateChild.disabled = false;
      btnSaveNodeAndCreateChild.innerHTML = originalHtml;
    }
  });

  btnCompleteNodeForEnrolled?.addEventListener('click', async () => {
    if (!state.selectedNode) {
      showAlert('info', 'Selecione um item', 'Clique em um item da arvore para aplicar a conclusao em massa.');
      return;
    }

    const nodeId = parseInt(String(state.selectedNode.id || '0'), 10) || 0;
    const kind = String(state.selectedNode.kind || '');
    const subtype = String(state.selectedNode.subtype || '');
    const title = String(state.selectedNode.title || 'Item');
    if (!nodeId || (kind === 'container' && subtype === 'root')) {
      showAlert('info', 'Item invalido', 'Escolha um item ou container valido para concluir em massa.');
      return;
    }

    let confirmed = true;
    if (window.Swal) {
      const answer = await Swal.fire({
        icon: 'question',
        title: kind === 'container' ? 'Concluir bloco para todos os inscritos?' : 'Concluir item para todos os inscritos?',
        html: kind === 'container'
          ? `O app vai marcar como concluido para todos os inscritos os conteudos publicados dentro de <strong>${safe(title)}</strong>.`
          : `O app vai marcar <strong>${safe(title)}</strong> como concluido para todos os inscritos do curso.`,
        showCancelButton: true,
        confirmButtonText: 'Concluir para todos',
        cancelButtonText: 'Cancelar'
      });
      confirmed = !!answer.isConfirmed;
    }

    if (!confirmed) return;

    const originalHtml = btnCompleteNodeForEnrolled.innerHTML;
    btnCompleteNodeForEnrolled.disabled = true;
    btnCompleteNodeForEnrolled.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processando...';

    try {
      const json = await apiPost('/api/admin/node/complete-for-enrolled', { node_id: nodeId });
      const summary = json && json.summary ? json.summary : {};
      await showAlert(
        'success',
        'Conclusao em massa aplicada',
        `Inscritos: ${Number(summary.enrolled_users || 0)} | Com acesso iniciado: ${Number(summary.started_users || summary.eligible_users || 0)} | Itens cobertos: ${Number(summary.target_nodes || 0)} | Marcacoes aplicadas: ${Number(summary.changed_rows || 0)}`
      );
    } catch (error) {
      showAlert('error', 'Erro na conclusao em massa', String(error.message || error));
    } finally {
      btnCompleteNodeForEnrolled.disabled = false;
      btnCompleteNodeForEnrolled.innerHTML = originalHtml;
      renderSelectionSummary();
    }
  });

  btnCleanupNodeForNeverStarted?.addEventListener('click', async () => {
    if (!state.selectedNode) {
      showAlert('info', 'Selecione um item', 'Clique em um item da arvore para corrigir o progresso indevido.');
      return;
    }

    const nodeId = parseInt(String(state.selectedNode.id || '0'), 10) || 0;
    const kind = String(state.selectedNode.kind || '');
    const subtype = String(state.selectedNode.subtype || '');
    const title = String(state.selectedNode.title || 'Item');
    if (!nodeId || (kind === 'container' && subtype === 'root')) {
      showAlert('info', 'Item invalido', 'Escolha um item ou container valido para corrigir.');
      return;
    }

    let confirmed = true;
    if (window.Swal) {
      const answer = await Swal.fire({
        icon: 'warning',
        title: kind === 'container' ? 'Corrigir bloco para quem nunca acessou?' : 'Corrigir item para quem nunca acessou?',
        html: kind === 'container'
          ? `O app vai remover o progresso indevido dos conteudos publicados dentro de <strong>${safe(title)}</strong> apenas para inscritos sem acesso iniciado.`
          : `O app vai remover o progresso indevido de <strong>${safe(title)}</strong> apenas para inscritos sem acesso iniciado.`,
        showCancelButton: true,
        confirmButtonText: 'Corrigir agora',
        cancelButtonText: 'Cancelar'
      });
      confirmed = !!answer.isConfirmed;
    }

    if (!confirmed) return;

    const originalHtml = btnCleanupNodeForNeverStarted.innerHTML;
    btnCleanupNodeForNeverStarted.disabled = true;
    btnCleanupNodeForNeverStarted.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processando...';

    try {
      const json = await apiPost('/api/admin/node/cleanup-never-started', { node_id: nodeId });
      const summary = json && json.summary ? json.summary : {};
      await showAlert(
        'success',
        'Correcao aplicada',
        `Inscritos: ${Number(summary.enrolled_users || 0)} | Sem acesso iniciado: ${Number(summary.never_started_users || 0)} | Itens cobertos: ${Number(summary.target_nodes || 0)} | Registros removidos: ${Number(summary.removed_rows || 0)}`
      );
    } catch (error) {
      showAlert('error', 'Erro na correcao', String(error.message || error));
    } finally {
      btnCleanupNodeForNeverStarted.disabled = false;
      btnCleanupNodeForNeverStarted.innerHTML = originalHtml;
      renderSelectionSummary();
    }
  });

  q('#btnRunProgressCatchup')?.addEventListener('click', async () => {
    if (!courseId) return;

    let confirmed = true;
    if (window.Swal) {
      const answer = await Swal.fire({
        icon: 'question',
        title: 'Rodar catchup de progresso?',
        text: 'Use isso depois de incluir itens novos que contam no percentual, para preservar alunos que ja tinham concluido o curso.',
        showCancelButton: true,
        confirmButtonText: 'Rodar catchup',
        cancelButtonText: 'Cancelar'
      });
      confirmed = !!answer.isConfirmed;
    }

    if (!confirmed) return;

    const button = q('#btnRunProgressCatchup');
    const originalHtml = button ? button.innerHTML : '';
    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processando...';
    }

    try {
      const json = await apiPost('/api/admin/course/progress/catchup', { course_id: courseId });
      const summary = json && json.summary ? json.summary : {};
      await showAlert(
        'success',
        'Catchup concluido',
        `Itens processados: ${Number(summary.processed_nodes || 0)} | Marcacoes aplicadas: ${Number(summary.auto_completed_rows || 0)}`
      );
    } catch (error) {
      showAlert('error', 'Erro no catchup', String(error.message || error));
    } finally {
      if (button) {
        button.disabled = false;
        button.innerHTML = originalHtml;
      }
    }
  });

  q('#btnDeleteNode')?.addEventListener('click', async () => {
    const nodeId = parseInt(String(nodeIdInput?.value || '0'), 10);
    if (!nodeId) {
      showAlert('info', 'Selecione um item', 'Clique em um item da arvore para excluir.');
      return;
    }

    let confirmed = true;
    if (window.Swal) {
      const answer = await Swal.fire({
        icon: 'warning',
        title: 'Excluir item?',
        text: 'O item e seus filhos serao removidos.',
        showCancelButton: true,
        confirmButtonText: 'Excluir',
        cancelButtonText: 'Cancelar'
      });
      confirmed = !!answer.isConfirmed;
    }

    if (!confirmed) return;

    try {
      await apiPost('/api/admin/node/delete', { node_id: nodeId });
      toast('Item excluido.', 'success');
      location.reload();
    } catch (error) {
      showAlert('error', 'Erro ao excluir item', String(error.message || error));
    }
  });

  btnEditSelectedNode?.addEventListener('click', () => {
    if (!state.selectedNode) return;
    modalNode?.show();
  });

  btnCreateChildSelected?.addEventListener('click', () => {
    if (!state.selectedNode || String(state.selectedNode.kind || '') !== 'container') return;
    selectedParentForAdd();
    setAddKindAndSubtype('content', 'video');
    if (addTitleInput) addTitleInput.value = '';
    syncAddFlowButtons();
    modalAdd?.show();
    focusAddTitle();
  });

  q('#btnUpload')?.addEventListener('click', async () => {
    if (!uploadInput?.files?.length) {
      showAlert('info', 'Selecione um arquivo', 'Escolha um arquivo antes de enviar.');
      return;
    }

    const fd = new FormData();
    fd.append('course_id', String(courseId));
    fd.append('file', uploadInput.files[0]);

    try {
      const json = await apiPost('/api/admin/upload', fd);
      const path = String(json.path || '');

      if (pdfPathInput && !pdfPathInput.value.trim()) {
        pdfPathInput.value = path;
      }
      if (linkUrlInput && !linkUrlInput.value.trim()) {
        linkUrlInput.value = String(json.url || path);
      }

      if (!toggleAdvanced?.checked) syncRawEditorsFromInputs();

      if (window.Swal) {
        Swal.fire({
          icon: 'success',
          title: 'Upload concluido',
          html: `<div class="small text-muted mb-2">file_path:</div><pre class="small text-start bg-light p-2 rounded border">${safe(path)}</pre>`
        });
      }
    } catch (error) {
      showAlert('error', 'Erro no upload', String(error.message || error));
    }
  });

  q('#btnSaveCourse')?.addEventListener('click', async () => {
    if (!frmCourse) return;
    try {
      await apiPost('/api/admin/course/update', Object.fromEntries(new FormData(frmCourse).entries()));
      toast('Curso salvo com sucesso.', 'success');
    } catch (error) {
      showAlert('error', 'Erro ao salvar curso', String(error.message || error));
    }
  });

  q('#btnPublishCourse')?.addEventListener('click', async () => {
    try {
      await apiPost('/api/admin/course/publish', { course_id: courseId, status: 'published' });
      toast('Curso publicado.', 'success');
      location.reload();
    } catch (error) {
      showAlert('error', 'Erro ao publicar', String(error.message || error));
    }
  });

  q('#btnUnpublishCourse')?.addEventListener('click', async () => {
    try {
      await apiPost('/api/admin/course/publish', { course_id: courseId, status: 'draft' });
      toast('Curso voltou para rascunho.', 'success');
      location.reload();
    } catch (error) {
      showAlert('error', 'Erro ao alterar status', String(error.message || error));
    }
  });

  q('#btnSaveExam')?.addEventListener('click', async () => {
    if (!frmExam) return;
    try {
      await apiPost('/api/admin/exam/save', Object.fromEntries(new FormData(frmExam).entries()));
      toast('Prova final salva.', 'success');
    } catch (error) {
      showAlert('error', 'Erro ao salvar prova', String(error.message || error));
    }
  });

  q('#btnApplyTemplate')?.addEventListener('click', async () => {
    let confirmed = true;
    if (window.Swal) {
      const answer = await Swal.fire({
        icon: 'question',
        title: 'Inserir modelo neste curso?',
        text: 'Isso adiciona itens de exemplo sem apagar os atuais.',
        showCancelButton: true,
        confirmButtonText: 'Inserir',
        cancelButtonText: 'Cancelar'
      });
      confirmed = !!answer.isConfirmed;
    }

    if (!confirmed) return;

    try {
      await apiPost('/api/admin/course/template/apply', { course_id: courseId });
      modalTemplate?.hide();
      toast('Modelo inserido.', 'success');
      location.reload();
    } catch (error) {
      showAlert('error', 'Erro ao aplicar modelo', String(error.message || error));
    }
  });

  q('#btnCreateTemplateCourse')?.addEventListener('click', async () => {
    const title = String(q('#templateCourseTitle')?.value || '').trim() || 'Curso Modelo';

    try {
      const json = await apiPost('/api/admin/course/template/new', { title });
      if (json.builder_url) {
        window.location.href = json.builder_url;
        return;
      }

      if (json.course_id) {
        window.location.href = `${base}/admin/courses/${json.course_id}/builder`;
        return;
      }

      toast('Curso modelo criado.', 'success');
    } catch (error) {
      showAlert('error', 'Erro ao criar modelo', String(error.message || error));
    }
  });

  const flattenTree = (list, parentId = null, depth = 0, output = []) => {
    const items = Array.from(list.children).filter((item) => item.classList.contains('builder-item'));

    items.forEach((item, index) => {
      const id = parseInt(String(item.dataset.id || '0'), 10);
      if (!id) return;

      output.push({
        id,
        parent_id: parentId,
        sort: index + 1,
        depth,
      });

      const childList = q(':scope > ul.builder-list', item);
      if (childList) {
        flattenTree(childList, id, depth + 1, output);
      }
    });

    return output;
  };

  q('#btnSaveOrder')?.addEventListener('click', async () => {
    if (!treeWrap) return;
    const rootList = q(':scope > ul.builder-list', treeWrap);
    if (!rootList) return;

    const tree = flattenTree(rootList, null, 0, []);

    try {
      await apiPost('/api/admin/node/reorder', {
        course_id: courseId,
        tree: JSON.stringify(tree),
      });
      toast('Ordem salva com sucesso.', 'success');
      updateNumbering();
    } catch (error) {
      showAlert('error', 'Erro ao salvar ordem', String(error.message || error));
    }
  });

  q('#treeSearch')?.addEventListener('input', (event) => {
    const query = String(event.target.value || '').trim().toLowerCase();
    qa('.builder-item', treeWrap || document).forEach((item) => {
      const text = String(item.textContent || '').toLowerCase();
      item.style.display = !query || text.includes(query) ? '' : 'none';
    });
  });

  const loadSortable = () => {
    return new Promise((resolve, reject) => {
      if (window.Sortable) {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
      script.onload = resolve;
      script.onerror = () => reject(new Error('Falha ao carregar SortableJS.'));
      document.head.appendChild(script);
    });
  };

  const initSortable = async () => {
    if (!treeWrap) return;

    try {
      await loadSortable();
      qa('ul.builder-list', treeWrap).forEach((list) => {
        initSortableList(list);
      });
      updateNumbering();
    } catch (error) {
      console.warn(error);
    }
  };

  initSortable();
  updateNumbering();
  renderSelectionSummary();
})();
