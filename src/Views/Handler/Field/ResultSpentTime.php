<?php

namespace Drupal\quizz\Views\Handler\Field;

use views_handler_field_entity;

class ResultSpentTime extends views_handler_field_entity {

  public function query() {
    parent::query();

    $this->add_additional_fields(array(
        'spent__start' => array('field' => 'time_start'),
        'spent__end'   => array('field' => 'time_end'),
    ));
  }

  function render($values) {
    $seconds = $values->{$this->aliases['spent__end']} - $values->{$this->aliases['spent__start']};
    return quizz()->formatDuration($seconds);
  }

}
