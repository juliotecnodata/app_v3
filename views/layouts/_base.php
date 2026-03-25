<?php
use App\App;

$flash = $flash ?? null;
$csrf  = $csrf ?? \App\Csrf::token();

$title = $title ?? 'Tecnodata - Portal do Aluno';
$base  = App::base_url('');
$asset = function(string $p): string {
  $clean = ltrim($p, '/');
  $url = App::base_url('/assets/' . $clean);
  $full = APP_DIR . '/assets/' . str_replace(['\\', '..'], ['/', ''], $clean);
  if (is_file($full)) {
    $url .= '?v=' . (string)filemtime($full);
  }
  return $url;
};

$css = $css ?? [];
$js = $js ?? [];
$extra_css = $extra_css ?? [];
$extra_js = $extra_js ?? [];
$css = array_values(array_merge($css, $extra_css));
$js = array_values(array_merge($js, $extra_js));
$body_class = $body_class ?? '';
$nav_partial = $nav_partial ?? null;
$include_plyr = $include_plyr ?? false;
$include_tawk = $include_tawk ?? false;
$tawk_page = $tawk_page ?? '';
$full_width = $full_width ?? false;
$content_padding = $content_padding ?? true;
$pad_class = $content_padding ? 'py-3 py-lg-4' : 'p-0';

$tawk_embed_src = (string)\App\App::cfg('tawk_embed_src', 'https://embed.tawk.to/642476d731ebfa0fe7f566a1/1gsn70esc');
$tawk_enabled = $include_tawk && $tawk_embed_src !== '';
$tawk_payload = null;
$tawk_context_url = App::base_url('/api/chat/context');
$resolved_tawk_user = null;
$bootstrap_user_alert = null;
$alert_page = trim((string)($alert_page ?? ''));
if ($alert_page === '') {
  $alert_page = '';
  if ((string)$tawk_page === 'dashboard') {
    $alert_page = 'dashboard';
  } elseif ((string)$tawk_page === 'estudo') {
    $alert_page = 'study';
  } elseif ((string)$tawk_page === 'biometric') {
    $alert_page = 'biometric';
  }
}
$alert_course_id = (int)($alert_course_id ?? 0);
if ($alert_course_id <= 0 && isset($course)) {
  if (is_array($course)) {
    $alert_course_id = (int)($course['id'] ?? 0);
  } elseif (is_object($course)) {
    $alert_course_id = (int)($course->id ?? 0);
  }
}

if (\function_exists('isloggedin') && \isloggedin() && \function_exists('isguestuser') && !\isguestuser()) {
  try {
    $resolved_tawk_user = \App\Auth::user();
  } catch (\Throwable $_e) {
    $resolved_tawk_user = null;
  }
}

if ($alert_page !== '' && $resolved_tawk_user !== null) {
  try {
    $alertUserId = 0;
    if (is_object($resolved_tawk_user)) {
      $alertUserId = (int)($resolved_tawk_user->id ?? 0);
    } elseif (is_array($resolved_tawk_user)) {
      $alertUserId = (int)($resolved_tawk_user['id'] ?? 0);
    }

    if ($alertUserId > 0) {
      $bootstrap_user_alert = \App\UserAlertService::current_for_user($alertUserId, $alert_page, $alert_course_id);
    }
  } catch (\Throwable $_e) {
    $bootstrap_user_alert = null;
  }
}

