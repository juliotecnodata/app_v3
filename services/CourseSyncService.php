<?php
declare(strict_types=1);

namespace App;

final class CourseSyncService {

  public static function importFromMoodle(int $courseId, int $moodleCourseId, string $mode = 'replace', array $syncOverrides = []): array {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    \rebuild_course_cache($moodleCourseId, true);

    $course = \get_course($moodleCourseId);
    if (!$course) {
      throw new \RuntimeException('Curso Moodle nao encontrado.');
    }

    $modinfo = \get_fast_modinfo($moodleCourseId);
    $sectionsInfo = $modinfo->get_section_info_all();
    $courseContext = \context_course::instance($moodleCourseId);

    $stats = [
      'sections' => 0,
      'nodes' => 0,
      'skipped' => 0,
      'skipped_items_override' => 0,
      'skipped_sections_override' => 0,
      'skipped_existing' => 0,
      'sections_reused' => 0,
      'summaries_reused' => 0,
      'final_exam_set' => false,
      'cover_synced' => false,
    ];

    $normalizedOverrides = self::normalizeSyncOverrides($syncOverrides);
    $itemSubtypeOverrides = $normalizedOverrides['item_subtype'];
    $itemTitleOverrides = $normalizedOverrides['item_title'];
    $sectionTitleOverrides = $normalizedOverrides['section_title'];
    $sectionLevelOverrides = $normalizedOverrides['section_level'];
    $sectionSubtypeOverrides = $normalizedOverrides['section_subtype'];
    $itemCmidOverrides = $normalizedOverrides['item_cmid'];
    $itemUrlOverrides = $normalizedOverrides['item_url'];
    $itemSkipOverrides = $normalizedOverrides['item_skip'];
    $sectionSkipOverrides = $normalizedOverrides['section_skip'];

    Db::tx(function () use (
      $courseId,
      $moodleCourseId,
      $mode,
      $itemSubtypeOverrides,
      $itemTitleOverrides,
      $sectionTitleOverrides,
      $sectionLevelOverrides,
      $sectionSubtypeOverrides,
      $itemCmidOverrides,
      $itemUrlOverrides,
      $itemSkipOverrides,
      $sectionSkipOverrides,
      $course,
      $modinfo,
      $sectionsInfo,
      $courseContext,
      &$stats,
      $DB
    ): void {
      $root = self::ensureRootNode($courseId, (string)($course->fullname ?? 'Curso'));
      $rootId = (int)$root['id'];
      $isReplaceMode = ($mode === 'replace');
      $isMergeMode = !$isReplaceMode;

      if ($isReplaceMode) {
        Db::exec("DELETE FROM app_node WHERE course_id = :cid AND parent_id IS NOT NULL", ['cid' => $courseId]);
        Db::exec("DELETE FROM app_course_exam WHERE course_id = :cid", ['cid' => $courseId]);
      }

      $existingSyncState = $isMergeMode ? self::collectExistingSyncState($courseId) : [
        'cmids' => [],
        'section_containers' => [],
        'section_summaries' => [],
        'final_exam_exists' => false,
      ];
      $existingMappedCmids = $existingSyncState['cmids'];

      $finalExamSet = $isMergeMode && !empty($existingSyncState['final_exam_exists']);
      if ($finalExamSet) {
        $stats['final_exam_set'] = true;
      }
      $sectionContainerByLevel = [];
      $sectionContainerBySection = $isMergeMode ? $existingSyncState['section_containers'] : [];
      $sectionRows = self::loadCourseSectionHierarchyRows((int)($course->id ?? $moodleCourseId));
      $sectionHierarchyMap = self::buildSectionHierarchyMap($course, $sectionsInfo, $sectionRows);
      $delegatedSectionOwners = self::buildDelegatedSectionOwnerMap($modinfo);
      $delegatedOwnerCmids = [];
      foreach ($delegatedSectionOwners as $delegatedOwner) {
        $ownerCmid = (int)($delegatedOwner['cmid'] ?? 0);
        if ($ownerCmid > 0) {
          $delegatedOwnerCmids[$ownerCmid] = true;
        }
      }
      $sectionChildrenMap = [];
      foreach ($sectionHierarchyMap as $mappedSectionNum => $mappedSectionMeta) {
        $parentSection = (int)($mappedSectionMeta['parent_section'] ?? -1);
        if ($parentSection >= 0) {
          if (!isset($sectionChildrenMap[$parentSection])) {
            $sectionChildrenMap[$parentSection] = [];
          }
          $sectionChildrenMap[$parentSection][] = (int)$mappedSectionNum;
        }
      }

      $sectionEntries = self::orderSectionEntries($modinfo->sections, $sectionRows);

      foreach ($sectionEntries as $sectionNum => $cmids) {
        $sectionInfo = $sectionsInfo[$sectionNum] ?? null;
        if (!$sectionInfo) continue;
        if (!empty($sectionSkipOverrides[(int)$sectionNum])) {
          $stats['skipped']++;
          $stats['skipped_sections_override']++;
          continue;
        }
        $sectionRow = $sectionRows[(int)$sectionNum] ?? null;
        $cmids = self::resolveSectionCmidsInOrder($cmids, $sectionInfo, $sectionRow, $modinfo);
        if (self::extractSectionSequenceRaw($sectionInfo, $sectionRow) === '') {
          $cmids = self::sortCmidsByTitlePrefix($cmids, $modinfo);
        }

        $sectionName = self::deriveSectionName($course, $sectionInfo, (int)$sectionNum);

        $sectionTitleOverride = self::normalizeSyncTitle($sectionTitleOverrides[(int)$sectionNum] ?? null);
        if ($sectionTitleOverride !== null) {
          $sectionName = $sectionTitleOverride;
        }

        $sectionContainer = self::resolveSectionContainerMeta($sectionName);
        $sectionHierarchyMeta = $sectionHierarchyMap[(int)$sectionNum] ?? null;
        $sectionLevel = (int)($sectionHierarchyMeta['level'] ?? (int)($sectionContainer['level'] ?? 1));
        $sectionSubtype = (string)($sectionHierarchyMeta['subtype'] ?? (string)($sectionContainer['subtype'] ?? 'module'));
        $sectionName = (string)($sectionContainer['title'] ?? $sectionName);
        $ownerMeta = $delegatedSectionOwners[(int)$sectionNum] ?? null;
        $ownerParentSection = (int)($ownerMeta['parent_section'] ?? -1);
        if ($ownerParentSection >= 0) {
          $parentLevelHint = (int)($sectionHierarchyMap[$ownerParentSection]['level'] ?? 1);
          if ($sectionLevel <= $parentLevelHint) {
            $sectionLevel = $parentLevelHint + 1;
          }
        }

        if (isset($sectionLevelOverrides[(int)$sectionNum])) {
          $sectionLevel = max(1, min(8, (int)$sectionLevelOverrides[(int)$sectionNum]));
        }
        if (isset($sectionSubtypeOverrides[(int)$sectionNum])) {
          $sectionSubtype = (string)$sectionSubtypeOverrides[(int)$sectionNum];
          $sectionLevel = self::sectionLevelForSubtype($sectionSubtype);
        } else {
          $sectionSubtype = self::sectionSubtypeForLevel($sectionLevel);
        }

        $hasChildSections = !empty($sectionChildrenMap[(int)$sectionNum]);
        $hasContent = !empty($cmids) || !empty($sectionInfo->summary) || $hasChildSections;
        if (!$hasContent) continue;

        $sectionVisible = ((int)($sectionInfo->visible ?? 1) === 1);

        if ($sectionLevel < 1) {
          $sectionLevel = 1;
        }
        foreach (array_keys($sectionContainerByLevel) as $existingLevel) {
          if ((int)$existingLevel >= $sectionLevel) {
            unset($sectionContainerByLevel[$existingLevel]);
          }
        }

        $explicitParentSection = (int)($sectionHierarchyMeta['parent_section'] ?? -1);
        if ($explicitParentSection < 0 && $ownerParentSection >= 0) {
          $explicitParentSection = $ownerParentSection;
        }
        $sectionParentId = $rootId;
        if ($explicitParentSection >= 0 && !empty($sectionContainerBySection[$explicitParentSection])) {
          $sectionParentId = (int)$sectionContainerBySection[$explicitParentSection];
        } else if ($sectionLevel > 1) {
          for ($parentLevel = $sectionLevel - 1; $parentLevel >= 1; $parentLevel--) {
            if (!empty($sectionContainerByLevel[$parentLevel])) {
              $sectionParentId = (int)$sectionContainerByLevel[$parentLevel];
              break;
            }
          }
        }

        $moduleMeta = ['moodle' => [
          'section' => (int)$sectionNum,
          'section_id' => (int)($sectionInfo->id ?? 0),
          'course_id' => $moodleCourseId,
          'section_level' => $sectionLevel,
        ]];

        $existingSectionContainerId = 0;
        if ($isMergeMode && !empty($sectionContainerBySection[(int)$sectionNum])) {
          $existingSectionContainerId = (int)$sectionContainerBySection[(int)$sectionNum];
        }

        if ($existingSectionContainerId > 0) {
          $moduleId = $existingSectionContainerId;
          $stats['sections_reused']++;
        } else {
          $moduleId = self::createNode(
            $courseId,
            $sectionParentId,
            'container',
            $sectionSubtype,
            $sectionName,
            $moduleMeta,
            null,
            $sectionVisible ? 1 : 0
          );
          $stats['sections']++;
        }
        $sectionContainerByLevel[$sectionLevel] = $moduleId;
        $sectionContainerBySection[(int)$sectionNum] = $moduleId;

        if (!empty($sectionInfo->summary)) {
          $sectionSummaryAlreadyExists = $isMergeMode && !empty($existingSyncState['section_summaries'][(int)$sectionNum]);
          if ($sectionSummaryAlreadyExists) {
            $stats['summaries_reused']++;
          } else {
          $summary = (string)$sectionInfo->summary;
          $summary = \file_rewrite_pluginfile_urls(
            $summary,
            'pluginfile.php',
            $courseContext->id,
            'course',
            'section',
            (int)$sectionInfo->id
          );
          $summary = \format_text(
            $summary,
            (int)($sectionInfo->summaryformat ?? FORMAT_HTML),
            ['context' => $courseContext, 'noclean' => true]
          );

          self::createNode(
            $courseId,
            $moduleId,
            'content',
            'text',
            'Resumo do modulo',
            ['html' => $summary, 'moodle' => ['section' => (int)$sectionNum, 'type' => 'section_summary']],
            null,
            $sectionVisible ? 1 : 0
          );
          $stats['nodes']++;
            $existingSyncState['section_summaries'][(int)$sectionNum] = true;
          }
        }

        foreach ($cmids as $cmid) {
          try {
            $cm = $modinfo->get_cm((int)$cmid);
          } catch (\Throwable $e) {
            $stats['skipped']++;
            continue;
          }
          if (!$cm) {
            $stats['skipped']++;
            continue;
          }
          if (!empty($cm->deletioninprogress)) {
            $stats['skipped']++;
            continue;
          }

          $sourceCmid = (int)($cm->id ?? 0);
          if (!empty($itemSkipOverrides[$sourceCmid])) {
            $stats['skipped']++;
            $stats['skipped_items_override']++;
            continue;
          }
          if (isset($delegatedOwnerCmids[$sourceCmid])) {
            $stats['skipped']++;
            continue;
          }
          $mappedCmid = (int)($itemCmidOverrides[$sourceCmid] ?? $sourceCmid);
          if ($mappedCmid <= 0) {
            $mappedCmid = $sourceCmid;
          }
          if ($isMergeMode && $mappedCmid > 0 && !empty($existingMappedCmids[$mappedCmid])) {
            $stats['skipped']++;
            $stats['skipped_existing']++;
            continue;
          }
          $urlOverride = self::normalizeSyncUrl($itemUrlOverrides[$sourceCmid] ?? null);

          $isPublished = ((int)($cm->visible ?? 1) === 1) ? 1 : 0;
          $modname = (string)($cm->modname ?? '');
          $title = (string)($cm->name ?? '');
          $title = self::sanitizeImportedTitle($title);
          $cmUrl = $cm->url ? $cm->url->out(false) : '';

          $kind = 'content';
          $subtype = 'link';
          $content = [
            'moodle' => [
              'cmid' => $mappedCmid,
              'modname' => $modname,
              'instance' => (int)($cm->instance ?? 0),
              'section' => (int)$sectionNum,
              'url' => $cmUrl,
            ],
            // Duplicado no topo para resiliencia em edicoes manuais/legadas.
            'cmid' => $mappedCmid,
            'moodle_cmid' => $mappedCmid,
            'moodle_modname' => $modname,
          ];
          $overrideSubtype = self::normalizeSyncSubtype($itemSubtypeOverrides[$sourceCmid] ?? null);
          if ($overrideSubtype === 'final_exam' && $modname !== 'quiz') {
            $overrideSubtype = 'link';
          }

          if ($overrideSubtype === 'final_exam') {
            if (!$finalExamSet) {
              $kind = 'action';
              $subtype = 'final_exam';
              $finalExamSet = true;
              $stats['final_exam_set'] = true;
              Db::exec(
                "REPLACE INTO app_course_exam (course_id, quiz_cmid, min_grade, exam_title, settings_json)
                 VALUES (:cid, :cmid, NULL, :title, NULL)",
                [
                  'cid' => $courseId,
                  'cmid' => $mappedCmid,
                  'title' => $title !== '' ? $title : 'Prova final',
                ]
              );
            } else {
              $kind = 'content';
              $subtype = 'link';
              $content['url'] = $cmUrl;
              $content['label'] = $title !== '' ? $title : 'Quiz';
            }
          } else if ($overrideSubtype === 'certificate') {
            $kind = 'action';
            $subtype = 'certificate';
          } else if ($modname === 'page') {
            $page = $DB->get_record('page', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
            if ($page) {
              $context = \context_module::instance((int)$cm->id);
              $html = \file_rewrite_pluginfile_urls((string)$page->content, 'pluginfile.php', $context->id, 'mod_page', 'content', (int)$page->revision);
              $html = \format_text($html, (int)($page->contentformat ?? FORMAT_HTML), ['context' => $context, 'noclean' => true]);
              $content['html'] = $html;
              $subtype = 'text';
            } else {
              $content['url'] = $cmUrl;
              $subtype = 'link';
            }
          } else if ($modname === 'label') {
            $label = $DB->get_record('label', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
            if ($label) {
              $context = \context_module::instance((int)$cm->id);
              $html = \file_rewrite_pluginfile_urls((string)$label->intro, 'pluginfile.php', $context->id, 'mod_label', 'intro', 0);
              $html = \format_text($html, (int)($label->introformat ?? FORMAT_HTML), ['context' => $context, 'noclean' => true]);
              $content['html'] = $html;
              $subtype = 'text';
              if ($title === '') $title = 'Informacao';
            }
          } else if ($modname === 'resource') {
            $context = \context_module::instance((int)$cm->id);
            $storage = \get_file_storage();
            $files = $storage->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder, id', false);
            $file = $files ? reset($files) : null;

            if ($file) {
              $fileUrl = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_resource',
                'content',
                0,
                $file->get_filepath(),
                $file->get_filename()
              )->out(false);

              $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
              $subtype = $ext === 'pdf' ? 'pdf' : 'download';
              $content['file_path'] = $fileUrl;
              $content['label'] = $title !== '' ? $title : $file->get_filename();
            } else {
              $content['url'] = $cmUrl;
              $subtype = 'link';
            }
          } else if ($modname === 'url') {
            $urlRecord = $DB->get_record('url', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
            $content['url'] = $urlRecord ? (string)$urlRecord->externalurl : $cmUrl;
            $content['label'] = $title !== '' ? $title : 'Link externo';
            $subtype = 'link';
          } else if ($modname === 'lesson') {
            $content['url'] = $cmUrl;
            $content['label'] = $title !== '' ? $title : 'Licao';

            $lessonSnapshot = self::buildLessonSnapshot((int)$cm->id, (int)$cm->instance, $cmUrl);
            if ($lessonSnapshot !== null) {
              $content['lesson_id'] = (int)($lessonSnapshot['id'] ?? 0);
              $content['lesson'] = $lessonSnapshot;

              $introHtml = trim((string)($lessonSnapshot['intro_html'] ?? ''));
              if ($introHtml !== '') {
                $content['html'] = $introHtml;
              }
            }

            $subtype = 'link';
          } else if ($modname === 'quiz') {
            if ($overrideSubtype !== null) {
              $kind = 'content';
              $subtype = $overrideSubtype;
              $content['url'] = $cmUrl;
              $content['label'] = $title !== '' ? $title : 'Quiz';
            } else if (!$finalExamSet && self::looksLikeFinalExamQuiz($title)) {
              $kind = 'action';
              $subtype = 'final_exam';
              $finalExamSet = true;
              $stats['final_exam_set'] = true;

              Db::exec(
                "REPLACE INTO app_course_exam (course_id, quiz_cmid, min_grade, exam_title, settings_json)
                 VALUES (:cid, :cmid, NULL, :title, NULL)",
                [
                  'cid' => $courseId,
                  'cmid' => $mappedCmid,
                  'title' => $title !== '' ? $title : 'Prova final',
                ]
              );
            } else {
              $content['url'] = $cmUrl;
              $content['label'] = $title !== '' ? $title : 'Quiz';
              $subtype = 'link';
            }
          } else {
            $content['url'] = $cmUrl;
            $content['label'] = $title !== '' ? $title : 'Abrir conteudo';
            $subtype = 'link';
          }

          if ($overrideSubtype !== null && $overrideSubtype !== 'final_exam' && $overrideSubtype !== 'certificate') {
            $kind = 'content';
            $subtype = $overrideSubtype;
            $content = self::normalizeContentForSubtype($content, $subtype, $cmUrl, $title !== '' ? $title : 'Item');
          }

          if ($urlOverride !== null) {
            $content = self::applyUrlOverrideForSubtype($content, $subtype, $urlOverride);
          }

          $titleOverride = self::normalizeSyncTitle($itemTitleOverrides[$sourceCmid] ?? null);
          if ($titleOverride !== null) {
            $title = $titleOverride;
          }

          self::createNode(
            $courseId,
            $moduleId,
            $kind,
            $subtype,
            $title !== '' ? $title : 'Item',
            $content,
            null,
            $isPublished
          );
          $stats['nodes']++;
          if ($mappedCmid > 0) {
            $existingMappedCmids[$mappedCmid] = true;
          }
        }
      }
    });

    $stats['mapping_columns_synced'] = self::syncNodeMappingColumnsForCourse($courseId);
    $stats['cover_synced'] = self::syncCourseCoverFromMoodle($courseId, $moodleCourseId);

    return $stats;
  }

  public static function previewFromMoodle(int $moodleCourseId, int $courseId = 0): array {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    \rebuild_course_cache($moodleCourseId, true);

    if ($moodleCourseId <= 0) {
      throw new \RuntimeException('Moodle Course ID invalido.');
    }

    $course = \get_course($moodleCourseId);
    if (!$course) {
      throw new \RuntimeException('Curso Moodle nao encontrado.');
    }

    $modinfo = \get_fast_modinfo($moodleCourseId);
    $sectionsInfo = $modinfo->get_section_info_all();
    $sectionRows = self::loadCourseSectionHierarchyRows((int)($course->id ?? $moodleCourseId));
    $sectionHierarchyMap = self::buildSectionHierarchyMap($course, $sectionsInfo, $sectionRows);
    $delegatedSectionOwners = self::buildDelegatedSectionOwnerMap($modinfo);
    $delegatedOwnerCmids = [];
    foreach ($delegatedSectionOwners as $delegatedOwner) {
      $ownerCmid = (int)($delegatedOwner['cmid'] ?? 0);
      if ($ownerCmid > 0) {
        $delegatedOwnerCmids[$ownerCmid] = true;
      }
    }
    $sectionChildrenMap = [];
    foreach ($sectionHierarchyMap as $mappedSectionNum => $mappedSectionMeta) {
      $parentSection = (int)($mappedSectionMeta['parent_section'] ?? -1);
      if ($parentSection >= 0) {
        if (!isset($sectionChildrenMap[$parentSection])) {
          $sectionChildrenMap[$parentSection] = [];
        }
        $sectionChildrenMap[$parentSection][] = (int)$mappedSectionNum;
      }
    }

    $existingSyncState = $courseId > 0 ? self::collectExistingSyncState($courseId) : [
      'cmids' => [],
      'section_containers' => [],
      'section_summaries' => [],
      'final_exam_exists' => false,
    ];

    $out = [
      'course' => [
        'id' => $moodleCourseId,
        'fullname' => (string)($course->fullname ?? ''),
        'shortname' => (string)($course->shortname ?? ''),
      ],
      'stats' => [
        'sections' => 0,
        'items' => 0,
        'quiz_count' => 0,
        'existing_items' => 0,
        'new_items' => 0,
      ],
      'sections' => [],
    ];

    $sectionEntries = self::orderSectionEntries($modinfo->sections, $sectionRows);

    foreach ($sectionEntries as $sectionNum => $cmids) {
      $sectionInfo = $sectionsInfo[$sectionNum] ?? null;
      if (!$sectionInfo) {
        continue;
      }
      $sectionRow = $sectionRows[(int)$sectionNum] ?? null;
      $cmids = self::resolveSectionCmidsInOrder($cmids, $sectionInfo, $sectionRow, $modinfo);
      if (self::extractSectionSequenceRaw($sectionInfo, $sectionRow) === '') {
        $cmids = self::sortCmidsByTitlePrefix($cmids, $modinfo);
      }

      $sectionName = self::deriveSectionName($course, $sectionInfo, (int)$sectionNum);
      $sectionHierarchyMeta = $sectionHierarchyMap[(int)$sectionNum] ?? null;
      $sectionLevel = max(1, (int)($sectionHierarchyMeta['level'] ?? 1));
      $ownerMeta = $delegatedSectionOwners[(int)$sectionNum] ?? null;
      $ownerParentSection = (int)($ownerMeta['parent_section'] ?? -1);
      if ($ownerParentSection >= 0) {
        $parentLevelHint = (int)($sectionHierarchyMap[$ownerParentSection]['level'] ?? 1);
        if ($sectionLevel <= $parentLevelHint) {
          $sectionLevel = $parentLevelHint + 1;
        }
      }
      $sectionSubtype = (string)($sectionHierarchyMeta['subtype'] ?? self::sectionSubtypeForLevel($sectionLevel));
      $sectionParent = isset($sectionHierarchyMeta['parent_section']) ? (int)$sectionHierarchyMeta['parent_section'] : null;
      if (($sectionParent === null || $sectionParent < 0) && $ownerParentSection >= 0) {
        $sectionParent = $ownerParentSection;
      }

      $summaryRaw = trim(strip_tags((string)($sectionInfo->summary ?? '')));
      $hasChildSections = !empty($sectionChildrenMap[(int)$sectionNum]);
      $hasContent = !empty($cmids) || $summaryRaw !== '' || $hasChildSections;
      if (!$hasContent) {
        continue;
      }

      $sectionPreview = [
        'section' => (int)$sectionNum,
        'title' => $sectionName !== '' ? $sectionName : ('Secao ' . (int)$sectionNum),
        'level' => $sectionLevel,
        'container_subtype' => $sectionSubtype,
        'parent_section' => $sectionParent,
        'visible' => ((int)($sectionInfo->visible ?? 1) === 1),
        'summary' => self::previewTrim($summaryRaw, 180),
        'items_count' => 0,
        'existing_items' => 0,
        'new_items' => 0,
        'items' => [],
      ];

      if ($summaryRaw !== '') {
        $summaryExistsInApp = $courseId > 0 && !empty($existingSyncState['section_summaries'][(int)$sectionNum]);
        $sectionPreview['items_count']++;
        if ($summaryExistsInApp) {
          $sectionPreview['existing_items']++;
          $out['stats']['existing_items']++;
        } else {
          $sectionPreview['new_items']++;
          $out['stats']['new_items']++;
        }
        $out['stats']['items']++;
        $sectionPreview['items'][] = [
          'title' => 'Resumo do modulo',
          'cmid' => 0,
          'kind' => 'content',
          'subtype' => 'text',
          'default_subtype' => 'text',
          'editable' => false,
          'source' => 'section_summary',
          'exists_in_app' => $summaryExistsInApp,
        ];
      }

      foreach ($cmids as $cmid) {
        try {
          $cm = $modinfo->get_cm((int)$cmid);
        } catch (\Throwable $e) {
          continue;
        }
        if (!$cm || !empty($cm->deletioninprogress)) {
          continue;
        }

        $map = self::previewMapModule((string)($cm->modname ?? ''));
        $modname = (string)($cm->modname ?? '');
        $sourceCmid = (int)($cm->id ?? 0);
        if (isset($delegatedOwnerCmids[$sourceCmid])) {
          continue;
        }
        $cmUrl = $cm->url ? $cm->url->out(false) : '';
        $itemUrl = $cmUrl;
        if ($modname === 'url') {
          $urlRecord = $DB->get_record('url', ['id' => (int)$cm->instance], '*', IGNORE_MISSING);
          if ($urlRecord && !empty($urlRecord->externalurl)) {
            $itemUrl = (string)$urlRecord->externalurl;
          }
        }

        $title = trim((string)($cm->name ?? ''));
        if ($title === '') {
          $title = 'Item';
        }
        $title = self::sanitizeImportedTitle($title);

        $existsInApp = $courseId > 0 && $sourceCmid > 0 && !empty($existingSyncState['cmids'][$sourceCmid]);

        $sectionPreview['items_count']++;
        if ($existsInApp) {
          $sectionPreview['existing_items']++;
          $out['stats']['existing_items']++;
        } else {
          $sectionPreview['new_items']++;
          $out['stats']['new_items']++;
        }
        $out['stats']['items']++;
        if ($map['subtype'] === 'final_exam') {
          $out['stats']['quiz_count']++;
        }

        $sectionPreview['items'][] = [
          'title' => $title,
          'cmid' => $sourceCmid,
          'kind' => $map['kind'],
          'subtype' => $map['subtype'],
          'default_subtype' => $map['subtype'],
          'editable' => true,
          'source' => $modname,
          'modname' => $modname,
          'view_url' => $cmUrl,
          'url' => $itemUrl,
          'exists_in_app' => $existsInApp,
        ];
      }

      $out['sections'][] = $sectionPreview;
      $out['stats']['sections']++;
    }

    return $out;
  }

  private static function collectExistingSyncState(int $courseId): array {
    $state = [
      'cmids' => [],
      'section_containers' => [],
      'section_summaries' => [],
      'final_exam_exists' => false,
    ];

    if ($courseId <= 0) {
      return $state;
    }

    $exam = Db::one(
      "SELECT quiz_cmid
         FROM app_course_exam
        WHERE course_id = :cid
        LIMIT 1",
      ['cid' => $courseId]
    );
    $examCmid = (int)($exam['quiz_cmid'] ?? 0);
    if ($examCmid > 0) {
      $state['cmids'][$examCmid] = true;
      $state['final_exam_exists'] = true;
    }

    $rows = Db::all(
      "SELECT id, kind, subtype, moodle_cmid, content_json, depth, sort
         FROM app_node
        WHERE course_id = :cid
        ORDER BY depth ASC, sort ASC, id ASC",
      ['cid' => $courseId]
    );

    foreach ($rows as $row) {
      $nodeId = (int)($row['id'] ?? 0);
      if ($nodeId <= 0) {
        continue;
      }

      $kind = (string)($row['kind'] ?? '');
      $subtype = (string)($row['subtype'] ?? '');
      if ($kind === 'action' && $subtype === 'final_exam') {
        $state['final_exam_exists'] = true;
      }

      $content = null;
      $contentRaw = (string)($row['content_json'] ?? '');
      if ($contentRaw !== '') {
        $decoded = json_decode($contentRaw, true);
        if (is_array($decoded)) {
          $content = $decoded;
        }
      }

      $cmid = (int)($row['moodle_cmid'] ?? 0);
      if ($cmid <= 0 && is_array($content)) {
        $cmid = (int)($content['moodle']['cmid'] ?? $content['moodle_cmid'] ?? $content['cmid'] ?? 0);
      }
      if ($cmid > 0) {
        $state['cmids'][$cmid] = true;
      }

      if (!is_array($content)) {
        continue;
      }

      $sectionNum = (int)($content['moodle']['section'] ?? -1);
      if ($kind === 'container' && $subtype !== 'root' && $sectionNum >= 0 && !isset($state['section_containers'][$sectionNum])) {
        $state['section_containers'][$sectionNum] = $nodeId;
      }

      $moodleType = trim((string)($content['moodle']['type'] ?? ''));
      if ($subtype === 'text' && $moodleType === 'section_summary' && $sectionNum >= 0) {
        $state['section_summaries'][$sectionNum] = true;
      }
    }

    return $state;
  }

  public static function applyTemplate(int $courseId): void {
    Db::tx(function () use ($courseId): void {
      $root = self::ensureRootNode($courseId, 'Root');
      self::templateBuild($courseId, (int)$root['id']);
    });
  }

  public static function syncCoverOnly(int $courseId, int $moodleCourseId = 0): array {
    if ($courseId <= 0) {
      throw new \RuntimeException('Curso invalido.');
    }

    $course = Db::one(
      "SELECT id, moodle_courseid, image
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );
    if (!$course) {
      throw new \RuntimeException('Curso local nao encontrado.');
    }

    $resolvedMoodleCourseId = $moodleCourseId > 0 ? $moodleCourseId : (int)($course['moodle_courseid'] ?? 0);
    if ($resolvedMoodleCourseId <= 0) {
      throw new \RuntimeException('Defina o Moodle Course ID para sincronizar a imagem.');
    }

    if ((int)($course['moodle_courseid'] ?? 0) !== $resolvedMoodleCourseId) {
      Db::exec(
        "UPDATE app_course
            SET moodle_courseid = :mcid
          WHERE id = :id",
        [
          'mcid' => $resolvedMoodleCourseId,
          'id' => $courseId,
        ]
      );
    }

    $synced = self::syncCourseCoverFromMoodle($courseId, $resolvedMoodleCourseId);
    if (!$synced) {
      throw new \RuntimeException('Nao foi encontrada imagem de capa no Moodle para este curso.');
    }

    $updated = Db::one(
      "SELECT image
         FROM app_course
        WHERE id = :id
        LIMIT 1",
      ['id' => $courseId]
    );

    return [
      'course_id' => $courseId,
      'moodle_courseid' => $resolvedMoodleCourseId,
      'image' => (string)($updated['image'] ?? ''),
    ];
  }

  public static function createTemplateCourse(string $title, int $creatorMoodleUserId): int {
    $title = trim($title);
    if ($title === '') $title = 'Curso Modelo';

    $slug = self::slugify($title);
    if ($slug === '') $slug = 'curso-modelo';
    $slug = self::uniqueSlug($slug);

    return Db::tx(function () use ($title, $slug, $creatorMoodleUserId): int {
      Db::exec(
        "INSERT INTO app_course (moodle_courseid, slug, title, description, access_days, status, created_by_moodle_userid)
         VALUES (NULL, :slug, :title, :description, NULL, 'draft', :uid)",
        [
          'slug' => $slug,
          'title' => $title,
          'description' => 'Curso modelo autoexplicativo',
          'uid' => $creatorMoodleUserId,
        ]
      );

      $courseId = (int)Db::lastId();
      $root = self::ensureRootNode($courseId, $title);
      self::templateBuild($courseId, (int)$root['id']);

      return $courseId;
    });
  }

  private static function templateBuild(int $courseId, int $rootId): void {
    $introModule = self::createNode($courseId, $rootId, 'container', 'module', 'Modelo - Comece aqui');
    $introTopic = self::createNode($courseId, $introModule, 'container', 'topic', 'Boas-vindas');

    $introHtml = '<h3>Guia rapido</h3>'
      . '<p>Este curso modelo mostra como organizar o conteudo usando <b>containers</b> (modulos e topicos), '
      . '<b>content</b> (video, PDF, texto, link) e <b>action</b> (prova final).</p>'
      . '<ul>'
      . '<li><b>Container</b>: organiza a hierarquia (modulo, topico e secao).</li>'
      . '<li><b>Content</b>: conteudo real do aluno (video, PDF, texto, link).</li>'
      . '<li><b>Action</b>: acoes especiais como prova final.</li>'
      . '</ul>'
      . '<p>Edite cada item e substitua os exemplos pelos seus arquivos reais.</p>';

    self::createNode($courseId, $introTopic, 'content', 'text', 'Leia antes de comecar', ['html' => $introHtml]);
    self::createNode($courseId, $introTopic, 'content', 'video', 'Aula em video (exemplo)', [
      'url' => 'https://www.youtube.com/watch?v=5qap5aO4i9A',
      'provider' => 'youtube',
      'min_video_percent' => 60,
    ]);
    self::createNode($courseId, $introTopic, 'content', 'pdf', 'Material PDF (exemplo)', [
      'file_path' => "/storage/courses/{$courseId}/exemplo.pdf",
    ]);
    self::createNode($courseId, $introTopic, 'content', 'text', 'Texto de apoio (exemplo)', [
      'html' => '<p>Use este bloco para textos longos, listas e explicacoes.</p>',
    ]);
    self::createNode($courseId, $introTopic, 'content', 'link', 'Link externo (exemplo)', [
      'url' => 'https://example.com',
      'label' => 'Abrir site de exemplo',
    ]);
    self::createNode($courseId, $introTopic, 'content', 'download', 'Download (exemplo)', [
      'url' => "/storage/courses/{$courseId}/material-modelo.zip",
      'label' => 'Baixar material',
    ]);

    $rulesModule = self::createNode($courseId, $rootId, 'container', 'module', 'Modelo - Regras e sequencia');
    self::createNode($courseId, $rulesModule, 'content', 'text', 'Item sequencial (exemplo)', [
      'html' => '<p>Exemplo de item com regra sequencial para travar o proximo.</p>',
    ], ['sequential' => true]);

    $examModule = self::createNode($courseId, $rootId, 'container', 'module', 'Modelo - Avaliacao');
    self::createNode($courseId, $examModule, 'action', 'final_exam', 'Prova final (exemplo)');
  }

  private static function ensureRootNode(int $courseId, string $title): array {
    $root = Db::one(
      "SELECT id, depth, path FROM app_node WHERE course_id = :cid AND parent_id IS NULL ORDER BY id ASC LIMIT 1",
      ['cid' => $courseId]
    );

    if ($root) return $root;

    Db::exec(
      "INSERT INTO app_node (course_id, parent_id, kind, subtype, title, sort, depth, path, is_published)
       VALUES (:cid, NULL, 'container', 'root', :title, 0, 0, '', 1)",
      ['cid' => $courseId, 'title' => $title !== '' ? $title : 'Root']
    );

    $rootId = (int)Db::lastId();
    $path = '/' . $rootId . '/';
    Db::exec("UPDATE app_node SET path = :path WHERE id = :id", ['path' => $path, 'id' => $rootId]);

    return ['id' => $rootId, 'depth' => 0, 'path' => $path];
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
    $moodleMap = self::extractMoodleMappingFromContent($content);
    $countInProgressPercent = ($kind === 'action' && $subtype === 'certificate') ? 0 : 1;

    Db::exec(
      "INSERT INTO app_node (course_id, parent_id, kind, subtype, title, sort, depth, path, moodle_cmid, moodle_modname, moodle_url, content_json, rules_json, is_published, count_in_progress_percent)
       VALUES (:cid, :pid, :kind, :subtype, :title, :sort, :depth, '', :moodle_cmid, :moodle_modname, :moodle_url, :content, :rules, :published, :cpp)",
      [
        'cid' => $courseId,
        'pid' => $parentId,
        'kind' => $kind,
        'subtype' => $subtype,
        'title' => $title,
        'sort' => $sort,
        'depth' => $depth,
        'moodle_cmid' => $moodleMap['cmid'] > 0 ? $moodleMap['cmid'] : null,
        'moodle_modname' => $moodleMap['modname'] !== '' ? $moodleMap['modname'] : null,
        'moodle_url' => $moodleMap['url'] !== '' ? $moodleMap['url'] : null,
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

  private static function extractMoodleMappingFromContent(?array $content): array {
    $map = [
      'cmid' => 0,
      'modname' => '',
      'url' => '',
    ];

    if (!is_array($content)) {
      return $map;
    }

    $cmid = (int)($content['moodle']['cmid'] ?? 0);
    if ($cmid <= 0) {
      $cmid = (int)($content['cmid'] ?? 0);
    }
    if ($cmid <= 0) {
      $cmid = (int)($content['moodle_cmid'] ?? 0);
    }
    if ($cmid > 0) {
      $map['cmid'] = $cmid;
    }

    $modname = trim((string)($content['moodle']['modname'] ?? $content['moodle_modname'] ?? ''));
    if ($modname === '' && $map['cmid'] > 0) {
      $modname = trim((string)Moodle::cm_modname((int)$map['cmid']));
    }
    if ($modname !== '') {
      $map['modname'] = function_exists('mb_substr') ? mb_substr($modname, 0, 50, 'UTF-8') : substr($modname, 0, 50);
    }

    $url = trim((string)($content['moodle']['url'] ?? $content['moodle_url'] ?? ''));
    if ($url === '' && $map['cmid'] <= 0) {
      $url = trim((string)($content['url'] ?? $content['file_path'] ?? $content['source_url'] ?? ''));
    }
    if ($url === '' && $map['cmid'] > 0) {
      $url = trim((string)Moodle::cm_view_url((int)$map['cmid']));
    }
    if ($url !== '') {
      $map['url'] = function_exists('mb_substr') ? mb_substr($url, 0, 2048, 'UTF-8') : substr($url, 0, 2048);
    }

    return $map;
  }

  public static function syncNodeMappingColumnsForCourse(int $courseId): int {
    if ($courseId <= 0) {
      return 0;
    }

    $rows = Db::all(
      "SELECT id, subtype, moodle_cmid, moodle_modname, moodle_url, content_json
         FROM app_node
        WHERE course_id = :cid",
      ['cid' => $courseId]
    );

    if (!$rows) {
      return 0;
    }

    $exam = Db::one(
      "SELECT quiz_cmid
         FROM app_course_exam
        WHERE course_id = :cid
        LIMIT 1",
      ['cid' => $courseId]
    );
    $examCmid = (int)($exam['quiz_cmid'] ?? 0);

    $changed = 0;
    foreach ($rows as $row) {
      $nodeId = (int)($row['id'] ?? 0);
      if ($nodeId <= 0) {
        continue;
      }

      $contentRaw = (string)($row['content_json'] ?? '');
      $content = $contentRaw !== '' ? json_decode($contentRaw, true) : null;
      $contentArr = is_array($content) ? $content : null;

      $map = self::extractMoodleMappingFromContent($contentArr);
      if ($map['cmid'] <= 0 && (string)($row['subtype'] ?? '') === 'final_exam' && $examCmid > 0) {
        $map['cmid'] = $examCmid;
      }

      $currentCmid = (int)($row['moodle_cmid'] ?? 0);
      $currentModname = trim((string)($row['moodle_modname'] ?? ''));
      $currentUrl = trim((string)($row['moodle_url'] ?? ''));

      if ($currentCmid === (int)$map['cmid']
        && $currentModname === (string)$map['modname']
        && $currentUrl === (string)$map['url']) {
        continue;
      }

      Db::exec(
        "UPDATE app_node
            SET moodle_cmid = :cmid,
                moodle_modname = :modname,
                moodle_url = :url
          WHERE id = :id",
        [
          'cmid' => $map['cmid'] > 0 ? $map['cmid'] : null,
          'modname' => $map['modname'] !== '' ? $map['modname'] : null,
          'url' => $map['url'] !== '' ? $map['url'] : null,
          'id' => $nodeId,
        ]
      );
      $changed++;
    }

    return $changed;
  }

  private static function deriveSectionName($course, $sectionInfo, int $sectionNum): string {
    $sectionName = trim((string)($sectionInfo->name ?? ''));
    if ($sectionName === '') {
      $sectionName = \get_section_name($course, $sectionInfo);
    }
    if ($sectionName === '' && $sectionNum === 0) {
      $sectionName = 'Apresentacao';
    }
    return self::sanitizeImportedTitle($sectionName);
  }

  private static function buildSectionHierarchyMap($course, array $sectionsInfo, ?array $rowsBySection = null): array {
    $sectionSet = [];
    $sectionNumById = [];
    $sectionTitleByNum = [];

    foreach ($sectionsInfo as $sectionNum => $sectionInfo) {
      if (!$sectionInfo) {
        continue;
      }
      $sectionNumInt = (int)$sectionNum;
      if ($sectionNumInt < 0) {
        continue;
      }

      $sectionSet[$sectionNumInt] = true;
      $sectionId = (int)($sectionInfo->id ?? 0);
      if ($sectionId > 0) {
        $sectionNumById[$sectionId] = $sectionNumInt;
      }
      $sectionTitleByNum[$sectionNumInt] = self::deriveSectionName($course, $sectionInfo, $sectionNumInt);
    }

    if (!$sectionSet) {
      return [];
    }

    if ($rowsBySection === null) {
      $courseId = (int)($course->id ?? 0);
      $rowsBySection = self::loadCourseSectionHierarchyRows($courseId);
    }
    $hints = [];

    foreach (array_keys($sectionSet) as $sectionNum) {
      $sectionInfo = $sectionsInfo[$sectionNum] ?? null;
      $sectionRow = $rowsBySection[$sectionNum] ?? null;

      $hints[$sectionNum] = [
        'level' => self::extractSectionLevelHint($sectionInfo, $sectionRow),
        'parent_section' => self::extractSectionParentHint($sectionNum, $sectionInfo, $sectionRow, $sectionSet, $sectionNumById),
      ];
    }

    $resolvedLevels = [];
    $resolving = [];
    $resolveLevel = null;
    $resolveLevel = function (int $sectionNum) use (&$resolveLevel, &$resolvedLevels, &$resolving, $hints, $sectionTitleByNum): int {
      if (isset($resolvedLevels[$sectionNum])) {
        return (int)$resolvedLevels[$sectionNum];
      }
      if (isset($resolving[$sectionNum])) {
        return 1;
      }

      $resolving[$sectionNum] = true;
      $hint = $hints[$sectionNum] ?? [];
      $level = (int)($hint['level'] ?? 0);

      if ($level <= 0) {
        $parentSection = (int)($hint['parent_section'] ?? -1);
        if ($parentSection >= 0) {
          $level = $resolveLevel($parentSection) + 1;
        }
      }

      if ($level <= 0) {
        $titleFallback = (string)($sectionTitleByNum[$sectionNum] ?? '');
        $fallbackMeta = self::resolveSectionContainerMeta($titleFallback);
        $level = (int)($fallbackMeta['level'] ?? 1);
      }

      if ($level <= 0) {
        $level = 1;
      }
      if ($level > 8) {
        $level = 8;
      }

      $resolvedLevels[$sectionNum] = $level;
      unset($resolving[$sectionNum]);
      return $level;
    };

    $result = [];
    foreach (array_keys($sectionSet) as $sectionNum) {
      $level = $resolveLevel((int)$sectionNum);
      $parentSection = (int)($hints[$sectionNum]['parent_section'] ?? -1);
      if ($parentSection >= 0 && !isset($sectionSet[$parentSection])) {
        $parentSection = -1;
      }

      $result[$sectionNum] = [
        'level' => $level,
        'subtype' => self::sectionSubtypeForLevel($level),
        'parent_section' => $parentSection,
      ];
    }

    return $result;
  }

  private static function extractSectionLevelHint($sectionInfo, ?array $sectionRow): ?int {
    $sources = [$sectionInfo, $sectionRow];

    foreach ($sources as $source) {
      $level = self::extractIntField($source, ['level', 'hierarchylevel', 'sectionlevel', 'sublevel'], false);
      if ($level !== null && $level > 0) {
        return $level;
      }
    }

    foreach ($sources as $source) {
      $depth = self::extractIntField($source, ['depth'], true);
      if ($depth !== null) {
        return max(1, $depth);
      }
    }

    foreach ($sources as $source) {
      $indent = self::extractIntField($source, ['indent'], true);
      if ($indent !== null) {
        return max(1, $indent + 1);
      }
    }

    return null;
  }

  private static function extractSectionParentHint(
    int $sectionNum,
    $sectionInfo,
    ?array $sectionRow,
    array $sectionSet,
    array $sectionNumById
  ): ?int {
    $fieldMap = [
      'parentsectionnum' => false,
      'parentsectionid' => true,
      'parentsection' => false,
      'parentid' => true,
      'parent' => false,
      'up' => false,
    ];

    $sources = [$sectionInfo, $sectionRow];
    foreach ($sources as $source) {
      foreach ($fieldMap as $field => $isId) {
        $rawValue = self::extractIntField($source, [$field], true);
        if ($rawValue === null || $rawValue < 0) {
          continue;
        }

        $parentSection = null;
        if ($isId) {
          if ($rawValue > 0 && isset($sectionNumById[$rawValue])) {
            $parentSection = (int)$sectionNumById[$rawValue];
          }
        } else {
          if (isset($sectionSet[$rawValue])) {
            $parentSection = (int)$rawValue;
          } else if ($rawValue > 0 && isset($sectionNumById[$rawValue])) {
            // Alguns formatos retornam ID no campo parent.
            $parentSection = (int)$sectionNumById[$rawValue];
          }
        }

        if ($parentSection === null || $parentSection === $sectionNum) {
          continue;
        }

        return $parentSection;
      }
    }

    return null;
  }

  private static function orderSectionEntries($sectionEntries, array $sectionRows): array {
    if (!is_array($sectionEntries)) {
      return [];
    }

    $normalized = [];
    foreach ($sectionEntries as $sectionNum => $cmids) {
      $sectionNumInt = (int)$sectionNum;
      if ($sectionNumInt < 0) {
        continue;
      }
      $normalized[$sectionNumInt] = $cmids;
    }

    if (!$normalized) {
      return [];
    }

    uksort($normalized, function ($left, $right) use ($sectionRows): int {
      $leftNum = (int)$left;
      $rightNum = (int)$right;

      $leftSort = null;
      if (isset($sectionRows[$leftNum]) && is_numeric($sectionRows[$leftNum]['sortorder'] ?? null)) {
        $leftSort = (int)$sectionRows[$leftNum]['sortorder'];
      }
      $rightSort = null;
      if (isset($sectionRows[$rightNum]) && is_numeric($sectionRows[$rightNum]['sortorder'] ?? null)) {
        $rightSort = (int)$sectionRows[$rightNum]['sortorder'];
      }

      if ($leftSort !== null || $rightSort !== null) {
        $leftWeight = $leftSort !== null ? $leftSort : (1000000 + $leftNum);
        $rightWeight = $rightSort !== null ? $rightSort : (1000000 + $rightNum);
        if ($leftWeight !== $rightWeight) {
          return $leftWeight <=> $rightWeight;
        }
      }

      return $leftNum <=> $rightNum;
    });

    return $normalized;
  }

  private static function resolveSectionCmidsInOrder($cmids, $sectionInfo, ?array $sectionRow, $modinfo = null): array {
    if (is_object($sectionInfo) && method_exists($sectionInfo, 'get_sequence_cm_infos')) {
      try {
        $cmInfos = $sectionInfo->get_sequence_cm_infos();
        if (is_array($cmInfos) && !empty($cmInfos)) {
          $orderedByApi = [];
          foreach ($cmInfos as $cmInfo) {
            if (!is_object($cmInfo)) {
              continue;
            }
            $cmid = (int)($cmInfo->id ?? 0);
            if ($cmid > 0 && !in_array($cmid, $orderedByApi, true)) {
              $orderedByApi[] = $cmid;
            }
          }
          if ($orderedByApi) {
            return self::fixPotentialCmidOrderByTitlePrefix($orderedByApi, $modinfo);
          }
        }
      } catch (\Throwable $e) {
        // Fall back to legacy sequence parsing below.
      }
    }

    $flat = [];
    if (is_array($cmids)) {
      foreach ($cmids as $key => $value) {
        $candidate = 0;
        if (is_numeric($value)) {
          $candidate = (int)$value;
        } else if (is_numeric($key)) {
          $candidate = (int)$key;
        }
        if ($candidate > 0 && !in_array($candidate, $flat, true)) {
          $flat[] = $candidate;
        }
      }
    }

    $sequenceRaw = self::extractSectionSequenceRaw($sectionInfo, $sectionRow);
    if ($sequenceRaw !== '') {
      $ordered = [];
      foreach (explode(',', $sequenceRaw) as $entry) {
        $cmid = (int)trim((string)$entry);
        if ($cmid > 0 && !in_array($cmid, $ordered, true)) {
          $ordered[] = $cmid;
        }
      }

      if ($flat) {
        foreach ($flat as $cmid) {
          if (!in_array($cmid, $ordered, true)) {
            $ordered[] = $cmid;
          }
        }
      }

      if ($ordered) {
        return self::fixPotentialCmidOrderByTitlePrefix($ordered, $modinfo);
      }
    }

    return self::fixPotentialCmidOrderByTitlePrefix($flat, $modinfo);
  }

  private static function buildDelegatedSectionOwnerMap($modinfo): array {
    $map = [];
    if (!is_object($modinfo) || !method_exists($modinfo, 'get_sections_delegated_by_cm')) {
      return $map;
    }

    try {
      $delegatedByCm = $modinfo->get_sections_delegated_by_cm();
    } catch (\Throwable $e) {
      return $map;
    }

    if (!is_array($delegatedByCm)) {
      return $map;
    }

    foreach ($delegatedByCm as $cmid => $sectionInfo) {
      $cmidInt = (int)$cmid;
      if ($cmidInt <= 0 || !is_object($sectionInfo)) {
        continue;
      }
      $sectionNum = (int)($sectionInfo->section ?? -1);
      if ($sectionNum < 0) {
        continue;
      }

      $parentSection = -1;
      try {
        $cm = $modinfo->get_cm($cmidInt);
        if ($cm) {
          $parentSection = (int)($cm->sectionnum ?? -1);
        }
      } catch (\Throwable $e) {
        $parentSection = -1;
      }

      $map[$sectionNum] = [
        'cmid' => $cmidInt,
        'parent_section' => $parentSection,
      ];
    }

    return $map;
  }

  private static function extractSectionSequenceRaw($sectionInfo, ?array $sectionRow): string {
    $sequenceRaw = '';
    if (is_object($sectionInfo) && property_exists($sectionInfo, 'sequence')) {
      $sequenceRaw = trim((string)$sectionInfo->sequence);
    }
    if ($sequenceRaw === '' && is_object($sectionInfo) && property_exists($sectionInfo, 'cmlist')) {
      $sequenceRaw = trim((string)$sectionInfo->cmlist);
    }
    if ($sequenceRaw === '' && is_object($sectionInfo) && property_exists($sectionInfo, 'modsequence')) {
      $sequenceRaw = trim((string)$sectionInfo->modsequence);
    }
    if ($sequenceRaw === '' && is_array($sectionRow) && isset($sectionRow['sequence'])) {
      $sequenceRaw = trim((string)$sectionRow['sequence']);
    }
    return $sequenceRaw;
  }

  private static function sortCmidsByTitlePrefix(array $cmids, $modinfo): array {
    if (count($cmids) < 2) {
      return $cmids;
    }

    $items = [];
    $prefixHits = 0;
    foreach ($cmids as $idx => $cmidRaw) {
      $cmid = (int)$cmidRaw;
      if ($cmid <= 0) {
        continue;
      }

      $title = '';
      try {
        $cm = $modinfo->get_cm($cmid);
        $title = trim((string)($cm->name ?? ''));
      } catch (\Throwable $e) {
        $title = '';
      }

      $prefix = self::extractNumericPrefixFromTitle($title);
      if ($prefix !== null) {
        $prefixHits++;
      }

      $items[] = [
        'cmid' => $cmid,
        'idx' => (int)$idx,
        'prefix' => $prefix,
      ];
    }

    if (count($items) < 2) {
      return $cmids;
    }

    // So aplica ordenacao numerica quando a maioria dos itens tem prefixo numerado.
    if ($prefixHits < (int)ceil(count($items) * 0.6)) {
      return array_values(array_map(static function ($row) {
        return (int)$row;
      }, $cmids));
    }

    usort($items, static function (array $a, array $b): int {
      $ap = $a['prefix'];
      $bp = $b['prefix'];
      if (is_array($ap) && is_array($bp)) {
        $max = max(count($ap), count($bp));
        for ($i = 0; $i < $max; $i++) {
          $av = (int)($ap[$i] ?? -1);
          $bv = (int)($bp[$i] ?? -1);
          if ($av !== $bv) {
            return $av <=> $bv;
          }
        }
      } else if (is_array($ap) && !is_array($bp)) {
        return -1;
      } else if (!is_array($ap) && is_array($bp)) {
        return 1;
      }

      return (int)$a['idx'] <=> (int)$b['idx'];
    });

    return array_values(array_map(static function (array $row): int {
      return (int)$row['cmid'];
    }, $items));
  }

  private static function fixPotentialCmidOrderByTitlePrefix(array $cmids, $modinfo): array {
    if (count($cmids) < 3 || $modinfo === null) {
      return $cmids;
    }

    $isAscending = true;
    $prev = null;
    foreach ($cmids as $cmidRaw) {
      $cmid = (int)$cmidRaw;
      if ($cmid <= 0) {
        continue;
      }
      if ($prev !== null && $cmid < $prev) {
        $isAscending = false;
        break;
      }
      $prev = $cmid;
    }

    if (!$isAscending) {
      return $cmids;
    }

    $prefixSorted = self::sortCmidsByTitlePrefix($cmids, $modinfo);
    if (count($prefixSorted) !== count($cmids)) {
      return $cmids;
    }

    for ($i = 0; $i < count($cmids); $i++) {
      if ((int)$prefixSorted[$i] !== (int)$cmids[$i]) {
        return $prefixSorted;
      }
    }

    return $cmids;
  }

  private static function extractNumericPrefixFromTitle(string $title): ?array {
    $title = trim($title);
    if ($title === '') {
      return null;
    }

    if (preg_match('/^(\d+(?:\.\d+){0,8})\b/u', $title, $match) !== 1) {
      return null;
    }

    $parts = explode('.', (string)$match[1]);
    $numbers = [];
    foreach ($parts as $part) {
      if (!is_numeric($part)) {
        return null;
      }
      $numbers[] = (int)$part;
    }

    return $numbers ?: null;
  }

  private static function loadCourseSectionHierarchyRows(int $courseId): array {
    global $DB;

    if ($courseId <= 0) {
      return [];
    }

    try {
      $columns = $DB->get_columns('course_sections');
      if (!$columns || !is_array($columns)) {
        return [];
      }

      $queryFields = ['id', 'section'];
      $optionalFields = [
        'component',
        'itemid',
        'sequence',
        'sortorder',
        'parent',
        'parentid',
        'parentsection',
        'parentsectionid',
        'parentsectionnum',
        'level',
        'depth',
        'indent',
        'hierarchylevel',
        'sectionlevel',
        'sublevel',
      ];

      foreach ($optionalFields as $field) {
        if (isset($columns[$field]) && !in_array($field, $queryFields, true)) {
          $queryFields[] = $field;
        }
      }

      $orderBy = isset($columns['sortorder']) ? 'sortorder ASC, section ASC, id ASC' : 'section ASC, id ASC';
      $sql = "SELECT " . implode(', ', $queryFields) . " FROM {course_sections} WHERE course = :course ORDER BY " . $orderBy;
      $rows = $DB->get_records_sql($sql, ['course' => $courseId]);
      if (!$rows) {
        return [];
      }

      $bySection = [];
      foreach ($rows as $row) {
        $rowArray = (array)$row;
        $sectionNum = (int)($rowArray['section'] ?? -1);
        if ($sectionNum < 0) {
          continue;
        }
        $bySection[$sectionNum] = $rowArray;
      }

      return $bySection;
    } catch (\Throwable $e) {
      return [];
    }
  }

  private static function extractIntField($source, array $fields, bool $allowZero): ?int {
    if (!is_array($source) && !is_object($source)) {
      return null;
    }

    foreach ($fields as $field) {
      $exists = false;
      $value = null;

      if (is_array($source) && array_key_exists($field, $source)) {
        $exists = true;
        $value = $source[$field];
      } else if (is_object($source) && property_exists($source, (string)$field)) {
        $exists = true;
        $value = $source->{$field};
      }

      if (!$exists || $value === null || $value === '') {
        continue;
      }
      if (!is_numeric($value)) {
        continue;
      }

      $intValue = (int)$value;
      if ($allowZero) {
        if ($intValue < 0) {
          continue;
        }
        return $intValue;
      }

      if ($intValue <= 0) {
        continue;
      }
      return $intValue;
    }

    return null;
  }

  private static function resolveSectionContainerMeta(string $title): array {
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    if ($title === '') {
      return ['level' => 1, 'subtype' => 'module', 'title' => 'Modulo'];
    }

    $level = 1;
    $subtype = 'module';
    $cleanTitle = $title;

    if (preg_match('/^(\\d+(?:\\.\\d+){0,8})\\s*[-–—:.)]?\\s*(.+)?$/u', $title, $match) === 1) {
      $indexPath = trim((string)($match[1] ?? ''));
      $rest = trim((string)($match[2] ?? ''));
      if ($indexPath !== '') {
        $level = substr_count($indexPath, '.') + 1;
      }
      $subtype = self::sectionSubtypeForLevel($level);
      if ($rest !== '') {
        $cleanTitle = $rest;
      }
    } else {
      $normalized = self::normalizeSectionToken($title);
      if (preg_match('/^topico\b/u', $normalized) === 1) {
        $level = 2;
      } else if (preg_match('/^(secao|section)\b/u', $normalized) === 1) {
        $level = 3;
      } else if (preg_match('/^(subsecao|subsection)\b/u', $normalized) === 1) {
        $level = 4;
      } else if (preg_match('/^(modulo|module)\b/u', $normalized) === 1) {
        $level = 1;
      }
      $subtype = self::sectionSubtypeForLevel($level);
    }

    $cleanTitle = self::sanitizeImportedTitle($cleanTitle);
    if ($cleanTitle === '') {
      $cleanTitle = $title;
    }
    if ($cleanTitle === '') {
      $cleanTitle = 'Secao';
    }

    return [
      'level' => max(1, (int)$level),
      'subtype' => $subtype,
      'title' => $cleanTitle,
    ];
  }

  private static function normalizeSectionToken(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    if (class_exists('\\Normalizer')) {
      $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
      if (is_string($normalized) && $normalized !== '') {
        $value = $normalized;
      }
      $withoutMarks = preg_replace('/\pM+/u', '', $value);
      if (is_string($withoutMarks) && $withoutMarks !== '') {
        $value = $withoutMarks;
      }
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
      $value = $ascii;
    }

    $value = strtolower($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
  }

  private static function sectionSubtypeForLevel(int $level): string {
    if ($level <= 1) {
      return 'module';
    }
    if ($level === 2) {
      return 'topic';
    }
    if ($level === 3) {
      return 'section';
    }
    return 'subsection';
  }

  private static function sectionLevelForSubtype(string $subtype): int {
    if ($subtype === 'module') {
      return 1;
    }
    if ($subtype === 'topic') {
      return 2;
    }
    if ($subtype === 'section') {
      return 3;
    }
    if ($subtype === 'subsection') {
      return 4;
    }
    return 1;
  }

  private static function syncCourseCoverFromMoodle(int $courseId, int $moodleCourseId): bool {
    if ($courseId <= 0 || $moodleCourseId <= 0) {
      return false;
    }

    try {
      $context = \context_course::instance($moodleCourseId, IGNORE_MISSING);
      if (!$context) {
        return false;
      }

      $storage = \get_file_storage();
      $files = $storage->get_area_files(
        (int)$context->id,
        'course',
        'overviewfiles',
        0,
        'sortorder ASC, id ASC',
        false
      );

      if (!$files) {
        return false;
      }

      $coverFile = self::pickCourseCoverFile($files);
      if ($coverFile === null) {
        return false;
      }

      $binary = $coverFile->get_content();
      if (!is_string($binary) || $binary === '') {
        return false;
      }

      $coverDir = APP_DIR . '/storage/courses/' . $courseId;
      if (!is_dir($coverDir) && !@mkdir($coverDir, 0775, true) && !is_dir($coverDir)) {
        return false;
      }

      $ext = self::guessImageExtension((string)$coverFile->get_filename(), (string)$coverFile->get_mimetype());
      $filename = 'cover_moodle.' . $ext;
      $fullPath = $coverDir . '/' . $filename;

      if (@file_put_contents($fullPath, $binary) === false) {
        return false;
      }

      $relativePath = '/storage/courses/' . $courseId . '/' . $filename;
      Db::exec("UPDATE app_course SET image = :image WHERE id = :id", [
        'image' => $relativePath,
        'id' => $courseId,
      ]);

      return true;
    } catch (\Throwable $e) {
      error_log('[app_v3] Falha ao sincronizar imagem do curso: ' . $e->getMessage());
      return false;
    }
  }

  private static function pickCourseCoverFile(array $files): ?\stored_file {
    $fallback = null;

    foreach ($files as $file) {
      if (!$file instanceof \stored_file) {
        continue;
      }

      $name = (string)$file->get_filename();
      if ($name === '' || $name === '.') {
        continue;
      }

      $mime = strtolower((string)$file->get_mimetype());
      if (\strpos($mime, 'image/') === 0) {
        return $file;
      }

      if ($fallback === null) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
          $fallback = $file;
        }
      }
    }

    return $fallback;
  }

  private static function guessImageExtension(string $filename, string $mime): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
      return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    $mime = strtolower(trim($mime));
    if ($mime === 'image/png') return 'png';
    if ($mime === 'image/webp') return 'webp';
    if ($mime === 'image/gif') return 'gif';
    return 'jpg';
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

  private static function uniqueSlug(string $slug): string {
    $base = $slug;
    $i = 2;
    while (Db::one("SELECT id FROM app_course WHERE slug = :slug LIMIT 1", ['slug' => $slug])) {
      $slug = $base . '-' . $i;
      $i++;
    }
    return $slug;
  }

  private static function normalizeSyncOverrides(array $syncOverrides): array {
    $result = [
      'item_subtype' => [],
      'item_title' => [],
      'section_title' => [],
      'section_level' => [],
      'section_subtype' => [],
      'item_cmid' => [],
      'item_url' => [],
      'item_skip' => [],
      'section_skip' => [],
    ];

    // Compatibilidade com formato antigo: {cmid: subtype}
    if (
      !isset($syncOverrides['item_subtype'])
      && !isset($syncOverrides['item_title'])
      && !isset($syncOverrides['section_title'])
      && !isset($syncOverrides['section_level'])
      && !isset($syncOverrides['section_subtype'])
      && !isset($syncOverrides['item_cmid'])
      && !isset($syncOverrides['item_url'])
      && !isset($syncOverrides['item_skip'])
      && !isset($syncOverrides['section_skip'])
    ) {
      foreach ($syncOverrides as $cmid => $subtype) {
        $cmidInt = (int)$cmid;
        $normalizedSubtype = self::normalizeSyncSubtype($subtype);
        if ($cmidInt <= 0 || $normalizedSubtype === null) {
          continue;
        }
        $result['item_subtype'][$cmidInt] = $normalizedSubtype;
      }
      return $result;
    }

    if (isset($syncOverrides['item_subtype']) && is_array($syncOverrides['item_subtype'])) {
      foreach ($syncOverrides['item_subtype'] as $cmid => $subtype) {
        $cmidInt = (int)$cmid;
        $normalizedSubtype = self::normalizeSyncSubtype($subtype);
        if ($cmidInt <= 0 || $normalizedSubtype === null) {
          continue;
        }
        $result['item_subtype'][$cmidInt] = $normalizedSubtype;
      }
    }

    if (isset($syncOverrides['item_title']) && is_array($syncOverrides['item_title'])) {
      foreach ($syncOverrides['item_title'] as $cmid => $title) {
        $cmidInt = (int)$cmid;
        $normalizedTitle = self::normalizeSyncTitle($title);
        if ($cmidInt <= 0 || $normalizedTitle === null) {
          continue;
        }
        $result['item_title'][$cmidInt] = $normalizedTitle;
      }
    }

    if (isset($syncOverrides['item_cmid']) && is_array($syncOverrides['item_cmid'])) {
      foreach ($syncOverrides['item_cmid'] as $cmid => $mappedCmid) {
        $cmidInt = (int)$cmid;
        $mappedCmidInt = (int)$mappedCmid;
        if ($cmidInt <= 0 || $mappedCmidInt <= 0) {
          continue;
        }
        $result['item_cmid'][$cmidInt] = $mappedCmidInt;
      }
    }

    if (isset($syncOverrides['item_url']) && is_array($syncOverrides['item_url'])) {
      foreach ($syncOverrides['item_url'] as $cmid => $url) {
        $cmidInt = (int)$cmid;
        $normalizedUrl = self::normalizeSyncUrl($url);
        if ($cmidInt <= 0 || $normalizedUrl === null) {
          continue;
        }
        $result['item_url'][$cmidInt] = $normalizedUrl;
      }
    }

    if (isset($syncOverrides['section_title']) && is_array($syncOverrides['section_title'])) {
      foreach ($syncOverrides['section_title'] as $section => $title) {
        $sectionInt = (int)$section;
        $normalizedTitle = self::normalizeSyncTitle($title);
        if ($sectionInt < 0 || $normalizedTitle === null) {
          continue;
        }
        $result['section_title'][$sectionInt] = $normalizedTitle;
      }
    }

    if (isset($syncOverrides['section_level']) && is_array($syncOverrides['section_level'])) {
      foreach ($syncOverrides['section_level'] as $section => $level) {
        $sectionInt = (int)$section;
        $levelInt = (int)$level;
        if ($sectionInt < 0 || $levelInt <= 0) {
          continue;
        }
        $result['section_level'][$sectionInt] = max(1, min(8, $levelInt));
      }
    }

    if (isset($syncOverrides['section_subtype']) && is_array($syncOverrides['section_subtype'])) {
      foreach ($syncOverrides['section_subtype'] as $section => $subtype) {
        $sectionInt = (int)$section;
        $normalizedSubtype = self::normalizeSyncContainerSubtype($subtype);
        if ($sectionInt < 0 || $normalizedSubtype === null) {
          continue;
        }
        $result['section_subtype'][$sectionInt] = $normalizedSubtype;
      }
    }

    if (isset($syncOverrides['item_skip']) && is_array($syncOverrides['item_skip'])) {
      foreach ($syncOverrides['item_skip'] as $cmid => $flag) {
        $cmidInt = (int)$cmid;
        if ($cmidInt <= 0) {
          continue;
        }
        if ($flag) {
          $result['item_skip'][$cmidInt] = 1;
        }
      }
    }

    if (isset($syncOverrides['section_skip']) && is_array($syncOverrides['section_skip'])) {
      foreach ($syncOverrides['section_skip'] as $section => $flag) {
        $sectionInt = (int)$section;
        if ($sectionInt < 0) {
          continue;
        }
        if ($flag) {
          $result['section_skip'][$sectionInt] = 1;
        }
      }
    }

    return $result;
  }

  private static function normalizeSyncTitle($value): ?string {
    if (!is_string($value)) {
      return null;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    if (function_exists('mb_substr')) {
      return mb_substr($value, 0, 255, 'UTF-8');
    }

    return substr($value, 0, 255);
  }

  private static function normalizeSyncSubtype($value): ?string {
    if (!is_string($value)) {
      return null;
    }

    $value = strtolower(trim($value));
    if ($value === '') {
      return null;
    }

    $allowed = ['text', 'pdf', 'video', 'download', 'link', 'final_exam', 'certificate'];
    return in_array($value, $allowed, true) ? $value : null;
  }

  private static function normalizeSyncContainerSubtype($value): ?string {
    if (!is_string($value)) {
      return null;
    }

    $value = strtolower(trim($value));
    if ($value === '') {
      return null;
    }

    $allowed = ['module', 'topic', 'section', 'subsection'];
    return in_array($value, $allowed, true) ? $value : null;
  }

  private static function normalizeSyncUrl($value): ?string {
    if (!is_string($value)) {
      return null;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    return function_exists('mb_substr')
      ? mb_substr($value, 0, 2048, 'UTF-8')
      : substr($value, 0, 2048);
  }

  private static function applyUrlOverrideForSubtype(array $content, string $subtype, string $url): array {
    $url = trim($url);
    if ($url === '') {
      return $content;
    }

    if (!isset($content['moodle']) || !is_array($content['moodle'])) {
      $content['moodle'] = [];
    }
    $content['moodle']['url'] = $url;

    if ($subtype === 'pdf' || $subtype === 'download') {
      $content['file_path'] = $url;
      return $content;
    }

    if ($subtype === 'text') {
      $content['source_url'] = $url;
      return $content;
    }

    $content['url'] = $url;
    return $content;
  }

  private static function normalizeContentForSubtype(array $content, string $subtype, string $fallbackUrl, string $fallbackLabel): array {
    $url = trim((string)($content['url'] ?? ''));
    $filePath = trim((string)($content['file_path'] ?? ''));

    if ($url === '' && $filePath !== '') {
      $url = $filePath;
    }
    if ($url === '' && $fallbackUrl !== '') {
      $url = $fallbackUrl;
    }

    if ($subtype === 'video') {
      $content['url'] = $url;
      if (empty($content['provider'])) {
        $content['provider'] = self::detectVideoProvider($url);
      }
      return $content;
    }

    if ($subtype === 'pdf') {
      if ($filePath === '' && $url !== '') {
        $content['file_path'] = $url;
      }
      return $content;
    }

    if ($subtype === 'download') {
      if ($url !== '' && $filePath === '') {
        $content['file_path'] = $url;
      }
      if (empty($content['label'])) {
        $content['label'] = $fallbackLabel !== '' ? $fallbackLabel : 'Baixar arquivo';
      }
      return $content;
    }

    if ($subtype === 'text') {
      if (empty($content['html'])) {
        if ($url !== '') {
          $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
          $content['html'] = '<p>Conteudo importado do Moodle.</p><p><a href="' . $safeUrl . '" target="_blank" rel="noopener">Abrir material</a></p>';
        } else {
          $content['html'] = '<p>Conteudo importado do Moodle. Edite este bloco no Builder.</p>';
        }
      }
      return $content;
    }

    // link (default)
    $content['url'] = $url;
    if (empty($content['label'])) {
      $content['label'] = $fallbackLabel !== '' ? $fallbackLabel : 'Abrir conteudo';
    }
    return $content;
  }

  private static function detectVideoProvider(string $url): string {
    $url = strtolower($url);
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
      return 'youtube';
    }
    if (strpos($url, 'vimeo.com') !== false) {
      return 'vimeo';
    }
    return 'iframe';
  }

  private static function buildLessonSnapshot(int $cmid, int $lessonId, string $fallbackUrl): ?array {
    global $DB;

    if ($cmid <= 0 || $lessonId <= 0) {
      return null;
    }

    try {
      $lesson = $DB->get_record('lesson', ['id' => $lessonId], '*', IGNORE_MISSING);
      if (!$lesson) {
        return null;
      }

      $context = \context_module::instance($cmid, IGNORE_MISSING);
      $introHtml = self::formatLessonHtml(
        (string)($lesson->intro ?? ''),
        (int)($lesson->introformat ?? FORMAT_HTML),
        $context,
        'intro',
        0
      );
      $introRefs = self::extractHtmlReferences($introHtml);

      $mediaUrl = '';
      $mediaRaw = trim((string)($lesson->mediafile ?? ''));
      if ($mediaRaw !== '') {
        if (is_object($context) && !empty($context->id)) {
          $mediaUrl = (string)\file_rewrite_pluginfile_urls(
            $mediaRaw,
            'pluginfile.php',
            (int)$context->id,
            'mod_lesson',
            'mediafile',
            0
          );
        } else {
          $mediaUrl = $mediaRaw;
        }
      }

      $pages = [];
      $pagesById = [];
      $pageRows = $DB->get_records('lesson_pages', ['lessonid' => $lessonId], 'id ASC');
      $allLinks = [];
      $allMedia = [];
      $questionPages = 0;

      self::mergeUniqueUrlList($allLinks, $introRefs['links']);
      self::mergeUniqueUrlList($allMedia, $introRefs['media']);
      if ($mediaUrl !== '') {
        self::mergeUniqueUrlList($allMedia, [$mediaUrl]);
      }

      if ($pageRows) {
        foreach ($pageRows as $pageRowObj) {
          $pageRow = (array)$pageRowObj;
          $pageId = (int)($pageRow['id'] ?? 0);
          if ($pageId <= 0) {
            continue;
          }

          $qtype = (int)($pageRow['qtype'] ?? 0);
          if ($qtype > 0) {
            $questionPages++;
          }

          $contentsHtml = self::formatLessonHtml(
            (string)($pageRow['contents'] ?? ''),
            (int)($pageRow['contentsformat'] ?? FORMAT_HTML),
            $context,
            'page_contents',
            $pageId
          );
          $contentRefs = self::extractHtmlReferences($contentsHtml);
          self::mergeUniqueUrlList($allLinks, $contentRefs['links']);
          self::mergeUniqueUrlList($allMedia, $contentRefs['media']);

          $answers = [];
          $answerRows = $DB->get_records('lesson_answers', ['pageid' => $pageId], 'id ASC');
          if ($answerRows) {
            foreach ($answerRows as $answerRowObj) {
              $answerRow = (array)$answerRowObj;
              $answerId = (int)($answerRow['id'] ?? 0);
              if ($answerId <= 0) {
                continue;
              }

              $answerHtml = self::formatLessonHtml(
                (string)($answerRow['answer'] ?? ''),
                (int)($answerRow['answerformat'] ?? FORMAT_HTML),
                $context,
                'page_answers',
                $answerId
              );
              $responseHtml = self::formatLessonHtml(
                (string)($answerRow['response'] ?? ''),
                (int)($answerRow['responseformat'] ?? FORMAT_HTML),
                $context,
                'page_responses',
                $answerId
              );

              $answerRefs = self::extractHtmlReferences($answerHtml);
              $responseRefs = self::extractHtmlReferences($responseHtml);
              self::mergeUniqueUrlList($allLinks, $answerRefs['links']);
              self::mergeUniqueUrlList($allLinks, $responseRefs['links']);
              self::mergeUniqueUrlList($allMedia, $answerRefs['media']);
              self::mergeUniqueUrlList($allMedia, $responseRefs['media']);

              $answers[] = [
                'id' => $answerId,
                'jump_to' => (int)($answerRow['jumpto'] ?? 0),
                'score' => (float)($answerRow['score'] ?? 0),
                'answer_html' => $answerHtml,
                'response_html' => $responseHtml,
                'answer_links' => $answerRefs['links'],
                'answer_media' => $answerRefs['media'],
                'response_links' => $responseRefs['links'],
                'response_media' => $responseRefs['media'],
              ];
            }
          }

          $pagesById[$pageId] = [
            'id' => $pageId,
            'title' => self::sanitizeImportedTitle((string)($pageRow['title'] ?? '')),
            'qtype' => $qtype,
            'qoption' => (int)($pageRow['qoption'] ?? 0),
            'prev_page_id' => (int)($pageRow['prevpageid'] ?? 0),
            'next_page_id' => (int)($pageRow['nextpageid'] ?? 0),
            'display_order' => (int)($pageRow['display'] ?? 0),
            'contents_html' => $contentsHtml,
            'content_links' => $contentRefs['links'],
            'content_media' => $contentRefs['media'],
            'answers' => $answers,
          ];
        }

        $pages = self::orderLessonPages($pagesById);
      }

      $pagesTotal = count($pages);
      $questionPages = min($pagesTotal, max(0, $questionPages));
      $contentPages = max(0, $pagesTotal - $questionPages);

      return [
        'id' => (int)$lesson->id,
        'name' => (string)($lesson->name ?? ''),
        'source_url' => $fallbackUrl,
        'intro_html' => $introHtml,
        'intro_links' => $introRefs['links'],
        'intro_media' => $introRefs['media'],
        'media_url' => $mediaUrl,
        'retake' => (int)($lesson->retake ?? 0) === 1,
        'max_attempts' => (int)($lesson->maxattempts ?? 0),
        'max_answers' => (int)($lesson->maxanswers ?? 0),
        'timelimit' => (int)($lesson->timelimit ?? 0),
        'require_password' => (int)($lesson->usepassword ?? 0) === 1,
        'password_hint' => (int)($lesson->usepassword ?? 0) === 1 ? 'password_required' : '',
        'pages' => $pages,
        'links' => $allLinks,
        'media' => $allMedia,
        'summary' => [
          'pages_total' => $pagesTotal,
          'question_pages' => $questionPages,
          'content_pages' => $contentPages,
          'links_total' => count($allLinks),
          'media_total' => count($allMedia),
        ],
        'snapshot_at' => gmdate('c'),
      ];
    } catch (\Throwable $e) {
      error_log('[app_v3][course_sync] lesson_snapshot_error cmid=' . $cmid . ' lesson=' . $lessonId . ' error=' . $e->getMessage());
      return null;
    }
  }

  private static function formatLessonHtml(string $rawHtml, int $format, $context, string $fileArea, int $itemId): string {
    $rawHtml = trim($rawHtml);
    if ($rawHtml === '') {
      return '';
    }

    $html = $rawHtml;
    if (is_object($context) && !empty($context->id)) {
      $html = (string)\file_rewrite_pluginfile_urls(
        $html,
        'pluginfile.php',
        (int)$context->id,
        'mod_lesson',
        $fileArea,
        $itemId
      );
    }

    $options = ['noclean' => true];
    if (is_object($context)) {
      $options['context'] = $context;
    }

    return (string)\format_text($html, $format > 0 ? $format : FORMAT_HTML, $options);
  }

  private static function extractHtmlReferences(string $html): array {
    $result = [
      'links' => [],
      'media' => [],
    ];

    $html = trim($html);
    if ($html === '') {
      return $result;
    }

    $extract = static function (string $pattern, string $source): array {
      $matches = [];
      if (preg_match_all($pattern, $source, $matches) <= 0 || empty($matches[2])) {
        return [];
      }

      $values = [];
      foreach ($matches[2] as $value) {
        $decoded = html_entity_decode(trim((string)$value), ENT_QUOTES, 'UTF-8');
        if ($decoded === '') {
          continue;
        }
        if (!in_array($decoded, $values, true)) {
          $values[] = $decoded;
        }
      }
      return $values;
    };

    $links = $extract('/<a\\b[^>]*\\bhref\\s*=\\s*(["\\\'])(.*?)\\1/iu', $html);
    $media = $extract('/<(?:iframe|video|source|embed|img)\\b[^>]*\\b(?:src|data-src|poster)\\s*=\\s*(["\\\'])(.*?)\\1/iu', $html);

    $plainUrls = [];
    if (preg_match_all('/https?:\\/\\/[^\\s<>"\\\']+/iu', $html, $plainMatches) > 0 && !empty($plainMatches[0])) {
      foreach ($plainMatches[0] as $urlRaw) {
        $url = html_entity_decode(trim((string)$urlRaw), ENT_QUOTES, 'UTF-8');
        if ($url === '') {
          continue;
        }
        if (!in_array($url, $plainUrls, true)) {
          $plainUrls[] = $url;
        }
      }
    }

    self::mergeUniqueUrlList($result['links'], $links);
    self::mergeUniqueUrlList($result['links'], $plainUrls);
    self::mergeUniqueUrlList($result['media'], $media);

    return $result;
  }

  private static function mergeUniqueUrlList(array &$target, array $incoming): void {
    foreach ($incoming as $value) {
      $url = trim((string)$value);
      if ($url === '') {
        continue;
      }
      if (!in_array($url, $target, true)) {
        $target[] = $url;
      }
    }
  }

  private static function orderLessonPages(array $pagesById): array {
    if (!$pagesById) {
      return [];
    }

    $startIds = [];
    foreach ($pagesById as $pageId => $pagePayload) {
      $prevId = (int)($pagePayload['prev_page_id'] ?? 0);
      if ($prevId <= 0 || !isset($pagesById[$prevId])) {
        $startIds[] = (int)$pageId;
      }
    }

    if (!$startIds) {
      $startIds = array_map(static function ($id): int {
        return (int)$id;
      }, array_keys($pagesById));
    }

    sort($startIds, SORT_NUMERIC);

    $orderedIds = [];
    $visited = [];
    $maxSteps = count($pagesById) + 5;

    foreach ($startIds as $startId) {
      $cursor = $startId;
      $steps = 0;
      while ($cursor > 0 && isset($pagesById[$cursor]) && !isset($visited[$cursor])) {
        $orderedIds[] = $cursor;
        $visited[$cursor] = true;
        $steps++;
        if ($steps > $maxSteps) {
          break;
        }

        $nextId = (int)($pagesById[$cursor]['next_page_id'] ?? 0);
        if ($nextId <= 0 || !isset($pagesById[$nextId])) {
          break;
        }
        $cursor = $nextId;
      }
    }

    if (count($orderedIds) < count($pagesById)) {
      $remainingIds = [];
      foreach (array_keys($pagesById) as $pageIdRaw) {
        $pageId = (int)$pageIdRaw;
        if ($pageId <= 0 || isset($visited[$pageId])) {
          continue;
        }
        $remainingIds[] = $pageId;
      }
      sort($remainingIds, SORT_NUMERIC);
      foreach ($remainingIds as $pageId) {
        $orderedIds[] = $pageId;
      }
    }

    $orderedPages = [];
    foreach ($orderedIds as $pageId) {
      if (!isset($pagesById[$pageId])) {
        continue;
      }
      $orderedPages[] = $pagesById[$pageId];
    }

    return $orderedPages;
  }

  private static function previewMapModule(string $modname): array {
    if ($modname === 'page' || $modname === 'label') {
      return ['kind' => 'content', 'subtype' => 'text'];
    }
    if ($modname === 'resource') {
      return ['kind' => 'content', 'subtype' => 'download'];
    }
    if ($modname === 'url') {
      return ['kind' => 'content', 'subtype' => 'link'];
    }
    if ($modname === 'quiz') {
      return ['kind' => 'content', 'subtype' => 'link'];
    }
    return ['kind' => 'content', 'subtype' => 'link'];
  }

  private static function looksLikeFinalExamQuiz(string $title): bool {
    $normalized = self::normalizeTitleToken($title);
    if ($normalized === '') {
      return false;
    }

    $patterns = [
      'prova final',
      'prova de conclusao',
      'prova conclusao',
      'avaliacao final',
      'avaliacao de conclusao',
      'exame final',
      'certificacao final',
      'prova',
      'conclusao',
    ];

    foreach ($patterns as $pattern) {
      if (strpos($normalized, $pattern) !== false) {
        return true;
      }
    }

    return false;
  }

  private static function normalizeTitleToken(string $value): string {
    $value = function_exists('mb_strtolower')
      ? mb_strtolower($value, 'UTF-8')
      : strtolower($value);
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $map = [
      'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
      'é' => 'e', 'ê' => 'e',
      'í' => 'i',
      'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
      'ú' => 'u',
      'ç' => 'c',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);
    return trim((string)$value);
  }

  private static function previewTrim(string $text, int $max): string {
    $text = trim($text);
    if ($text === '' || $max <= 0) {
      return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
      }
      return rtrim(mb_substr($text, 0, $max - 3, 'UTF-8')) . '...';
    }

    if (strlen($text) <= $max) {
      return $text;
    }
    return rtrim(substr($text, 0, $max - 3)) . '...';
  }

  private static function sanitizeImportedTitle(string $title): string {
    $original = trim($title);
    if ($original === '') {
      return '';
    }

    $clean = $original;
    $clean = (string)preg_replace('/^\s*\(\s*\d+(?:\.\d+)*\s*\)\s*/u', '', $clean);
    $clean = (string)preg_replace('/^\s*(?:modulo|mÃ³dulo|unidade|aula|topico|tÃ³pico|secao|seÃ§Ã£o|section|subsecao|subseÃ§Ã£o|subsection)\s*\d+(?:\.\d+)*\s*[-â€“â€”.:)]*\s*/iu', '', $clean);
    $clean = (string)preg_replace('/^\s*\d+(?:\.\d+)*\s*[-â€“â€”.:)]*\s*/u', '', $clean);
    $clean = trim($clean);

    return $clean !== '' ? $clean : $original;
  }
}

