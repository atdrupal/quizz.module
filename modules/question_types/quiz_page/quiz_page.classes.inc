<?php

use Drupal\quiz_question\QuestionHandler;
use Drupal\quiz_question\ResponseHandler;

/**
 * @file quiz_page.classes.inc
 * Support class for Quiz pages.
 */

/**
 * Extension of QuizQuestion.
 */
class QuizPagesQuestion extends QuestionHandler {

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form) {

  }

  /**
   * {@inheritdoc}
   */
  public function saveEntityProperties($is_new = FALSE) {

  }

  /**
   * {@inheritdoc}
   */
  public function getBodyFieldTitle() {
    return t('Page');
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationForm(array &$form_state = NULL) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore() {
    return 0;
  }

  public function isGraded() {
    return FALSE;
  }

  function getAnsweringForm(array $form_state = NULL, $result_id) {
    $element = array(
        '#type' => 'hidden',
    );
    return $element;
  }

  public function hasFeedback() {
    return FALSE;
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class QuizPagesResponse extends ResponseHandler {

  /**
   * {@inheritdoc}
   */
  public function save() {

  }

  /**
   * {@inheritdoc}
   */
  public function delete() {

  }

  /**
   * {@inheritdoc}
   */
  public function score() {

  }

  /**
   * {@inheritdoc}
   */
  public function isCorrect() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportForm() {
    return array('#no_report' => TRUE);
  }

}
