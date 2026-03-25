<?php
use App\App;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$course = $course ?? [];
$tree = $tree ?? ['root' => [], 'byId' => []];
$csrf = $csrf ?? '';
$exam = $exam ?? null;

$status = (string)($course['status'] ?? 'draft');
$sequenceLockEnabled = ((int)($course['enable_sequence_lock'] ?? 1) === 1);
$biometricRequired = ((int)($course['require_biometric'] ?? 0) === 1);
$showNumbering = ((int)($course['show_numbering'] ?? 0) === 1);
$statusLabel = [
  'draft' => 'Rascunho',
  'published' => 'Publicado',
  'archived' => 'Arquivado',
];

$nodesById = is_array($tree['byId'] ?? null) ? $tree['byId'] : [];
$totalNodes = count($nodesById);
$publishedNodes = 0;
foreach ($nodesById as $node) {
  if ((int)($node['is_published'] ?? 0) === 1) $publishedNodes++;
}

$iconForNode = function (string $kind, string $subtype): string {
  if ($kind === 'container') return 'fa-regular fa-folder-open';
  if ($kind === 'action') return 'fa-solid fa-bolt';

  if ($subtype === 'video') return 'fa-solid fa-circle-play';
  if ($subtype === 'pdf') return 'fa-solid fa-file-pdf';
  if ($subtype === 'text') return 'fa-solid fa-align-left';
  if ($subtype === 'download') return 'fa-solid fa-download';
  if ($subtype === 'link') return 'fa-solid fa-up-right-from-square';
  return 'fa-solid fa-file-lines';
};

$renderNodes = function (array $nodes) use (&$renderNodes, $iconForNode): void {
  echo '<ul class="builder-list">';
  foreach ($nodes as $node) {
    $id = (int)($node['id'] ?? 0);
    $kind = (string)($node['kind'] ?? 'content');
    $subtype = (string)($node['subtype'] ?? 'text');
    $title = (string)($node['title'] ?? 'Sem titulo');
    $icon = $iconForNode($kind, $subtype);

    echo '<li class="builder-item" data-id="' . $id . '" data-kind="' . h($kind) . '" data-subtype="' . h($subtype) . '">';
    echo '  <div class="builder-row">';
    echo '    <span class="builder-handle" title="Arrastar"><i class="fa-solid fa-grip-vertical"></i></span>';
    echo '    <span class="builder-title">';
    echo '      <i class="' . h($icon) . '"></i>';
    echo '      <span class="builder-num"></span>';
    echo '      <span class="text-truncate">' . h($title) . '</span>';
    echo '      <span class="badge text-bg-light border ms-1">' . h($kind) . '</span>';
    echo '      <span class="badge text-bg-light border">' . h($subtype) . '</span>';
    echo '    </span>';
    echo '    <button type="button" class="btn btn-sm btn-outline-secondary btnSelectNode" title="Editar item">';
    echo '      <i class="fa-solid fa-sliders"></i>';
    echo '    </button>';
    echo '  </div>';

    if (!empty($node['children']) && is_array($node['children'])) {
      $renderNodes($node['children']);
    }

    echo '</li>';
  }
  echo '</ul>';
};
?>

