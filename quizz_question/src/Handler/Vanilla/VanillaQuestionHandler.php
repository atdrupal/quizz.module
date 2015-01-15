<?php

namespace Drupal\quizz_question\Handler\Vanilla;

use Drupal\quizz_question\Entity\QuestionType;
use Drupal\quizz_question\QuestionHandler;

class VanillaQuestionHandler extends QuestionHandler {

  /**
   * {@inheritdoc}
   */
  public function questionTypeConfigForm(QuestionType $question_type) {
    $form = array();

    $form['manual_scoring'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Manual scoring'),
        '#description'   => t("Manuall scoring user's answer."),
        '#default_value' => $question_type->getConfig('manual_scoring', 0),
    );

    return $form;
  }

}
