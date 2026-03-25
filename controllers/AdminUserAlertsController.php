<?php
declare(strict_types=1);

namespace App\Controllers;

use App\App;
use App\Auth;
use App\Csrf;
use App\Db;
use App\Response;
use App\UserAlertService;

final class AdminUserAlertsController {
  public static function index(): void {
    Auth::require_app_admin();

    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
      self::handle_post();
      return;
    }

    $selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
    $openModal = $editId > 0 || isset($_GET['new']);

    $courseFilters = Db::all(
      "SELECT id, title, moodle_courseid
         FROM app_course
        ORDER BY title ASC, id DESC"
    );

    $selectedCourse = null;
    foreach ($courseFilters as $course) {
      if ((int)($course['id'] ?? 0) === $selectedCourseId) {
        $selectedCourse = $course;
        break;
      }
    }
    if ($selectedCourseId > 0 && $selectedCourse === null) {
      $selectedCourseId = 0;
    }

    $editAlert = null;
    if ($editId > 0) {
      $editAlert = UserAlertService::get($editId);
      if ($editAlert) {
        $editCourseId = (int)($editAlert['course_id'] ?? 0);
        if ($editCourseId > 0) {
          $selectedCourseId = $editCourseId;
          foreach ($courseFilters as $course) {
            if ((int)($course['id'] ?? 0) === $selectedCourseId) {
              $selectedCourse = $course;
              break;
            }
          }
        }
      }
    }

    $students = $selectedCourseId > 0 && is_array($selectedCourse)
      ? self::course_students((int)($selectedCourse['moodle_courseid'] ?? 0))
      : [];

    $rows = UserAlertService::list_recent($selectedCourseId > 0 ? $selectedCourseId : null);
    $summary = UserAlertService::summary($selectedCourseId > 0 ? $selectedCourseId : null);

    Response::html(App::render('admin/user_alerts/index.php', [
      'user' => Auth::user(),
      'rows' => $rows,
      'summary' => $summary,
      'courseFilters' => $courseFilters,
      'selectedCourseId' => $selectedCourseId,
      'selectedCourse' => $selectedCourse,
      'students' => $students,
      'editAlert' => $editAlert,
      'openModal' => $openModal,
      'flash' => App::flash_get(),
      'csrf' => Csrf::token(),
    ]));
  }

  private static function handle_post(): void {
    Csrf::check((string)($_POST['csrf'] ?? ''));

    $action = trim((string)($_POST['action'] ?? 'save'));
    $selectedCourseId = (int)($_POST['selected_course_id'] ?? $_POST['course_id'] ?? 0);
    $redirectBase = App::base_url('/admin/alertas-aluno' . ($selectedCourseId > 0 ? '?course_id=' . $selectedCourseId : ''));

    if ($action === 'dismiss') {
      $alertId = (int)($_POST['id'] ?? 0);
      if ($alertId <= 0) {
        App::flash_set('error', 'Alerta nao informado.');
        Response::redirect($redirectBase);
      }

      UserAlertService::dismiss($alertId, (int)(Auth::user()->id ?? 0));
      App::flash_set('success', 'Alerta encerrado.');
      Response::redirect($redirectBase);
    }

    if ($action === 'delete') {
      $alertId = (int)($_POST['id'] ?? 0);
      if ($alertId <= 0) {
        App::flash_set('error', 'Alerta nao informado.');
        Response::redirect($redirectBase);
      }

      UserAlertService::delete($alertId);
      App::flash_set('success', 'Alerta excluido.');
      Response::redirect($redirectBase);
    }

    $alertId = (int)($_POST['id'] ?? 0);
    $payload = [
      'moodle_userid' => (int)($_POST['moodle_userid'] ?? 0),
      'course_id' => (int)($_POST['course_id'] ?? 0),
      'scope' => (string)($_POST['scope'] ?? 'all'),
      'severity' => (string)($_POST['severity'] ?? 'warning'),
      'title' => (string)($_POST['title'] ?? ''),
      'message' => (string)($_POST['message'] ?? ''),
      'is_blocking' => (int)($_POST['is_blocking'] ?? 0) === 1 ? 1 : 0,
      'require_ack' => (int)($_POST['require_ack'] ?? 1) === 1 ? 1 : 0,
      'active_from' => self::compose_datetime((string)($_POST['active_from_date'] ?? ''), (string)($_POST['active_from_time'] ?? '')),
      'expires_at' => self::compose_datetime((string)($_POST['expires_at_date'] ?? ''), (string)($_POST['expires_at_time'] ?? '')),
    ];

    try {
      if ($alertId > 0) {
        UserAlertService::update($alertId, $payload);
        App::flash_set('success', 'Alerta atualizado.');
      } else {
        UserAlertService::create($payload, (int)(Auth::user()->id ?? 0));
        App::flash_set('success', 'Alerta enviado para o aluno.');
      }
    } catch (\Throwable $exception) {
      $errorRedirect = $redirectBase . ($alertId > 0 ? (str_contains($redirectBase, '?') ? '&' : '?') . 'edit=' . $alertId : '');
      App::flash_set('error', $exception->getMessage());
      Response::redirect($errorRedirect);
    }

    Response::redirect($redirectBase);
  }

  private static function course_students(int $moodleCourseId): array {
    global $DB;

    if ($moodleCourseId <= 0) {
      return [];
    }

    $records = $DB->get_records_sql(
      "SELECT DISTINCT u.id, u.username, u.email, u.firstname, u.lastname
         FROM {enrol} e
         JOIN {user_enrolments} ue
           ON ue.enrolid = e.id
         JOIN {user} u
           ON u.id = ue.userid
        WHERE e.courseid = :course_id
          AND e.status = 0
          AND ue.status = 0
          AND u.deleted = 0
        ORDER BY u.firstname ASC, u.lastname ASC, u.id ASC",
      ['course_id' => $moodleCourseId]
    );

    $rows = [];
    foreach ($records as $record) {
      $rows[] = [
        'id' => (int)($record->id ?? 0),
        'username' => (string)($record->username ?? ''),
        'email' => (string)($record->email ?? ''),
        'full_name' => trim((string)\fullname($record)),
      ];
    }

    return $rows;
  }

  private static function compose_datetime(string $date, string $time): string {
    $date = trim($date);
    $time = trim($time);
    if ($date === '') {
      return '';
    }
    if ($time === '') {
      $time = '00:00';
    }
    return $date . ' ' . $time;
  }
}
