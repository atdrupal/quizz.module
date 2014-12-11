<?php

namespace Drupal\quiz_question;

use Drupal\quiz_question\Entity\Question;
use Drupal\quizz\Entity\Answer;

/**
 * Each question type must store its own response data and be able to calculate a score for
 * that data.
 */
abstract class ResponseHandler extends ResponseHandlerBase {

  /** @var bool */
  protected $allow_feedback = FALSE;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);

    $conds = array(
        'result_id'    => $this->result_id,
        'question_qid' => $this->question->qid,
        'question_vid' => $this->question->vid
    );

    if ($find = entity_load('quiz_result_answer', FALSE, $conds)) {
      /* @var $answer Answer */
      $answer = reset($find);
      $this->is_doubtful = $answer->is_doubtful;
      $this->is_skipped = $answer->is_skipped;
    }
  }

  /**
   * {@inheritdoc}
   * This default version returns TRUE if the score is equal to the maximum
   * possible score.
   */
  public function isCorrect() {
    return $this->getQuestionMaxScore() == $this->getScore();
  }

  /**
   * Returns stored score if it exists, if not the score is calculated and returned.
   *
   * @param $weight_adjusted
   *  If the returned score shall be adjusted according to the max_score the question has in a quiz
   * @return
   *  Score(int)
   */
  function getScore($weight_adjusted = TRUE) {
    if ($this->is_skipped) {
      return 0;
    }

    if (!isset($this->score)) {
      $this->score = $this->score();
    }

    if (isset($this->question->score_weight) && $weight_adjusted) {
      return round($this->score * $this->question->score_weight);
    }

    return $this->score;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestionMaxScore($weight_adjusted = TRUE) {
    if (!isset($this->question->max_score)) {
      $this->question->max_score = $this->question->getHandler()->getMaximumScore();
    }
    if ($weight_adjusted && isset($this->question->score_weight)) {
      return round($this->question->max_score * $this->question->score_weight);
    }
    return $this->question->max_score;
  }

  /**
   * Represent the response as a stdClass object.
   *
   * Convert data to an object that has the following properties:
   *  score, result_id, question_qid, question_vid, is_correct, …
   */
  public function toBareObject() {
    return (object) array(
          'score'        => $this->getScore(),
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
          'is_correct'   => (int) $this->isCorrect(),
          'is_evaluated' => $this->isEvaluated(),
          'is_skipped'   => isset($this->is_skipped) ? (int) $this->is_skipped : 0,
          'is_doubtful'  => isset($this->is_doubtful) ? (int) $this->is_doubtful : 0,
          'is_valid'     => $this->isValid(),
    );
  }

  /**
   * Get data suitable for reporting a user's score on the question.
   * This expects an object with the following attributes:
   *
   *  answer_id; // The answer ID
   *  answer; // The full text of the answer
   *  is_evaluated; // 0 if the question has not been evaluated, 1 if it has
   *  score; // The score the evaluator gave the user; this should be 0 if is_evaluated is 0.
   *  question_vid
   *  question_qid
   *  result_id
   */
  public function getReport() {
    // Basically, we encode internal information in a legacy array format for Quiz.
    return array(
        'answer_id'    => 0, // <-- Stupid vestige of multichoice.
        'answer'       => $this->answer,
        'is_evaluated' => $this->isEvaluated(),
        'is_correct'   => $this->isCorrect(),
        'score'        => $this->getScore(),
        'question_vid' => $this->question->vid,
        'question_qid' => $this->question->qid,
        'result_id'    => $this->result_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReportForm(array $form = array()) {
    global $user;

    // Add general data, and data from the question type implementation
    $form['qid'] = array('#type' => 'value', '#value' => $this->question->qid);
    $form['vid'] = array('#type' => 'value', '#value' => $this->question->vid);
    $form['result_id'] = array('#type' => 'value', '#value' => $this->result_id);
    $form['max_score'] = array('#type' => 'value', '#value' => $this->canReview('score') ? $this->getQuestionMaxScore() : '?');

    if ($this->result->canAccessOwnScore($user)) {
      if ($submit = $this->getReportFormSubmit()) {
        $form['submit'] = array('#type' => 'value', '#value' => $submit);
      }

      if ($answer_feedback = $this->getReportFormAnswerFeedback()) {
        $form['answer_feedback'] = $answer_feedback;
      }
    }

    if (quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user) && $submit) {
      $form['score'] = $this->getReportFormScore();
    }

    foreach ($this->getFeedback() as $type => $render) {
      $form[$type] = $render;
    }

    return $form;
  }

  protected function getFeedback() {
    global $user;

    $output = array();

    $entity_info = entity_get_info('quiz_question');
    foreach (array_keys($entity_info['view modes']) as $view_mode) {
      if ($this->canReview("quiz_question_view_{$view_mode}")) {
        $question_view = entity_view('quiz_question', array($this->question), $view_mode, NULL, TRUE);
        $output['question'][$view_mode] = $question_view['quiz_question'][$this->question->qid];
      }
    }

    $labels = array(
        'attempt'         => t('Your answer'),
        'choice'          => t('Choice'),
        'correct'         => t('Correct?'),
        'score'           => t('Score'),
        'answer_feedback' => t('Feedback'),
        'solution'        => t('Correct answer'),
    );

    drupal_alter('quiz_feedback_labels', $labels);

    $rows = array();
    foreach ($this->getFeedbackValues() as $idx => $row) {
      foreach (array_keys($labels) as $review_type) {
        if (('choice' === $review_type) || (isset($row[$review_type]) && $this->canReview($review_type))) {
          $rows[$idx][$review_type] = $row[$review_type];
        }
      }
    }

    $score = t('?');
    $class = 'q-waiting';
    if ($this->isEvaluated()) {
      $score = $this->getScore();
      $class = $this->isCorrect() ? 'q-correct' : 'q-wrong';
    }

    if ($this->canReview('score') || quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user)) {
      $output['score_display']['#markup'] = theme('quiz_question_score', array(
          'score'     => $score,
          'max_score' => $this->getQuestionMaxScore(),
          'class'     => $class
      ));
    }

    if ($rows) {
      $headers = array_intersect_key($labels, $rows[0]);
      $output['response']['#markup'] = theme('quiz_question_feedback__' . $this->question->type, array(
          'labels' => $headers,
          'data'   => $rows
      ));
    }

    if ($this->canReview('question_feedback')) {
      if (!empty($this->question_handler->question)) {
        $output['question_feedback']['#markup'] = check_markup($this->question_handler->question->feedback, $this->question_handler->question->feedback_format);
      }
    }

    if ($this->canReview('score')) {
      $output['max_score'] = array('#type' => 'value', '#value' => $this->getQuestionMaxScore());
    }

    return $output;
  }

  /**
   * Get the response part of the report form
   * @return array[]
   *  Array of choices
   */
  public function getFeedbackValues() {
    return array(
        array(
            'choice'            => t('True'),
            'attempt'           => t('Did the user choose this?'),
            'correct'           => t('Was their answer correct?'),
            'score'             => t('Points earned for this answer'),
            'answer_feedback'   => t('Feedback specific to the answer'),
            'question_feedback' => t('General question feedback for any answer'),
            'solution'          => t('Is this choice the correct solution?'),
            'quiz_feedback'     => t('Quiz feedback at this time'),
        )
    );
  }

  public function getReportFormAnswerFeedback() {
    $feedback = isset($this->answer_feedback) ? $this->answer_feedback : '';
    $format = isset($this->answer_feedback_format) ? $this->answer_feedback_format : filter_default_format();
    if ($this->allow_feedback) {
      return array(
          '#title'         => t('Enter feedback'),
          '#type'          => 'text_format',
          '#default_value' => filter_xss_admin($feedback),
          '#format'        => $format,
          '#attributes'    => array('class' => array('quiz-report-score')),
      );
    }
  }

  /**
   * Callback method to validate report form.
   */
  public function validateReportForm(&$element, &$form_state) {

  }

  /**
   * Can the quiz taker view the requested review?
   * @param string $op
   * @return bool
   */
  public function canReview($op) {
    $perms = &drupal_static(__METHOD__, array());
    if (!isset($perms[$op])) {
      $perms[$op] = $this->result->canReview($op);
    }

    return $perms[$op];
  }

  public function getReportFormScore() {
    $score = ($this->isEvaluated()) ? $this->getScore() : '';
    return array(
        '#title'            => 'Enter score',
        '#type'             => 'textfield',
        '#default_value'    => $score,
        '#size'             => 3,
        '#maxlength'        => 3,
        '#attributes'       => array('class' => array('quiz-report-score')),
        '#element_validate' => array('element_validate_integer'),
        '#required'         => TRUE,
        '#field_suffix'     => '/ ' . $this->getQuestionMaxScore(),
    );
  }

}
