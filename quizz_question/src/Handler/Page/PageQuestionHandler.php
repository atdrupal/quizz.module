<?php

namespace Drupal\quizz_question\Handler\Page;

use Drupal\quizz_question\QuestionHandler;

class PageQuestionHandler extends QuestionHandler {

  /** @var string */
  protected $body_field_title = 'Page';

  /** @var int */
  public $default_max_score = 0;

  /**
   * {@inheritdoc}
   */
  public function getCreationForm(array &$form_state = NULL) {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function isGraded() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  function getAnsweringForm(array $form_state = NULL, $result_id) {
    return array('#type' => 'hidden');
  }

  /**
   * {@inheritdoc}
   */
  public function hasFeedback() {
    return FALSE;
  }

}
