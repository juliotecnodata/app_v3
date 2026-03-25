<div class="study-shell hotmart-theme">
  <header class="study-header hotmart-header">
    <div class="study-header-left d-flex align-items-center gap-2">
      <button
        id="btnToggleMenu"
        class="btn btn-hotmart"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#studyOffcanvas"
        aria-controls="studyOffcanvas"
        aria-label="Abrir menu do curso"
        aria-pressed="false"
      >
        <i class="fa-solid fa-bars"></i>
      </button>
      <div class="study-title-wrap">
        <div class="fw-bold text-truncate hotmart-title"><i class="fa-solid fa-graduation-cap me-2 text-hotmart"></i><span class="study-title-text">Curso Mock</span></div>
        <div class="study-subtitle text-truncate">Treinamento completo • Acesso liberado</div>
      </div>
    </div>
    <div class="study-header-center d-none d-lg-flex align-items-center gap-2">
      <div class="study-meta-chip"><i class="fa-solid fa-layer-group me-1"></i>15 itens</div>
      <div class="study-meta-chip"><i class="fa-solid fa-check me-1"></i>2 concluídos</div>
    </div>
    <div class="study-header-right d-flex align-items-center gap-2">
      <div class="progress study-progress hotmart-progress">
        <div id="studyProgressBar" class="progress-bar bg-hotmart-gradient" style="width:35%"></div>
      </div>
      <span id="studyProgressLabel" class="study-progress-label">35%</span>
    </div>
  </header>

  <div class="offcanvas offcanvas-start hotmart-offcanvas d-md-none" tabindex="-1" id="studyOffcanvas" aria-labelledby="studyOffcanvasLabel" data-bs-backdrop="false" data-bs-scroll="true">
    <div class="offcanvas-header hotmart-offcanvas-header">
      <h5 class="offcanvas-title fw-semibold text-hotmart" id="studyOffcanvasLabel"><i class="fa-solid fa-list-ul me-2"></i>Trilha do curso</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body pt-2">
      <input id="trailSearchMobile" type="text" class="form-control form-control-sm hotmart-search mb-3" placeholder="Buscar aula, módulo...">
      <div class="rail-list hotmart-rail-list">
        <div class="small text-muted px-2 mt-2 mb-1">Módulo 1 • Fundamentos</div>
        <div class="trail-item is-active d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="0">
          <div class="trail-dot">1</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 01 • Introdução</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="1">
          <div class="trail-dot">2</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-pdf me-2"></i>Guia do aluno</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">PDF</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="2">
          <div class="trail-dot">3</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 02 • Fundamentos</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item is-done d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="3">
          <div class="trail-dot"><i class="fa-solid fa-check"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Checklist inicial</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span><span class="badge bg-success-subtle text-success">Concluído</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="4">
          <div class="trail-dot">5</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Quiz rápido</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>

        <div class="small text-muted px-2 mt-3 mb-1">Módulo 2 • Prática</div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="5">
          <div class="trail-dot">6</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 03 • Prática guiada</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="6">
          <div class="trail-dot">7</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-zipper me-2"></i>Template do projeto</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Arquivo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="7">
          <div class="trail-dot">8</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 04 • Demonstração</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item is-done d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="8">
          <div class="trail-dot"><i class="fa-solid fa-check"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Resumo prático</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span><span class="badge bg-success-subtle text-success">Concluído</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="9">
          <div class="trail-dot">10</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Desafio 01</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>

        <div class="small text-muted px-2 mt-3 mb-1">Módulo 3 • Avaliação</div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="10">
          <div class="trail-dot">11</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 05 • Avançado</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="11">
          <div class="trail-dot">12</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Estudos de caso</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="12">
          <div class="trail-dot">13</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-pdf me-2"></i>Material extra</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">PDF</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="13">
          <div class="trail-dot">14</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Simulado final</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="14">
          <div class="trail-dot">15</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Encerramento</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="study-layout d-flex">
    <aside class="study-sidebar d-none d-md-flex flex-column hotmart-sidebar shadow-lg">
      <div class="p-3 border-bottom hotmart-sidebar-header">
        <div class="fw-bold fs-6 mb-1 text-hotmart"><i class="fa-solid fa-list-ul me-2"></i>Trilha do curso</div>
        <input id="trailSearchDesktop" type="text" class="form-control form-control-sm hotmart-search" placeholder="Buscar aula, módulo...">
      </div>
      <div class="rail-list flex-grow-1 overflow-auto px-2 pb-3 hotmart-rail-list">
        <!-- Módulo 1 -->
        <div class="small text-muted px-2 mt-2 mb-1">Módulo 1 • Fundamentos</div>
        <div class="trail-item is-active d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="0">
          <div class="trail-dot">1</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 01 • Introdução</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="1">
          <div class="trail-dot">2</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-pdf me-2"></i>Guia do aluno</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">PDF</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="2">
          <div class="trail-dot">3</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 02 • Fundamentos</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item is-done d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="3">
          <div class="trail-dot"><i class="fa-solid fa-check"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Checklist inicial</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span><span class="badge bg-success-subtle text-success">Concluído</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="4">
          <div class="trail-dot">5</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Quiz rápido</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>

        <!-- Módulo 2 -->
        <div class="small text-muted px-2 mt-3 mb-1">Módulo 2 • Prática</div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="5">
          <div class="trail-dot">6</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 03 • Prática guiada</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="6">
          <div class="trail-dot">7</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-zipper me-2"></i>Template do projeto</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Arquivo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="7">
          <div class="trail-dot">8</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 04 • Demonstração</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item is-done d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="8">
          <div class="trail-dot"><i class="fa-solid fa-check"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Resumo prático</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span><span class="badge bg-success-subtle text-success">Concluído</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="9">
          <div class="trail-dot">10</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Desafio 01</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>

        <!-- Módulo 3 -->
        <div class="small text-muted px-2 mt-3 mb-1">Módulo 3 • Avaliação</div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="10">
          <div class="trail-dot">11</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Aula 05 • Avançado</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="11">
          <div class="trail-dot">12</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-align-left me-2"></i>Estudos de caso</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Leitura</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="12">
          <div class="trail-dot">13</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-file-pdf me-2"></i>Material extra</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">PDF</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="13">
          <div class="trail-dot">14</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-bolt me-2"></i>Simulado final</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Ação</span></div>
          </div>
        </div>
        <div class="trail-item d-flex align-items-center gap-2 py-3 px-2" data-lesson-index="14">
          <div class="trail-dot">15</div>
          <div class="flex-grow-1">
            <div class="fw-semibold text-truncate"><i class="fa-solid fa-circle-play me-2"></i>Encerramento</div>
            <div class="small text-muted"><span class="badge bg-light text-dark me-1">Vídeo</span></div>
          </div>
        </div>
      </div>
    </aside>

    <main class="study-main flex-grow-1 px-0 px-md-4 py-3">
      <div class="study-title-block mb-3">
        <div class="d-flex align-items-center gap-2">
          <span class="hotmart-content-icon" id="lessonTypeIcon"><i class="fa-solid fa-circle-play text-hotmart"></i></span>
          <nav class="study-breadcrumb" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
              <li id="lessonBreadcrumbModule" class="breadcrumb-item">Módulo 1 • Fundamentos</li>
              <li id="lessonBreadcrumbLesson" class="breadcrumb-item active" aria-current="page">Aula 01 • Introdução</li>
            </ol>
          </nav>
        </div>
      </div>
      <section class="study-content card border-0 shadow-sm p-4 mb-4 bg-white rounded-4 hotmart-content">
        <div id="lessonMedia" class="ratio ratio-16x9 mb-3" style="background:#000;border-radius:12px;"></div>
        <div id="lessonBody" class="text-muted small mb-3">Conteúdo introdutório para o aluno começar com confiança.</div>
        <button id="lessonAction" class="btn btn-hotmart-success hotmart-btn-done w-100"><i class="fa-solid fa-check me-1"></i> Concluir aula</button>
      </section>
    </main>
  </div>

  <nav class="study-nav-footer navbar fixed-bottom p-0">
    <div class="container-fluid">
      <div class="nav-actions d-flex justify-content-center gap-3 py-2">
        <button id="btnPrevLesson" class="btn btn-lg btn-outline-secondary px-4 d-flex align-items-center justify-content-center gap-2">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Anterior</span>
        </button>
        <button id="btnNextLesson" class="btn btn-lg btn-primary px-4 d-flex align-items-center justify-content-center gap-2">
          <span>Próximo</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
      </div>
    </div>
  </nav>
