<?php

namespace Drupal\quizz_question\Handler\Vanilla;

use Drupal\quizz_question\ResponseHandler;

class VanillaResonseHandler extends ResponseHandler {

  public function score() {

  }

  public function isFeedbackable() {
    return $this->question->getQuestionType()->getConfig('allow_feedback', 0);
  }

  public function isManualScoring() {
    return $this->question->getQuestionType()->getConfig('manual_scoring', 0);
  }

}
