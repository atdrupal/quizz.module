<?php

namespace Drupal\quiz_question;

use Drupal\quiz_question\Entity\QuestionType;
use Drupal\quizz\Entity\Result;

interface QuestionHandlerInterface {

  /**
   * Get the maximum possible score for this question.
   * @return int
   */
  public function getMaximumScore();

  /**
   * Get the form used to create a new question.
   * @param array $form_state
   * @return array Form structure
   */
  public function getCreationForm(array &$form_state = NULL);

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
   * Save question type specific node properties
   * @return bool
   */
  public function saveEntityProperties($is_new = FALSE);

  /**
   * To be called when new question type created.
   */
  public function onNewQuestionTypeCreated(QuestionType $question_type);

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
   * Delete question data from the database. Called by question's controller.
   * @param bool $delete_revision
   */
  public function delete($delete_revision);

  /**
   * Getter function returning properties to be loaded when question is loaded.
   * Called by question's controler.
   * @return array
   */
  public function load();

  /**
   * Retrieve information relevant for viewing question. Called by question's
   * controller ::buildContent().
   * @return array
   */
  public function view();
}