</div>

<script>
  window.MOCK_STUDY = {
    startIndex: 0,
    lessons: [
      { title: 'Introdução', type: 'Vídeo-aula', duration: '12 min', pct: 6, media: 'video', body: 'Boas-vindas ao curso e visão geral do que vamos construir.' },
      { title: 'Guia do aluno', type: 'PDF de apoio', duration: '6 páginas', pct: 12, media: 'pdf', body: 'Baixe o guia com dicas de estudo, materiais e cronograma.' },
      { title: 'Fundamentos', type: 'Vídeo-aula', duration: '15 min', pct: 18, media: 'video', body: 'Conceitos essenciais para seguir com segurança nas próximas etapas.' },
      { title: 'Checklist inicial', type: 'Leitura', duration: '3 min', pct: 24, media: 'text', body: 'Checklist rápido para preparar ambiente e revisar pontos-chave.' },
      { title: 'Quiz rápido', type: 'Atividade', duration: '5 questões', pct: 30, media: 'action', body: 'Teste rápido para validar entendimento do módulo 1.' },

      { title: 'Prática guiada', type: 'Vídeo-aula', duration: '20 min', pct: 36, media: 'video', body: 'Passo a passo com prática real para fixar o conteúdo.' },
      { title: 'Template do projeto', type: 'Arquivo', duration: 'Download', pct: 42, media: 'pdf', body: 'Baixe o template para acelerar o desenvolvimento do projeto.' },
      { title: 'Demonstração', type: 'Vídeo-aula', duration: '18 min', pct: 48, media: 'video', body: 'Demonstração completa e boas práticas do módulo.' },
      { title: 'Resumo prático', type: 'Leitura', duration: '4 min', pct: 54, media: 'text', body: 'Resumo prático com pontos mais importantes da etapa.' },
      { title: 'Desafio 01', type: 'Atividade', duration: '10 min', pct: 60, media: 'action', body: 'Desafio aplicado para consolidar a prática.' },

      { title: 'Avançado', type: 'Vídeo-aula', duration: '22 min', pct: 70, media: 'video', body: 'Técnicas avançadas para elevar a qualidade do projeto.' },
      { title: 'Estudos de caso', type: 'Leitura', duration: '8 min', pct: 78, media: 'text', body: 'Casos reais para comparar boas decisões e resultados.' },
      { title: 'Material extra', type: 'PDF de apoio', duration: '8 páginas', pct: 86, media: 'pdf', body: 'Conteúdos extras e referências para aprofundamento.' },
      { title: 'Simulado final', type: 'Atividade', duration: '10 questões', pct: 94, media: 'action', body: 'Simulado final para validar o aprendizado.' },
      { title: 'Encerramento', type: 'Vídeo-aula', duration: '6 min', pct: 100, media: 'video', body: 'Encerramento com próximos passos e orientação final.' }
    ]
  };
</script>
