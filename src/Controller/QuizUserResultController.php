<?php

namespace Drupal\quizz\Controller;

/**
 * Callback for:
 *
 *  - quiz-result/%
 *  - user/%/quiz-results/%quiz_result/view
 *
 * Show result page for a given result
 */
class QuizUserResultController extends QuizResultBaseController {

  /**
   * Render user's result.
   *
   * Check issue #2362097
   */
  public function render() {
    $this->setBreadcrumb();

    $data = array(
        'quiz'      => $this->quiz_revision,
        'questions' => $this->getAnswers(),
        'score'     => $this->score,
        'summary'   => $this->getSummaryText(),
        'result_id' => $this->result->result_id,
        'account'   => user_load($this->result->uid),
    );

    // User can view own quiz results OR the current quiz has "display solution".
    if (user_access('view own quiz results')) {
      return theme('quiz_result', $data);
    }

    // the current quiz has "display solution".
    if (!empty($this->quiz->review_options['end']) && array_filter($this->quiz->review_options['end'])) {
      return theme('quiz_result', $data);
    }

    // User cannot view own results or show solution. Show summary.
    return theme('quiz_result', $data);
  }

  private function setBreadcrumb() {
    $bc = drupal_get_breadcrumb();
    $bc[] = l($this->quiz->title, 'quiz/' . $this->quiz_id);
    drupal_set_breadcrumb($bc);
  }

}
