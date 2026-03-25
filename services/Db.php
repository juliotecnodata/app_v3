<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Db {
  private static ?PDO $pdo = null;
  private static int $txDepth = 0;
  private static array $config = [];

  public static function init(array $cfg): void {
    // Inicializacao lazy:
    // - apenas armazena configuracao
    // - conexao real abre somente no primeiro uso (one/all/exec/tx)
    // Isso reduz conexoes desnecessarias por request.
    self::$config = $cfg;
  }

  private static function connectIfNeeded(): void {
    if (self::$pdo instanceof PDO) {
      return;
    }

    $cfg = self::$config;
    $dsn = trim((string)($cfg['dsn'] ?? ''));
    $user = (string)($cfg['user'] ?? '');
    $pass = array_key_exists('pass', $cfg)
      ? (string)$cfg['pass']
      : (string)($cfg['password'] ?? '');
    $opts = (array)($cfg['opts'] ?? []);
    $connectRetries = max(1, (int)($cfg['connect_retries'] ?? 2));
    $retryDelayMs = max(0, (int)($cfg['connect_retry_delay_ms'] ?? 250));

    if ($dsn === '') {
      throw new \RuntimeException('DSN do banco do APP nao foi configurado.');
    }

    $defaults = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];

    foreach ($defaults as $key => $value) {
      if (!array_key_exists($key, $opts)) $opts[$key] = $value;
    }

    $lastException = null;
    $attemptsMade = 0;
    for ($attempt = 1; $attempt <= $connectRetries; $attempt++) {
      $attemptsMade = $attempt;
      try {
        self::$pdo = new PDO($dsn, $user, $pass, $opts);
        self::applySessionSettings(self::$pdo, $cfg);
        return;
      } catch (PDOException $exception) {
        $lastException = $exception;
        $code = (string)$exception->getCode();
        $nonRetryable = ($code === '1226' || $code === '1040' || $code === '1045' || $code === '28000');
        if ($nonRetryable) {
          break;
        }
        if ($attempt < $connectRetries && $retryDelayMs > 0) {
          usleep($retryDelayMs * 1000);
        }
      }
    }

    $errorCode = $lastException instanceof PDOException ? (string)$lastException->getCode() : '';
    $errorMessage = $lastException instanceof PDOException ? trim((string)$lastException->getMessage()) : '';
    error_log(
      '[app_v3][db_init] connect_failed'
      . ' dsn=' . self::mask_dsn($dsn)
      . ' user=' . ($user !== '' ? $user : '(empty)')
      . ' attempts=' . $attemptsMade
      . ($errorCode !== '' ? ' code=' . $errorCode : '')
      . ($errorMessage !== '' ? ' message=' . $errorMessage : '')
    );

    if ($errorCode === '1226') {
      throw new \RuntimeException('Limite de conexoes do banco do APP excedido. Ajuste o recurso max_connections_per_hour no MySQL do APP.', 0, $lastException);
    }

    throw new \RuntimeException('Falha ao conectar no banco do APP. Verifique app_v3/includes/config.php.', 0, $lastException);
  }

  private static function mask_dsn(string $dsn): string {
    $safe = preg_replace('/password=[^;]+/i', 'password=***', $dsn);
    if (!is_string($safe)) {
      return 'invalid_dsn';
    }
    return $safe;
  }

  private static function applySessionSettings(PDO $pdo, array $cfg): void {
    $sessionTimeZone = trim((string)($cfg['session_time_zone'] ?? ''));
    if ($sessionTimeZone !== '') {
      try {
        $statement = $pdo->prepare('SET time_zone = :tz');
        $statement->execute(['tz' => $sessionTimeZone]);
      } catch (\Throwable $exception) {
        error_log('[app_v3][db_init] failed_to_set_time_zone tz=' . $sessionTimeZone . ' message=' . $exception->getMessage());
      }
    }
  }

  public static function pdo(): PDO {
    if (!(self::$pdo instanceof PDO)) {
      self::connectIfNeeded();
    }
    if (!(self::$pdo instanceof PDO)) {
      throw new \RuntimeException('DB nao inicializado. Verifique bootstrap/config do APP.');
    }
    return self::$pdo;
  }

  public static function one(string $sql, array $params = []): ?array {
    $statement = self::pdo()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row !== false ? $row : null;
  }

  public static function all(string $sql, array $params = []): array {
    $statement = self::pdo()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
  }

  public static function exec(string $sql, array $params = []): int {
    $statement = self::pdo()->prepare($sql);
    $statement->execute($params);
    return $statement->rowCount();
  }

  public static function lastId(): string {
    return self::pdo()->lastInsertId();
  }

  public static function tx(callable $callback) {
    $pdo = self::pdo();
    $savepoint = null;
    $isOuter = self::$txDepth === 0;

    if ($isOuter) {
      $pdo->beginTransaction();
    } else {
      $savepoint = 'app_tx_' . self::$txDepth;
      $pdo->exec('SAVEPOINT ' . $savepoint);
    }

    self::$txDepth++;

    try {
      $result = $callback($pdo);
      self::$txDepth--;

      if ($isOuter) {
        $pdo->commit();
      } else if ($savepoint !== null) {
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
      }

      return $result;
    } catch (\Throwable $exception) {
      self::$txDepth--;

      if ($isOuter) {
        if ($pdo->inTransaction()) $pdo->rollBack();
      } else if ($savepoint !== null) {
        $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
      }

      throw $exception;
    }
  }
}
