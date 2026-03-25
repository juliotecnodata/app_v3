<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Csrf;
use App\Response;

final class AuthController {

  public static function login(): void {
    if (\isloggedin() && !\isguestuser()) {
      Response::redirect(self::resolve_post_login_redirect());
    }

    $err = null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
      $token = (string)($_POST['csrf'] ?? '');
      Csrf::check($token);

      $username = trim((string)($_POST['username'] ?? ''));
      $password = (string)($_POST['password'] ?? '');

      if ($username === '' || $password === '') {
        $err = 'Informe usuario e senha.';
      } else {
        $ok = Auth::login_with_moodle($username, $password);
        if ($ok) {
          App::flash_set('success', 'Bem-vindo!');
          Response::redirect(self::resolve_post_login_redirect());
        } else {
          $err = 'Credenciais invalidas. Verifique e tente novamente.';
        }
      }
    }

    Response::html(App::render('auth/login.php', [
      'csrf' => Csrf::token(),
      'error' => $err,
    ]));
  }

  public static function logout(): void {
    Auth::logout_and_redirect(App::base_url('/login'));
  }

  private static function resolve_post_login_redirect(): string {
    global $CFG;

    $user = Auth::user();
    $moodleUserId = (int)($user->id ?? 0);
    if ($moodleUserId <= 0) {
      return App::base_url('/dashboard');
    }

    if (Auth::is_app_admin($moodleUserId)) {
      return App::base_url('/admin/cursos-roda-app');
    }

    Auth::ensure_runtime_snapshot(true);
    if (Auth::has_any_cached_app_enrollment()) {
      return App::base_url('/dashboard');
    }

    return rtrim((string)$CFG->wwwroot, '/') . '/my/courses.php';
  }
}
