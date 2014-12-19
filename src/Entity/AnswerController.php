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

}
