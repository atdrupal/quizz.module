<?php

namespace Drupal\quiz_question;

interface ResponseHandlerInterface {

  /**
   * Validates response from a quiz taker. If the response isn't valid the quiz
   * taker won't be allowed to proceed.
   * @return bool
   */
  public function isValid();

  /**
   * Check to see if the answer is marked as correct.
   * @return bool
   */
  public function isCorrect();

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   *
   * @return bool
   */
  public function isEvaluated();

  /**
   * Save the current response.
   * Method is called when user's answer is saved.
   */
  public function save();

  /**
   * Delete the response.
   * Method is called when user's answer is deleted.
   */
  public function delete();

  /**
   * Calculate the score for the response.
   * @return int
   */
  public function score();

  /**
   * Get the user's response.
   * @return mixed
   */
  public function getResponse();

  /**
   * @return array
   */
  public function getReportFormScore();
}
