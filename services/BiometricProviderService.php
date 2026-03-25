<?php
declare(strict_types=1);

namespace App;

final class BiometricProviderService {
  private const PROVIDER_GRYFO = 'gryfo';
  private static ?bool $providerAuditColumnsAvailable = null;

  public static function verify_for_user(int $moodleUserId, string $imageBase64): array {
    $provider = strtolower(trim((string)SettingsService::biometric_provider()));
    if ($provider === '') {
      return [
        'enabled' => false,
        'provider' => '',
        'approved' => true,
        'status' => 'approved',
        'operation' => 'local',
        'external_id' => '',
        'external_source' => '',
        'http_status' => 0,
        'score' => null,
        'message' => '',
        'response_json' => '',
      ];
    }

    if ($provider !== self::PROVIDER_GRYFO) {
      return [
        'enabled' => true,
        'provider' => $provider,
        'approved' => false,
        'status' => 'error',
        'operation' => 'unsupported',
        'external_id' => '',
        'external_source' => '',
        'http_status' => 0,
        'score' => null,
        'message' => 'Provedor biometrico nao suportado no app.',
        'response_json' => '',
      ];
    }

    return self::verify_with_gryfo($moodleUserId, $imageBase64);
  }

  private static function verify_with_gryfo(int $moodleUserId, string $imageBase64): array {
    $authorization = trim((string)App::cfg('gryfo_authorization', ''));
    $baseUrl = rtrim(trim((string)App::cfg('gryfo_base_url', 'https://api.gryfo.com.br')), '/');
    if ($authorization === '' || $baseUrl === '') {
      return [
        'enabled' => true,
        'provider' => self::PROVIDER_GRYFO,
        'approved' => false,
        'status' => 'error',
        'operation' => 'config',
        'external_id' => '',
        'external_source' => '',
        'http_status' => 0,
        'score' => null,
        'message' => 'Integracao biometrica Gryfo nao configurada no app.',
        'response_json' => '',
      ];
    }

    $identity = self::resolve_identity($moodleUserId);
    $externalId = $identity['external_id'];
    $externalSource = $identity['external_source'];

    $hasLocalReference = self::has_local_reference($moodleUserId, $externalId);
    if (!$hasLocalReference) {
      $register = self::gryfo_register($baseUrl, $authorization, $externalId, $imageBase64);
      if ($register['ok']) {
        return self::build_result(true, 'approved', 'register', $externalId, $externalSource, $register);
      }

      if (self::is_existing_person_response($register)) {
        $recognizeExisting = self::gryfo_recognize($baseUrl, $authorization, $externalId, $imageBase64);
        if ($recognizeExisting['ok']) {
          return self::build_result(true, 'approved', 'recognize', $externalId, $externalSource, $recognizeExisting);
        }

        return self::build_result(false, 'rejected', 'recognize', $externalId, $externalSource, $recognizeExisting);
      }

      return self::build_result(false, 'rejected', 'register', $externalId, $externalSource, $register);
    }

    $recognize = self::gryfo_recognize($baseUrl, $authorization, $externalId, $imageBase64);
    if ($recognize['ok']) {
      return self::build_result(true, 'approved', 'recognize', $externalId, $externalSource, $recognize);
    }

    return self::build_result(false, 'rejected', 'recognize', $externalId, $externalSource, $recognize);
  }

