<?php
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="page-header">
  <div>
    <div class="page-title">Mock Teste</div>
    <div class="page-subtitle">Tela rápida para validar cores, botões, cards e componentes.</div>
  </div>
  <div class="toolbar">
    <button class="btn btn-outline-secondary" id="btnMockToast"><i class="fa-solid fa-bell me-1"></i> Toast</button>
    <button class="btn btn-primary" id="btnMockSwal"><i class="fa-solid fa-bolt me-1"></i> Alert</button>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card app-card">
      <div class="card-body">
        <div class="section-title">Cards e Métricas</div>
        <div class="row g-2">
          <div class="col-6">
            <div class="stat-card">
              <div class="stat-value">12</div>
              <div class="stat-label">Cursos ativos</div>
            </div>
          </div>
          <div class="col-6">
            <div class="stat-card">
              <div class="stat-value">78%</div>
              <div class="stat-label">Média de progresso</div>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <span class="chip"><i class="fa-solid fa-star"></i> Destaque</span>
          <span class="badge-soft ms-2">Novo</span>
        </div>
      </div>
    </div>

    <div class="card app-card mt-3">
      <div class="card-body">
        <div class="section-title">Formulário</div>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label">Título</label>
            <input class="form-control" placeholder="Ex.: Curso de Reciclagem">
          </div>
          <div class="col-6">
            <label class="form-label">Status</label>
            <select class="form-select">
              <option>Publicado</option>
              <option>Rascunho</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Carga horária</label>
            <input class="form-control" placeholder="40h">
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-primary">Salvar</button>
          <button class="btn btn-outline-secondary">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card app-card">
      <div class="card-body">
        <div class="section-title">Tabela</div>
        <div class="table-responsive">
          <table class="table app-table">
            <thead>
              <tr>
                <th>Curso</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Reciclagem CNH</td>
                <td><span class="badge text-bg-success">Publicado</span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary">Editar</button>
                </td>
              </tr>
              <tr>
                <td>Direção Defensiva</td>
                <td><span class="badge text-bg-secondary">Rascunho</span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary">Preview</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="mt-4">
  <div class="section-title mb-2">Mock Área de Estudos</div>
  <div class="study-shell hotmart-theme">
    <header class="study-header hotmart-header">
      <div class="study-header-left d-flex align-items-center gap-2">
        <button class="btn btn-hotmart" type="button"><i class="fa-solid fa-bars"></i></button>
        <div class="study-title-wrap">
          <div class="fw-bold text-truncate hotmart-title"><i class="fa-solid fa-graduation-cap me-2 text-hotmart"></i><span class="study-title-text">Curso Mock</span></div>
          <div class="study-subtitle text-truncate">Descrição curta do curso</div>
        </div>
      </div>
      <div class="study-header-center d-none d-lg-flex align-items-center gap-2">
        <div class="study-meta-chip"><i class="fa-solid fa-layer-group me-1"></i>8 itens</div>
        <div class="study-meta-chip"><i class="fa-solid fa-check me-1"></i>3 concluídos</div>
      </div>
      <div class="study-header-right d-flex align-items-center gap-2">
        <div class="progress study-progress hotmart-progress">
          <div class="progress-bar bg-hotmart-gradient" style="width:45%"></div>
        </div>
        <span class="study-progress-label">45%</span>
      </div>
    </header>

    <div class="study-layout d-flex">
      <aside class="study-sidebar d-none d-md-flex flex-column hotmart-sidebar shadow-lg">
        <div class="p-3 border-bottom hotmart-sidebar-header">
          <div class="fw-bold fs-6 mb-1 text-hotmart"><i class="fa-solid fa-list-ul me-2"></i>Trilha do curso</div>
          <input type="text" class="form-control form-control-sm hotmart-search" placeholder="Buscar aula...">
        </div>
        <div class="rail-list flex-grow-1 overflow-auto px-2 pb-3 hotmart-rail-list">
          <div class="trail-item is-active d-flex align-items-center gap-2 py-3 px-2">
            <div class="trail-dot">1</div>
            <div class="flex-grow-1">
              <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 01</div>
              <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
            </div>
          </div>
          <div class="trail-item is-done d-flex align-items-center gap-2 py-3 px-2">
            <div class="trail-dot"><i class="fa-solid fa-check"></i></div>
            <div class="flex-grow-1">
              <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-pdf me-2"></i>Material PDF</div>
              <div class="small text-muted"><span class="badge bg-light text-dark me-1">PDF</span><span class="badge bg-success-subtle text-success">Concluído</span></div>
            </div>
          </div>
        </div>
      </aside>

      <main class="study-main flex-grow-1 px-0 px-md-4 py-3">
        <section class="study-content card border-0 shadow-sm p-4 mb-4 bg-white rounded-4 hotmart-content">
          <div class="d-flex align-items-center gap-2 mb-2 hotmart-content-header">
            <span class="hotmart-content-icon"><i class="fa-solid fa-circle-play text-hotmart"></i></span>
            <span class="fw-semibold text-hotmart">Vídeo-aula</span>
            <span class="badge bg-hotmart-pct ms-2"><i class="fa-solid fa-bolt me-1"></i>45%</span>
          </div>
          <h2 class="fs-5 fw-bold mb-2 hotmart-lesson-title">Aula de Introdução</h2>
          <div class="mb-3 text-muted small hotmart-lesson-duration">12 min</div>
          <div class="ratio ratio-16x9 mb-3" style="background:#000;border-radius:12px;"></div>
          <button class="btn btn-hotmart-success hotmart-btn-done w-100"><i class="fa-solid fa-check me-1"></i> Concluir aula</button>
        </section>
      </main>
    </div>
  </div>
</div>
