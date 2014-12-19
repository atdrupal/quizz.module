<?php

namespace Drupal\quizz\Entity;

use DatabaseTransaction;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;
use EntityAPIController;
use RuntimeException;

class AnswerController extends EntityAPIController {

  /**
   * {@inheritdoc}
   * @param Answer[] $queried_entities
   */
  protected function attachLoad(&$queried_entities, $revision_id = FALSE) {
    // Make sure entity has bundle property.
    foreach ($queried_entities as $entity) {
      $entity->bundle();
    }

    return parent::attachLoad($queried_entities, $revision_id);
  }

  public function save($entity, DatabaseTransaction $transaction = NULL) {
    $entity->bundle();
    if (!empty($entity->result_answer_id)) {
      $entity->is_new = FALSE;
    }

    $entity->points_awarded = round($entity->points_awarded);

    return parent::save($entity, $transaction);
  }

  /**
   * Load answer by Result & questions IDs.
   *
   * @param int $result_id
   * @param int $question_vid
   * @return Answer
   */
  public function loadByResultAndQuestion($result_id, $question_vid) {
    $conditions = array('result_id' => $result_id, 'question_vid' => $question_vid);
    if ($return = entity_load('quiz_result_answer', FALSE, $conditions)) {
      return reset($return);
    }
  }

  /**
   * Get an instance of a quiz question responce.
   *
   * Get information about the class and use it to construct a new
   * object of the appropriate type.
   *
   * @param int $result_id
   * @param Question $question
   * @param string $answer
   * @param int $question_qid
   * @param int $question_vid
   * @return \Drupal\quiz_question\ResponseHandlerInterface
   *  The appropriate QuizQuestionResponce extension instance
   */
  public function getHandler($result_id, Question $question = NULL, $answer = NULL, $question_qid = NULL, $question_vid = NULL) {
    $handlers = &drupal_static(__METHOD__, array());

    if (is_object($question) && isset($handlers[$result_id][$question->vid])) {
      // We refresh the question in case it has been changed since we cached the response
      $handlers[$result_id][$question->vid]->refreshQuestionEntity($question);
      if (FALSE !== $handlers[$result_id][$question->vid]->is_skipped) {
        return $handlers[$result_id][$question->vid];
      }
    }

    if (isset($handlers[$result_id][$question_vid]) && $handlers[$result_id][$question_vid]->is_skipped !== FALSE) {
      return $handlers[$result_id][$question_vid];
    }

    // If the question isn't set we fetch it from the QuizQuestion instance
    // this responce belongs to
    if (!$question && ($_question = quiz_question_entity_load(NULL, $question_vid))) {
      $question = $_question->getHandler()->question;
    }

    // Cache the responce instance
    if ($question) {
      $handlers[$result_id][$question->vid] = $this->doGetFindHandler($question, $result_id, $answer);
      return $handlers[$result_id][$question->vid];
    }

    return FALSE;
  }

  private function doGetFindHandler(Question $question, $result_id, $answer) {
    $handler_info = $question->getHandlerInfo();
    $handler = new $handler_info['response provider']($result_id, $question, $answer);
    if (!$handler instanceof ResponseHandler) {
      throw new RuntimeException('The question-response isn\'t a QuizQuestionResponse. It needs to extend the QuizQuestionResponse interface, or extend the abstractQuizQuestionResponse class.');
    }
    return $handler;
  }

}
