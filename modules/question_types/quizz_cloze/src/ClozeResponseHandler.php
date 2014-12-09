<?php

namespace Drupal\quizz_cloze;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;

/**
 * Extension of QuizQuestionResponse
 */
class ClozeResponseHelper extends ResponseHandler {

  protected $answer_id = 0;

  /** @var \Drupal\quizz_cloze\Helper */
  private $clozeHelper;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);
    $this->clozeHelper = new \Drupal\quizz_cloze\Helper();

    if (NULL === $input) {
      $stored_input = db_query(
        "SELECT answer_id, answer, score, question_vid, question_qid, result_id"
        . " FROM {quiz_cloze_user_answers}"
        . " WHERE question_qid = :question_qid"
        . "   AND question_vid = :question_vid"
        . "   AND result_id = :result_id", array(
          ':question_qid' => $question->qid,
          ':question_vid' => $question->vid,
          ':result_id'    => $result_id
        ))->fetch();
      if (!empty($stored_input)) {
        $this->answer = unserialize($stored_input->answer);
        $this->score = $stored_input->score;
        $this->answer_id = $stored_input->answer_id;
      }
    }
    else {
      $this->answer = $input;
    }
  }

  /**
   * Implementation of isValid
   *
   * @see ResponseHandler#isValid()
   */
  public function isValid() {
    return TRUE;
  }

  /**
   * Implementation of save
   *
   * @see ResponseHandler#save()
   */
  public function save() {
    $this->answer_id = db_insert('quiz_cloze_user_answers')
      ->fields(array(
          'answer'       => serialize($this->answer),
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->rid,
          'score'        => $this->getScore(FALSE),
      ))
      ->execute();
  }

  /**
   * Implementation of delete()
   *
   * @see ResponseHandler#delete()
   */
  public function delete() {
    db_delete('quiz_cloze_user_answers')
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->condition('result_id', $this->rid)
      ->execute();
  }

  /**
   * Implementation of score()
   *
   * @see ResponseHandler#score()
   */
  public function score() {
    $shortAnswer = new ClozeQuestion($this->question);
    $score = $shortAnswer->evaluateAnswer($this->answer);
    return $score;
  }

  /**
   * Implementation of getResponse()
   *
   * @see ResponseHandler#getResponse()
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Implementation of getReportForm()
   *
   * @see ResponseHandler#getReportForm($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportForm($showpoints = TRUE, $showfeedback = TRUE, $allow_scoring = FALSE) {
    $form = parent::getReportForm($showpoints, $showfeedback, $allow_scoring);
    $question = strip_tags($form['question']['#markup']);
    $question_form['open_wrapper'] = array(
        '#markup' => '<div class="cloze-question">',
    );
    foreach ($this->clozeHelper->getQuestionChunks($question) as $position => $chunk) {
      if (strpos($chunk, '[') === FALSE) {
        // this "tries[foobar]" hack is needed becaues response handler engine checks for input field
        // with name tries
        $question_form['tries[' . $position . ']'] = array(
            '#markup' => str_replace("\n", "<br/>", $chunk),
            '#prefix' => '<div class="form-item">',
            '#suffix' => '</div>',
        );
      }
      else {
        $chunk = str_replace(array('[', ']'), '', $chunk);
        $choices = explode(',', $chunk);
        if (count($choices) > 1) {
          $question_form['tries[' . $position . ']'] = array(
              '#type'     => 'select',
              '#title'    => '',
              '#options'  => $this->clozeHelper->shuffleChoices(drupal_map_assoc($choices)),
              '#required' => FALSE,
          );
        }
        else {
          $question_form['tries[' . $position . ']'] = array(
              '#type'       => 'textfield',
              '#title'      => '',
              '#size'       => 32,
              '#required'   => FALSE,
              '#attributes' => array(
                  'autocomplete' => 'off',
              ),
          );
        }
      }
    }

    $question_form['close_wrapper']['#markup'] = '</div>';
    $form['question']['#markup'] = drupal_render($question_form);
    return $form;
  }

  /**
   * Implementation of getReportFormResponse()
   *
   * @see ResponseHandler#getReportFormResponse($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportFormResponse($showpoints = TRUE, $showfeedback = TRUE, $allow_scoring = FALSE) {
    $form = array();
    $form['#theme'] = 'cloze_response_form';
    $form['#attached']['css'] = array(
        drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.css'
    );
    if (($this->question) && !empty($this->question->answers)) {
      $answer = (object) current($this->question->answers);
    }
    else {
      return $form;
    }
    $this->question = node_load($this->question->qid);
    $question = $this->question->quiz_question_body[LANGUAGE_NONE][0]['value'];
    $correct_answer = $this->clozeHelper->getCorrectAnswer($question);
    $user_answer = $this->clozeHelper->getUserAnswer($question, $this->answer);
    $form['answer']['#markup'] = theme('cloze_user_answer', array('answer' => $user_answer, 'correct' => $correct_answer));
    return $form;
  }

  private function getCorrectAnswer($question) {
    $chunks = $this->clozeHelper->getQuestionChunks($question);
    $answer = $this->clozeHelper->getCorrectAnswerChunks($question);
    $correct_answer = array();
    foreach ($chunks as $key => $chunk) {
      if (isset($answer[$key])) {
        $correct_answer[] = '<span class="answer correct correct-answer">' . $answer[$key] . '</span>';
      }
      else {
        $correct_answer[] = $chunk;
      }
    }
    return str_replace("\n", "<br/>", implode(' ', $correct_answer));
  }

  /**
   * Implementation of getReportFormScore()
   *
   * @see ResponseHandler#getReportFormScore($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportFormScore($showfeedback = TRUE, $showpoints = TRUE, $allow_scoring = FALSE) {
    return array('#markup' => $this->getScore());
  }

}
