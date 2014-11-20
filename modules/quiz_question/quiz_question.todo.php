<?php

use Drupal\quiz_question\Entity\Question;

function quiz_question_type_access() {
  return TRUE;
}

/**
 * Access callback for question entity.
 *
 * @TODO: Action on own
 *
 * @param string $op
 * @param Question|null $question
 * @param stdClass $account
 */
function quiz_question_access_callback($op, $question = NULL, $account = NULL, $entity_type = '') {
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
