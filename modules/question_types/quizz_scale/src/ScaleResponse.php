<?php

namespace Drupal\quizz_scale;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;

/**
 * Extension of QuizQuestionResponse
 */
class ScaleResponse extends ResponseHandler {

  /**
   * ID of the answer.
   */
  protected $answer_id = 0;

  /**
   * Constructor
   */
  public function __construct($result_id, Question $question, $answer = NULL) {
    parent::__construct($result_id, $question, $answer);

    if (isset($answer)) {
      $this->answer_id = intval($answer);
    }
    else {
      $this->answer_id = db_query('SELECT answer_id FROM {quiz_scale_user_answers} WHERE result_id = :rid AND question_qid = :qqid AND question_vid = :qvid', array(':rid' => $result_id, ':qqid' => $this->question->qid, ':qvid' => $this->question->vid))->fetchField();
    }
    $answer = db_query(
      'SELECT answer FROM {quiz_scale_answer} WHERE id = :id', array(
        ':id' => $this->answer_id
      ))->fetchField();
    $this->answer = check_plain($answer);
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    $id = db_insert('quiz_scale_user_answers')
      ->fields(array(
          'answer_id'    => $this->answer_id,
          'result_id'    => $this->result_id,
          'question_vid' => $this->question->vid,
          'question_qid' => $this->question->qid,
      ))
      ->execute();
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestionResponse#delete()
   */
  public function delete() {
    db_delete('quiz_scale_user_answers')
      ->condition('result_id', $this->result_id)
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->execute();
  }

  /**
   * Implementation of score
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return $this->isValid() ? 1 : 0;
  }

  /**
   * Implementation of getResponse
   *
   * @see QuizQuestionResponse#getResponse()
   */
  public function getResponse() {
    return $this->answer_id;
  }

  /**
   * Implmenets QuizQuestionResponse::getReportFormResponse().
   */
  public function getReportFormResponse() {
    $data = array();
    $data[] = array(
        'choice' => $this->answer,
    );
    return $data;
  }

}
