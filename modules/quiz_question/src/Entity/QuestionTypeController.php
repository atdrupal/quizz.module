<?php

namespace Drupal\quiz_question\Entity;

use DatabaseTransaction;
use Drupal\quiz_question\Entity\QuestionType;
use EntityAPIControllerExportable;

class QuestionTypeController extends EntityAPIControllerExportable {

  /**
   * {@inheritdoc}
   * @param QuestionType $question_type
   * @param DatabaseTransaction $transaction
   */
  public function save($question_type, DatabaseTransaction $transaction = NULL) {
    $return = parent::save($question_type, $transaction);

    $question_type
      ->getHandler()
      ->onNewQuestionTypeCreated($question_type)
    ;

    return $return;
  }

}
