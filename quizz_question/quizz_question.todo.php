<?php

use Drupal\quizz_question\Entity\Question;

/**
 * @TODO: Create new permission 'manage quiz question types'
 * @return boolean
 */
function quizz_question_type_access() {
  return TRUE;
}

/**
 * @TODO: Rename to: quizz_question_load().
 *
 * Load question entity.
 *
 * @param int $id
 * @param int $vid
 * @param bool $reset
 * @return \Drupal\quizz_question\Entity\Question
 */
function quizz_question_load($id = NULL, $vid = NULL, $reset = FALSE) {
  if (NULL === $id || is_numeric($id)) { // Drupal this is hook_entity_load!
    $conditions = NULL === $vid ? array('qid' => $id) : array('vid' => $vid);
    if ($results = entity_load('quiz_question_entity', FALSE, $conditions, $reset)) {
      return reset($results);
    }
  }
}

/**
 * @TODO: Move details for entity's extra fieldscontroller class
 * Implements hook_field_extra_fields()
 */
function quizz_question_field_extra_fields() {
  $extra = array();

  foreach (quizz_question_get_types() as $name => $question_type) {
    $extra['quiz_question_entity'][$name] = array(
        'display' => array(
            'title'            => array(
                'label'       => t('Title'),
                'description' => t("Question's title."),
                'weight'      => -10,
            ),
            'question_handler' => array(
                'label'       => t("Handler fields"),
                'description' => t("Custom fields defined by question handler."),
                'weight'      => -5,
            ),
        ),
        'form'    => array(
            'title'            => array(
                'label'       => t('Title'),
                'description' => t("Question's title."),
                'weight'      => -10,
            ),
            'question_handler' => array(
                'label'       => t("Handler fields"),
                'description' => t("Custom fields defined by question handler."),
                'weight'      => -5,
            ),
            'feedback'         => array(
                'label'       => t('Question feedback'),
                'description' => '',
                'weight'      => -1,
            ),
        ),
    );

    if (module_exists('locale')) {
      $extra['quiz_question_entity'][$name]['form']['language'] = array(
          'label'       => t('Language'),
          'description' => t('Language selector'),
          'weight'      => -20,
      );
    }
  }

  return $extra;
}

/**
 * Access callback for question entity.
 *
 * @TODO: Action on own
 *
 * @param string $op
 * @param Question|null $question
 * @param stdClass $account
 * @return bool
 */
function quizz_question_access_callback($op, $question = NULL, $account = NULL, $entity_type = '') {
  switch ($op) {
    case 'update':
      if (user_access('edit any question content', $account)) {
        return TRUE;
      }

      if ($question) {
        return user_access('edit any ' . $question->type . ' question', $account);
      }

      return FALSE;

    case 'view':
      return user_access('view quiz question outside of a quiz', $account) || user_access('view any questions', $account);

    case 'delete':
      if (user_access('delete any question content', $account)) {
        return TRUE;
      }

      if ($question) {
        return user_access('delete any ' . $question->type . ' question', $account);
      }

      return FALSE;
  }

  return TRUE;
}