if ($tawk_enabled) {
  $tawk_user_id = 0;
  $tawk_firstname = '';
  $tawk_lastname = '';
  $tawk_username = '';
  $tawk_email = '';

  if ($resolved_tawk_user !== null) {
    if (is_object($resolved_tawk_user)) {
      $tawk_user_id = (int)($resolved_tawk_user->id ?? 0);
      $tawk_firstname = trim((string)($resolved_tawk_user->firstname ?? ''));
      $tawk_lastname = trim((string)($resolved_tawk_user->lastname ?? ''));
      $tawk_username = trim((string)($resolved_tawk_user->username ?? ''));
      $tawk_email = trim((string)($resolved_tawk_user->email ?? ''));
    } elseif (is_array($resolved_tawk_user)) {
      $tawk_user_id = (int)($resolved_tawk_user['id'] ?? 0);
      $tawk_firstname = trim((string)($resolved_tawk_user['firstname'] ?? ''));
      $tawk_lastname = trim((string)($resolved_tawk_user['lastname'] ?? ''));
      $tawk_username = trim((string)($resolved_tawk_user['username'] ?? ''));
      $tawk_email = trim((string)($resolved_tawk_user['email'] ?? ''));
    }
  }

  $tawk_course = '';
  $tawk_course_id = 0;
  if (isset($course)) {
    if (is_array($course)) {
      $tawk_course = trim((string)($course['title'] ?? ''));
      $tawk_course_id = (int)($course['id'] ?? 0);
    } elseif (is_object($course)) {
      $tawk_course = trim((string)($course->title ?? ''));
      $tawk_course_id = (int)($course->id ?? 0);
    }
  }
  if ($tawk_course === '') {
    if ($tawk_page === 'dashboard') {
      $tawk_course = 'Dashboard';
    } elseif ($tawk_page === 'estudo') {
      $tawk_course = 'Estudo';
    } elseif ($tawk_page === 'login') {
      $tawk_course = 'Login';
    }
  }

  $tawk_fullname = trim($tawk_firstname . ' ' . $tawk_lastname);
  if ($tawk_fullname === '') {
    $tawk_fullname = $tawk_username !== '' ? $tawk_username : 'Usuario';
  }

  $tawk_payload = [
    'page' => (string)$tawk_page,
    'course' => $tawk_course,
    'course_id' => $tawk_course_id,
    'user_id' => $tawk_user_id,
    'firstname' => $tawk_firstname,
    'lastname' => $tawk_lastname,
    'fullname' => $tawk_fullname,
    'username' => $tawk_username,
    'email' => $tawk_email,
  ];
}
?>
<!doctype html>
<html lang="pt-BR" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description" content="Portal do Aluno Tecnodata - Plataforma de Cursos">
  <meta name="theme-color" content="#0080C8">
  <meta name="app-base" content="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="csrf" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="app-alert-page" content="<?= htmlspecialchars($alert_page, ENT_QUOTES, 'UTF-8') ?>">
  <meta name="app-course-id" content="<?= $alert_course_id > 0 ? (int)$alert_course_id : '' ?>">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- FontAwesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.min.css" rel="stylesheet">
  <!-- DataTables -->
  <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <!-- Toastify -->
  <link href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.css" rel="stylesheet">
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <?php if ($include_plyr): ?>
    <link href="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.css" rel="stylesheet">
  <?php endif; ?>

  <!-- Custom CSS -->
  <?php foreach ($css as $c): ?>
    <link href="<?= $asset($c) ?>" rel="stylesheet">
  <?php endforeach; ?>

  <link rel="icon" type="image/png" href="<?= $asset('favicon.png') ?>">
</head>
<body class="<?= htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') ?>">

