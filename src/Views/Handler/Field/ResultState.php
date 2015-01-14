<?php

namespace Drupal\quizz\Views\Handler\Field;

use views_handler_field_entity;

class ResultState extends views_handler_field_entity {

  public function query() {
    parent::query();

    $this->add_additional_fields(array(
        'spent__end' => array('field' => 'time_end'),
    ));
  }

  function render($values) {
    return $values->{$this->aliases['spent__end']} ? t('Finished') : t('In Progress');
  }

}
