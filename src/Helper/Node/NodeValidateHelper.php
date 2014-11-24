<?php

namespace Drupal\quizz\Helper\Node;

class NodeValidateHelper {

  public function execute($quiz) {
    // Don't check dates if the quiz is always available.
    if (!$quiz->quiz_always) {
      if (mktime(0, 0, 0, $quiz->quiz_open['month'], $quiz->quiz_open['day'], $quiz->quiz_open['year']) > mktime(0, 0, 0, $quiz->quiz_close['month'], $quiz->quiz_close['day'], $quiz->quiz_close['year'])) {
        form_set_error('quiz_close', t('"Close date" must be later than the "open date".'));
      }
    }

    if (!empty($quiz->pass_rate)) {
      if (!quiz_valid_integer($quiz->pass_rate, 0, 100)) {
        form_set_error('pass_rate', t('"Passing rate" must be a number between 0 and 100.'));
      }
    }

    if (isset($quiz->time_limit)) {
      if (!quiz_valid_integer($quiz->time_limit, 0)) {
        form_set_error('time_limit', t('"Time limit" must be a positive number.'));
      }
    }

    $this->validateResultOptions($quiz);

    if ($quiz->allow_jumping && !$quiz->allow_skipping) {
      // @todo when we have pages of questions, we have to check that jumping is
      // not enabled, and randomization is not enabled unless there is only 1 page
      form_set_error('allow_skipping', t('If jumping is allowed, skipping must also be allowed.'));
    }
  }

  private function validateResultOptions($node) {
    if (!isset($node->resultoptions) || !count($node->resultoptions)) {
      return;
    }

    $taken_values = array();
    $num_options = 0;
    foreach ($node->resultoptions as $option) {
      $this->validateResultOption($node, $option, $taken_values, $num_options);
    }
  }

  private function validateResultOption($node, $option, &$taken_values, &$num_options) {
    if (empty($option['option_name']) && !$this->isEmptyHTML($option['option_summary']['value'])) {
      form_set_error('option_summary', t('Range has a summary, but no name.'));
      return;
    }

    $num_options++;
    if (empty($option['option_summary'])) {
      form_set_error('option_summary', t('Range has no summary text.'));
    }

    if ($node->pass_rate && (isset($option['option_start']) || isset($option['option_end']))) {
      // Check for a number between 0-100.
      foreach (array('option_start' => 'start', 'option_end' => 'end') as $bound => $bound_text) {
        if (!quiz_valid_integer($option[$bound], 0, 100)) {
          form_set_error($bound, t('The range %start value must be a number between 0 and 100.', array('%start' => $bound_text)));
        }
      }

      // Check that range end >= start.
      if ($option['option_start'] > $option['option_end']) {
        form_set_error('option_start', t('The start must be less than the end of the range.'));
      }

      // Check that range doesn't collide with any other range.
      $option_range = range($option['option_start'], $option['option_end']);
      if ($intersect = array_intersect($taken_values, $option_range)) {
        form_set_error('option_start', t('The ranges must not overlap each other. (%intersect)', array('%intersect' => implode(',', $intersect))));
      }
      else {
        $taken_values = array_merge($taken_values, $option_range);
      }
    }
  }

  /**
   * Helper function used when figuring out if a textfield or textarea is empty.
   *
   * Solves a problem with some wysiwyg editors inserting spaces and tags without content.
   *
   * @param string $html
   * @return bool
   */
  private function isEmptyHTML($html) {
    return drupal_strlen(trim(str_replace('&nbsp;', '', strip_tags($html, '<img><object><embed>')))) == 0;
  }

}