<script>
window.APP_BOOTSTRAP_ALERT = <?= json_encode($bootstrap_user_alert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<div class="app-shell">
  <?php if ($nav_partial): ?>
    <?php App::partial($nav_partial, ['user'=>$user ?? null]); ?>
  <?php endif; ?>

  <main class="app-main">
    <div class="<?= $full_width ? 'app-container-full' : 'app-container' ?> <?= $pad_class ?>">
      <?php App::partial('partials/flash.php', ['flash'=>$flash]); ?>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2">
          <i class="fa-solid fa-triangle-exclamation mt-1"></i>
          <div>
            <div class="fw-semibold">Ops...</div>
            <div><?= $error ?></div>
            <?= $trace ?? '' ?>
          </div>
        </div>
      <?php endif; ?>
      <?php include $viewFile; ?>
    </div>
  </main>
</div>

<?php if ($tawk_enabled): ?>
<div class="app-tawk-wrap" id="appTawkLauncherWrap" data-page="<?= htmlspecialchars((string)$tawk_page, ENT_QUOTES, 'UTF-8') ?>">
  <button type="button" class="app-tawk-launcher is-loading" id="appTawkLauncher" aria-label="Abrir suporte no chat">
    <span class="app-tawk-launcher__pulse" aria-hidden="true"></span>
    <span class="app-tawk-launcher__icon"><i class="fa-solid fa-headset"></i></span>
    <span class="app-tawk-launcher__label" data-label>Suporte</span>
    <span class="app-tawk-launcher__badge d-none" id="appTawkUnread">0</span>
  </button>
</div>
<script>
(() => {
  const payload = <?= json_encode($tawk_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || {};
  const embedSrc = <?= json_encode($tawk_embed_src, JSON_UNESCAPED_SLASHES) ?>;
  const contextUrl = <?= json_encode($tawk_context_url, JSON_UNESCAPED_SLASHES) ?>;

  const wrap = document.getElementById('appTawkLauncherWrap');
  const launcher = document.getElementById('appTawkLauncher');
  const unreadBadge = document.getElementById('appTawkUnread');
  if (!wrap || !launcher || !embedSrc) return;

  const api = window.Tawk_API = window.Tawk_API || {};
  window.Tawk_LoadStart = new Date();

  let isLoaded = false;
  let isOpen = false;
  let contextCache = null;
  let lastSyncedUserId = 0;

  if (Number(payload.user_id || 0) > 0) {
    try {
      api.visitor = {
        name: String(payload.fullname || payload.username || 'Usuario'),
        email: String(payload.email || '')
      };
    } catch (_error) {
      // Sem bloqueio.
    }
  }

  const normalizeContext = (raw) => {
    const source = raw && typeof raw === 'object' ? raw : {};
    const page = String(source.page || payload.page || '');
    const course = String(source.course || payload.course || '');
    const courseId = Number(source.course_id || payload.course_id || 0);
    const userId = Number(source.user_id || payload.user_id || 0);
    const firstname = String(source.firstname || payload.firstname || '');
    const lastname = String(source.lastname || payload.lastname || '');
    const username = String(source.username || payload.username || '');
    const email = String(source.email || payload.email || '');
    const fullnameRaw = String(source.fullname || payload.fullname || '').trim();
    const fullname = fullnameRaw !== '' ? fullnameRaw : `${firstname} ${lastname}`.trim();
    const matricula = String(source.matricula || source.user_id || payload.user_id || '').trim();
    const usuario = String(source.usuario || username || '').trim();
    const nome = String(source.nome || fullname || '').trim();
    const cursos = String(source.cursos || '').trim();
    return {
      page,
      course,
      course_id: courseId > 0 ? courseId : 0,
      user_id: userId > 0 ? userId : 0,
      firstname,
      lastname,
      username,
      email,
      fullname: fullname !== '' ? fullname : (username || 'Usuario'),
      matricula,
      usuario,
      nome: nome !== '' ? nome : (fullname !== '' ? fullname : (username || 'Usuario')),
      cursos
    };
  };

  contextCache = normalizeContext(payload);

  const setUnread = (count) => {
    const safeCount = Math.max(0, Number(count || 0));
    if (!unreadBadge) return;
    if (safeCount <= 0) {
      unreadBadge.classList.add('d-none');
      unreadBadge.textContent = '0';
      return;
    }
    unreadBadge.classList.remove('d-none');
    unreadBadge.textContent = safeCount > 99 ? '99+' : String(safeCount);
  };

  const updateLauncherState = () => {
    launcher.classList.toggle('is-open', isOpen);
    launcher.classList.toggle('is-loading', !isLoaded);
    const label = launcher.querySelector('[data-label]');
    if (label) {
      label.textContent = isOpen ? 'Fechar suporte' : 'Suporte';
    }
  };

  const updateLauncherOffset = () => {
    const isStudyMobile = String(payload.page || '') === 'estudo'
      && window.matchMedia('(max-width: 991.98px)').matches;
    wrap.classList.toggle('is-study-mobile', isStudyMobile);
  };

  const loadContext = async (forceRefresh = false) => {
    const isLoginPage = String(payload.page || '') === 'login';
    if (!contextUrl || isLoginPage) {
      return contextCache;
    }
    if (!forceRefresh && contextCache._fresh === true) {
      return contextCache;
    }

    const query = new URLSearchParams();
    if (contextCache.page) query.set('page', contextCache.page);
    if (Number(contextCache.course_id || 0) > 0) query.set('course_id', String(contextCache.course_id));
    if (contextCache.course) query.set('course', contextCache.course);

    const url = query.toString() !== '' ? `${contextUrl}?${query.toString()}` : contextUrl;

    try {
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      });
      const raw = await response.text();
      const data = raw ? JSON.parse(raw) : null;
      if (response.ok && data && data.ok && data.context) {
        contextCache = normalizeContext(data.context);
        contextCache._fresh = true;
      }
    } catch (_error) {
      // Sem bloqueio: fallback para payload inicial renderizado no servidor.
    }

    return contextCache;
  };

  const hideNativeWidget = () => {
    if (typeof api.hideWidget !== 'function') return;
    try {
      api.hideWidget();
    } catch (_error) {
      // Sem bloqueio.
    }
  };

  const sendContext = async (forceRefresh = false) => {
    if (typeof api.setAttributes !== 'function') return;
    const context = await loadContext(forceRefresh);
    if (!context || Number(context.user_id || 0) <= 0) return;
    const currentUserId = Number(context.user_id || 0);

    if (lastSyncedUserId > 0 && currentUserId > 0 && lastSyncedUserId !== currentUserId) {
      try {
        if (typeof api.endChat === 'function') {
          api.endChat();
        }
      } catch (_error) {
        // Sem bloqueio.
      }
      try {
        if (typeof api.logout === 'function') {
          api.logout();
        }
      } catch (_error) {
        // Sem bloqueio.
      }
    }
    lastSyncedUserId = currentUserId;

    try {
      api.visitor = {
        name: String(context.fullname || context.username || 'Usuario'),
        email: String(context.email || '')
      };
    } catch (_error) {
      // Ignora falha de visitor.
    }

    const attrs = {
      // Mesmo padrao usado no plugin do Moodle.
      Matricula: String(context.matricula || context.user_id || ''),
      Usuario: String(context.usuario || context.username || ''),
      Nome: String(context.nome || context.fullname || context.username || 'Usuario'),
      Cursos: String(context.cursos || ''),
      // Campos padrao do Tawk (reforco para painel do suporte).
      name: String(context.nome || context.fullname || context.username || 'Usuario'),
      email: String(context.email || '')
    };

    // Tawk nao aceita chaves com "_". Usamos nomes alfanumericos.
    const extraAttrs = {
      CursoApp: String(context.course || ''),
      CourseIdApp: String(context.course_id || ''),
      UserIdApp: String(context.user_id || ''),
      FirstName: String(context.firstname || ''),
      LastName: String(context.lastname || ''),
      Username: String(context.username || '')
    };
    Object.keys(extraAttrs).forEach((key) => {
      const value = String(extraAttrs[key] || '').trim();
      if (value !== '') {
        attrs[key] = value;
      }
    });

    await new Promise((resolve) => {
      api.setAttributes(attrs, function(error) {
        if (error && window.console && typeof window.console.error === 'function') {
          console.error('[app_v3][tawk] setAttributes error', error, attrs);
        }
        resolve(true);
      });
    });
  };

  api.onLoad = function() {
    isLoaded = true;
    updateLauncherState();
    hideNativeWidget();
    sendContext(true);
  };

  api.onChatMaximized = function() {
    isOpen = true;
    updateLauncherState();
    sendContext(false);
  };

  api.onChatMinimized = function() {
    isOpen = false;
    updateLauncherState();
    hideNativeWidget();
  };

  api.onChatHidden = function() {
    isOpen = false;
    updateLauncherState();
    hideNativeWidget();
  };

  api.onUnreadCountChanged = function(count) {
    setUnread(count);
  };

  api.onChatUnhidden = function() {
    sendContext(true);
  };

  launcher.addEventListener('click', async () => {
    if (!isLoaded) return;

    if (isOpen) {
      if (typeof api.minimize === 'function') {
        api.minimize();
      } else if (typeof api.toggle === 'function') {
        api.toggle();
      }
      isOpen = false;
      updateLauncherState();
      hideNativeWidget();
      return;
    }

    await sendContext(true);

    if (typeof api.maximize === 'function') {
      api.maximize();
    } else if (typeof api.toggle === 'function') {
      api.toggle();
    }
    isOpen = true;
    updateLauncherState();
  });

  updateLauncherState();
  updateLauncherOffset();
  window.addEventListener('resize', updateLauncherOffset);

  const s1 = document.createElement('script');
  const s0 = document.getElementsByTagName('script')[0];
  s1.async = true;
  s1.src = embedSrc;
  s1.charset = 'UTF-8';
  s1.setAttribute('crossorigin', '*');
  s0.parentNode.insertBefore(s1, s0);
})();
</script>
<?php endif; ?>

<!-- JS: Bootstrap, jQuery, DataTables, SweetAlert2, Toastify, Select2 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.12.4/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.4.0/dist/hls.min.js"></script>
<?php if ($include_plyr): ?>
  <script src="https://cdn.jsdelivr.net/npm/plyr@3.7.8/dist/plyr.polyfilled.min.js"></script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>

<?php foreach ($js as $f): ?>
  <script src="<?= $asset($f) ?>"></script>
<?php endforeach; ?>

</body>
</html>
