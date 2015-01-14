<?php

namespace Drupal\quizz\Views\Handler\Filter;

use views_handler_filter_boolean_operator;

class ResultState extends views_handler_filter_boolean_operator {

  public function value_form(&$form, &$form_state) {
    parent::value_form($form, $form_state);
    $form['value']['#title'] = t('Value');
    $form['value']['#options'][0] = t('In progress');
    $form['value']['#options'][1] = t('Finished');
  }

  function query() {
    $this->ensure_my_table();
    $field = $this->table_alias . '.' . $this->real_field;
    $this->query->add_where($this->options['group'], $field, NULL, $this->value ? 'IS NOT NULL' : 'IS NULL');
  }

}