<section class="builder-hero">
  <div class="builder-hero__content">
    <div class="builder-hero__meta">
      <span class="status-pill status-<?= h($status) ?>"><?= h($statusLabel[$status] ?? $status) ?></span>
      <span class="builder-hero__dot">&bull;</span>
      <span><i class="fa-solid fa-hashtag me-1"></i>ID <?= (int)($course['id'] ?? 0) ?></span>
      <?php if (!empty($course['moodle_courseid'])): ?>
        <span class="builder-hero__dot">&bull;</span>
        <span><i class="fa-solid fa-id-badge me-1"></i>Moodle <?= h((string)$course['moodle_courseid']) ?></span>
      <?php endif; ?>
      <span class="builder-hero__dot">&bull;</span>
      <span><i class="fa-solid fa-link me-1"></i><?= $sequenceLockEnabled ? 'Com trava sequencial' : 'Sem trava sequencial' ?></span>
      <span class="builder-hero__dot">&bull;</span>
      <span><i class="fa-solid fa-fingerprint me-1"></i><?= $biometricRequired ? 'Com biometria' : 'Sem biometria' ?></span>
    </div>

    <div class="builder-hero__actions">
      <button class="btn btn-primary btn-admin" type="button" data-bs-toggle="modal" data-bs-target="#modalAddNode">
        <i class="fa-solid fa-plus me-2"></i> Novo item
      </button>
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalTemplate">
        <i class="fa-solid fa-layer-group me-2"></i> Usar modelo
      </button>
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalCourseSettings">
        <i class="fa-solid fa-gear me-2"></i> Configuracoes
      </button>
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalExam">
        <i class="fa-solid fa-graduation-cap me-2"></i> Prova final
      </button>
      <button class="btn btn-outline-success" type="button" id="btnRunProgressCatchup">
        <i class="fa-solid fa-user-check me-2"></i> Preservar alunos 100%
      </button>
      <a class="btn btn-outline-secondary" href="<?= App::base_url('/course/' . (int)$course['id']) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-eye me-2"></i> Preview aluno
      </a>
      <a class="btn btn-outline-secondary" href="<?= App::base_url('/admin/courses') ?>">
        <i class="fa-solid fa-arrow-left me-2"></i> Voltar
      </a>
    </div>
  </div>

  <div class="builder-hero__stats">
    <div class="stat-pill">
      <div class="stat-pill__value"><?= (int)$totalNodes ?></div>
      <div class="stat-pill__label">Itens na estrutura</div>
    </div>
    <div class="stat-pill">
      <div class="stat-pill__value"><?= (int)$publishedNodes ?></div>
      <div class="stat-pill__label">Itens publicados</div>
    </div>
    <div class="stat-pill">
      <div class="stat-pill__value"><?= max(0, (int)$totalNodes - (int)$publishedNodes) ?></div>
      <div class="stat-pill__label">Itens em rascunho</div>
    </div>
  </div>
</section>

<section class="builder-shell">
  <div class="builder-workspace">
    <div class="builder-panel">
      <div class="card-body p-3 p-lg-4">
        <div class="builder-toolbar">
          <div class="fw-semibold">Estrutura do curso</div>
          <input id="treeSearch" type="text" class="form-control form-control-sm builder-search" placeholder="Buscar item na arvore">
          <div class="ms-auto d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-primary" id="btnAddNode" type="button" data-bs-toggle="modal" data-bs-target="#modalAddNode">
              <i class="fa-solid fa-plus me-1"></i> Adicionar
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnSaveOrder" type="button">
              <i class="fa-solid fa-floppy-disk me-1"></i> Salvar ordem
            </button>
          </div>
        </div>

        <div class="builder-hint mb-2">
          Arraste para reorganizar. Clique no item para abrir o editor em modal. A numeracao no menu pode ser ligada ou desligada nas configuracoes do curso.
        </div>

        <div id="treeWrap" class="builder-tree" data-course="<?= (int)($course['id'] ?? 0) ?>">
          <?php $renderNodes($tree['root'] ?? []); ?>
        </div>
      </div>
    </div>
  </div>

  <aside class="builder-side">
    <section class="builder-side-card">
      <div class="builder-side-card__head">
        <div>
          <div class="builder-side-card__eyebrow">Fluxo recomendado</div>
          <h3 class="builder-side-card__title">Monte o curso em 4 passos</h3>
        </div>
      </div>

      <div class="builder-flow">
        <div class="builder-flow__item">
          <span class="builder-flow__index">1</span>
          <div>
            <div class="builder-flow__title">Crie o container</div>
            <div class="builder-flow__text">Modulo, topico, secao ou subsecão para organizar a trilha.</div>
          </div>
        </div>
        <div class="builder-flow__item">
          <span class="builder-flow__index">2</span>
          <div>
            <div class="builder-flow__title">Adicione o conteudo</div>
            <div class="builder-flow__text">Video, PDF, texto, link ou download dentro do ponto certo.</div>
          </div>
        </div>
        <div class="builder-flow__item">
          <span class="builder-flow__index">3</span>
          <div>
            <div class="builder-flow__title">Defina as regras</div>
            <div class="builder-flow__text">Sequencial, video, PDF e se o item soma percentual.</div>
          </div>
        </div>
        <div class="builder-flow__item">
          <span class="builder-flow__index">4</span>
          <div>
            <div class="builder-flow__title">Revise a liberacao final</div>
            <div class="builder-flow__text">Prova final, certificado e preview do aluno.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="builder-side-card builder-side-card--selected">
      <div class="builder-side-card__head">
        <div>
          <div class="builder-side-card__eyebrow">Item selecionado</div>
          <h3 class="builder-side-card__title">Resumo rapido</h3>
        </div>
      </div>

      <div id="builderSelectionEmpty" class="builder-selection-empty">
        Clique em um item da arvore para ver o resumo e editar com mais seguranca.
      </div>

      <div id="builderSelectionState" class="builder-selection d-none">
        <div class="builder-selection__title-wrap">
          <div class="builder-selection__title" id="builderSelectionTitle">-</div>
          <div class="builder-selection__subtitle" id="builderSelectionPath">-</div>
        </div>

        <div class="builder-selection__grid">
          <div class="builder-selection__meta">
            <span class="builder-selection__label">Tipo</span>
            <strong id="builderSelectionType">-</strong>
          </div>
          <div class="builder-selection__meta">
            <span class="builder-selection__label">Publicado</span>
            <strong id="builderSelectionPublished">-</strong>
          </div>
          <div class="builder-selection__meta">
            <span class="builder-selection__label">Soma percentual</span>
            <strong id="builderSelectionPercent">-</strong>
          </div>
          <div class="builder-selection__meta">
            <span class="builder-selection__label">Numero</span>
            <strong id="builderSelectionNumber">-</strong>
          </div>
        </div>

        <div class="builder-selection__actions">
          <button type="button" class="btn btn-admin btn-primary" id="btnEditSelectedNode">
            <i class="fa-solid fa-pen-to-square me-2"></i>Editar item
          </button>
          <button type="button" class="btn btn-outline-secondary" id="btnCreateChildSelected">
            <i class="fa-solid fa-plus me-2"></i>Novo filho
          </button>
        </div>
      </div>
    </section>
  </aside>
