<?php
use App\App;

$usernamePrefill = htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
$logoUrl = App::base_url('/storage/images/logotecnodatav.svg');
?>
<section class="auth-model-shell">
  <div class="auth-model-shell__backdrop" aria-hidden="true"></div>

  <div class="auth-model-wrap">
    <header class="auth-model-head">
      <a class="auth-model-logo" href="<?= App::base_url('/login') ?>" aria-label="TecEAD Transito">
        <img
          class="auth-model-logo__image"
          src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
          alt="TecEAD Transito">
      </a>

      <h1 class="auth-model-title">Cursos de Educacao <span>para o Transito</span></h1>
    </header>

    <div class="auth-model-grid">
      <div class="auth-model-video d-none d-lg-block">
        <div class="auth-model-video__frame ratio ratio-16x9">
          <iframe
            src="https://tecnodata.videotecaead.com.br/Embed/code/APRESENTACAO_PLATAFORMA"
            title="Apresentacao da Plataforma"
            frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen></iframe>
        </div>
      </div>

      <div class="auth-model-panel">
        <?php if (!empty($login_error)): ?>
          <div class="alert alert-danger auth-alert auth-model-alert" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars((string)$login_error, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endif; ?>

        <div class="card auth-model-card">
          <div class="card-body">
            <div class="auth-model-card__intro">
              <div class="auth-model-card__title">Acesse sua conta</div>
              <div class="auth-model-card__subtitle">Informe seu usuario e senha para entrar</div>
            </div>

            <form id="loginform" method="post" action="<?= App::base_url('/login') ?>" class="auth-model-form" novalidate autocomplete="on">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$csrf, ENT_QUOTES, 'UTF-8') ?>">

              <div class="form-floating auth-model-floating mb-3">
                <input
                  id="username"
                  name="username"
                  type="text"
                  class="form-control"
                  placeholder="Usuario"
                  autocomplete="username"
                  value="<?= $usernamePrefill ?>"
                  required
                  autocapitalize="none"
                  autocorrect="off"
                  spellcheck="false"
                  inputmode="text">
                <label for="username">Usuario</label>
              </div>

              <div class="form-floating auth-model-floating mb-3">
                <input
                  id="password"
                  name="password"
                  type="password"
                  class="form-control"
                  placeholder="Senha"
                  autocomplete="current-password"
                  required
                  autocapitalize="none"
                  autocorrect="off"
                  spellcheck="false">
                <label for="password">Senha</label>
              </div>

              <button type="submit" class="btn auth-model-submit w-100">
                Entrar
              </button>

              <div class="d-lg-none mt-3">
                <button
                  class="btn auth-model-preview-btn w-100"
                  type="button"
                  data-bs-toggle="collapse"
                  data-bs-target="#authModelVideoMobile"
                  aria-expanded="false"
                  aria-controls="authModelVideoMobile">
                  Ver apresentacao
                </button>

                <div class="collapse mt-3" id="authModelVideoMobile">
                  <div class="auth-model-video__frame ratio ratio-16x9">
                    <iframe
                      src="https://tecnodata.videotecaead.com.br/Embed/code/APRESENTACAO_PLATAFORMA"
                      title="Apresentacao da Plataforma"
                      frameborder="0"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      allowfullscreen></iframe>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
