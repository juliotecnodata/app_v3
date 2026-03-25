<?php
use App\App;
use App\Auth;

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$course = $course ?? [];
$csrf = $csrf ?? '';
$courseId = (int)($course['id'] ?? 0);
$courseTitle = trim((string)($course['title'] ?? 'Curso'));
$returnUrl = (string)($return_url ?? App::base_url('/course/' . $courseId));
$user = Auth::user();
$userName = trim((string)($user->firstname ?? '') . ' ' . (string)($user->lastname ?? ''));
if ($userName === '') {
  $userName = (string)($user->username ?? 'Aluno');
}
$userId = (int)($user->id ?? 0);
?>

<div class="study-shell study-shell--biometric">
  <main class="biometric-gate biometric-gate--immersive">
    <section class="biometric-card biometric-card--premium biometric-card--device">
      <div class="biometric-shell-actions">
        <a class="biometric-shell-link" href="<?= h(App::base_url('/dashboard')) ?>">
          <i class="fa-solid fa-chevron-left"></i> Meus cursos
        </a>
        <a class="biometric-shell-link" href="<?= h(App::base_url('/logout')) ?>">
          <i class="fa-solid fa-right-from-bracket"></i> Sair
        </a>
      </div>

      <div class="bio-device">
        <div class="bio-device__notch" aria-hidden="true"></div>
        <div class="bio-device__speaker" aria-hidden="true"></div>

        <div class="bio-device__screen">
          <header class="bio-device__header">
            <div class="bio-device__brand">
              <span class="bio-device__brand-dot" aria-hidden="true"></span>
              <i class="fa-solid fa-id-card-clip"></i>
              <span>Tecnodata | Biometria</span>
            </div>
            <div class="bio-device__student" title="<?= h($userName) ?>">
              <?= h($userName) ?>
            </div>
          </header>

          <section class="bio-device__intro">
            <div class="bio-device__title">Posicione seu rosto</div>
            <div class="bio-device__subtitle">Use boa iluminacao, mantenha o rosto centralizado e capture uma selfie nitida.</div>
            <div class="bio-device__meta">
              <span>ID <?= h($userId) ?></span>
              <span class="bio-device__meta-separator" aria-hidden="true"></span>
              <span class="bio-device__meta-course" title="<?= h($courseTitle) ?>"><?= h($courseTitle) ?></span>
            </div>
          </section>

          <div class="bio-device__statebar">
            <span class="biometric-device-pill is-wait" id="bioDeviceState">
              <i class="fa-solid fa-circle"></i>Camera inativa
            </span>
          </div>

          <section class="bio-device__view">
            <video id="bioVideo" class="biometric-capture__video" autoplay playsinline webkit-playsinline muted></video>
            <canvas id="bioCanvas" class="d-none"></canvas>
            <img id="bioPreview" class="biometric-capture__preview d-none" alt="Foto capturada para validacao">

            <div class="bio-device__thumb d-none" id="bioThumb">
              <img id="bioThumbImage" alt="Miniatura capturada">
              <span class="bio-device__thumb-badge" id="bioThumbLabel">OK</span>
            </div>

            <div class="biometric-capture-overlay" id="bioCapture">
              <span class="bio-corner bio-corner--tl"></span>
              <span class="bio-corner bio-corner--tr"></span>
              <span class="bio-corner bio-corner--bl"></span>
              <span class="bio-corner bio-corner--br"></span>
              <div class="bio-focus-ring"></div>
              <div class="bio-face-oval">
                <span class="bio-eye-guide"></span>
                <span class="bio-center-point"></span>
                <span class="bio-chin-guide"></span>
              </div>
              <div class="bio-scan-line"></div>
            </div>

            <div class="bio-device__tap-overlay" id="bioTapOverlay">
              <div class="bio-device__tap-card">
                <div class="bio-device__tap-title">
                  <i class="fa-solid fa-face-smile me-1"></i>Iniciar camera
                </div>
                <p class="bio-device__tap-text">Abra a camera e capture uma selfie nitida para validar seu acesso.</p>
                <button type="button" class="bio-device__tap-btn" id="bioTapStart">
                  <i class="fa-solid fa-video"></i>Ligar camera
                </button>
              </div>
            </div>
          </section>

          <footer class="bio-device__bottom">
            <p class="bio-device__hint" id="bioHint">Toque em "Ligar camera" para iniciar.</p>

            <div class="bio-device__controls">
              <button type="button" class="bio-device__control bio-device__control--switch d-none" id="btnBioSwitch" aria-label="Trocar camera">
                <i class="fa-solid fa-camera-rotate"></i>
              </button>

              <button type="button" class="bio-device__control bio-device__control--capture" id="btnBioShutter" aria-label="Capturar selfie" disabled>
                <i class="fa-solid fa-camera"></i>
                <span>Capturar foto</span>
              </button>
            </div>

            <div class="biometric-mobile-actions biometric-mobile-actions--device">
              <button type="button" class="btn btn-light d-none" id="btnBioSecondary">
                <i class="fa-solid fa-rotate-left me-1"></i>Nova foto
              </button>
              <button type="button" class="btn btn-primary d-none" id="btnBioPrimary">
                <i class="fa-solid fa-circle-check me-1"></i>Enviar selfie
              </button>
            </div>

            <div class="biometric-status biometric-status--device" id="bioStatus">Aguardando camera.</div>
          </footer>
        </div>
      </div>
    </section>
  </main>