</section>

<div class="modal fade" id="modalAddNode" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Adicionar item</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="frmAddNode" class="row g-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="course_id" value="<?= (int)($course['id'] ?? 0) ?>">

          <div class="col-12">
            <div class="app-soft p-3">
              <div class="small text-muted mb-2">Pai selecionado. Se vazio, o item sera criado no nivel raiz.</div>
              <div class="row g-2">
                <div class="col-12 col-lg-8">
                  <input type="hidden" name="parent_id" value="">
                  <input class="form-control" name="parent_title" value="" readonly placeholder="(root)">
                </div>
                <div class="col-12 col-lg-4 d-grid">
                  <button type="button" class="btn btn-outline-secondary" id="btnClearParent">
                    <i class="fa-solid fa-arrow-turn-up me-1"></i> Criar no root
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="module"><i class="fa-regular fa-folder-open me-1"></i>Modulo</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="video"><i class="fa-solid fa-circle-play me-1"></i>Video</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="pdf"><i class="fa-solid fa-file-pdf me-1"></i>PDF</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="text"><i class="fa-solid fa-align-left me-1"></i>Texto</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="ebook"><i class="fa-solid fa-book-open me-1"></i>E-book</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="exercise"><i class="fa-solid fa-pen-to-square me-1"></i>Exercicio</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" data-quick-add="link"><i class="fa-solid fa-up-right-from-square me-1"></i>Link</button>
            </div>
            <div class="form-text mt-2">Fluxo rapido: crie o modulo e use <strong>Criar e adicionar conteudo</strong> para continuar no mesmo modal.</div>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Kind</label>
            <select class="form-select" name="kind">
              <option value="container">container</option>
              <option value="content" selected>content</option>
              <option value="action">action</option>
            </select>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Subtype</label>
            <select class="form-select" name="subtype">
              <option value="module">module</option>
              <option value="topic">topic</option>
              <option value="section">section</option>
              <option value="subsection">subsection</option>
              <option value="video" selected>video</option>
              <option value="pdf">pdf</option>
              <option value="text">text</option>
              <option value="download">download</option>
              <option value="link">link</option>
              <option value="final_exam">final_exam</option>
              <option value="certificate">certificate</option>
            </select>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Titulo</label>
            <input class="form-control" name="title" required placeholder="Ex.: Aula 01 - Sinalizacao">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-secondary" id="btnCreateNodeAndContinue" type="button">Criar e outro</button>
        <button class="btn btn-outline-primary" id="btnCreateNodeAndChild" type="button">Criar e adicionar conteudo</button>
        <button class="btn btn-primary btn-admin" id="btnCreateNode" type="button">Criar item</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalNodeEditor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar item <span class="badge text-bg-light border ms-2" id="nodeNumberBadge">--</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="nodeEmpty" class="app-soft p-3 mb-3">
          <div class="small text-muted">Selecione um item na arvore para editar.</div>
        </div>

        <div id="nodeEditor" class="d-none">
          <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
            <span class="badge text-bg-light border">Kind: <span id="nodeKindBadge">-</span></span>
            <span class="badge text-bg-light border">Subtype: <span id="nodeSubtypeBadge">-</span></span>
            <span class="small text-muted ms-auto">Ao salvar, o aluno ve o novo conteudo imediatamente.</span>
          </div>

          <form id="frmNode" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="node_id" value="">

            <div class="col-12 col-lg-4">
              <label class="form-label">Titulo do item</label>
              <input class="form-control" id="nodeTitle" name="title" required>
            </div>

            <div class="col-12 col-lg-2">
              <label class="form-label">Kind</label>
              <select class="form-select" id="nodeKind" name="kind">
                <option value="container">container</option>
                <option value="content">content</option>
                <option value="action">action</option>
              </select>
            </div>

            <div class="col-12 col-lg-2">
              <label class="form-label">Subtype</label>
              <select class="form-select" id="nodeSubtype" name="subtype"></select>
            </div>

            <div class="col-12 col-lg-2">
              <label class="form-label">Publicado</label>
              <select class="form-select" id="nodePublished" name="is_published">
                <option value="1">Sim</option>
                <option value="0">Nao</option>
              </select>
            </div>

            <div class="col-12 col-lg-2">
              <label class="form-label">Soma %</label>
              <select class="form-select" id="nodeCountInProgress" name="count_in_progress_percent">
                <option value="1">Sim</option>
                <option value="0">Nao</option>
              </select>
              <div class="form-text">Quando estiver em "Nao", o item fica fora do percentual.</div>
            </div>

            <div class="col-12" id="boxMoodleMap">
              <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-link me-2"></i>Vinculo Moodle (sincronizacao)</div>
                <div class="row g-3">
                  <div class="col-12 col-lg-4">
                    <label class="form-label">CMID Moodle</label>
                    <input class="form-control" id="moodleCmid" type="number" min="1" placeholder="Ex.: 12345">
                  </div>
                  <div class="col-12 col-lg-8">
                    <label class="form-label">URL da view Moodle</label>
                    <input class="form-control" id="moodleViewUrl" type="text" readonly placeholder="Sera preenchida automaticamente pelo CMID">
                  </div>
                  <div class="col-12">
                    <div class="form-control bg-white text-muted small" style="height:auto; min-height:38px;">
                      Informe o CMID. A URL da view e capturada automaticamente e mostrada aqui.
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 d-none" id="boxVideo">
              <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-circle-play me-2"></i>Video</div>
                <div class="row g-3">
                  <div class="col-12 col-lg-7">
                    <label class="form-label">URL do video</label>
                    <input class="form-control" id="videoUrl" placeholder="https://...">
                  </div>
                  <div class="col-12 col-lg-3">
                    <label class="form-label">Provider</label>
                    <select class="form-select" id="videoProvider">
                      <option value="mp4">mp4</option>
                      <option value="hls">hls</option>
                      <option value="videofront">videofront</option>
                      <option value="youtube">youtube</option>
                      <option value="vimeo">vimeo</option>
                    </select>
                  </div>
                  <div class="col-12 col-lg-2">
                    <label class="form-label">Min. %</label>
                    <input class="form-control" id="videoMinPercent" type="number" min="0" max="100" placeholder="70">
                  </div>
                  <div class="col-12">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExampleVideofront">Exemplo videofront</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 d-none" id="boxPdf">
              <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-file-pdf me-2"></i>PDF / Download</div>
                <label class="form-label">file_path</label>
                <input class="form-control font-monospace" id="pdfPath" placeholder="/storage/courses/1/material.pdf">
              </div>
            </div>

            <div class="col-12 d-none" id="boxText">
              <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-align-left me-2"></i>Texto</div>
                <label class="form-label">HTML</label>
                <textarea class="form-control font-monospace" id="textHtml" rows="6" placeholder="<h2>Titulo</h2><p>Conteudo...</p>"></textarea>
              </div>
            </div>

            <div class="col-12 d-none" id="boxLink">
              <div class="border rounded-4 p-3 bg-light">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-up-right-from-square me-2"></i>Link / Download</div>
                <div class="row g-3">
                  <div class="col-12 col-lg-8">
                    <label class="form-label">URL</label>
                    <input class="form-control" id="linkUrl" placeholder="https://...">
                  </div>
                  <div class="col-12 col-lg-4">
                    <label class="form-label">Rotulo</label>
                    <input class="form-control" id="linkLabel" placeholder="Ex.: Abrir material">
                  </div>
                  <div class="col-12">
                    <div class="form-text">
                      Esse tipo abre em nova aba para o aluno. Para biblioteca digital, marque <strong>Exibir somente na biblioteca digital</strong>.
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ruleLibraryOnly">
                      <label class="form-check-label" for="ruleLibraryOnly">Exibir somente na biblioteca digital</label>
                      <div class="form-text">Quando marcado, o link nao aparece dentro do curso. Ele fica disponivel apenas na dashboard do aluno.</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="border rounded-4 p-3">
                <div class="fw-semibold mb-2"><i class="fa-solid fa-shield-halved me-2"></i>Regras do item</div>
                <div class="row g-2">
                  <div class="col-12 col-lg-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ruleSequential">
                      <label class="form-check-label" for="ruleSequential">Obedecer trava sequencial</label>
                      <div class="form-text">Se desmarcar, este item fica livre mesmo quando o curso estiver com trava.</div>
                    </div>
                  </div>
                  <div class="col-12 col-lg-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ruleRequireVideo">
                      <label class="form-check-label" for="ruleRequireVideo">Exigir video completo</label>
                    </div>
                  </div>
                  <div class="col-12 col-lg-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="ruleRequirePdf">
                      <label class="form-check-label" for="ruleRequirePdf">Exigir PDF visualizado</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="fw-semibold">Modo avancado</div>
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" id="toggleAdvanced">
                  <label class="form-check-label small text-muted" for="toggleAdvanced">Editar JSON manualmente</label>
                </div>
              </div>

              <div class="mt-2 d-none" id="nodeAdvanced">
                <div class="row g-3">
                  <div class="col-12 col-lg-6">
                    <label class="form-label">content_json</label>
                    <textarea class="form-control font-monospace" id="contentRaw" name="content" rows="7"></textarea>
                  </div>
                  <div class="col-12 col-lg-6">
                    <label class="form-label">rules_json</label>
                    <textarea class="form-control font-monospace" id="rulesRaw" name="rules" rows="7"></textarea>
                  </div>
                  <div class="col-12">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRebuildJson">
                      <i class="fa-solid fa-rotate me-1"></i> Regerar JSON pelos campos
                    </button>
                  </div>
                </div>
              </div>

              <div class="mt-2" id="jsonPreview"></div>
            </div>

            <div class="col-12">
              <div class="sticky-actions d-flex gap-2 align-items-center flex-wrap">
                <button class="btn btn-outline-primary" type="button" id="btnSaveNode">
                  <i class="fa-solid fa-floppy-disk me-1"></i> Salvar item
                </button>
                <button class="btn btn-outline-secondary" type="button" id="btnSaveNodeAndCreateChild">
                  <i class="fa-solid fa-diagram-next me-1"></i> Salvar e novo filho
                </button>
                <button class="btn btn-outline-success" type="button" id="btnCompleteNodeForEnrolled">
                  <i class="fa-solid fa-user-check me-1"></i> Concluir para inscritos
                </button>
                <button class="btn btn-outline-warning" type="button" id="btnCleanupNodeForNeverStarted">
                  <i class="fa-solid fa-eraser me-1"></i> Corrigir sem acesso
                </button>
                <button class="btn btn-outline-danger" type="button" id="btnDeleteNode">
                  <i class="fa-solid fa-trash me-1"></i> Excluir
                </button>

                <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
                  <input type="file" id="uploadFile" class="form-control form-control-sm" style="max-width:240px">
                  <button class="btn btn-sm btn-outline-secondary" type="button" id="btnUpload">
                    <i class="fa-solid fa-upload me-1"></i> Upload
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalTemplate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Modelo de curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">
          O modelo cria uma estrutura autoexplicativa com exemplos de modulo, video, PDF,
          texto, link, download e prova final.
        </p>

        <div class="row g-3 align-items-end">
          <div class="col-12 col-lg-8">
            <label class="form-label">Nome de um novo curso modelo (opcional)</label>
            <input class="form-control" id="templateCourseTitle" value="Curso Modelo" placeholder="Curso Modelo">
          </div>
          <div class="col-12 col-lg-4 d-grid">
            <button class="btn btn-outline-primary" id="btnCreateTemplateCourse" type="button">Criar curso modelo</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-admin" id="btnApplyTemplate" type="button">Inserir neste curso</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalCourseSettings" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Configuracoes do curso</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="frmCourse" class="row g-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="course_id" value="<?= (int)($course['id'] ?? 0) ?>">

          <div class="col-12 col-lg-6">
            <label class="form-label">Titulo</label>
            <input class="form-control" name="title" value="<?= h($course['title'] ?? '') ?>" required>
          </div>

          <div class="col-12 col-lg-6">
            <label class="form-label">Course ID Moodle</label>
            <input class="form-control" type="number" name="moodle_courseid" value="<?= h((string)($course['moodle_courseid'] ?? '')) ?>" min="1">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Acesso (dias)</label>
            <input class="form-control" type="number" name="access_days" value="<?= h((string)($course['access_days'] ?? '')) ?>" min="0">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Fluxo de estudo</label>
            <select class="form-select" name="enable_sequence_lock">
              <option value="1" <?= $sequenceLockEnabled ? 'selected' : '' ?>>Com trava sequencial</option>
              <option value="0" <?= !$sequenceLockEnabled ? 'selected' : '' ?>>Sem trava (livre)</option>
            </select>
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Biometria</label>
            <select class="form-select" name="require_biometric">
              <option value="0" <?= !$biometricRequired ? 'selected' : '' ?>>Sem biometria</option>
              <option value="1" <?= $biometricRequired ? 'selected' : '' ?>>Com biometria por foto</option>
            </select>
          </div>
          <div class="col-12 col-lg-4">
            <label class="form-label">Numeracao no menu</label>
            <select class="form-select" name="show_numbering">
              <option value="0" <?= !$showNumbering ? 'selected' : '' ?>>Nao exibir numeracao</option>
              <option value="1" <?= $showNumbering ? 'selected' : '' ?>>Exibir numeracao automatica</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Descricao</label>
            <textarea class="form-control" name="description" rows="2"><?= h($course['description'] ?? '') ?></textarea>
          </div>

          <div class="col-12 d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" type="button" id="btnSaveCourse"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar curso</button>
            <?php if ($status !== 'published'): ?>
              <button class="btn btn-success" type="button" id="btnPublishCourse"><i class="fa-solid fa-rocket me-1"></i>Publicar</button>
            <?php else: ?>
              <button class="btn btn-outline-secondary" type="button" id="btnUnpublishCourse"><i class="fa-solid fa-circle-pause me-1"></i>Voltar para rascunho</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalExam" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Prova final (quiz Moodle)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form id="frmExam" class="row g-3">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="course_id" value="<?= (int)($course['id'] ?? 0) ?>">

          <div class="col-12 col-lg-4">
            <label class="form-label">CMID do quiz</label>
            <input class="form-control" type="number" name="quiz_cmid" min="1" value="<?= h((string)($exam['quiz_cmid'] ?? '')) ?>" placeholder="Ex.: 12345">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Nota minima (opcional)</label>
            <input class="form-control" name="min_grade" value="<?= h((string)($exam['min_grade'] ?? '')) ?>" placeholder="Ex.: 70,00">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label">Titulo da prova (opcional)</label>
            <input class="form-control" name="exam_title" value="<?= h((string)($exam['exam_title'] ?? '')) ?>" placeholder="Ex.: Prova final">
          </div>

          <div class="col-12">
            <button class="btn btn-outline-primary" type="button" id="btnSaveExam"><i class="fa-solid fa-floppy-disk me-1"></i>Salvar prova</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
window.BUILDER_BOOT = {
  base: "<?= h(App::base_url('')) ?>",
  csrf: "<?= h($csrf) ?>",
  courseId: <?= (int)($course['id'] ?? 0) ?>
};
</script>
