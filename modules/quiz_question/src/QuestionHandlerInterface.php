<?php

namespace Drupal\quiz_question;

use Drupal\quizz\Entity\Result;

interface QuestionHandlerInterface {

  /**
   * Get the maximum possible score for this question.
   * @return int
   */
  public function getMaximumScore();

  /**
   * Provides validation for question before it is created.
   *
   * When a new question is created and initially submited, this is
   * called to validate that the settings are acceptible.
   *
   * @param array $form
   */
  public function validate(array &$form);

  /**
   * Method is called when user retry.
   * @param Result $result
   * @param array $element
   */
  public function onRepeatUntiCorrect(Result $result, array &$element);

  /**
   * Is this question graded?
   * Questions like Quiz Directions, Quiz Page, and Scale are not.
   * @return bool
   */
  public function isGraded();

  /**
   * Does this question type give feedback?
   * Questions like Quiz Directions and Quiz Pages do not.
   * By default, questions give feedback
   * @return bool
   */
  public function hasFeedback();

  /**
   * @return array Drupal render array.
   */
  public function getEntityView();
  /**
   * Retrieve information relevant for viewing question. Called by question's
   * controller ::buildContent().
   * @return array
   */
  public function view();
}
