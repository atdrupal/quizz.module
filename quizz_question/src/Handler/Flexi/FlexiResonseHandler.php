<?php

namespace Drupal\quizz_question\Handler\Flexi;

use Drupal\quizz_question\ResponseHandler;

class FlexiResonseHandler extends ResponseHandler {

  public function score() {

  }

  public function isFeedbackable() {
    return $this->question->getQuestionType()->getConfig('allow_feedback', 0);
  }

  public function isManualScoring() {
    return $this->question->getQuestionType()->getConfig('manual_scoring', 0);
  }

}
