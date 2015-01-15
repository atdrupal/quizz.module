<?php

namespace Drupal\quizz\Form;

use Drupal\quizz\Entity\Result;
use Drupal\quizz_question\Entity\Question;

class QuizReportForm {

  /**
   * Form for showing feedback, and for editing the feedback if necessary…
   *
   * @param array $form
   * @param array $form_state
   * @param Result $result
   * @param Question[] $questions
   * @return array
   */
  public function getForm($form, $form_state, Result $result, $questions) {
    $form['#tree'] = TRUE;
    $show_submit = FALSE;

    foreach ($questions as $question) {
      $form_to_add = $question->getHandler()->getReportForm($result, $question);
      if (empty($form_to_add['#no_report'])) {
        $form_to_add['#element_validate'][] = 'quizz_report_form_element_validate';

        if ($question->getResponseHandler($result->result_id)->isManualScoring()) {
          $show_submit = $show_submit || !empty($form_to_add['score']);
        }

        $form[] = $form_to_add;
      }
    }

    // The submit button is only shown if one or more of the questions has input elements
    if (!empty($show_submit)) {
      $form['submit'] = array('#type' => 'submit', '#value' => t('Save score'));
    }

    if (arg(4) === 'feedback') {
      $quiz = $result->getQuiz();
      if (empty($_SESSION['quiz'][$quiz->qid])) { // Quiz is done.
        $form['finish'] = array('#type' => 'submit', '#value' => t('Finish'));
      }
      else {
        $form['next'] = array('#type' => 'submit', '#value' => t('Next question'));
      }
    }

    return $form;
  }

  /**
   * Submit handler to go to the next question from the question feedback.
   */
  public function formSubmitFeedback($form, &$form_state) {
    $quiz_id = quizz_get_id_from_url();
    $form_state['redirect'] = "quiz/{$quiz_id}/take/" . $_SESSION['quiz'][$quiz_id]['current'];
  }

  /**
   * Validate a single question sub-form.
   */
  public static function validateElement(&$element, &$form_state) {
    $question = $element['question_entity']['#value'];
    $result = $element['result']['#value'];
    if ($handler = $question->getResponseHandler($result->result_id)) {
      $handler->validateReportForm($element, $form_state);
    }
  }

  /**
   * Submit the report form
   *
   * We go through the form state values and submit all questiontypes with
   * validation functions declared.
   */
  public function formSubmit($form, &$form_state) {
    global $user;

    /* @var $result Result  */
    $result = isset($form_state['values'][0]['result']) ? $form_state['values'][0]['result'] : NULL;
    $quiz = $result->getQuiz();


    foreach ($form_state['values'] as $key => $question_values) {
      if (is_numeric($key)) {
        /* @var $question Question */
        $question = $form_state['values'][0]['question_entity'];

        // We call the submit function provided by the question
        $question
          ->getResponseHandler($result->result_id)
          ->submitReportForm($question_values)
        ;
      }
    }

    // Scores may have been changed. We take the necessary actions
    $this->updateLastTotalScore($result);
    $changed = db_update('quiz_results')
      ->fields(array('is_evaluated' => 1))
      ->condition('result_id', $result->result_id)
      ->execute();
    $results_got_deleted = $result->maintenance($user->uid);

    // A message saying the quiz is unscored has already been set. We unset it here…
    if ($changed > 0) {
      $this->removeUnscoredMessage();
    }

    // Notify the user if results got deleted as a result of him scoring an answer.
    $add = $quiz->keep_results == QUIZZ_KEEP_BEST && $results_got_deleted ? ' ' . t('Note that this @quiz is set to only keep each users best answer.', array('@quiz' => QUIZZ_NAME)) : '';
    $score_data = $this->getScoreArray($result, TRUE);

    module_invoke_all('quiz_scored', $quiz, $score_data, $result->result_id);

    drupal_set_message(t('The scoring data you provided has been saved.') . $add);
    if (user_access('score taken quiz answer') && !user_access('view any quiz results')) {
      if ($result && $result->uid == $user->uid) {
        $form_state['redirect'] = $result->getUrl();
      }
    }
  }

  /**
   * Submit handler to go to the quiz results from the last question's feedback.
   */
  public function formEndSubmit($form, &$form_state) {
    $result_id = $_SESSION['quiz']['temp']['result_id'];
    $form_state['redirect'] = "quiz-result/{$result_id}";
  }

  /**
   * Helper function to remove the message saying the quiz haven't been scored
   */
  private function removeUnscoredMessage() {
    if (!empty($_SESSION['messages']['warning'])) {
      // Search for the message, and remove it if we find it.
      foreach ($_SESSION['messages']['warning'] as $key => $val) {
        if ($val == t('This @quiz has not been scored yet.', array('@quiz' => QUIZZ_NAME))) {
          unset($_SESSION['messages']['warning'][$key]);
        }
      }

      // Clean up if the message array was left empty
      if (empty($_SESSION['messages']['warning'])) {
        unset($_SESSION['messages']['warning']);
        if (empty($_SESSION['messages'])) {
          unset($_SESSION['messages']);
        }
      }
    }
  }

  /**
   * Returns an array of score information for a quiz
   *
   * @param Result $result
   * @param int $is_evaluated
   * @return array
   */
  private function getScoreArray(Result $result, $is_evaluated) {
    $quiz = $result->getQuiz();

    $question_count = $quiz->number_of_random_questions;
    $question_count += quizz_entity_controller()->getStats()->countAlwaysQuestions($quiz->vid);

    $sql = 'SELECT SUM(points_awarded) FROM {quiz_answer_entity} WHERE result_id = :result_id';
    $total_score = db_query($sql, array(':result_id' => $result->result_id))->fetchField();

    return array(
        'question_count'   => $question_count,
        'possible_score'   => $quiz->max_score,
        'numeric_score'    => $total_score,
        'percentage_score' => ($quiz->max_score == 0) ? 0 : round(($total_score * 100) / $quiz->max_score),
        'is_evaluated'     => $is_evaluated,
    );
  }

  /**
   * Update total score using.
   */
  private function updateLastTotalScore(Result $result) {
    $select = db_select('quiz_answer_entity', 'a');
    $select
      ->condition('a.result_id', $result->result_id)
      ->addExpression('SUM(a.points_awarded)');

    $score = $select->execute()->fetchColumn();
    $max_score = $result->getQuiz()->max_score;

    $result->score = round(100 * ($score / $max_score));
    $result->save();
  }

}
