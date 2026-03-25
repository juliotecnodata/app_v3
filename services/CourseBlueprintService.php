<?php
declare(strict_types=1);

namespace App;

final class CourseBlueprintService {

  public static function createDraftCourse(array $input, int $creatorMoodleUserId): int {
    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
      throw new \RuntimeException('Titulo obrigatorio.');
    }

    $rawSlug = trim((string)($input['slug'] ?? ''));
    $slugProvided = $rawSlug !== '';
    $slugSource = $slugProvided ? $rawSlug : $title;
    $slug = self::slugify($slugSource);
    if ($slug === '') {
      throw new \RuntimeException('Slug invalido.');
    }

    if ($slugProvided) {
      if (self::slugExists($slug)) {
        throw new \RuntimeException('Slug ja existe. Ajuste e tente novamente.');
      }
    } else {
      $slug = self::uniqueSlug($slug);
    }

    $structure = ((string)($input['structure'] ?? 'simple') === 'complex') ? 'complex' : 'simple';
    $description = trim((string)($input['description'] ?? ''));
    $moodleCourseId = (int)($input['moodle_courseid'] ?? 0);
    $accessDays = (int)($input['access_days'] ?? 0);
    $enableSequenceLock = CoursePolicyService::bool_from_input($input['enable_sequence_lock'] ?? null, true);
    $requireBiometric = CoursePolicyService::bool_from_input($input['require_biometric'] ?? null, false);
    $finalExamUnlockHours = max(0, (int)($input['final_exam_unlock_hours'] ?? 0));

    $wizardEnabled = !empty($input['wizard_enabled']);
    $wizard = [
      'modules' => (int)($input['wizard_modules'] ?? 3),
      'units' => (int)($input['wizard_units'] ?? 4),
      'welcome' => !empty($input['wizard_welcome']),
      'materials' => !empty($input['wizard_materials']),
      'exercises' => !empty($input['wizard_exercises']),
      'final_exam' => !empty($input['wizard_final_exam']),
      'default_type' => (string)($input['wizard_default_type'] ?? 'video'),
    ];

