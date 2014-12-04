<?php

namespace Drupal\quiz_question\Entity;

use Drupal\quiz_question\QuestionHandler;
use Entity;
use RuntimeException;
use Drupal\quizz\Entity\Result;
use stdClass;

class Question extends Entity {

  /** @var int */
  public $qid;

  /** @var int */
  public $vid;

  /** @var string */
  public $type;

  /** @var QuestionHandler */
  private $handler;

  /** @var string */
  public $language = LANGUAGE_NONE;

  /** @var bool */
  public $status = 1;

  /** @var string */
  public $title;

  /** @var int */
  public $created;

  /** @var int */
  public $changed;

  /** @var int */
  public $uid;

  /** @var int */
  public $revision_uid;

  /** @var int */
  public $log;

  /** @var int */
  public $max_score;

  /** @var string */
  public $feedback;

  /** @var string */
  public $feedback_format;

  /** @var bool Magic flag to create new revision on save */
  public $is_new_revision;

  /**
   * @return QuestionController
   */
  public function getController() {
    return quiz_question_controller();
  }

  /**
   * Get question type object.
   *
   * @return QuestionType
   */
  public function getQuestionType() {
    return quiz_question_type_load($this->type);
  }

  /**
   * @return QuestionHandler
   */
  public function getHandler() {
    if (NULL === $this->handler) {
      $this->handler = $this->doGetHandler();
    }
    return $this->handler;
  }

  /**
   * Get handler info.
   * @return array
   * @throws RuntimeException
   */
  public function getHandlerInfo() {
    if ($question_type = $this->getQuestionType()) {
      return quiz_question_get_handler_info($question_type->handler);
    }
    throw new RuntimeException('Question handler not found for question #' . $this->qid . ' (type: ' . $this->type . ')');
  }

  /**
   * @return QuestionHandler
   */
  private function doGetHandler() {
    $handler_info = $this->getHandlerInfo();
    return new $handler_info['question provider']($this);
  }

  /**
   * Override parent defaultUri method.
   * @return array
   */
  protected function defaultUri() {
    return array('path' => 'quiz-question/' . $this->identifier());
  }

  /**
   * {#inheritedoc}
   *
   * Update created/updated/uid when needed.
   *
   * @global stdClass $user
   */
  public function save() {
    global $user;

    $this->changed = time();
    if ($this->is_new = isset($this->is_new) ? $this->is_new : 0) {
      $this->created = time();
      if (null === $this->uid) {
        $this->uid = $user->uid;
      }
    }

    return parent::save();
  }

  /**
   * Get module of question handler.
   * @return string
   */
  public function getModule() {
    return $this->getQuestionType()->getHandlerModule();
  }

  /**
   * @TODO The method name maybe wrong, I (Andy) not really know what doest this
   * function do. Moved to here from quiz_question_report_form() function.
   */
  public function findLegacyMaxScore(Result $result) {
    // If need to specify the score weight if it isn't already specified.
    if (isset($this->score_weight)) {
      return;
    }

    if ($relationship = $this->getController()->findRelationship($result->getQuiz(), $this)) {
      $max_score = $relationship->max_score;
    }

    if (!isset($max_score)) {
      $max_score = db_query('SELECT qt.max_score
        FROM {quiz_results} result
         JOIN {quiz_results_answers} answer ON (result.result_id = answer.result_id)
         JOIN {quiz_terms} qt ON (qt.vid = result.quiz_vid AND qt.tid = answer.tid)
         WHERE result.result_id = :rid
          AND answer.question_qid = :question_id
          AND answer.question_vid = :question_vid', array(
          ':rid'          => $result->result_id,
          ':question_id'  => $this->qid,
          ':question_vid' => $this->vid
        ))->fetchField();
    }

    $this->score_weight = 0;
    if (!empty($max_score) && $this->max_score) {
      $this->score_weight = $max_score / $this->max_score;
    }
  }

}