  private static function resolve_identity(int $moodleUserId): array {
    global $DB;

    $user = \core_user::get_user($moodleUserId, 'id,username,firstname,lastname,email,idnumber', IGNORE_MISSING);
    $customFields = [];
    $username = trim((string)($user->username ?? ''));

    try {
      $rows = $DB->get_records_sql(
        "SELECT f.shortname, d.data
           FROM {user_info_data} d
           JOIN {user_info_field} f ON f.id = d.fieldid
          WHERE d.userid = :userid",
        ['userid' => $moodleUserId]
      );

      foreach ($rows as $row) {
        $shortname = strtolower(trim((string)($row->shortname ?? '')));
        if ($shortname === '') {
          continue;
        }
        $customFields[$shortname] = trim((string)($row->data ?? ''));
      }
    } catch (\Throwable $exception) {
      error_log('[app_v3][biometric] Falha ao ler campos personalizados do usuario: ' . $exception->getMessage());
    }

    $usernameCpf = self::extract_cpf_from_username($username);

    $sources = [
      'username_cpf' => $usernameCpf,
      'cpf' => $customFields['cpf'] ?? '',
      'cpf_cnpj' => $customFields['cpf_cnpj'] ?? '',
      'cpfcnpj' => $customFields['cpfcnpj'] ?? '',
      'documento' => $customFields['documento'] ?? '',
      'document' => $customFields['document'] ?? '',
      'doc' => $customFields['doc'] ?? '',
      'idnumber' => trim((string)($user->idnumber ?? '')),
      'username' => $username,
      'userid' => (string)$moodleUserId,
    ];

    foreach ($sources as $source => $value) {
      $normalized = self::normalize_external_id($value, $source);
      if ($normalized !== '') {
        return [
          'external_id' => $normalized,
          'external_source' => $source,
        ];
      }
    }

    return [
      'external_id' => 'user-' . $moodleUserId,
      'external_source' => 'userid',
    ];
  }

  private static function normalize_external_id(string $value, string $source): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $cpfLikeSources = ['username_cpf', 'cpf', 'cpf_cnpj', 'cpfcnpj', 'documento', 'document', 'doc', 'idnumber'];
    if (in_array($source, $cpfLikeSources, true)) {
      $digits = preg_replace('/\D+/', '', $value);
      if (is_string($digits) && $digits !== '') {
        return $digits;
      }
    }

