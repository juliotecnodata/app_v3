<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Csrf;
use App\Response;
use App\SettingsService;

final class AdminBiometricSettingsController {
  public static function index(): void {
    Auth::require_app_admin();
    $user = Auth::user();
    $error = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      try {
        Csrf::check((string)($_POST['csrf'] ?? ''));
        $mode = strtolower(trim((string)($_POST['biometric_provider_mode'] ?? 'simple')));
        if (!in_array($mode, ['simple', 'gryfo'], true)) {
          $mode = 'simple';
        }

        SettingsService::set('biometric_provider_mode', $mode, (int)($user->id ?? 0));
        App::flash_set('success', $mode === 'gryfo'
          ? 'Biometria Gryfo ativada no app.'
          : 'Biometria simples ativada no app.'
        );
        Response::redirect(App::base_url('/admin/biometria-config'));
        return;
      } catch (\Throwable $exception) {
        $error = $exception->getMessage();
      }
    }

    $mode = SettingsService::biometric_mode();
    $usingGryfo = $mode === 'gryfo';

    $actions = '<div class="admin-page-toolbar">'
      . '<span class="admin-page-chip ' . ($usingGryfo ? 'is-success' : 'is-neutral') . '">'
      . ($usingGryfo ? 'Modo Gryfo ativo' : 'Modo simples ativo')
      . '</span>'
      . '</div>';

    Response::html(App::render('admin/biometric_settings/index.php', [
      'user' => $user,
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
      'error' => $error,
      'biometric_mode' => $mode,
      'admin_page_kicker' => 'Painel administrativo',
      'admin_page_title' => 'Configuracao de biometria',
      'admin_page_subtitle' => 'Escolha se o app usa biometria simples ou validacao externa Gryfo.',
      'admin_page_actions' => $actions,
    ]));
  }
}