</div>

<script>
(function () {
  const courseId = <?= (int)$courseId ?>;
  const csrf = <?= json_encode((string)$csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const returnUrl = <?= json_encode((string)$returnUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const base = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';

  const video = document.getElementById('bioVideo');
  const canvas = document.getElementById('bioCanvas');
  const preview = document.getElementById('bioPreview');
  const status = document.getElementById('bioStatus');
  const captureBox = document.getElementById('bioCapture');
  const deviceState = document.getElementById('bioDeviceState');
  const hint = document.getElementById('bioHint');
  const btnPrimary = document.getElementById('btnBioPrimary');
  const btnSecondary = document.getElementById('btnBioSecondary');
  const btnSwitch = document.getElementById('btnBioSwitch');
  const btnShutter = document.getElementById('btnBioShutter');
  const tapOverlay = document.getElementById('bioTapOverlay');
  const tapStart = document.getElementById('bioTapStart');
  const thumb = document.getElementById('bioThumb');
  const thumbImage = document.getElementById('bioThumbImage');
  const thumbLabel = document.getElementById('bioThumbLabel');

  let mediaStream = null;
  let photoData = '';
  let captureQualityIssue = '';
  let cameraDevices = [];
  let cameraIndex = 0;
  let currentDeviceId = '';
  let flowState = 'idle';
  let primaryBusy = false;

  const userAgent = String(navigator.userAgent || '');
  const isIOS = /iPad|iPhone|iPod/i.test(userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isAndroid = /Android/i.test(userAgent);
  const isDesktop = !isIOS && !isAndroid;

  const pulse = (pattern) => {
    if (typeof navigator.vibrate !== 'function') return;
    navigator.vibrate(pattern);
  };

  const waitForVideoReady = async (element, timeoutMs = 2500) => {
    if (!element) return false;
    if (element.readyState >= 2 && element.videoWidth > 0 && element.videoHeight > 0) {
      return true;
    }

    await new Promise((resolve) => {
      let done = false;
      const finalize = () => {
        if (done) return;
        done = true;
        resolve();
      };
      element.addEventListener('loadedmetadata', finalize, { once: true });
      setTimeout(finalize, 250);
    });

    const startedAt = Date.now();
    while ((element.videoWidth === 0 || element.videoHeight === 0) && (Date.now() - startedAt) < timeoutMs) {
      await new Promise((resolve) => setTimeout(resolve, 50));
    }

    return element.videoWidth > 0 && element.videoHeight > 0;
  };

  const waitForRenderableFrame = async (element, timeoutMs = 2500) => {
    const ready = await waitForVideoReady(element, timeoutMs);
    if (!ready) {
      return false;
    }

    if (typeof element.requestVideoFrameCallback === 'function') {
      const rendered = await new Promise((resolve) => {
        let settled = false;
        const finish = (value) => {
          if (settled) return;
          settled = true;
          resolve(value);
        };

        const timer = window.setTimeout(() => finish(false), Math.min(timeoutMs, 1000));
        element.requestVideoFrameCallback(() => {
          window.clearTimeout(timer);
          finish(true);
        });
      });

      if (rendered) {
        return true;
      }
    }

    await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
    await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
    await new Promise((resolve) => window.setTimeout(resolve, 80));
    return element.videoWidth > 0 && element.videoHeight > 0;
  };

  const setStatus = (message, type = 'neutral') => {
    if (!status) return;
    status.textContent = message;
    status.classList.remove('is-error', 'is-success');
    if (type === 'error') status.classList.add('is-error');
    if (type === 'success') status.classList.add('is-success');
  };

  const applyPreviewMode = (showPreview) => {
    if (showPreview) {
      preview.classList.remove('d-none');
      video.classList.add('d-none');
    } else {
      preview.classList.add('d-none');
      video.classList.remove('d-none');
    }
  };

  const updateThumb = (src, label) => {
    if (!thumb || !thumbImage || !thumbLabel) return;
    thumbImage.src = src;
    thumbLabel.textContent = label;
    thumb.classList.remove('d-none');
  };

  const hideThumb = () => {
    if (!thumb) return;
    thumb.classList.add('d-none');
  };

  const syncButtons = () => {
    if (btnPrimary) {
      btnPrimary.disabled = primaryBusy || flowState !== 'captured';
      btnPrimary.classList.toggle('d-none', flowState !== 'captured');
    }
    if (btnSecondary) {
      btnSecondary.disabled = primaryBusy || flowState !== 'captured';
      btnSecondary.classList.toggle('d-none', flowState !== 'captured');
    }
    if (btnShutter) {
      btnShutter.disabled = primaryBusy || flowState !== 'live';
      btnShutter.classList.toggle('is-ready', flowState === 'live' && !primaryBusy);
    }
    if (btnSwitch) {
      const hasMultiple = cameraDevices.length > 1;
      btnSwitch.disabled = primaryBusy || !hasMultiple || !mediaStream;
      btnSwitch.classList.toggle('d-none', !hasMultiple);
    }
    if (tapOverlay) {
      const shouldShow = !mediaStream && flowState === 'idle';
      tapOverlay.classList.toggle('d-none', !shouldShow);
    }
  };

  const setBusy = (busy) => {
    primaryBusy = !!busy;
    if (tapStart) tapStart.disabled = primaryBusy;
    syncButtons();
  };

  const decodeBiometricError = (raw) => {
    const original = String(raw || '').trim();
    let parsed = null;
    if (original.startsWith('{') && original.endsWith('}')) {
      try {
        parsed = JSON.parse(original);
      } catch (_error) {
        parsed = null;
      }
    }

    const responseCode = Number(parsed?.response_code || 0);
    const messageText = String(
      parsed?.message
      || parsed?.image_quality_info
      || parsed?.error
      || original
      || 'Nao foi possivel validar a biometria.'
    ).trim();
    const normalized = messageText.toLowerCase();

    if (
      responseCode === 109
      || normalized.includes('bad quality')
      || normalized.includes('image quality')
      || normalized.includes('baixa qualidade')
    ) {
      return {
        title: 'Selfie rejeitada',
        message: 'A foto nao passou na validacao de qualidade.',
        detail: 'Tire outra selfie com boa iluminacao, rosto inteiro dentro da moldura e sem movimento.'
      };
    }

    if (normalized.includes('multiple face') || normalized.includes('more than one face')) {
      return {
        title: 'Mais de um rosto detectado',
        message: 'A captura precisa ter apenas um rosto visivel.',
        detail: 'Fique sozinho no enquadramento e tire outra selfie.'
      };
    }

    if (
      normalized.includes('face not found')
      || normalized.includes('no face')
      || normalized.includes('rosto nao encontrado')
    ) {
      return {
        title: 'Rosto nao detectado',
        message: 'Nao foi possivel localizar seu rosto na foto.',
        detail: 'Aproxime-se um pouco, centralize o rosto e tente novamente.'
      };
    }

    return {
      title: 'Validacao nao concluida',
      message: 'Nao foi possivel validar a selfie agora.',
      detail: messageText
    };
  };

  const showBiometricError = (raw) => {
    const errorInfo = decodeBiometricError(raw);
    setStatus(errorInfo.message, 'error');

    if (typeof window.Swal === 'object' && typeof window.Swal.fire === 'function') {
      const detail = errorInfo.detail ? '<div class="bio-error-modal__detail">' + errorInfo.detail + '</div>' : '';
      window.Swal.fire({
        icon: 'warning',
        title: errorInfo.title,
        html: '<div class="bio-error-modal__message">' + errorInfo.message + '</div>' + detail,
        confirmButtonText: 'Tirar outra selfie',
        customClass: {
          popup: 'bio-error-modal',
          confirmButton: 'bio-error-modal__confirm'
        }
      });
    }
  };

  const inspectCaptureQuality = (context, width, height) => {
    try {
      const sampleWidth = Math.max(24, Math.min(180, width));
      const sampleHeight = Math.max(24, Math.round((height / Math.max(1, width)) * sampleWidth));
      const probe = document.createElement('canvas');
      probe.width = sampleWidth;
      probe.height = sampleHeight;
      const probeCtx = probe.getContext('2d');
      if (!probeCtx) {
        return { ok: true, reason: '' };
      }

      probeCtx.drawImage(context.canvas, 0, 0, width, height, 0, 0, sampleWidth, sampleHeight);
      const imageData = probeCtx.getImageData(0, 0, sampleWidth, sampleHeight).data;
      const luminance = new Float32Array(sampleWidth * sampleHeight);

      let sum = 0;
      for (let i = 0, p = 0; i < imageData.length; i += 4, p++) {
        const value = (0.299 * imageData[i]) + (0.587 * imageData[i + 1]) + (0.114 * imageData[i + 2]);
        luminance[p] = value;
        sum += value;
      }

      const mean = sum / luminance.length;
      let variance = 0;
      for (let i = 0; i < luminance.length; i++) {
        const diff = luminance[i] - mean;
        variance += diff * diff;
      }
      variance /= luminance.length;
      const contrast = Math.sqrt(variance);

      let edgeEnergy = 0;
      for (let y = 1; y < sampleHeight - 1; y++) {
        for (let x = 1; x < sampleWidth - 1; x++) {
          const idx = (y * sampleWidth) + x;
          const laplacian =
            (4 * luminance[idx]) -
            luminance[idx - 1] -
            luminance[idx + 1] -
            luminance[idx - sampleWidth] -
            luminance[idx + sampleWidth];
          edgeEnergy += Math.abs(laplacian);
        }
      }

      const sharpness = edgeEnergy / Math.max(1, (sampleWidth - 2) * (sampleHeight - 2));
      if (mean < 55) {
        return { ok: false, reason: 'A selfie ficou escura. Aproxime-se de uma luz frontal e capture novamente.' };
      }
      if (contrast < 22) {
        return { ok: false, reason: 'A imagem ficou sem contraste. Evite contraluz e sombras no rosto.' };
      }
      if (sharpness < 10) {
        return { ok: false, reason: 'A selfie ficou sem nitidez. Fique parado por um instante e tente novamente.' };
      }

      return { ok: true, reason: '' };
    } catch (_error) {
      return { ok: true, reason: '' };
    }
  };

  const setCaptureState = (state) => {
    if (captureBox) {
      captureBox.classList.remove('is-idle', 'is-live', 'is-captured');
      captureBox.classList.add('is-' + state);
    }

    if (deviceState) {
      deviceState.classList.remove('is-wait', 'is-live', 'is-captured');
      if (state === 'live') {
        deviceState.classList.add('is-live');
        deviceState.innerHTML = '<i class="fa-solid fa-circle"></i>Camera ativa';
      } else if (state === 'captured') {
        deviceState.classList.add('is-captured');
        deviceState.innerHTML = '<i class="fa-solid fa-circle-check"></i>Foto capturada';
      } else {
        deviceState.classList.add('is-wait');
        deviceState.innerHTML = '<i class="fa-solid fa-circle"></i>Camera inativa';
      }
    }

    if (hint) {
      if (state === 'live') {
        hint.textContent = 'Enquadre o rosto na moldura e toque em capturar.';
      } else if (state === 'captured') {
        hint.textContent = 'Confira a selfie. Se estiver boa, envie para validacao.';
      } else {
        hint.textContent = 'Toque em "Ligar camera" para iniciar.';
      }
    }

    syncButtons();
  };

  const stopStream = () => {
    if (!mediaStream) return;
    mediaStream.getTracks().forEach((track) => track.stop());
    mediaStream = null;
    currentDeviceId = '';
    flowState = 'idle';
    setCaptureState('idle');
  };

  const refreshCameraList = async () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
      cameraDevices = [];
      return;
    }
    try {
      const allDevices = await navigator.mediaDevices.enumerateDevices();
      cameraDevices = allDevices.filter((item) => item.kind === 'videoinput');
      if (currentDeviceId) {
        const found = cameraDevices.findIndex((item) => item.deviceId === currentDeviceId);
        if (found >= 0) {
          cameraIndex = found;
        }
      }
    } catch (_error) {
      cameraDevices = [];
    }
  };

  const buildConstraintProfiles = (forcedDeviceId = '') => {
    const profiles = [];
    const hdResolution = { width: { ideal: 1280 }, height: { ideal: 720 } };

    if (forcedDeviceId) {
      profiles.push({
        video: Object.assign({ deviceId: { exact: forcedDeviceId } }, hdResolution),
        audio: false
      });
      profiles.push({
        video: { deviceId: { exact: forcedDeviceId } },
        audio: false
      });
    }

    profiles.push(
      { video: Object.assign({ facingMode: { ideal: 'user' } }, hdResolution), audio: false },
      { video: { facingMode: 'user' }, audio: false },
      { video: hdResolution, audio: false },
      { video: true, audio: false }
    );

    return profiles;
  };

  const openCameraWithFallback = async (forcedDeviceId = '') => {
    const profiles = buildConstraintProfiles(forcedDeviceId);
    let lastError = null;

    for (let i = 0; i < profiles.length; i++) {
      try {
        return await navigator.mediaDevices.getUserMedia(profiles[i]);
      } catch (error) {
        lastError = error;
      }
    }

    throw (lastError || new Error('Nao foi possivel abrir a camera.'));
  };

  const resetCapture = () => {
    photoData = '';
    captureQualityIssue = '';
    preview.src = '';
    hideThumb();
    applyPreviewMode(false);
    flowState = mediaStream ? 'live' : 'idle';
    setCaptureState(flowState);
  };

  const startCamera = async (forcedDeviceId = '') => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Seu navegador nao suporta camera para validacao biometrica.', 'error');
      return;
    }

    const hostname = String(window.location.hostname || '');
    const isLocalHost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    if (!window.isSecureContext && !isLocalHost) {
      setStatus('Para usar biometria fora do localhost, acesse o sistema em HTTPS.', 'error');
      return;
    }

    setBusy(true);
    stopStream();
    resetCapture();

    try {
      mediaStream = await openCameraWithFallback(forcedDeviceId);

      if (!video) return;
      video.srcObject = mediaStream;
      video.setAttribute('playsinline', 'true');
      video.setAttribute('webkit-playsinline', 'true');
      await video.play();
      await waitForRenderableFrame(video);

      const track = mediaStream.getVideoTracks()[0] || null;
      const settings = track && track.getSettings ? track.getSettings() : {};
      currentDeviceId = String(settings.deviceId || forcedDeviceId || '');

      await refreshCameraList();

      setStatus('Camera ativa. Posicione o rosto dentro da moldura.');
      flowState = 'live';
      setCaptureState('live');
    } catch (error) {
      const name = String((error && error.name) || '');
      if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
        setStatus('Permissao da camera negada. Libere o acesso nas configuracoes do navegador.', 'error');
      } else if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
        setStatus('Nenhuma camera foi encontrada neste aparelho.', 'error');
      } else if (name === 'NotReadableError' || name === 'TrackStartError') {
        setStatus('A camera esta sendo usada por outro aplicativo. Feche o app e tente novamente.', 'error');
      } else {
        setStatus('Nao foi possivel acessar a camera. Verifique a permissao do navegador.', 'error');
      }
      flowState = 'idle';
      mediaStream = null;
      setCaptureState('idle');
    } finally {
      setBusy(false);
    }
  };

  const capturePhoto = async () => {
    if (!video || !canvas || !preview) return;
    if (!mediaStream) {
      setStatus('Ative a camera antes de capturar.', 'error');
      return;
    }

    const ready = await waitForRenderableFrame(video, 3000);
    if (!ready) {
      setStatus('A camera ainda esta iniciando. Tente capturar novamente em 1 segundo.', 'error');
      return;
    }

    const sourceWidth = video.videoWidth || 960;
    const sourceHeight = video.videoHeight || 720;
    const targetAspect = 4 / 5;
    let cropWidth = Math.max(1, Math.round(sourceWidth * (isDesktop ? 0.76 : 0.84)));
    let cropHeight = Math.max(1, Math.round(cropWidth / targetAspect));

    if (cropHeight > sourceHeight) {
      cropHeight = sourceHeight;
      cropWidth = Math.max(1, Math.round(cropHeight * targetAspect));
    }

    const cropX = Math.max(0, Math.round((sourceWidth - cropWidth) / 2));
    const cropY = Math.max(0, Math.round((sourceHeight - cropHeight) / 2));

    const maxEdge = isDesktop ? 900 : 720;
    const biggestEdge = Math.max(cropWidth, cropHeight);
    const scale = biggestEdge > maxEdge ? (maxEdge / biggestEdge) : 1;
    const targetWidth = Math.max(1, Math.round(cropWidth * scale));
    const targetHeight = Math.max(1, Math.round(cropHeight * scale));

    canvas.width = targetWidth;
    canvas.height = targetHeight;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
      setStatus('Falha ao preparar captura da imagem.', 'error');
      return;
    }

    ctx.imageSmoothingEnabled = true;
    if ('imageSmoothingQuality' in ctx) {
      ctx.imageSmoothingQuality = 'high';
    }
    ctx.drawImage(
      video,
      cropX,
      cropY,
      cropWidth,
      cropHeight,
      0,
      0,
      targetWidth,
      targetHeight
    );

    const qualityCheck = inspectCaptureQuality(ctx, targetWidth, targetHeight);
    captureQualityIssue = qualityCheck.ok ? '' : qualityCheck.reason;

    photoData = canvas.toDataURL('image/jpeg', isDesktop ? 0.86 : 0.82);
    preview.src = photoData;
    applyPreviewMode(true);
    updateThumb(photoData, 'Selfie');

    if (captureQualityIssue !== '') {
      setStatus('Selfie capturada, mas a qualidade parece baixa. Se puder, tire outra foto.', 'error');
    } else {
      setStatus('Selfie capturada. Confira antes de validar.', 'success');
    }
    pulse([20, 30, 20]);
    flowState = 'captured';
    setCaptureState('captured');
  };

  const retakePhoto = () => {
    if (!mediaStream) {
      startCamera(currentDeviceId).catch(() => {});
      return;
    }
    resetCapture();
    setStatus('Camera pronta para uma nova selfie.');
  };

  const switchCamera = async () => {
    if (primaryBusy) return;
    if (!cameraDevices.length || cameraDevices.length < 2) return;
    cameraIndex = (cameraIndex + 1) % cameraDevices.length;
    currentDeviceId = String(cameraDevices[cameraIndex].deviceId || '');
    await startCamera(currentDeviceId);
  };

  const submitBiometric = async () => {
    if (!photoData) {
      setStatus('Capture sua foto antes de continuar.', 'error');
      return;
    }

    setBusy(true);
    setStatus('Validando biometria...');

    try {
      if (captureQualityIssue !== '' && typeof window.Swal === 'object' && typeof window.Swal.fire === 'function') {
        const answer = await window.Swal.fire({
          icon: 'warning',
          title: 'Qualidade abaixo do ideal',
          html: '<div class="bio-error-modal__message">A selfie foi capturada, mas pode falhar na validacao.</div><div class="bio-error-modal__detail">' + captureQualityIssue + '</div>',
          showCancelButton: true,
          confirmButtonText: 'Enviar mesmo assim',
          cancelButtonText: 'Tirar outra selfie',
          customClass: {
            popup: 'bio-error-modal',
            confirmButton: 'bio-error-modal__confirm'
          }
        });

        if (!answer.isConfirmed) {
          setBusy(false);
          setStatus(captureQualityIssue, 'error');
          return;
        }
      }

      const fd = new FormData();
      fd.append('csrf', csrf);
      fd.append('course_id', String(courseId));
      fd.append('photo_data', photoData);
      fd.append('return_url', returnUrl);

      const res = await fetch(base + '/api/course/biometric/verify', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const rawText = await res.text();
      let json = null;
      try {
        json = rawText ? JSON.parse(rawText) : null;
      } catch (_error) {
        json = null;
      }
      if (!res.ok || !json || json.ok === false) {
        throw new Error((json && json.error) ? json.error : (rawText || 'Falha ao validar biometria.'));
      }

      setStatus('Biometria aprovada. Redirecionando...', 'success');
      pulse([30, 40, 30, 40, 30]);
      const target = String(json.redirect || (base + '/course/' + courseId));
      window.location.href = target;
    } catch (error) {
      showBiometricError(String(error.message || error));
      setBusy(false);
      syncButtons();
    }
  };

  if (isIOS) {
    setStatus('Toque para ativar a camera.');
  }
  flowState = 'idle';
  setCaptureState('idle');
  syncButtons();

  tapStart?.addEventListener('click', () => {
    startCamera(currentDeviceId).catch(() => {});
  });
  btnShutter?.addEventListener('click', () => {
    capturePhoto().catch(() => {});
  });
  video?.addEventListener('click', () => {
    if (flowState === 'live' && !primaryBusy) {
      capturePhoto().catch(() => {});
    }
  });
  btnPrimary?.addEventListener('click', () => {
    submitBiometric().catch(() => {});
  });
  btnSecondary?.addEventListener('click', retakePhoto);
  btnSwitch?.addEventListener('click', () => {
    switchCamera().catch(() => {});
  });

  window.addEventListener('beforeunload', stopStream);
})();
</script>

