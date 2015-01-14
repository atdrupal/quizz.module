<?php

namespace Drupal\quizz\Views\Handler\Field;

use views_handler_field_numeric;

/**
 * Views field handler that translates question status integer constants (as
 * defined in quiz.module) into their human-readable string counterparts.
 */
class RelationshipQuestionStatus extends views_handler_field_numeric {

  function render($values) {
    switch ($values->{$this->field_alias}) {
      case QUIZZ_QUESTION_RANDOM: return t('Random'); // 'Random-ly' better?
      case QUIZZ_QUESTION_ALWAYS: return t('Always');
      case QUIZZ_QUESTION_NEVER: return t('Never');
      case QUIZZ_QUESTION_CATEGORIZED_RANDOM: return t('Categorized random questions');
    }
    return t('Unknow');
  }

}
