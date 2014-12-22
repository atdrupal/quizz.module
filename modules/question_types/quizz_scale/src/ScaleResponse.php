<?php

namespace Drupal\quizz_scale;

use Drupal\quizz_question\Entity\Question;
use Drupal\quizz_question\ResponseHandler;
use Drupal\quizz\Entity\Answer;

/**
 * Extension of QuizQuestionResponse
 */
class ScaleResponse extends ResponseHandler {

  /**
   * {@inheritdoc}
   * @var string
   */
  protected $base_table = 'quiz_scale_user_answers';
  protected $answer_id = 0;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);

    if (NULL === $input) {
      if (($answer = $this->loadAnswerEntity()) && ($input = $answer->getInput())) {
        $this->answer_id = $answer->getInput();
      }
    }
    else {
      $this->answer_id = (int) $input;
    }

    $sql = 'SELECT answer FROM {quiz_scale_answer} WHERE id = :id';
    if ($input = db_query($sql, array(':id' => $this->answer_id))->fetchField()) {
      $this->answer = check_plain($input);
    }
  }

  public function onLoad(Answer $answer) {
    $sql = 'SELECT answer_id FROM {quiz_scale_user_answers} WHERE result_id = :rid AND question_vid = :vid';
    if ($input = db_query($sql, array(':rid' => $answer->result_id, ':vid' => $answer->question_vid))->fetchField()) {
      $answer->setInput($input);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    db_insert('quiz_scale_user_answers')
      ->fields(array(
          'answer_id'    => $this->answer_id,
          'result_id'    => $this->result_id,
          'question_vid' => $this->question->vid,
          'question_qid' => $this->question->qid,
      ))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function score() {
    return $this->isValid() ? 1 : 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return $this->answer_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues() {
    return array(array('choice' => $this->answer));
  }

}
