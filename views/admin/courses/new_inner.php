<?php
use App\App;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<div class="admin-toolbar mb-3">
  <a class="btn btn-outline-secondary btn-lg" href="<?= App::base_url('/admin/courses') ?>">
    <i class="fa-solid fa-arrow-left me-2"></i> Voltar para cursos
  </a>
  <div class="admin-panel__hint">Fluxo guiado em 4 passos.</div>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger mt-3 mb-0"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= App::base_url('/admin/courses/new') ?>" id="frmNewCourse" class="mt-3">
  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
  <input type="hidden" name="structure" id="structure" value="simple">

  <section class="admin-card">
    <div class="admin-card__title">1. Dados principais</div>
    <div class="row g-3">
      <div class="col-12 col-lg-8">
        <label class="form-label">Titulo do curso</label>
        <input class="form-control form-control-lg" name="title" id="title" required placeholder="Ex.: Reciclagem CNH - Videoaulas">
        <div class="admin-help">Nome exibido para admin e aluno.</div>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Slug</label>
        <input class="form-control form-control-lg" name="slug" id="slug" required placeholder="reciclagem-cnh-videoaulas">
        <div class="admin-help">Gerado automaticamente, pode ser ajustado.</div>
      </div>
    </div>
  </section>

  <section class="admin-card">
    <div class="admin-card__title">2. Modelo de estrutura</div>
    <div class="choice-grid">
      <button type="button" class="choice-card is-active" id="choiceSimple" data-structure="simple">
        <span class="choice-card__icon"><i class="fa-solid fa-stream"></i></span>
        <span>
          <span class="d-block fw-semibold">Trilha simples</span>
          <span class="admin-help">Aulas em sequencia unica. Melhor para cursos curtos.</span>
        </span>
      </button>

      <button type="button" class="choice-card" id="choiceComplex" data-structure="complex">
        <span class="choice-card__icon"><i class="fa-regular fa-folder-open"></i></span>
        <span>
          <span class="d-block fw-semibold">Com modulos</span>
          <span class="admin-help">Modulo > topico > aula. Melhor para cursos grandes.</span>
        </span>
      </button>
    </div>
    <div class="admin-help mt-2">Toda essa estrutura pode ser reorganizada depois no Builder.</div>
  </section>

  <section class="admin-card">
    <div class="admin-card__title">3. Assistente de esqueleto</div>
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" id="wizardEnabled" name="wizard_enabled" value="1" checked>
      <label class="form-check-label" for="wizardEnabled">Criar estrutura inicial automatica</label>
    </div>

    <div id="wizardFields">
      <div class="row g-3 align-items-end">
        <div class="col-6 col-lg-3" id="wizardModulesWrap">
          <label class="form-label">Qtd. de modulos</label>
          <input class="form-control" type="number" name="wizard_modules" min="1" max="20" value="3">
        </div>
        <div class="col-6 col-lg-3">
          <label class="form-label" id="wizardUnitsLabel">Aulas por modulo</label>
          <input class="form-control" type="number" name="wizard_units" min="1" max="30" value="4">
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Tipo base das aulas</label>
          <select class="form-select" name="wizard_default_type">
            <option value="video" selected>Video</option>
            <option value="text">Texto</option>
          </select>
        </div>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-6 col-lg-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="wizardWelcome" name="wizard_welcome" value="1" checked>
            <label class="form-check-label" for="wizardWelcome">Incluir boas-vindas</label>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="wizardMaterials" name="wizard_materials" value="1" checked>
            <label class="form-check-label" for="wizardMaterials">Materiais (PDF/Texto)</label>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="wizardExercises" name="wizard_exercises" value="1">
            <label class="form-check-label" for="wizardExercises">Exercicios de fixacao</label>
          </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="wizardFinal" name="wizard_final_exam" value="1" checked>
            <label class="form-check-label" for="wizardFinal">Prova final (quiz)</label>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-card">
    <div class="admin-card__title">4. Integracao com Moodle</div>
    <div class="row g-3">
      <div class="col-12 col-lg-4">
        <label class="form-label">Course ID Moodle</label>
        <input class="form-control" type="number" name="moodle_courseid" min="1" placeholder="Ex.: 60">
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Acesso (dias)</label>
        <input class="form-control" type="number" name="access_days" min="0" placeholder="Ex.: 45">
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Link do curso no Moodle</label>
        <input class="form-control" type="text" id="moodleLink" placeholder="Cole a URL (opcional)">
        <div class="admin-help">Se o link tiver ?id=, o campo acima sera preenchido.</div>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Fluxo de estudo</label>
        <select class="form-select" name="enable_sequence_lock">
          <option value="1" selected>Com trava sequencial</option>
          <option value="0">Sem trava (livre)</option>
        </select>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Biometria</label>
        <select class="form-select" name="require_biometric">
          <option value="0" selected>Sem biometria</option>
          <option value="1">Com biometria por foto</option>
        </select>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label">Liberacao prova final (horas)</label>
        <input class="form-control" type="number" name="final_exam_unlock_hours" min="0" step="1" value="0" placeholder="Ex.: 72">
        <div class="admin-help">0 desativa. Ex.: 72 = libera 72h apos o primeiro acesso no curso.</div>
      </div>
    </div>
  </section>

  <div class="d-flex flex-wrap gap-2 mt-3">
    <button class="btn btn-primary btn-admin btn-lg" type="submit">
      <i class="fa-solid fa-wand-magic-sparkles me-2"></i> Criar e abrir builder
    </button>
    <a class="btn btn-outline-secondary btn-lg" href="<?= App::base_url('/admin/courses') ?>">Cancelar</a>
  </div>
