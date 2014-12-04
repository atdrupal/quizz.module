<?php

namespace Drupal\question_pool;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;

/**
 * Extension of QuizQuestionResponse
 */
class PoolResponseHandler extends ResponseHandler {

  /**
   * ID of the answers.
   */
  protected $user_answer_ids;
  protected $choice_order;
  protected $need_evaluated;

  /**
   * Constructor
   */
  public function __construct($result_id, Question $question, $answer = NULL) {
    parent::__construct($result_id, $question, $answer);
    if (!isset($answer)) {
      $r = $this->getCorrectAnswer();
      if (!empty($r)) {
        $this->answer = $r->answer;
        $this->score = $r->score;
      }
    }
    else {
      $this->answer = $answer;
    }
  }

  /**
   * Implementation of getCorrectAnswer
   */
  public function getCorrectAnswer() {
    return db_query('SELECT answer, score FROM {quiz_pool_user_answers} WHERE question_vid = :qvid AND result_id = :rid', array(':qvid' => $this->question->vid, ':rid' => $this->result_id))->fetch();
  }

  /**
   * Implementation of isValid
   *
   * @see QuizQuestionResponse#isValid()
   */
  public function isValid() {
    return ($this->answer == 2) ? t('You haven\'t completed the quiz pool') : TRUE;
  }

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   */
  public function isEvaluated() {
    return TRUE;
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    $sess = &$_SESSION['quiz_' . $this->result->getQuiz()->qid];
    $sess_key = "pool_{$this->question->qid}";
    $passed = &$sess[$sess_key]['passed'];
    $delta = $sess[$sess_key]['delta'];
    if ($question = $wrapper->field_question_reference[$delta]->value()) {
      $result = $this->evaluateQuestion($question);

      if ($result->is_valid) {
        if ($result->is_correct) {
          $passed = true;
        }

        $total = $wrapper->field_question_reference->count();
        if ($sess['pool_' . $this->question->qid]['delta'] < $total) {
          $sess['pool_' . $this->question->qid]['delta'] ++;
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
            'is_correct'   => $this->result->is_correct,
            'score'        => (int) $this->result->score,
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
   * @return uint
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return $this->answer ? $this->getMaxScore() : 0;
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
   * Implementation of getResponse
   *
   * @return answer
   * @see QuizQuestionResponse#getResponse()
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Implementation of getReportFormResponse
   *
   * @see getReportFormResponse($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportFormResponse() {
    $result = db_select('quiz_pool_user_answers_questions', 'p')
      ->fields('p', array('question_vid'))
      ->condition('pool_qid', $this->question->qid)
      ->condition('pool_vid', $this->question->vid)
      ->condition('result_id', $this->result_id)
      ->condition('is_correct', 1)
      ->execute()
      ->fetchAllKeyed();

    if (empty($result)) {
      return array('#markup' => t('No question passed.'));
    }

    $question_vid = reset($result);
    $question = quiz_question_entity_load(NULL, $question_vid);
    return quiz_answer_controller()
        ->getHandler($this->result_id, $question)
        ->getReportFormResponse()
    ;
  }

}
