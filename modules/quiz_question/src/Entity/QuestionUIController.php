<?php

namespace Drupal\quiz_question\Entity;

use EntityDefaultUIController;

class QuestionUIController extends EntityDefaultUIController {

  /**
   * Overrides hook_menu() defaults.
   */
  public function hook_menu() {
    $items = parent::hook_menu();

    // "Questions" should be a tab of /admin/content
    $items['admin/content/quiz-questions']['type'] = MENU_LOCAL_TASK;

    // Change /admin/content/quiz-question/manage/ to /quiz-question/
    $items['quiz-question/%entity_object/edit'] = $items['admin/content/quiz-questions/manage/%entity_object'];
    $items['quiz-question/%entity_object/edit']['type'] = MENU_LOCAL_TASK;
    $items['quiz-question/%entity_object/edit']['page arguments'][1] = 1;
    $items['quiz-question/%entity_object/edit']['access arguments'][2] = 1;
    $items['quiz-question/%entity_object/%'] = $items['admin/content/quiz-questions/manage/%entity_object/%'];
    $items['quiz-question/%entity_object/%']['page arguments'][2] = 1;
    $items['quiz-question/%entity_object/%']['page arguments'][3] = 2;
    $items['quiz-question/%entity_object/%']['access arguments'][2] = 1;

    // Change path from /admin/content/quiz/add -> /quizz/add
    $items['quiz-question/add'] = array(
        'file path'      => drupal_get_path('module', 'quiz_question'),
        'file'           => 'quiz_question.pages.inc',
        'page callback'  => 'quiz_question_adding_landing_page',
        'page arguments' => array(),
      ) + $items['admin/content/quiz-questions/add'];

    // Remove unneeded menu items
    unset($items['quiz-question/%entity_object/edit']['title callback']);
    unset($items['quiz-question/%entity_object/edit']['title arguments']);
    unset($items['admin/content/quiz-questions/manage/%entity_object']);
    unset($items['admin/content/quiz-questions/manage/%entity_object/edit']);
    unset($items['admin/content/quiz-questions/manage/%entity_object/clone']);
    unset($items['admin/content/quiz-questions/add']);

    $items['quiz-question/%quiz_question_entity/revision-actions'] = array(
        'title'            => 'Revision actions',
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_question_revision_actions_form', 1),
        'access arguments' => array('manual quiz revisioning'),
        'file path'        => drupal_get_path('module', 'quiz_question'),
        'file'             => 'quiz_question.pages.inc',
        'type'             => MENU_NORMAL_ITEM,
    );

    $this->fixMenuItemPermissions($items);

    return $items + $this->getExtraMenuItems();
  }

  private function fixMenuItemPermissions(&$items) {
    $items['admin/content/quiz-questions']['access callback'] = 'user_access';
    $items['admin/content/quiz-questions']['access arguments'] = array('administer quiz questions');
  }

  private function getExtraMenuItems() {
    $items = array();

    $items['quiz-question/%quiz_question_entity/revisions'] = array(
        'title'            => 'Revisions',
        'type'             => MENU_LOCAL_ACTION,
        'access callback'  => 'entity_access',
        'access arguments' => array('update', 'quiz_question', 1),
        'file path'        => drupal_get_path('module', 'quiz_question'),
        'file'             => 'quiz_question.pages.inc',
        'page callback'    => 'quiz_question_revisions_page',
        'page arguments'   => array(1),
    );

    foreach (array_keys(quiz_question_get_types()) as $name) {
      $items['quiz-question/add/' . str_replace('_', '-', $name)] = array(
          'title callback'   => 'entity_ui_get_action_title',
          'title arguments'  => array('add', 'quiz_question'),
          'access callback'  => 'user_access',
          'access arguments' => array('create ' . $name . ' question'),
          'page callback'    => 'quiz_question_adding_page',
          'page arguments'   => array($name),
          'file path'        => drupal_get_path('module', 'quiz_question'),
          'file'             => 'quiz_question.pages.inc',
      );
    }

    $items['quiz-question/%/%/revision-actions'] = array(
        'title'            => 'Revision actions',
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_question_revision_actions', 1, 2),
        'access arguments' => array('manual quiz revisioning'),
        'file path'        => drupal_get_path('module', 'quiz_question'),
        'file'             => 'quiz_question.pages.inc',
        'type'             => MENU_NORMAL_ITEM,
    );

    $items['quiz-question/%quiz_question_entity'] = array(
        'title callback'   => 'entity_class_label',
        'title arguments'  => array(1),
        'access callback'  => 'quiz_question_access_callback',
        'access arguments' => array('view', 1),
        'file path'        => drupal_get_path('module', 'quiz_question'),
        'file'             => 'quiz_question.pages.inc',
        'page callback'    => 'quiz_question_page',
        'page arguments'   => array(1),
    );

    $items['quiz-question/%quiz_question_entity/view'] = array(
        'title'  => 'View',
        'type'   => MENU_DEFAULT_LOCAL_TASK,
        'weight' => -10,
    );

    if (module_exists('devel')) {
      $items['quiz-question/%quiz_question_entity/devel'] = array(
          'title'            => 'Devel',
          'access arguments' => array('access devel information'),
          'page callback'    => 'devel_load_object',
          'page arguments'   => array('quiz_question', 1),
          'type'             => MENU_LOCAL_TASK,
          'file'             => 'devel.pages.inc',
          'file path'        => drupal_get_path('module', 'devel'),
          'weight'           => 20,
      );
    }

    return $items;
  }

  /**
   * {@inheritdoc}
   * Override parent method to provide more column.
   */
  protected function overviewTableHeaders($conditions, $rows, $additional_header = array()) {
    $additional_header[] = t('Type');
    $headers = parent::overviewTableHeaders($conditions, $rows, $additional_header);
    $headers[0] = t('Question');
    return $headers;
  }

  /**
   * {@inheritdoc}
   * Override parent method to provide more column.
   * @param \Drupal\quiz_question\Entity\Question $question
   */
  protected function overviewTableRow($conditions, $id, $question, $additional_cols = array()) {
    $handler_info = $question->getHandlerInfo();
    $handler_name = $handler_info['name'];
    $additional_cols[] = $question->getQuestionType()->label . ' (' . $handler_name . ')';
    $columns = parent::overviewTableRow($conditions, $id, $question, $additional_cols);

    // change manage prefix from '/admin/content/quiz-questions/manage/' to 'quiz-question/'
    foreach ($columns as &$column) {
      if (!is_string($column)) {
        continue;
      }
      $column = str_replace("/admin/content/quiz-questions/manage/{$id}\"", "/quiz-question/{$id}/edit\"", $column);
      $column = str_replace('/admin/content/quiz-questions/manage/', '/quiz-question/', $column);
    }
    return $columns;
  }

}
