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
   * @param mixed $input
   * @return \Drupal\quiz_question\ResponseHandlerInterface
   */
  public function getHandler($result_id, Question $question = NULL, $input = NULL) {
    $handlers = &drupal_static(__METHOD__, array());

    // We refresh the question in case it has been changed since we cached the response
    if ((NULL !== $question) && isset($handlers[$result_id][$question->vid])) {
      $handlers[$result_id][$question->vid]->refreshQuestionEntity($question);
      if (FALSE !== $handlers[$result_id][$question->vid]->is_skipped) {
        return $handlers[$result_id][$question->vid];
      }
    }

    if (isset($handlers[$result_id][$question->vid]) && $handlers[$result_id][$question->vid]->is_skipped !== FALSE) {
      return $handlers[$result_id][$question->vid];
    }

    return $handlers[$result_id][$question->vid] = $question->getResponseHandler($result_id, $input);
  }

}
