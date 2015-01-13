<?php

namespace Drupal\quizz\Context\Condition;

use context_condition;

class QuizTypeCondition extends context_condition {

  const ENTITY_VIEW = 0;
  const ENTITY_FORM = 1;
  const ENTITY_FORM_ONLY = 2;

  function condition_values() {
    $values = array();
    foreach (quizz_get_types() as $type) {
      $values[$type->type] = check_plain($type->label);
    }
    return $values;
  }

  function options_form($context) {
    $defaults = $this->fetch_from_context($context, 'options');
    return array(
        'quiz_entity_form' => array(
            '#title'         => t('Set on quiz entity form'),
            '#type'          => 'select',
            '#options'       => array(
                static::ENTITY_VIEW      => t('No'),
                static::ENTITY_FORM      => t('Yes'),
                static::ENTITY_FORM_ONLY => t('Only on node form')
            ),
            '#description'   => t('Set this context on node forms'),
            '#default_value' => isset($defaults['quiz_entity_form']) ? $defaults['quiz_entity_form'] : TRUE,
        ),
    );
  }

  function execute($quiz, $op) {
    foreach ($this->get_contexts($quiz->type) as $context) {
      // Check the quiz form option.
      $options = $this->fetch_from_context($context, 'options');
      if ($op === 'form') {
        $options = $this->fetch_from_context($context, 'options');
        if (!empty($options['quiz_entity_form']) && in_array($options['quiz_entity_form'], array(static::ENTITY_FORM, static::ENTITY_FORM_ONLY))) {
          $this->condition_met($context, $quiz->type);
        }
      }
      elseif (empty($options['quiz_entity_form']) || $options['quiz_entity_form'] != static::ENTITY_FORM_ONLY) {
        $this->condition_met($context, $quiz->type);
      }
    }
  }

}
