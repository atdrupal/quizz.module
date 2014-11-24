<?php

namespace Drupal\quizz\Helper\HookImplementation;

class HookMenu {

  public function execute() {
    $items = array();

    $items += $this->getQuizAdminMenuItems();
    $items += $this->getQuizUserMenuItems();

    $items['quiz-result/%quiz_result'] = array(
        'title'            => 'User results',
        'access callback'  => 'quiz_access_my_result',
        'access arguments' => array(1),
        'page callback'    => 'quiz_result_page',
        'page arguments'   => array(1),
        'file'             => 'quiz.pages.inc',
    );

    if (module_exists('devel')) {
      $items['admin/config/development/generate/quiz'] = array(
          'title'            => 'Generate quiz',
          'description'      => 'Generate a given number of quizzes',
          'access arguments' => array('create quiz content'),
          'page callback'    => 'drupal_get_form',
          'page arguments'   => array('quiz_generate_form'),
          'file'             => 'quiz.devel.inc',
      );
    }

    return $items;
  }

  private function getQuizAdminMenuItems() {
    $items = array();

    // Admin pages.
    $items['admin/quiz'] = array(
        'title'            => '@quiz',
        'title arguments'  => array('@quiz' => QUIZ_NAME),
        'description'      => 'View results, score answers, run reports and edit configurations.',
        'page callback'    => 'system_admin_menu_block_page',
        'access arguments' => array('administer quiz configuration', 'score any quiz', 'score own quiz', 'view any quiz results', 'view results for own quiz'),
        'access callback'  => 'quiz_access_multi_or',
        'type'             => MENU_NORMAL_ITEM,
        'file'             => 'system.admin.inc',
        'file path'        => drupal_get_path('module', 'system'),
    );

    $items['admin/quiz/settings'] = array(
        'title'            => '@quiz settings',
        'title arguments'  => array('@quiz' => QUIZ_NAME),
        'description'      => 'Change settings for the all Quiz project modules.',
        'page callback'    => 'system_admin_menu_block_page',
        'access arguments' => array('administer quiz configuration'),
        'type'             => MENU_NORMAL_ITEM,
        'file'             => 'system.admin.inc',
        'file path'        => drupal_get_path('module', 'system'),
    );

    $items['admin/quiz/settings/config'] = array(
        'title'            => '@quiz configuration',
        'title arguments'  => array('@quiz' => QUIZ_NAME),
        'description'      => 'Configure the Quiz module.',
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_admin_settings_form'),
        'access arguments' => array('administer quiz configuration'),
        'type'             => MENU_NORMAL_ITEM, // optional
        'file'             => 'quiz.pages.inc',
    );

    $items['admin/quiz/settings/quiz-form'] = array(
        'title'            => '@quiz form configuration',
        'title arguments'  => array('@quiz' => QUIZ_NAME),
        'description'      => 'Configure default values for the quiz creation form.',
        'page callback'    => 'drupal_get_form',
        'page arguments'   => array('quiz_admin_entity_form'),
        'access arguments' => array('administer quiz configuration'),
        'type'             => MENU_NORMAL_ITEM, // optional
        'file'             => 'quiz.pages.inc',
    );

    $items['admin/quiz/reports'] = array(
        'title'            => '@quiz reports and scoring',
        'title arguments'  => array('@quiz' => QUIZ_NAME),
        'description'      => 'View reports and score answers.',
        'page callback'    => 'system_admin_menu_block_page',
        'access arguments' => array('view any quiz results', 'view results for own quiz'),
        'access callback'  => 'quiz_access_multi_or',
        'type'             => MENU_NORMAL_ITEM,
        'file'             => 'system.admin.inc',
        'file path'        => drupal_get_path('module', 'system'),
    );

    return $items;
  }

  private function getQuizUserMenuItems() {
    $items = array();

    // User pages.
    $items['user/%/quiz-results/%quiz_result/view'] = array(
        'title'            => 'User results',
        'access arguments' => array(3),
        'access callback'  => 'quiz_access_my_result',
        'file'             => 'quiz.pages.inc',
        'page callback'    => 'quiz_result_page',
        'page arguments'   => array(3),
        'type'             => MENU_CALLBACK,
    );

    return $items;
  }

}
