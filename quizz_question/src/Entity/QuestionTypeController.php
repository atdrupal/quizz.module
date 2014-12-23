<?php

namespace Drupal\quizz_question\Entity;

use DatabaseTransaction;
use Drupal\quizz_question\Entity\QuestionType;
use EntityAPIControllerExportable;

class QuestionTypeController extends EntityAPIControllerExportable {

  /**
   * {@inheritdoc}
   * @param QuestionType $question_type
   * @param DatabaseTransaction $transaction
   */
  public function save($question_type, DatabaseTransaction $transaction = NULL) {
    $return = parent::save($question_type, $transaction);

    if (!QuestionController::$disable_invoking) {
      $question_type
        ->getHandler()
        ->onNewQuestionTypeCreated($question_type)
      ;
    }

    return $return;
  }

}
