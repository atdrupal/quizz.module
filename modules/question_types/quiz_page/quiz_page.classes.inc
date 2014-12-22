<?php

use Drupal\quizz_question\QuestionHandler;
use Drupal\quizz_question\ResponseHandler;

/**
 * @file quiz_page.classes.inc
 * Support class for Quiz pages.
 */

/**
 * Extension of QuizQuestion.
 */
class QuizPagesQuestion extends QuestionHandler {

  protected $body_field_title = 'Page';

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form) {

  }

  /**
   * {@inheritdoc}
   */
  public function onSave($is_new = FALSE) {

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
