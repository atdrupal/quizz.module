<?php

namespace Drupal\quiz_question;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionHandler;
use Drupal\quizz\Entity\Result;

abstract class ResponseHandlerBase implements ResponseHandlerInterface {

  /** @var Result */
  protected $result;
  protected $result_id = 0;
  protected $is_correct = FALSE;
  protected $evaluated = TRUE;

  /** @var Question */
  public $question = NULL;

  /** @var QuestionHandler */
  public $question_handler = NULL;
  protected $answer = NULL;
  protected $score;
  public $is_skipped;
  public $is_doubtful;

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Set the target result ID for this Question response.
   * Useful for cloning entire result sets.
   *
   * @param int $result_id
   */
  public function setResultId($result_id) {
    $this->result_id = $result_id;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvaluated() {
    return (bool) $this->evaluated;
  }

  /**
   * @return QuestionHandler
   */
  public function getQuizQuestion() {
    return $this->question_handler;
  }

  /**
   * Used to refresh this instances question in case drupal has changed it.
   * @param Question $newQuestion
   */
  public function refreshQuestionEntity($newQuestion) {
    $this->question = $newQuestion;
  }

  /**
   * Get the submit function for the reportForm
   *
   * @return
   *  Submit function as a string, or FALSE if no submit function
   */
  public function getReportFormSubmit() {
    return FALSE;
  }

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

}