    $normalized = preg_replace('/\s+/', '', $value);
    if (!is_string($normalized)) {
      return '';
    }
    return $normalized;
  }

  private static function extract_cpf_from_username(string $username): string {
    $username = trim($username);
    if ($username === '') {
      return '';
    }

    if (preg_match('/(\d{11})$/', $username, $matches) === 1) {
      return (string)$matches[1];
    }

    $digits = preg_replace('/\D+/', '', $username);
    if (!is_string($digits) || strlen($digits) < 11) {
      return '';
    }

    return substr($digits, -11);
  }

  private static function has_local_reference(int $moodleUserId, string $externalId): bool {
    $params = [
      'moodle_userid' => $moodleUserId,
      'status' => 'approved',
    ];

    $sql = "SELECT id
              FROM app_course_biometric_audit
             WHERE moodle_userid = :moodle_userid
               AND status = :status";

    if (self::provider_audit_columns_available()) {
      $sql .= " AND provider_name = :provider_name";
      $params['provider_name'] = self::PROVIDER_GRYFO;

      if ($externalId !== '') {
        $sql .= " AND provider_external_id = :external_id";
        $params['external_id'] = $externalId;
      }
    }

    $sql .= " ORDER BY captured_at DESC, id DESC LIMIT 1";
    return Db::one($sql, $params) !== null;
  }

  private static function provider_audit_columns_available(): bool {
    if (self::$providerAuditColumnsAvailable !== null) {
      return self::$providerAuditColumnsAvailable;
    }

    self::$providerAuditColumnsAvailable = self::check_provider_audit_columns();
    if (!self::$providerAuditColumnsAvailable) {
      try {
        Schema::ensure_biometric_provider_columns();
      } catch (\Throwable $exception) {
        error_log('[app_v3][biometric] Falha ao garantir colunas de auditoria biometrica: ' . $exception->getMessage());
      }

      self::$providerAuditColumnsAvailable = self::check_provider_audit_columns();
    }

    return self::$providerAuditColumnsAvailable;
  }

  private static function check_provider_audit_columns(): bool {
    try {
      $row = Db::one(
        "SELECT COUNT(*) AS total
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'app_course_biometric_audit'
            AND COLUMN_NAME IN ('provider_name', 'provider_external_id')"
      );
      return (int)($row['total'] ?? 0) >= 2;
    } catch (\Throwable $exception) {
      return false;
    }
  }

  private static function gryfo_register(string $baseUrl, string $authorization, string $externalId, string $imageBase64): array {
    $payload = self::gryfo_common_payload($externalId, $imageBase64);
    return self::gryfo_request($baseUrl . '/register', $authorization, $payload);
  }

  private static function gryfo_recognize(string $baseUrl, string $authorization, string $externalId, string $imageBase64): array {
    $payload = self::gryfo_common_payload($externalId, $imageBase64);
    $payload['person']['recognize_threshold'] = (float)App::cfg('gryfo_recognize_threshold', 0.9);
    return self::gryfo_request($baseUrl . '/recognize', $authorization, $payload);
  }

  private static function gryfo_common_payload(string $externalId, string $imageBase64): array {
    return [
      'disable_quality_check' => self::cfg_bool('gryfo_disable_quality_check', false),
      'ignore_nearest' => self::cfg_bool('gryfo_ignore_nearest', false),
      'enable_liveness' => self::cfg_bool('gryfo_enable_liveness', false),
      'person' => [
        'external_id' => $externalId,
        'image' => $imageBase64,
      ],
    ];
  }

  private static function gryfo_request(string $url, string $authorization, array $payload): array {
    if (!function_exists('curl_init')) {
      return [
        'ok' => false,
        'http_status' => 0,
        'message' => 'Extensao cURL nao disponivel no servidor.',
        'response_json' => '',
        'score' => null,
      ];
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === false) {
      return [
        'ok' => false,
        'http_status' => 0,
        'message' => 'Falha ao montar payload biometrico.',
        'response_json' => '',
        'score' => null,
      ];
    }

    $timeout = max(5, (int)App::cfg('gryfo_timeout_seconds', 20));
    $curl = curl_init($url);
    curl_setopt_array($curl, [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: ' . $authorization,
      ],
      CURLOPT_POSTFIELDS => $jsonPayload,
    ]);

    $rawResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    $responseText = is_string($rawResponse) ? trim($rawResponse) : '';
    $decoded = null;
    if ($responseText !== '') {
      $decoded = json_decode($responseText, true);
      if (!is_array($decoded)) {
        $decoded = null;
      }
    }

    if ($curlError !== '') {
      return [
        'ok' => false,
        'http_status' => $httpStatus,
        'message' => 'Falha ao comunicar com o provedor biometrico: ' . $curlError,
        'response_json' => $responseText,
        'score' => self::extract_score($decoded),
      ];
    }

    $message = self::extract_message($decoded);
    $ok = ($httpStatus >= 200 && $httpStatus < 300) && !self::payload_has_explicit_error($decoded);
    if ($ok && self::payload_has_explicit_negative_result($decoded)) {
      $ok = false;
    }

    if ($message === '') {
      if ($ok) {
        $message = 'Validacao biometrica aprovada.';
      } else {
        $message = 'Foto rejeitada pelo validador biometrico.';
      }
    }

    $message = self::normalize_provider_message($decoded, $message, $ok);

    return [
      'ok' => $ok,
      'http_status' => $httpStatus,
      'message' => $message,
      'response_json' => $responseText,
      'score' => self::extract_score($decoded),
    ];
  }

  private static function build_result(
    bool $approved,
    string $status,
    string $operation,
    string $externalId,
    string $externalSource,
    array $providerResponse
  ): array {
    return [
      'enabled' => true,
      'provider' => self::PROVIDER_GRYFO,
      'approved' => $approved,
      'status' => $status,
      'operation' => $operation,
      'external_id' => $externalId,
      'external_source' => $externalSource,
      'http_status' => (int)($providerResponse['http_status'] ?? 0),
      'score' => isset($providerResponse['score']) ? (float)$providerResponse['score'] : null,
      'message' => (string)($providerResponse['message'] ?? ''),
      'response_json' => (string)($providerResponse['response_json'] ?? ''),
    ];
  }

  private static function payload_has_explicit_error(?array $payload): bool {
    if (!$payload) {
      return false;
    }

    foreach (['error', 'errors', 'exception'] as $key) {
      if (array_key_exists($key, $payload) && !empty($payload[$key])) {
        return true;
      }
    }

    return false;
  }

  private static function payload_has_explicit_negative_result(?array $payload): bool {
    if (!$payload) {
      return false;
    }

    $negativeKeys = ['success', 'ok', 'match', 'matched', 'recognized', 'approved'];
    foreach ($negativeKeys as $key) {
      if (!array_key_exists($key, $payload)) {
        continue;
      }

      $value = $payload[$key];
      if ($value === false || $value === 0 || $value === '0') {
        return true;
      }

      if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['false', 'no', 'nao', 'não', 'rejected', 'denied', 'failed'], true)) {
          return true;
        }
      }
    }

    return false;
  }

  private static function is_existing_person_response(array $response): bool {
    $httpStatus = (int)($response['http_status'] ?? 0);
    $message = strtolower(trim((string)($response['message'] ?? '')));
    if ($httpStatus === 409) {
      return true;
    }

    if ($message === '') {
      return false;
    }

    $fragments = [
      'already exists',
      'already registered',
      'ja existe',
      'já existe',
      'ja cadastrado',
      'já cadastrado',
      'duplicate',
      'duplicado',
      'conflict',
    ];

    foreach ($fragments as $fragment) {
      if (strpos($message, $fragment) !== false) {
        return true;
      }
    }

    return false;
  }

  private static function extract_message(?array $payload): string {
    if (!$payload) {
      return '';
    }

    $keys = ['message', 'error', 'detail', 'description'];
    foreach ($keys as $key) {
      if (!array_key_exists($key, $payload)) {
        continue;
      }

      $value = $payload[$key];
      if (is_string($value) && trim($value) !== '') {
        return trim($value);
      }

      if (is_array($value)) {
        $flattened = self::flatten_scalar_values($value);
        if ($flattened !== '') {
          return $flattened;
        }
      }
    }

    return '';
  }

  private static function extract_score(?array $payload): ?float {
    if (!$payload) {
      return null;
    }

    $keys = ['score', 'confidence', 'similarity', 'distance', 'probability'];
    $stack = [$payload];

    while ($stack !== []) {
      $current = array_pop($stack);
      if (!is_array($current)) {
        continue;
      }

      foreach ($current as $key => $value) {
        if (is_array($value)) {
          $stack[] = $value;
          continue;
        }

        if (!is_scalar($value)) {
          continue;
        }

        $normalizedKey = strtolower((string)$key);
        if (!in_array($normalizedKey, $keys, true)) {
          continue;
        }

        if (is_numeric($value)) {
          return (float)$value;
        }
      }
    }

    return null;
  }

  private static function normalize_provider_message(?array $payload, string $message, bool $ok): string {
    $message = trim($message);
    if ($ok) {
      return $message;
    }

    $responseCode = (int)($payload['response_code'] ?? 0);
    $qualityInfo = strtolower(trim((string)($payload['image_quality_info'] ?? '')));
    $normalized = strtolower($message);

    if (
      $responseCode === 109
      || strpos($normalized, 'bad quality') !== false
      || strpos($qualityInfo, 'bad quality') !== false
    ) {
      return 'A foto nao passou na validacao de qualidade. Tire outra selfie com boa iluminacao e o rosto inteiro dentro da moldura.';
    }

    if (strpos($normalized, 'multiple face') !== false || strpos($normalized, 'more than one face') !== false) {
      return 'Foi detectado mais de um rosto na foto. Fique sozinho no enquadramento e tente novamente.';
    }

    if (
      strpos($normalized, 'face not found') !== false
      || strpos($normalized, 'no face') !== false
      || strpos($qualityInfo, 'face not found') !== false
    ) {
      return 'Nao foi possivel identificar seu rosto na selfie. Centralize o rosto e capture novamente.';
    }

    return $message;
  }

  private static function flatten_scalar_values(array $payload): string {
    $values = [];
    array_walk_recursive($payload, static function ($value) use (&$values): void {
      if (is_scalar($value)) {
        $text = trim((string)$value);
        if ($text !== '') {
          $values[] = $text;
        }
      }
    });

    return implode(' | ', array_slice($values, 0, 4));
  }

  private static function cfg_bool(string $key, bool $default): bool {
    $value = App::cfg($key, $default ? 1 : 0);
    if (is_bool($value)) {
      return $value;
    }

    $normalized = strtolower(trim((string)$value));
    if (in_array($normalized, ['1', 'true', 'on', 'sim', 'yes'], true)) {
      return true;
    }
    if (in_array($normalized, ['0', 'false', 'off', 'nao', 'não', 'no'], true)) {
      return false;
    }

    return $default;
  }
}
