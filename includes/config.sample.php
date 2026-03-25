<?php
/**
 * Copie para config.php e ajuste os dados do banco do APP.
 * O banco do APP deve ser separado do banco do Moodle.
 */
return [
  // Exibicao padrao do app em horario do Brasil.
  'timezone' => 'America/Sao_Paulo',

  'db' => [
    'dsn' => 'mysql:host=localhost;dbname=SEU_BANCO_APP;charset=utf8mb4',
    'user' => 'SEU_USUARIO',
    'pass' => 'SUA_SENHA',
    // Alternativa aceita: 'password' => 'SUA_SENHA',
    'connect_retries' => 2,
    'connect_retry_delay_ms' => 250,
    // Sessao MySQL do app em horario do Brasil.
    'session_time_zone' => '-03:00',
    'opts' => [
      // PDO::ATTR_PERSISTENT => true,
    ],
  ],

  // Limite de upload em bytes para PDFs e arquivos.
  'upload_max_bytes' => 50 * 1024 * 1024,

  // Caminho base do app (ex.: /app_v3).
  'base_path' => '/app_v3',

  // Tempo (em segundos) para reaproveitar a ultima biometria aprovada.
  // Dentro dessa janela o aluno entra sem nova foto, mesmo em outro navegador.
  // Ex.: 4h = 14400. Se <= 0, a validacao vale ate encerrar a sessao.
  'biometric_ttl_seconds' => 4 * 3600,

  // Provedor externo de biometria.
  // Opcoes: '' (desligado) ou 'gryfo'.
  'biometric_provider' => 'gryfo',
  'gryfo_base_url' => 'https://api.gryfo.com.br',
  'gryfo_authorization' => 'SEU_TOKEN_AQUI',
  // Para rejeitar fotos ruins, mantenha o quality check ativo.
  'gryfo_disable_quality_check' => 0,
  'gryfo_ignore_nearest' => 0,
  'gryfo_enable_liveness' => 0,
  'gryfo_recognize_threshold' => 0.9,
  'gryfo_timeout_seconds' => 20,

  // Intervalo minimo para sincronizar progresso Moodle -> APP (por aluno/curso).
  // Evita carga excessiva no dashboard e ao abrir o curso.
  'moodle_progress_sync_interval_seconds' => 900,

  // Prefetch ao entrar: carrega matriculas/acessos do Moodle uma vez por sessao
  // e sincroniza progresso para o banco local do APP.
  'moodle_prefetch_on_login_enabled' => 1,
  'moodle_login_prefetch_progress_enabled' => 1,

  // Limite de alunos por execucao na sincronizacao em lote (admin inscritos).
  'moodle_progress_sync_bulk_limit' => 200,

  // Quando concluido no APP, replica a conclusao da atividade no Moodle.
  'moodle_completion_sync_enabled' => 1,

  // Intervalo minimo para atualizar "ultimo acesso ao curso" no Moodle
  // durante os pings de progresso do app (evita carga excessiva).
  'moodle_course_access_sync_interval_seconds' => 900,

  // Se 1, cada ping de progresso tenta atualizar "ultimo acesso" no Moodle.
  // Para modo local-first, mantenha 0.
  'moodle_track_course_access_on_progress' => 0,

  // Throttle de entrada no endpoint /api/progress/update:
  // se dois pings de "in_progress" chegarem muito proximos e com pouca variacao
  // de percentual, o segundo retorna OK sem gravar no banco.
  'progress_ingress_throttle_seconds' => 120,
  'progress_ingress_percent_step' => 10,

  // Cache curto para reduzir consultas repetidas em rotas quentes.
  'app_admin_role_cache_seconds' => 1800,
  'course_access_block_cache_seconds' => 120,

  // Intervalo minimo para checagem automatica de schema no bootstrap.
  // Em producao, mantenha alto para reduzir overhead por request.
  'schema_ensure_interval_seconds' => 21600,

  // Log detalhado do endpoint de progresso (desligado por padrao).
  'progress_debug_log_enabled' => 0,

  // Limites de compactacao da foto biometrica (salva em Base64 no banco).
  'biometric_max_width' => 360,
  'biometric_max_height' => 480,
  'biometric_max_compact_bytes' => 32 * 1024,
  // Limites de imagem enviados ao provedor externo.
  'biometric_provider_max_width' => 720,
  'biometric_provider_max_height' => 960,
  'biometric_provider_max_bytes' => 180 * 1024,

  // Chat suporte (Tawk.to)
  // Troque a URL abaixo caso mude o property/widget no provedor.
  'tawk_embed_src' => 'https://embed.tawk.to/642476d731ebfa0fe7f566a1/1gsn70esc',
];
