<?php

namespace Drupal\quizz_question\Handler\Flexi;

use Drupal\quizz_question\Entity\QuestionType;
use Drupal\quizz_question\QuestionHandler;

class FlexiQuestionHandler extends QuestionHandler {

  /**
   * {@inheritdoc}
   */
  public function questionTypeConfigForm(QuestionType $question_type) {
    $form = array();

    $form['allow_feedback'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Allow feedback'),
        '#description'   => t("Can give feedback to user answer. If this option is selected, new textarea field (quizz_answer_feedback) will be created to store the answer's feedbacks."),
        '#default_value' => $question_type->getConfig('allow_feedback', 0),
    );

    $form['manual_scoring'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Manual scoring'),
        '#description'   => t("Manuall scoring user's answer. If this option is selected, new number field (quizz_answer_score) will be created to store the score of answers."),
        '#default_value' => $question_type->getConfig('manual_scoring', 0),
    );

    return $form;
  }

}
