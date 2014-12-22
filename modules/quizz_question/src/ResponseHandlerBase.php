<?php

namespace Drupal\quizz_question;

use Drupal\quizz_question\Entity\Question;
use Drupal\quizz_question\QuestionHandler;
use Drupal\quizz\Entity\Answer;
use Drupal\quizz\Entity\Result;

abstract class ResponseHandlerBase implements ResponseHandlerInterface {

  /** @var Result */
  protected $result;

  /** @var int */
  protected $result_id = 0;

  /** @var bool */
  protected $is_correct = FALSE;

  /** @var bool */
  protected $evaluated = TRUE;

  /** @var Question */
  public $question = NULL;

  /** @var QuestionHandler */
  public $question_handler = NULL;

  /** @var mixed */
  protected $answer = NULL;

  /** @var int */
  protected $score;

  /** @var bool */
  public $is_skipped;

  /** @var bool */
  public $is_doubtful;

  /** @var string */
  protected $base_table = NULL;

  /** @var Answer */
  private $answer_entity;

  /**
   * @param int $result_id
   * @param Question $question
   * @param mixed $input (dependent on question type).
   */
  public function __construct($result_id, Question $question, $input = NULL) {
    $this->result_id = $result_id;
    $this->result = quiz_result_load($result_id);
    $this->question = $question;
    $this->question_handler = $question->getHandler();
    $this->answer = $input;
    $this->question->setResponseHandler($this);
  }

  /**
   * @return Answer
   */
  public function loadAnswerEntity($refresh = TRUE) {
    if ($refresh || (NULL === $this->answer_entity)) {
      if ($this->answer_entity = quiz_answer_controller()->loadByResultAndQuestion($this->result_id, $this->question->vid)) {
        $this->onLoad($this->answer_entity);
      }
    }
    return $this->answer_entity;
  }

  /**
   * Inject user's answer.
   * @param mixed $input
   */
  public function setAnswerInput($input) {
    $this->answer = $input;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse() {
    return is_string($this->answer) ? trim($this->answer) : $this->answer;
  }

  /**
   * Set the target result ID for this Question response.
   * Useful for cloning entire result sets.
   *
   * @param int $result_id
   */
  public function setResultId($result_id) {
    $this->result_id = $result_id;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvaluated() {
    return (bool) $this->evaluated;
  }

  /**
   * @return QuestionHandler
   */
  public function getQuizQuestion() {
    return $this->question_handler;
  }

  /**
   * Used to refresh this instances question in case drupal has changed it.
   * @param Question $newQuestion
   * @return self
   */
  public function refreshQuestionEntity($newQuestion) {
    $this->question = $newQuestion;
    return $this;
  }

  public function getReportFormSubmit() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function save() {

  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    if (NULL !== $this->base_table) {
      db_delete($this->base_table)
        ->condition('question_qid', $this->question->qid)
        ->condition('question_vid', $this->question->vid)
        ->condition('result_id', $this->result_id)
        ->execute();
    }
  }

  public function onLoad(Answer $answer) {

  }

}