    return Db::tx(function () use (
      $title,
      $slug,
      $description,
      $moodleCourseId,
      $accessDays,
      $enableSequenceLock,
      $requireBiometric,
      $finalExamUnlockHours,
      $creatorMoodleUserId,
      $structure,
      $wizardEnabled,
      $wizard
    ): int {
      try {
        Db::exec(
          "INSERT INTO app_course (moodle_courseid, slug, title, description, access_days, enable_sequence_lock, require_biometric, final_exam_unlock_hours, status, created_by_moodle_userid)
           VALUES (:mcid, :slug, :title, :description, :days, :sequence_lock, :require_biometric, :final_exam_unlock_hours, 'draft', :uid)",
          [
            'mcid' => $moodleCourseId > 0 ? $moodleCourseId : null,
            'slug' => $slug,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'days' => $accessDays > 0 ? $accessDays : null,
            'sequence_lock' => $enableSequenceLock,
            'require_biometric' => $requireBiometric,
            'final_exam_unlock_hours' => $finalExamUnlockHours,
            'uid' => $creatorMoodleUserId,
          ]
        );
      } catch (\PDOException $e) {
        $message = (string)$e->getMessage();
        $missingPolicyCols = strpos($message, 'enable_sequence_lock') !== false
          || strpos($message, 'require_biometric') !== false
          || strpos($message, 'final_exam_unlock_hours') !== false;
        if (!$missingPolicyCols) {
          throw $e;
        }

        Db::exec(
          "INSERT INTO app_course (moodle_courseid, slug, title, description, access_days, status, created_by_moodle_userid)
           VALUES (:mcid, :slug, :title, :description, :days, 'draft', :uid)",
          [
            'mcid' => $moodleCourseId > 0 ? $moodleCourseId : null,
            'slug' => $slug,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'days' => $accessDays > 0 ? $accessDays : null,
            'uid' => $creatorMoodleUserId,
          ]
        );
      }

      $courseId = (int)Db::lastId();
      $rootId = self::createRootNode($courseId, $title);

      if ($wizardEnabled) {
        self::buildCourseSkeleton($courseId, $rootId, $structure, $wizard);
      } else if ($structure === 'complex') {
        for ($index = 1; $index <= 2; $index++) {
          self::createNode($courseId, $rootId, 'container', 'module', self::defaultModuleTitle($index));
        }
      }

      return $courseId;
    });
  }

  private static function buildCourseSkeleton(int $courseId, int $rootId, string $structure, array $options): void {
    $modules = max(1, min(20, (int)($options['modules'] ?? 3)));
    $units = max(1, min(30, (int)($options['units'] ?? 4)));

    $welcome = !empty($options['welcome']);
    $materials = !empty($options['materials']);
    $exercises = !empty($options['exercises']);
    $finalExam = !empty($options['final_exam']);

    $defaultType = (string)($options['default_type'] ?? 'video');
    if (!in_array($defaultType, ['video', 'text'], true)) {
      $defaultType = 'video';
    }

    $contentFromType = function (string $type): array {
      if ($type === 'text') {
        return ['html' => '<p>Edite este conteudo e substitua pelo texto real da aula.</p>'];
      }

      return [
        'url' => '',
        'provider' => 'youtube',
        'min_video_percent' => 60,
      ];
    };

    if ($welcome) {
      $welcomeModule = self::createNode($courseId, $rootId, 'container', 'module', 'Boas-vindas');
      $welcomeTopic = self::createNode($courseId, $welcomeModule, 'container', 'topic', 'Comece aqui');

      self::createNode($courseId, $welcomeTopic, 'content', 'text', 'Como funciona este curso', [
        'html' => '<p>Apresente o curso, as regras e o que o aluno vai aprender.</p>',
      ]);

      self::createNode($courseId, $welcomeTopic, 'content', 'video', 'Video de boas-vindas', [
        'url' => '',
        'provider' => 'youtube',
        'min_video_percent' => 50,
      ]);
    }

    if ($structure === 'simple') {
      for ($index = 1; $index <= $units; $index++) {
        $title = $defaultType === 'text' ? 'Leitura' : 'Aula';
        self::createNode($courseId, $rootId, 'content', $defaultType, $title, $contentFromType($defaultType));
      }

      if ($materials) {
        self::createNode($courseId, $rootId, 'content', 'pdf', 'Material em PDF', ['file_path' => '']);
        self::createNode($courseId, $rootId, 'content', 'text', 'Texto de apoio', [
          'html' => '<p>Inclua materiais extras e referencias.</p>',
        ]);
      }

      if ($exercises) {
        self::createNode($courseId, $rootId, 'content', 'text', 'Exercicio de fixacao', [
          'html' => '<p>Descreva o exercicio aqui.</p>',
        ]);
      }

      if ($finalExam) {
        self::createNode($courseId, $rootId, 'action', 'final_exam', 'Prova final');
      }

      return;
    }

    for ($module = 1; $module <= $modules; $module++) {
      $moduleId = self::createNode($courseId, $rootId, 'container', 'module', self::defaultModuleTitle($module));
      $topicId = self::createNode($courseId, $moduleId, 'container', 'topic', 'Aulas');

      for ($unit = 1; $unit <= $units; $unit++) {
        $title = $defaultType === 'text' ? 'Leitura' : 'Aula';

        self::createNode($courseId, $topicId, 'content', $defaultType, $title, $contentFromType($defaultType));
      }

      if ($materials) {
        $materialsTopic = self::createNode($courseId, $moduleId, 'container', 'topic', 'Materiais');
        self::createNode($courseId, $materialsTopic, 'content', 'pdf', 'PDF de apoio', ['file_path' => '']);
        self::createNode($courseId, $materialsTopic, 'content', 'text', 'Resumo e texto de apoio', [
          'html' => '<p>Inclua materiais extras e referencias.</p>',
        ]);
      }

      if ($exercises) {
        $exerciseTopic = self::createNode($courseId, $moduleId, 'container', 'topic', 'Exercicios');
        self::createNode($courseId, $exerciseTopic, 'content', 'text', 'Exercicio do modulo', [
          'html' => '<p>Descreva o exercicio aqui.</p>',
        ]);
      }
    }

    if ($finalExam) {
      $examModule = self::createNode($courseId, $rootId, 'container', 'module', 'Avaliacao final');
      self::createNode($courseId, $examModule, 'action', 'final_exam', 'Prova final');
    }
  }

  private static function createRootNode(int $courseId, string $title): int {
    Db::exec(
      "INSERT INTO app_node (course_id, parent_id, kind, subtype, title, sort, depth, path, is_published)
       VALUES (:cid, NULL, 'container', 'root', :title, 0, 0, '', 1)",
      [
        'cid' => $courseId,
        'title' => $title,
      ]
    );

    $rootId = (int)Db::lastId();
    Db::exec("UPDATE app_node SET path = :path WHERE id = :id", [
      'path' => '/' . $rootId . '/',
      'id' => $rootId,
    ]);

    return $rootId;
  }

  private static function createNode(
    int $courseId,
    ?int $parentId,
    string $kind,
    string $subtype,
    string $title,
    ?array $content = null,
    ?array $rules = null,
    int $isPublished = 1
  ): int {
    $parentPath = '';
    $depth = 0;

    if ($parentId) {
      $parent = Db::one(
        "SELECT id, depth, path FROM app_node WHERE id = :id AND course_id = :cid LIMIT 1",
        ['id' => $parentId, 'cid' => $courseId]
      );

      if (!$parent) {
        throw new \RuntimeException('Parent invalido.');
      }

      $depth = (int)$parent['depth'] + 1;
      $parentPath = (string)$parent['path'];
    }

    $params = ['cid' => $courseId];
    $whereParent = 'parent_id IS NULL';

    if ($parentId) {
      $whereParent = 'parent_id = :pid';
      $params['pid'] = $parentId;
    }

    $maxSort = Db::one("SELECT COALESCE(MAX(sort),0) AS ms FROM app_node WHERE course_id = :cid AND {$whereParent}", $params);
    $sort = ((int)($maxSort['ms'] ?? 0)) + 1;

    $contentJson = $content ? json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $rulesJson = $rules ? json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $countInProgressPercent = ($kind === 'action' && $subtype === 'certificate') ? 0 : 1;

    Db::exec(
      "INSERT INTO app_node (course_id, parent_id, kind, subtype, title, sort, depth, path, content_json, rules_json, is_published, count_in_progress_percent)
       VALUES (:cid, :pid, :kind, :subtype, :title, :sort, :depth, '', :content, :rules, :published, :cpp)",
      [
        'cid' => $courseId,
        'pid' => $parentId,
        'kind' => $kind,
        'subtype' => $subtype,
        'title' => $title,
        'sort' => $sort,
        'depth' => $depth,
        'content' => $contentJson,
        'rules' => $rulesJson,
        'published' => $isPublished ? 1 : 0,
        'cpp' => $countInProgressPercent,
      ]
    );

    $id = (int)Db::lastId();
    $path = rtrim($parentPath, '/') . '/' . $id . '/';
    Db::exec("UPDATE app_node SET path = :path WHERE id = :id", ['path' => $path, 'id' => $id]);

    ProgressCatchupService::backfill_new_counted_node_for_completed_students(
      $courseId,
      $id,
      [
        'id' => $id,
        'course_id' => $courseId,
        'kind' => $kind,
        'subtype' => $subtype,
        'is_published' => $isPublished ? 1 : 0,
        'rules_json' => $rulesJson,
        'count_in_progress_percent' => $countInProgressPercent,
      ],
      $id
    );

    return $id;
  }

  private static function slugify(string $value): string {
    $value = mb_strtolower($value);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
      $value = $ascii;
    }

    $value = preg_replace('/[^a-z0-9]+/i', '-', (string)$value);
    $value = trim((string)$value, '-');
    return substr($value, 0, 120);
  }

  private static function slugExists(string $slug): bool {
    return Db::one("SELECT id FROM app_course WHERE slug = :slug LIMIT 1", ['slug' => $slug]) !== null;
  }

  private static function uniqueSlug(string $slug): string {
    $base = $slug;
    $index = 2;

    while (self::slugExists($slug)) {
      $slug = $base . '-' . $index;
      $index++;
    }

    return $slug;
  }

  private static function defaultModuleTitle(int $index): string {
    $titles = [
      'Modulo de introducao',
      'Modulo de desenvolvimento',
      'Modulo de pratica',
      'Modulo de revisao',
      'Modulo de consolidacao',
    ];

    $position = max(0, $index - 1);
    if (isset($titles[$position])) {
      return $titles[$position];
    }
    return 'Modulo complementar';
  }
}
