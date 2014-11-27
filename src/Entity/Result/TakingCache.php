<?php

namespace Drupal\quizz\Entity\Result;

class TakingCache {

  public $resultId;
  public $previousQuestions;
  public $currentPageNumber;

  public function getResult() {
    return quiz_result_load($this->resultId);
  }

  public function getQuiz() {
    if ($result = $this->getResult()) {
      return $result->getQuiz();
    }
  }

}
