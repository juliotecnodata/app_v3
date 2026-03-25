<?php
declare(strict_types=1);

namespace App;

final class Csrf {
  public static function token(): string {
    global $SESSION;
    if (empty($SESSION->app_csrf)) {
      $SESSION->app_csrf = bin2hex(random_bytes(16));
    }
    return (string)$SESSION->app_csrf;
  }

  public static function check(?string $token): void {
    global $SESSION;
    $expected = (string)($SESSION->app_csrf ?? '');
    if (!$expected || !$token || !hash_equals($expected, $token)) {
      throw new \RuntimeException('CSRF inválido. Recarregue a página e tente novamente.');
    }
  }
}
