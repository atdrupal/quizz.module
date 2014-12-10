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
    // Basically, we encode internal information in a
    // legacy array format for Quiz.
    $report = array(
        'answer_id'    => 0, // <-- Stupid vestige of multichoice.
        'answer'       => $this->answer,
        'is_evaluated' => $this->isEvaluated(),
        'is_correct'   => $this->isCorrect(),
        'score'        => $this->getScore(),
        'question_vid' => $this->question->vid,
        'question_qid' => $this->question->qid,
        'result_id'    => $this->result_id,
    );
    return $report;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportForm($form = array()) {
    global $user;

    // Add general data, and data from the question type implementation
    $form['qid'] = array('#type' => 'value', '#value' => $this->question->qid);
    $form['vid'] = array('#type' => 'value', '#value' => $this->question->vid);
    $form['result_id'] = array('#type' => 'value', '#value' => $this->result_id);
    $form['max_score'] = array('#type' => 'value', '#value' => $this->canReview('score') ? $this->getQuestionMaxScore() : '?');
    $form['question'] = $this->getReportFormQuestion();

    if ($this->result->canAccessOwnScore($user)) {
      if ($submit = $this->getReportFormSubmit()) {
        $form['submit'] = array('#type' => 'value', '#value' => $submit);
      }
    }

    if ($this->result->canAccessOwnScore($user)) {
      if ($answer_feedback = $this->getReportFormAnswerFeedback()) {
        $form['answer_feedback'] = $answer_feedback;
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
    foreach ($this->getReportFormResponse() as $idx => $row) {
      foreach (array_keys($labels) as $reviewType) {
        if (('choice' === $reviewType) || (isset($row[$reviewType]) && $this->canReview($reviewType))) {
          $rows[$idx][$reviewType] = $row[$reviewType];
        }
      }
    }

    if (quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user) && $submit) {
      $form['score'] = $this->getReportFormScore();
    }

    $score = t('?');
    $class = 'q-waiting';
    if ($this->isEvaluated()) {
      $score = $this->getScore();
      $class = $this->isCorrect() ? 'q-correct' : 'q-wrong';
    }

    if ($this->canReview('score') || quiz()->getQuizHelper()->getAccessHelper()->canAccessQuizScore($user)) {
      $form['score_display']['#markup'] = theme('quiz_question_score', array(
          'score'     => $score,
          'max_score' => $this->getQuestionMaxScore(),
          'class'     => $class
      ));
    }

    $headers = array_intersect_key($labels, $rows[0]);
    $form['response']['#markup'] = theme('quiz_question_feedback__' . $this->question->type, array(
        'labels' => $headers,
        'data'   => $rows
    ));

    if ($this->canReview('question_feedback')) {
      if (!empty($this->question_handler->question)) {
        $form['question_feedback']['#markup'] = check_markup($this->question_handler->question->feedback, $this->question_handler->question->feedback_format);
      }
    }

    if ($theme = $this->getReportFormTheme()) {
      $form['#theme'] = $theme;
    }

    return $form;
  }

  /**
   * Get the question part of the reportForm
   * @return array
   */
  protected function getReportFormQuestion() {
    $question = clone ($this->question);
    $question->no_answer_form = TRUE;
    $output = entity_view('quiz_question', array($question), 'feedback', NULL, TRUE);
    return $output['quiz_question'][$this->question->qid];
  }

  /**
   * Get the response part of the report form
   * @return array[]
   *  Array of choices
   */
  public function getReportFormResponse() {
    return array(
        array(
            'choice'            => 'True',
            'attempt'           => 'Did the user choose this?',
            'correct'           => 'Was their answer correct?',
            'score'             => 'Points earned for this answer',
            'answer_feedback'   => 'Feedback specific to the answer',
            'question_feedback' => 'General question feedback for any answer',
            'solution'          => 'Is this choice the correct solution?',
            'quiz_feedback'     => 'Quiz feedback at this time',
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
   * Get the validate function for the reportForm
   *
   * @return
   *  Validate function as a string, or FALSE if no validate function
   */
  public function getReportFormValidate(&$element, &$form_state) {
    return FALSE;
  }

  /**
   * Get the theme key for the reportForm
   *
   * @return
   *  Theme key as a string, or FALSE if no submit function
   */
  public function getReportFormTheme() {
    return FALSE;
  }

  /**
   * Can the quiz taker view the requested review?
   */
  public function canReview($option) {
    $can_review = &drupal_static(__METHOD__, array());
    if (!isset($can_review[$option])) {
      $result = quiz_result_load($this->result_id);
      $can_review[$option] = $result->canReview($option);
    }
    return $can_review[$option];
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
