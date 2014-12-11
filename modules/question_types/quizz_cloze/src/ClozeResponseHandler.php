<?php

namespace Drupal\quizz_cloze;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\ResponseHandler;

/**
 * Extension of QuizQuestionResponse
 */
class ClozeResponseHandler extends ResponseHandler {

  /**
   * {@inheritdoc}
   * @var string
   */
  protected $base_table = 'quiz_cloze_user_answers';

  /** @var int */
  protected $answer_id = 0;

  /** @var Helper */
  private $helper;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);
    $this->helper = new Helper();

    if (NULL === $input) {
      if ($stored_input = $this->getStoredUserInput()) {
        $this->answer = unserialize($stored_input->answer);
        $this->score = $stored_input->score;
        $this->answer_id = $stored_input->answer_id;
      }
    }
    else {
      $this->answer = $input;
    }
  }

  private function getStoredUserInput() {
    return db_query(
        "SELECT answer_id, answer, score, question_vid, question_qid, result_id"
        . " FROM {quiz_cloze_user_answers}"
        . " WHERE question_qid = :question_qid"
        . "   AND question_vid = :question_vid"
        . "   AND result_id = :result_id", array(
          ':question_qid' => $this->question->qid,
          ':question_vid' => $this->question->vid,
          ':result_id'    => $this->result_id
      ))->fetch();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $this->answer_id = db_merge('quiz_cloze_user_answers')
      ->key(array(
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
      ))
      ->fields(array(
          'answer' => serialize($this->answer),
          'score'  => $this->getScore(FALSE),
      ))
      ->execute()
    ;
  }

  /**
   * {@inheritdoc}
   */
  public function score() {
    return $this->question->getHandler()->evaluateAnswer($this->answer);
  }

  public function getReportForm(array $form = array()) {
    $form += parent::getReportForm($form);

    $s_question = strip_tags($form['question']['#markup']);
    $question_form['open_wrapper']['#markup'] = '<div class="cloze-question">';
    foreach ($this->helper->getQuestionChunks($s_question) as $position => $chunk) {
      if (strpos($chunk, '[') === FALSE) {
        // this "tries[foobar]" hack is needed becaues response handler engine
        // checks for input field with name tries
        $question_form['tries[' . $position . ']'] = array(
            '#markup' => str_replace("\n", "<br/>", $chunk),
            '#prefix' => '<div class="form-item">',
            '#suffix' => '</div>',
        );
        continue;
      }

      $choices = explode(',', str_replace(array('[', ']'), '', $chunk));
      if (count($choices) > 1) {
        $question_form['tries[' . $position . ']'] = array(
            '#type'     => 'select',
            '#options'  => $this->helper->shuffleChoices(drupal_map_assoc($choices)),
            '#required' => FALSE,
        );
        continue;
      }

      $question_form['tries[' . $position . ']'] = array(
          '#type'       => 'textfield',
          '#size'       => 32,
          '#required'   => FALSE,
          '#attributes' => array('autocomplete' => 'off'),
      );
    }

    $question_form['close_wrapper']['#markup'] = '</div>';
    $form['question']['#markup'] = drupal_render($question_form);

    return $form;
  }

  public function getFeedbackValues() {
    if (!$this->question || empty($this->question->answers)) {
      return array();
    }

    $s_question = $this->question->quiz_question_body[LANGUAGE_NONE][0]['value'];

    return array(
        '#attached' => array(
            'css' => array(
                drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.css'
            ),
        ),
        'answer'    => array(
            '#theme'   => 'cloze_user_answer',
            '#answer'  => $this->helper->getUserAnswer($s_question, $this->answer),
            '#correct' => $this->getCorrectAnswer($s_question),
        ),
    );
  }

  private function getCorrectAnswer($question) {
    $chunks = $this->helper->getQuestionChunks($question);
    $answer = $this->helper->getCorrectAnswerChunks($question);
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

  public function getReportFormScore() {
    return array('#markup' => $this->getScore());
  }

}
