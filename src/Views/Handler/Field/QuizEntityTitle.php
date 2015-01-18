<?php

namespace Drupal\quizz\Views\Handler\Field;

use views_handler_field;

class QuizEntityTitle extends views_handler_field {

  function init(&$view, &$options) {
    parent::init($view, $options);

    // Don't add the additional fields to groupby
    if (!empty($this->options['link_to_quiz'])) {
      $this->additional_fields['qid'] = array('table' => 'quiz_entity', 'field' => 'qid');
    }
  }

  function option_definition() {
    $options = parent::option_definition();
    $options['link_to_quiz'] = array(
        'default' => isset($this->definition['link_to_quiz default']) ? $this->definition['link_to_quiz default'] : FALSE,
        'bool'    => TRUE
    );
    return $options;
  }

  /**
   * Provide link to quiz option
   */
  function options_form(&$form, &$form_state) {
    $form['link_to_quiz'] = array(
        '#title'         => t('Link this field to the original piece of content'),
        '#description'   => t("Enable to override this field's links."),
        '#type'          => 'checkbox',
        '#default_value' => !empty($this->options['link_to_quiz']),
    );

    parent::options_form($form, $form_state);
  }

  /**
   * Render whatever the data is as a link to the quiz.
   *
   * Data should be made XSS safe prior to calling this function.
   */
  function render_link($data, $values) {
    if (!empty($this->options['link_to_quiz']) && !empty($this->additional_fields['qid'])) {
      $this->options['alter']['make_link'] = FALSE;
      if ($data !== NULL && $data !== '') {
        $this->options['alter']['make_link'] = TRUE;
        $this->options['alter']['path'] = "quiz/" . $this->get_value($values, 'qid');
      }
    }
    return $data;
  }

  function render($values) {
    $value = $this->get_value($values);
    return $this->render_link($this->sanitize_value($value), $values);
  }

}
