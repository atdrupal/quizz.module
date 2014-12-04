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
   * Returns stored max score if it exists, if not the max score is calculated and returned.
   *
   * @param bool $weight_adjusted
   *  If the returned max score shall be adjusted according to the max_score the question has in a quiz
   * @return int
   */
  public function getQuestionMaxScore($weight_adjusted = TRUE);

  /**
   * Get the user's response.
   * @return mixed
   */
  public function getResponse();

  /**
   * @return array
   */
  public function getReportFormScore();

  /**
   * Get the submit function for the reportForm.
   * @return string
   *  Submit function as a string, empty string if no submit function
   */
  public function getReportFormSubmit();
}
