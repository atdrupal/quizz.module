<?php

namespace Drupal\quiz_question;

use Drupal\quizz\Entity\Result;

interface QuestionHandlerInterface {

  /**
   * Method is called when user retry.
   * @param Result $result
   * @param array $element
   */
  public function onRepeatUntiCorrect(Result $result, array &$element);
}
