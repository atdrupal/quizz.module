<?php

namespace Drupal\quizz\Entity;

use EntityDefaultUIController;

class QuizUIController extends EntityDefaultUIController {

  public function hook_menu() {
    $items = parent::hook_menu();
    $items['admin/content/quizz']['type'] = MENU_LOCAL_TASK;

    $base = array(
        'file path' => drupal_get_path('module', 'quizz'),
        'file'      => 'quizz.pages.inc',
    );

    $this->addQuizAddLinks($items, $base);
    $this->addQuizCRUDItems($items, $base);
    $this->addQuizTabItems($items, $base);
    $this->addQuizTakeItems($items, $base);

    return $items;
  }

  private function addQuizCRUDItems(&$items, $base) {
    $items['quiz/%quizz'] = $base + array(
        'title callback'   => 'entity_class_label',
        'title arguments'  => array(1),
        'access callback'  => 'quizz_entity_access_callback',
        'access arguments' => array('view'),
        'page callback'    => 'quiz_page',
        'page arguments'   => array(1),
    );

    $items['quiz/%quizz/view'] = array(
        'title'  => 'View',
        'type'   => MENU_DEFAULT_LOCAL_TASK,
        'weight' => -30,
    );

    // Define menu item structure for /quiz/%/edit
    $items['quiz/%entity_object/edit'] = $items['admin/content/quizz/manage/%entity_object'];
    $items['quiz/%entity_object/edit']['title'] = 'Edit';
    unset($items['quiz/%entity_object/edit']['title callback'], $items['quiz/%entity_object/edit']['title arguments']);
    $items['quiz/%entity_object/edit']['type'] = MENU_LOCAL_TASK;
    $items['quiz/%entity_object/edit']['weight'] = -25;
    $items['quiz/%entity_object/edit']['page arguments'][1] = 1;
    $items['quiz/%entity_object/edit']['access arguments'][2] = 1;

    // Define menu item structure for /quiz/%/delete
    $items['quiz/%quizz/delete'] = array(
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_entity_operation_form', 'quiz_entity', 1, 'delete'),
        'access callback'  => 'entity_access',
        'access arguments' => array('delete', 'quiz_entity', 1),
        'file'             => 'includes/entity.ui.inc',
    );
  }

  private function addQuizTabItems(&$items, $base) {
    // Define menu structure for /quiz/%/revisions
    $items['quiz/%quizz/revisions'] = $base + array(
        'title'            => 'Revisions',
        'type'             => MENU_LOCAL_TASK,
        'access callback'  => 'entity_access',
        'access arguments' => array('update', 'quiz_entity', 1),
        'page callback'    => 'quiz_revisions_page',
        'page arguments'   => array(1),
        'weight'           => -20,
    );

    // Define menu structure for /quiz/%/questions
    $items['quiz/%quizz/questions'] = $base + array(
        'title'            => 'Manage questions',
        'type'             => MENU_LOCAL_TASK,
        'access callback'  => 'entity_access',
        'access arguments' => array('update', 'quiz_entity', 1),
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_question_admin_page', 1),
        'weight'           => 5,
    );

    // Define menu structure for /quiz/%/results
    $items['quiz/%quizz/results'] = $base + array(
        'title'            => 'Results',
        'type'             => MENU_LOCAL_TASK,
        'access callback'  => 'entity_access',
        'access arguments' => array('update', 'quiz_entity', 1),
        'page callback'    => 'quiz_results_page',
        'page arguments'   => array(1),
        'weight'           => 6,
    );

    // Define menu structure for /quiz/%/results
    $items['quiz/%quizz/my-results'] = $base + array(
        'title'            => 'My results',
        'type'             => MENU_LOCAL_TASK,
        'access callback'  => 'entity_access',
        'access arguments' => array('update', 'quiz_entity', 1),
        'page callback'    => 'quiz_results_user_page',
        'page arguments'   => array(1),
        'weight'           => 6,
    );

    $items['quiz/%quizz/questions/term_ahah'] = $base + array(
        'type'             => MENU_CALLBACK,
        'access callback'  => 'entity_access',
        'access arguments' => array('create', 'quiz_entity', 1),
        'page callback'    => 'Drupal\quizz\Form\QuizCategorizedForm::categorizedTermAhah',
    );

    if (module_exists('devel')) {
      $items['quiz/%quizz/devel'] = array(
          'title'            => 'Devel',
          'access arguments' => array('access devel information'),
          'page callback'    => 'devel_load_object',
          'page arguments'   => array('quiz_entity', 1),
          'type'             => MENU_LOCAL_TASK,
          'file'             => 'devel.pages.inc',
          'file path'        => drupal_get_path('module', 'devel'),
          'weight'           => 20,
      );
    }
  }

  private function addQuizAddLinks(&$items) {
    // Change path from /admin/content/quizz/add -> /quizz/add
    $items['quiz/add'] = $items['admin/content/quizz/add'];
    unset($items['admin/content/quizz/add']);

    // Menu items for /quiz/add/*
    if (($types = quizz_get_types()) && (1 < count($types))) {
      $items['quiz/add'] = array(
          'title'            => 'Add @quiz',
          'title arguments'  => array('@quiz' => QUIZZ_NAME),
          'access callback'  => 'entity_access',
          'access arguments' => array('create', 'quiz_entity'),
          'file path'        => drupal_get_path('module', 'quizz'),
          'file'             => 'quizz.pages.inc',
          'page callback'    => 'quiz_entity_adding_landing_page',
      );

      foreach (array_keys($types) as $name) {
        $items["quiz/add/{$name}"] = array(
            'title callback'   => 'entity_ui_get_action_title',
            'title arguments'  => array('add', 'quiz_entity'),
            'access callback'  => 'entity_access',
            'access arguments' => array('create', 'quiz_entity'),
            'page callback'    => 'quiz_entity_adding_page',
            'page arguments'   => array($name),
            'file path'        => drupal_get_path('module', 'quizz'),
            'file'             => 'quizz.pages.inc',
        );
      }
    }
  }

  private function addQuizTakeItems(&$items, $base) {
    $items['quiz/%quizz/take'] = $base + array(
        'title callback'   => 'entity_class_label',
        'title arguments'  => array(1),
        'page callback'    => 'quiz_take_page',
        'page arguments'   => array(1),
        'access callback'  => 'entity_access',
        'access arguments' => array('view', 'quiz_entity', 1),
    );

    $items['quiz/%quizz/take/%quizz_question_number'] = $base + array(
        'access callback'  => 'quizz_access_question',
        'access arguments' => array(1, 3),
        'file path'        => drupal_get_path('module', 'quizz'),
        'file'             => 'quizz.pages.inc',
        'page callback'    => 'quiz_question_take_page',
        'page arguments'   => array(1, 3),
    );

    $items['quiz/%quizz/take/%quizz_question_number/feedback'] = array(
        'title'            => 'Feedback',
        'file path'        => drupal_get_path('module', 'quizz'),
        'file'             => 'quizz.pages.inc',
        'page callback'    => 'quiz_question_feedback_page',
        'page arguments'   => array(1, 3),
        'access callback'  => 'quizz_question_feedback_access',
        'access arguments' => array(1, 3),
    );
  }

}
