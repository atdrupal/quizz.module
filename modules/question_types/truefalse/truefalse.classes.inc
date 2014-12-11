<?php

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionHandler;
use Drupal\quiz_question\ResponseHandler;

/**
 * Defines the classes necessary for a True/False quiz.
 */

/**
 * Extension of QuizQuestion.
 */
class TrueFalseQuestion extends QuestionHandler {

  /**
   * {@inheritdoc}
   */
  public function saveEntityProperties($is_new = FALSE) {
    if ($is_new || $this->question->revision == 1) {
      db_insert('quiz_truefalse')
        ->fields(array(
            'qid'            => $this->question->qid,
            'vid'            => $this->question->vid,
            'correct_answer' => $this->question->correct_answer,
        ))
        ->execute();
    }
    else {
      db_update('quiz_truefalse')
        ->fields(array(
            'correct_answer' => (int) $this->question->correct_answer
        ))
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form) {
    // This space intentionally left blank. :)
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete($only_this_version)
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);

    $delete_ans = db_delete('quiz_truefalse_user_answers');
    $delete_ans->condition('question_qid', $this->question->qid);

    $delete_node = db_delete('quiz_truefalse');
    $delete_node->condition('qid', $this->question->qid);

    if ($only_this_version) {
      $delete_ans->condition('question_vid', $this->question->vid);
      $delete_node->condition('vid', $this->question->vid);
    }

    $delete_ans->execute();
    $delete_node->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    if (isset($this->properties)) {
      return $this->properties;
    }
    $props = parent::load();

    $res_a = db_query('SELECT correct_answer '
      . ' FROM {quiz_truefalse} '
      . ' WHERE qid = :qid AND vid = :vid', array(
        ':qid' => $this->question->qid,
        ':vid' => $this->question->vid))->fetchAssoc();

    if (is_array($res_a)) {
      $props = array_merge($props, $res_a);
    }
    $this->properties = $props;
    return $props;
  }

  public function view() {
    $content = parent::view();
    if ($this->viewCanRevealCorrect()) {
      $answer = !empty($this->question->correct_answer) ? t('True') : t('False');
      $content['answers']['#markup'] = '<div class="quiz-solution">' . $answer . '</div>';
      $content['answers']['#weight'] = 2;
    }
    else {
      $content['answers'] = array(
          '#markup' => '<div class="quiz-answer-hidden">' . t('Answer hidden') . '</div>',
          '#weight' => 2,
      );
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $element = parent::getAnsweringForm($form_state, $result_id);

    // 'tries' is unfortunately required by quiz.module
    $element += array(
        '#type'          => 'radios',
        '#title'         => t('Choose one'),
        '#options'       => array(1 => t('True'), 0 => t('False')),
        '#default_value' => NULL, // prevent default value set to NULL
    );

    if (isset($result_id)) {
      $response = new TrueFalseResponse($result_id, $this->question);
      $default = $response->getResponse();
      $element['#default_value'] = is_null($default) ? NULL : $default;
    }

    return $element;
  }

  /**
   * Question response validator.
   */
  public function validateAnsweringForm(array &$form, array &$form_state = NULL) {
    if (is_null($form_state['values']['question'][$this->question->qid])) {
      form_set_error('', t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBodyFieldTitle() {
    return t('True/false statement');
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm($form_state)
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['correct_answer'] = array(
        '#type'          => 'radios',
        '#title'         => t('Correct answer'),
        '#options'       => array(
            1 => t('True'),
            0 => t('False'),
        ),
        '#default_value' => isset($this->question->correct_answer) ? $this->question->correct_answer : 1,
        '#required'      => TRUE,
        '#weight'        => -4,
        '#description'   => t('Choose if the correct answer for this question is "true" or "false".')
    );
    return $form;
  }

  /**
   * Implementation of getMaximumScore
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    return 1;
  }

  /**
   * Get the answer to this question.
   *
   * This is a utility function. It is not defined in the interface.
   */
  public function getCorrectAnswer() {
    return db_query('SELECT correct_answer FROM {quiz_truefalse} WHERE qid = :qid AND vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid))->fetchField();
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class TrueFalseResponse extends ResponseHandler {

  /**
   * {@inheritdoc}
   * @var string
   */
  protected $base_table = 'quiz_truefalse_user_answers';

  /**
   * Constructor
   */
  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);

    if (!isset($input)) {
      if ($response = $this->getStoredUserinput()) {
        $this->answer = $response->answer;
        $this->score = $response->score;
      }
    }
    else {
      $this->answer = $input;
    }
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    db_insert('quiz_truefalse_user_answers')
      ->fields(array(
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
          'answer'       => (int) $this->answer,
          'score'        => (int) $this->getScore(),
      ))
      ->execute();
  }

  /**
   * Implementation of score
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return $this->getResponse() == $this->question->getHandler()->getCorrectAnswer() ? 1 : 0;
  }

  protected function getStoredUserinput() {
    return db_query('SELECT answer, score'
        . ' FROM {quiz_truefalse_user_answers}'
        . ' WHERE question_vid = :qvid AND result_id = :rid', array(
          ':qvid' => $this->question->vid,
          ':rid'  => $this->result_id
      ))->fetch();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues() {
    $answer = $this->answer;
    $correct_answer = !empty($this->question->correct_answer);

    return array(
        array(
            'choice'          => t('True'),
            'attempt'         => $answer ? quiz_icon('selected') : '',
            'correct'         => $answer == 1 ? quiz_icon($correct_answer ? 'correct' : 'incorrect') : '',
            'score'           => intval($correct_answer == 1 && $answer == 1),
            'answer_feedback' => '',
            'solution'        => $correct_answer == 1 ? quiz_icon('should') : '',
            'quiz_feedback'   => t('@quiz feedback', array('@quiz' => QUIZ_NAME)),
        ),
        array(
            'choice'          => t('False'),
            'attempt'         => !$answer ? quiz_icon('selected') : '',
            'correct'         => $answer == 0 ? (quiz_icon(!$correct_answer ? 'correct' : 'incorrect')) : '',
            'score'           => intval($correct_answer == 0 && $answer == 0),
            'answer_feedback' => '',
            'solution'        => $correct_answer == 0 ? quiz_icon('should') : '',
        )
    );
  }

}
