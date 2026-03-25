<?php
function h_bio_cfg($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$mode = (string)($biometric_mode ?? 'simple');
$error = (string)($error ?? '');
$flash = $flash ?? null;
?>

<?php if ($flash && !empty($flash['msg'])): ?>
  <div class="alert alert-<?= h_bio_cfg($flash['type'] ?? 'info') ?> admin-inline-alert" role="alert">
    <?= h_bio_cfg($flash['msg']) ?>
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger admin-inline-alert" role="alert">
    <?= h_bio_cfg($error) ?>
  </div>
<?php endif; ?>

<section class="admin-settings-panel">
  <form method="post" class="admin-settings-form">
    <input type="hidden" name="csrf" value="<?= h_bio_cfg($csrf ?? '') ?>">

    <div class="choice-grid">
      <label class="choice-card <?= $mode === 'simple' ? 'is-active' : '' ?>">
        <input type="radio" class="d-none" name="biometric_provider_mode" value="simple" <?= $mode === 'simple' ? 'checked' : '' ?>>
        <span class="choice-card__icon"><i class="fa-solid fa-camera"></i></span>
        <span>
          <strong class="d-block mb-1">Biometria simples</strong>
          <span class="text-muted small d-block">Valida a captura no app e registra a auditoria sem enviar para a Gryfo.</span>
        </span>
      </label>

      <label class="choice-card <?= $mode === 'gryfo' ? 'is-active' : '' ?>">
        <input type="radio" class="d-none" name="biometric_provider_mode" value="gryfo" <?= $mode === 'gryfo' ? 'checked' : '' ?>>
        <span class="choice-card__icon"><i class="fa-solid fa-fingerprint"></i></span>
        <span>
          <strong class="d-block mb-1">Biometria Gryfo</strong>
          <span class="text-muted small d-block">Envia a selfie para validacao externa, aplica score e respeita as regras de qualidade.</span>
        </span>
      </label>
    </div>

    <div class="admin-settings-panel__summary">
      <div>
        <span class="admin-settings-panel__label">Modo atual</span>
        <strong><?= $mode === 'gryfo' ? 'Gryfo' : 'Simples' ?></strong>
      </div>
      <button type="submit" class="btn btn-ui btn-ui--primary">
        <i class="fa-solid fa-floppy-disk"></i> Salvar configuracao
      </button>
    </div>
  </form>
</section>

<section class="admin-settings-notes">
  <article class="admin-settings-note">
    <h3>Quando usar simples</h3>
    <p>Use quando quiser apenas capturar a selfie e registrar no app, sem validacao externa.</p>
  </article>
  <article class="admin-settings-note">
    <h3>Quando usar Gryfo</h3>
    <p>Use quando quiser comparar a selfie com o cadastro e rejeitar fotos ruins ou fora do padrao.</p>
  </article>
</section>
