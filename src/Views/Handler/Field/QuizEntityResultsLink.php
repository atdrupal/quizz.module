<?php

namespace Drupal\quizz\Views\Handler\Field;

use views_handler_field_node_link;

class QuizEntityResultsLink extends views_handler_field_node_link {

  function render_link($quiz, $values) {
    if (quizz_results_tab_access($quiz)) {
      $uri = entity_uri('quiz_entity', $quiz);
      $this->options['alter']['make_link'] = TRUE;
      $this->options['alter']['path'] = $uri['path'] . '/results';
      return !empty($this->options['text']) ? $this->options['text'] : t('results');
    }
  }

}
