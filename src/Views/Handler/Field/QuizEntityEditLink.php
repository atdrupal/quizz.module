<?php

namespace Drupal\quizz\Views\Handler\Field;

class QuizEntityEditLink extends QuizEntityLink {

  function render_link($quiz, $values) {
    if (entity_access('update', 'quiz_entity', $quiz)) {
      $uri = entity_uri('quiz_entity', $quiz);
      $this->options['alter']['make_link'] = TRUE;
      $this->options['alter']['path'] = $uri['path'] . '/edit';
      return !empty($this->options['text']) ? $this->options['text'] : t('edit');
    }
  }

}
