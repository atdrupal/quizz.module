<?php

namespace Drupal\question_pool;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;

# Retry: unset($_SESSION['quiz_' . $quiz_id]['pool_' . $pool->qid]);

/**
 * Extension of QuizQuestionResponse
 */
class PoolResponseHandler extends ResponseHandler {

  protected $user_answer_ids;
  protected $choice_order;
  protected $need_evaluated;

  public function __construct($result_id, Question $question, $answer = NULL) {
    parent::__construct($result_id, $question, $answer);
    if (isset($answer)) {
      $this->answer = $answer;
    }
    elseif ($correct = $this->getCorrectAnswer()) {
      $this->answer = $correct->answer;
      $this->score = $correct->score;
    }
  }

  /**
   * Implementation of getCorrectAnswer
   */
  public function getCorrectAnswer() {
    return db_query('SELECT answer, score'
        . ' FROM {quiz_pool_user_answers}'
        . ' WHERE question_vid = :qvid AND result_id = :rid', array(
          ':qvid' => $this->question->vid,
          ':rid'  => $this->result_id
      ))->fetch();
  }

  public function isValid() {
    if (2 == $this->answer) { // @TODO Number 2 here is too magic.
      drupal_set_message(t("You haven't completed the quiz pool"), 'warning');
      return FALSE;
    }
    return parent::isValid();
  }

  /**
   * @return \Drupal\quiz_question\Entity\Question
   */
  private function getQuestion() {
    $quiz_id = $this->result->getQuiz()->qid;
    $key = "pool_{$this->question->qid}";

    if (!empty($_SESSION['quiz'][$quiz_id][$key])) {
      $sess = $_SESSION['quiz'][$quiz_id][$key];
      $passed = isset($sess['passed']) ? $sess['passed'] : FALSE;
      $delta = isset($sess['delta']) ? $sess['delta'] : 0;
      return entity_metadata_wrapper('quiz_question', $this->question)
          ->field_question_reference[$passed ? $delta - 1 : $delta]
          ->value();
    }

    $question_vid = db_select('quiz_pool_user_answers_questions', 'p')
      ->fields('p', array('question_vid'))
      ->condition('pool_qid', $this->question->qid)
      ->condition('pool_vid', $this->question->vid)
      ->condition('result_id', $this->result_id)
      ->execute()
      ->fetchColumn();

    if (!empty($question_vid)) {
      return $question = quiz_question_entity_load(NULL, $question_vid);
    }
  }

  /**
   * Implementation of save
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    $sess = &$_SESSION['quiz'][$this->result->getQuiz()->qid]["pool_{$this->question->qid}"];
    $passed = &$sess['passed'];
    $delta = &$sess['delta'];

    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    if ($question = $wrapper->field_question_reference[$delta]->value()) {
      if (($result = $this->evaluateQuestion($question)) && $result->is_valid) {
        $passed = $result->is_correct ? TRUE : $passed;
        if ($delta < $wrapper->field_question_reference->count()) {
          $delta++;
        }
      }
    }

    if (!$passed) {
      $question = $wrapper->field_question_reference[$delta - 1]->value();
    }

    db_insert('quiz_pool_user_answers')
      ->fields(array(
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
          'score'        => (int) $this->getScore(),
          'answer'       => (int) $this->answer,
      ))
      ->execute();
  }

  private function evaluateQuestion(Question $question) {
    $handler = quiz_answer_controller()->getHandler($this->result_id, $question, $this->answer);
    $result = $handler->toBareObject();

    // If a result_id is set, we are taking a quiz.
    if (isset($this->answer)) {
      $keys = array(
          'pool_qid'     => $this->question->qid,
          'pool_vid'     => $this->question->vid,
          'question_qid' => $question->qid,
          'question_vid' => $question->vid,
          'result_id'    => $this->result->result_id,
      );
      db_merge('quiz_pool_user_answers_questions')
        ->key($keys)
        ->fields($keys + array(
            'answer'       => serialize($this->answer),
            'is_evaluated' => (int) $handler->isEvaluated(),
            'is_correct'   => $result->is_correct,
            'score'        => (int) $result->score,
        ))
        ->execute()
      ;
    }

    // fix error with score
    if ($this->result->score < 0) {
      $this->result->score = 0;
    }

    return $this->result;
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestionResponse#delete()
   */
  public function delete() {
    db_delete('quiz_pool_user_answers')
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->condition('result_id', $this->result_id)
      ->execute();

    // Please view quiz_question.module line 277.
    // $response->delete();
    // $response->saveResult();
    // The quiz question delete and resave instead update
    // We have a difference between $respone update and delete.
    // Question update $this->question is entity
    // Question delete $this->question is custom object.
    if (!isset($this->question->created)) {
      db_delete('quiz_pool_user_answers_questions')
        ->condition('pool_qid', $this->question->qid)
        ->condition('pool_vid', $this->question->vid)
        ->condition('result_id', $this->result_id)
        ->execute();
    }
  }

  /**
   * Implementation of score
   *
   * @return int
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return $this->answer ? $this->getQuestionMaxScore() : 0;
  }

  public function isCorrect() {
    return quiz_answer_controller()
        ->getHandler($this->result_id, $this->getQuestion(), $this->answer)
        ->isCorrect();
  }

  /**
   * If all answers in a question is wrong
   *
   * @return boolean
   *  TRUE if all answers are wrong. False otherwise.
   */
  public function isAllWrong() {
    return FALSE;
  }

  /**
   * Implementation of getReportFormResponse
   *
   * @see getReportFormResponse($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportFormResponse() {
    if (!$question = $this->getQuestion()) {
      return array('#markup' => t('No question passed.'));
    }

    return quiz_answer_controller()
        ->getHandler($this->result_id, $question)
        ->getReportFormResponse()
    ;
  }

}
