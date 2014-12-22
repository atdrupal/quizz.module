<?php

class quiz_views_handler_filter_quiz_question_type extends views_handler_filter_in_operator {

  public function get_value_options() {
    if (!isset($this->value_options)) {
      $this->value_title = t('Quiz question types');
      $types = quizz_question_get_handler_info();

      $options = array();
      foreach ($types as $type => $info) {
        $options[$type] = t($info['name']);
      }
      asort($options);
      $this->value_options = $options;
    }
  }

}
