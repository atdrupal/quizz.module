<?php

namespace Drupal\quizz\Entity\QuizEntity;

use Drupal\quizz\Entity\QuizEntity;
use Drupal\quizz\Entity\Result;
use RuntimeException;

/**
 * Generate result entity with dummy page/question layout.
 *
 * This class is used when use start taking a quiz.
 */
class ResultGenerator {

  /**
   * @param QuizEntity $quiz
   * @param Result $result
   * @return Result
   * @throws RuntimeException
   */
  public function generate(QuizEntity $quiz, $account, Result $base_result) {
    if (!$questions = $quiz->getQuestionIO()->getQuestionList()) {
      throw new RuntimeException(t(
        'No questions were found. Please !assign before trying to take this @quiz.', array(
          '@quiz'   => QUIZ_NAME,
          '!assign' => l(t('assign questions'), 'quiz/' . $quiz->identifier() . '/questions')
      )));
    }
    return $this->doGenerate($quiz, $questions, $account, $base_result);
  }

  private function doGenerate(QuizEntity $quiz, $questions, $account, Result $base_result) {
    // correct item numbers
    $count = $display_count = 0;
    $question_list = array();
    foreach ($questions as &$question) {
      $display_count++;
      $question['number'] = ++$count;
      if ($question['type'] !== 'quiz_page') {
        $question['display_number'] = $display_count;
      }
      $question_list[$count] = $question;
    }

    // Write the layout for this result.
    $result = NULL !== $base_result ? $base_result : entity_create('quiz_result', array('type' => $quiz->type));
    $result->quiz_qid = $quiz->identifier();
    $result->quiz_vid = $quiz->vid;
    $result->uid = $account->uid;
    $result->time_start = REQUEST_TIME;
    $result->layout = $question_list;
    $result->save();

    foreach ($question_list as $i => $question) {
      entity_create('quiz_result_answer', array(
          'result_id'    => $result->result_id,
          'question_qid' => $question['qid'],
          'question_vid' => $question['vid'],
          'number'       => $i,
      ))->save();
    }

    return quiz_result_load($result->result_id);
  }

}
