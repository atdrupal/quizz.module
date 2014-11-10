<?php

namespace Drupal\quiz\Entity\Result;

use Drupal\quiz\Entity\QuizEntity;
use Drupal\quiz\Entity\Result;
use stdClass;

class ScoreIO {

  /**
   * Calculates the score user received on quiz.
   *
   * @param $quiz
   *   The quiz entity.
   * @param $result_id
   *   Quiz result ID.
   *
   * @return array
   *   Contains three elements: question_count, num_correct and percentage_score.
   */
  public function calculate(QuizEntity $quiz, $result_id) {
    // 1. Fetch all questions and their max scores
    $questions = db_query('SELECT a.question_nid, a.question_vid, n.type, r.max_score
      FROM {quiz_results_answers} a
      LEFT JOIN {node} n ON (a.question_nid = n.nid)
      LEFT OUTER JOIN {quiz_relationship} r ON (r.question_vid = a.question_vid) AND r.quiz_vid = :vid
      WHERE result_id = :rid', array(':vid' => $quiz->vid, ':rid' => $result_id));

    // 2. Callback into the modules and let them do the scoring. @todo after 4.0: Why isn't the scores already saved? They should be
    // Fetched from the db, not calculated…
    $scores = array();
    $count = 0;
    foreach ($questions as $question) {
      // Questions picked from term id's won't be found in the quiz_relationship table
      if ($question->max_score === NULL && isset($quiz->tid) && $quiz->tid > 0) {
        $question->max_score = $quiz->max_score_for_random;
      }

      // Invoke hook_quiz_question_score().
      // We don't use module_invoke() because (1) we don't necessarily want to wed
      // quiz type to module, and (2) this is more efficient (no NULL checks).
      $mod = quiz_question_module_for_type($question->type);
      if (!$mod) {
        continue;
      }

      $function = $mod . '_quiz_question_score';
      if (function_exists($function)) {
        // Allow for max score to be considered.
        $scores[] = $function($quiz, $question->question_nid, $question->question_vid, $result_id);
      }
      else {
        drupal_set_message(t('A @quiz question could not be scored: No scoring info is available', array('@quiz' => QUIZ_NAME)), 'error');
        $dummy_score = new stdClass();
        $dummy_score->possible = 0;
        $dummy_score->attained = 0;
        $scores[] = $dummy_score;
      }
      ++$count;
    }

    // 3. Sum the results.
    $possible_score = 0;
    $total_score = 0;
    $is_evaluated = TRUE;
    foreach ($scores as $score) {
      $possible_score += $score->possible;
      $total_score += $score->attained;
      if (isset($score->is_evaluated)) {
        // Flag the entire quiz if one question has not been evaluated.
        $is_evaluated &= $score->is_evaluated;
      }
    }

    // 4. Return the score.
    return array(
        'question_count'   => $count,
        'possible_score'   => $possible_score,
        'numeric_score'    => $total_score,
        'percentage_score' => ($possible_score == 0) ? 0 : round(($total_score * 100) / $possible_score),
        'is_evaluated'     => $is_evaluated,
    );
  }

  /**
   * Update a score for a quiz.
   *
   * This updates the quiz entity results table.
   *
   * It is used in cases where a quiz score is changed after the quiz has been
   * taken. For example, if a long answer question is scored later by a human,
   * then the quiz should be updated when that answer is scored.
   *
   * Important: The value stored in the table is the *percentage* score.
   *
   * @param Result $result
   *
   * @return
   *   The score as an integer representing percentage. E.g. 55 is 55%.
   */
  public function updateTotalScore(Result $result) {
    global $user;

    $quiz = $result->getQuiz();
    $score = $this->calculate($quiz, $result->result_id);

    db_update('quiz_results')
      ->fields(array('score' => $score['percentage_score']))
      ->condition('result_id', $result->result_id)
      ->execute();

    if ($score['is_evaluated']) {
      module_invoke_all('quiz_scored', $quiz, $score, $result->result_id);
      quiz()->getQuizHelper()->getResultHelper()->maintainResult($user, $quiz, $result->result_id);

      db_update('quiz_results')
        ->fields(array('is_evaluated' => 1))
        ->condition('result_id', $result->result_id)
        ->execute();
    }

    return $score['percentage_score'];
  }

}