<?php

use Drupal\quizz\Entity\QuizEntity;

/**
 * @file
 * Hooks provided by Quiz module.
 *
 * These entity types provided by Quiz also have entity API hooks.
 *
 * quiz (settings for quiz entities)
 * quiz_result (quiz attempt/result)
 * quiz_result_answer (answer to a specific question in a quiz result)
 * quiz_relationship (relationship from quiz to question)
 *
 * So for example
 *
 * hook_quiz_result_presave(&$course_report)
 *   - Runs before a result is saved to the DB.
 * hook_quiz_relationship_insert($course_object_fulfillment)
 *  - Runs when a new question is added to a quiz.
 *
 * Enjoy :)
 */

/**
 * Implements hook_quiz_question_info().
 */
function hook_quiz_question_info() {
  return array(
      'long_answer' => array(
          'name'              => t('Example question type'),
          'description'       => t('An example question type that does something.'),
          'question provider' => 'ExampleAnswerQuestion',
          'response provider' => 'ExampleAnswerResponse',
          'module'            => 'quiz_question',
      ),
  );
}

/**
 * Implements hook_quiz_question_info_alter().
 */
function hook_quiz_question_info_alter(&$info) {
  // …
}

/**
 * Implements hook_quiz_begin().
 *
 * Fired when a new quiz result is created.
 */
function hook_quiz_begin(QuizEntity $quiz, $result_id) {

}

/**
 * Implements hook_quiz_finished().
 *
 * Fired after the last question is submitted.
 */
function hook_quiz_finished(QuizEntity $quiz, $score, $data) {

}

/**
 * Implements hook_quiz_scored().
 *
 * Fired when a quiz is evaluated.
 */
function hook_quiz_scored(QuizEntity $quiz, $score, $result_id) {

}

/**
 * @see \Drupal\quiz_question\ResponseHandler::getReportForm()
 * @param array $labels Associated string[]
 */
function hook_quiz_feedback_labels_alter(&$labels) {

}