</form>

<script>
(function () {
  const title = document.getElementById('title');
  const slug = document.getElementById('slug');
  const structure = document.getElementById('structure');

  const choiceSimple = document.getElementById('choiceSimple');
  const choiceComplex = document.getElementById('choiceComplex');

  const wizardEnabled = document.getElementById('wizardEnabled');
  const wizardFields = document.getElementById('wizardFields');
  const wizardModulesWrap = document.getElementById('wizardModulesWrap');
  const wizardUnitsLabel = document.getElementById('wizardUnitsLabel');

  const moodleLink = document.getElementById('moodleLink');

  const slugify = (value) => {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 120) || 'curso';
  };

  const setStructure = (value) => {
    structure.value = value;

    choiceSimple.classList.toggle('is-active', value === 'simple');
    choiceComplex.classList.toggle('is-active', value === 'complex');

    if (wizardModulesWrap) {
      wizardModulesWrap.style.display = value === 'simple' ? 'none' : '';
    }

    if (wizardUnitsLabel) {
      wizardUnitsLabel.textContent = value === 'simple' ? 'Qtd. total de aulas' : 'Aulas por modulo';
    }
  };

  title?.addEventListener('input', () => {
    if (!slug.dataset.touched) {
      slug.value = slugify(title.value);
    }
  });

  slug?.addEventListener('input', () => {
    slug.dataset.touched = '1';
  });

  choiceSimple?.addEventListener('click', () => setStructure('simple'));
  choiceComplex?.addEventListener('click', () => setStructure('complex'));

  wizardEnabled?.addEventListener('change', () => {
    const enabled = wizardEnabled.checked;
    if (wizardFields) {
      wizardFields.style.display = enabled ? '' : 'none';
      wizardFields.querySelectorAll('input, select').forEach((el) => {
        el.disabled = !enabled;
      });
    }
  });

  moodleLink?.addEventListener('change', () => {
    const value = String(moodleLink.value || '').trim();
    if (!value) return;

    const match = value.match(/[?&]id=(\d+)/) || value.match(/\/course\/view\.php\?id=(\d+)/);
    if (!match) return;

    const id = parseInt(match[1], 10);
    if (!id || id < 1) return;

    const moodleInput = document.querySelector('input[name="moodle_courseid"]');
    if (moodleInput && !moodleInput.value) {
      moodleInput.value = String(id);
    }
  });

  setStructure('simple');
  wizardEnabled?.dispatchEvent(new Event('change'));
})();
</script>
