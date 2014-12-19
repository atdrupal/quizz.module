<?php

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionHandler;
use Drupal\quiz_question\ResponseHandler;

/**
 * The main classes for the short answer question type.
 *
 * These inherit or implement code found in quiz_question.classes.inc.
 *
 * If you are developing your own question type, the easiest place to start is with
 * multichoice.classes.inc. short_answer and long_answer are good for understanding
 * question types that involve textual answers.
 *
 * @file
 */

/**
 * Extension of QuizQuestion.
 *
 * This could have extended long answer, except that that would have entailed
 * adding long answer as a dependency.
 */
class ShortAnswerQuestion extends QuestionHandler {

  // Constants for answer matching options
  const ANSWER_MATCH = 0;
  const ANSWER_INSENSITIVE_MATCH = 1;
  const ANSWER_REGEX = 2;
  const ANSWER_MANUAL = 3;

  /**
   * {@inheritdoc}
   */
  public function onSave($is_new = FALSE) {
    if ($is_new || $this->question->revision == 1) {
      db_insert('quiz_short_answer_properties')
        ->fields(array(
            'qid'                       => $this->question->qid,
            'vid'                       => $this->question->vid,
            'correct_answer'            => $this->question->correct_answer,
            'correct_answer_evaluation' => $this->question->correct_answer_evaluation,
        ))
        ->execute();
    }
    else {
      db_update('quiz_short_answer_properties')
        ->fields(array(
            'correct_answer'            => $this->question->correct_answer,
            'correct_answer_evaluation' => $this->question->correct_answer_evaluation,
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
    if ($this->question->correct_answer_evaluation != self::ANSWER_MANUAL && empty($this->question->correct_answer)) {
      form_set_error('correct_answer', t('An answer must be specified for any evaluation type other than manual scoring.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    $delete_ans = db_delete('quiz_short_answer_user_answers');
    $delete_ans->condition('question_qid', $this->question->qid);

    $delete_node = db_delete('quiz_short_answer_properties');
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
    $res_a = db_query('SELECT correct_answer, correct_answer_evaluation FROM {quiz_short_answer_properties}
      WHERE qid = :qid AND vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid))->fetchAssoc();
    $this->properties = (is_array($res_a)) ? array_merge($props, $res_a) : $props;
    return $this->properties;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    $content = parent::view();
    if ($this->viewCanRevealCorrect()) {
      $content['answers'] = array(
          '#markup' => '<div class="quiz-solution">' . check_plain($this->question->correct_answer) . '</div>',
          '#weight' => 2,
      );
    }
    else {
      $content['answers'] = array(
          '#markup' => '<div class="quiz-answer-hidden">Answer hidden</div>',
          '#weight' => 2,
      );
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $form = parent::getAnsweringForm($form_state, $result_id);

    $form = array(
        '#type'          => 'textfield',
        '#title'         => t('Answer'),
        '#description'   => t('Enter your answer here'),
        '#default_value' => '',
        '#size'          => 60,
        '#maxlength'     => 256,
        '#required'      => FALSE,
        '#attributes'    => array('autocomplete' => 'off'),
    );

    if (isset($result_id)) {
      $response = new ShortAnswerResponse($result_id, $this->question);
      $form['#default_value'] = $response->getResponse();
    }

    return $form;
  }

  /**
   * Question response validator.
   */
  public function validateAnsweringForm(array &$form, array &$form_state = NULL) {
    if ('' === $form_state['values']['question'][$this->question->qid]['answer']) {
      form_set_error('', t('You must provide an answer.'));
    }
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm($form_state)
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['answer'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Answer'),
        '#description' => t('Provide the answer and the method by which the answer will be evaluated.'),
        '#collapsible' => TRUE,
        '#collapsed'   => FALSE,
        '#weight'      => -4,
    );

    $options = array(
        self::ANSWER_MATCH             => t('Automatic and case sensitive'),
        self::ANSWER_INSENSITIVE_MATCH => t('Automatic. Not case sensitive'),
    );
    $access_regex = user_access('use regex for short answer');
    if ($access_regex) {
      $options[self::ANSWER_REGEX] = t('Match against a regular expression (answer must match the supplied regular expression)');
    }
    $options[self::ANSWER_MANUAL] = t('Manual');

    $form['answer']['correct_answer_evaluation'] = array(
        '#type'          => 'radios',
        '#title'         => t('Pick an evaluation method'),
        '#description'   => t('Choose how the answer shall be evaluated.'),
        '#options'       => $options,
        '#default_value' => isset($this->question->correct_answer_evaluation) ? $this->question->correct_answer_evaluation : self::ANSWER_INSENSITIVE_MATCH,
        '#required'      => FALSE,
    );
    if ($access_regex) {
      $form['answer']['regex_box'] = array(
          '#type'        => 'fieldset',
          '#title'       => t('About regular expressions'),
          '#collapsible' => TRUE,
          '#collapsed'   => TRUE,
      );

      $form['answer']['regex_box']['regex_help'] = array(
          '#markup' => '<p>' .
          t('Regular expressions are an advanced syntax for pattern matching. They allow you to create a concise set of rules that must be met before a value can be considered a match.') .
          '</p><p>' .
          t('For more on regular expression syntax, visit !url.', array('!url' => l('the PHP regular expressions documentation', 'http://www.php.net/manual/en/book.pcre.php'))) .
          '</p>',
      );
    }

    $form['answer']['correct_answer'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Correct answer'),
        '#description'   => t('Specify the answer. If this question is manually scored, no answer needs to be supplied.'),
        '#default_value' => isset($this->question->correct_answer) ? $this->question->correct_answer : '',
        '#size'          => 60,
        '#maxlength'     => 256,
        '#required'      => FALSE,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore() {
    return $this->question->getQuestionType()->getConfig('short_answer_default_max_score', 5);
  }

  /**
   * Evaluate the correctness of an answer based on the correct answer and evaluation method.
   */
  public function evaluateAnswer($input) {
    $score = 0;

    // Ignore white spaces for correct answer
    $this->question->correct_answer = trim($this->question->correct_answer);

    switch ($this->question->correct_answer_evaluation) {
      case self::ANSWER_MATCH:
        if ($input == $this->question->correct_answer) {
          $score = $this->question->max_score;
        }
        break;
      case self::ANSWER_INSENSITIVE_MATCH:
        if (drupal_strtolower($input) == drupal_strtolower($this->question->correct_answer)) {
          $score = $this->question->max_score;
        }
        break;
      case self::ANSWER_REGEX:
        if (preg_match($this->question->correct_answer, $input) > 0) {
          $score = $this->question->max_score;
        }
        break;
    }
    return $score;
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class ShortAnswerResponse extends ResponseHandler {

  /**
   * {@inheritdoc}
   * @var string
   */
  protected $base_table = 'quiz_short_answer_user_answers';
  protected $answer_id = 0;

  /** @var bool */
  protected $allow_feedback = TRUE;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input);
    if (NULL === $input) {
      if (($answer = $this->loadAnswerEntity()) && ($input = $answer->getInput())) {
        $this->answer = $input->answer;
        $this->score = $input->score;
        $this->evaluated = $input->is_evaluated;
        $this->answer_id = $input->answer_id;
        $this->answer_feedback = $input->answer_feedback;
        $this->answer_feedback_format = $input->answer_feedback_format;
      }
    }
    else {
      if (is_array($input)) {
        $this->answer = $input['answer'];
      }
      else {
        $this->answer = $input;
        $this->evaluated = $this->question->correct_answer_evaluation != ShortAnswerQuestion::ANSWER_MANUAL;
      }
    }
  }

  public function onLoad(\Drupal\quizz\Entity\Answer $answer) {
    $sql = 'SELECT input.* FROM {quiz_short_answer_user_answers} input WHERE question_vid = :qvid AND result_id = :rid';
    $conds = array(':qvid' => $answer->question_vid, ':rid' => $answer->result_id);
    if ($input = db_query($sql, $conds)->fetchObject()) {
      $answer->setInput($input);
    }
  }

  /**
   * Get all quiz scores that haven't been evaluated yet.
   * @param int $count
   * @param int $offset
   * @return array Array of objects describing unanswered questions. Each object will have result_id, question_qid, and question_vid.
   */
  public static function fetchAllUnscoredAnswers($count = 50, $offset = 0) {
    global $user;

    $query = db_select('quiz_short_answer_user_answers', 'answer');
    $query->fields('answer', array('result_id', 'question_qid', 'question_vid', 'answer_feedback', 'answer_feedback_format'));
    $query->fields('question_revision', array('title'));
    $query->fields('qr', array('time_end', 'time_start', 'uid'));
    $query->join('node_revision', 'question_revision', 'answer.question_vid = question_revision.vid');
    $query->join('quiz_results', 'qr', 'answer.result_id = qr.result_id');
    $query->join('quiz_entity', 'quiz', 'qr.quiz_qid = quiz.qid');
    $query->condition('answer.is_evaluated', 0);

    if (user_access('score own quiz') && user_access('score taken quiz answer')) {
      $query->condition(db_or()->condition('quiz.uid', $user->uid)->condition('qr.uid', $user->uid));
    }
    elseif (user_access('score own quiz')) {
      $query->condition('quiz.uid', $user->uid);
    }
    elseif (user_access('score taken quiz answer')) {
      $query->condition('qr.uid', $user->uid);
    }

    $unscored = array();
    foreach ($query->execute() as $row) {
      $unscored[] = $row;
    }
    return $unscored;
  }

  /**
   * Given a question, return a list of all of the unscored answers.
   *
   * @param int $question_qid
   * @param int $question_vid
   * @param int $count
   * @param int $offset
   * @return array Indexed array of result IDs that need to be scored.
   */
  public static function fetchUnscoredAnswersByQuestion($question_qid, $question_vid, $count = 50, $offset = 0) {
    return db_query(
        'SELECT result_id
          FROM {quiz_short_answer_user_answers}
          WHERE is_evaluated = :is_evaluated
            AND question_qid = :question_qid
            AND question_vid = :question_vid', array(
          ':is_evaluated' => 0,
          ':question_qid' => $question_qid,
          ':question_vid' => $question_vid
      ))->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // We need to set is_evaluated depending on whether the type requires evaluation.
    $this->is_evaluated = (int) ($this->question->correct_answer_evaluation != ShortAnswerQuestion::ANSWER_MANUAL);
    $this->answer_id = db_insert('quiz_short_answer_user_answers')
      ->fields(array(
          'answer'       => $this->answer,
          'question_qid' => $this->question->qid,
          'question_vid' => $this->question->vid,
          'result_id'    => $this->result_id,
          'score'        => $this->getScore(FALSE),
          'is_evaluated' => $this->is_evaluated,
      ))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function score() {
    // Manual scoring means we go with what is in the DB.
    if ($this->question->correct_answer_evaluation == ShortAnswerQuestion::ANSWER_MANUAL) {
      $score = db_query('SELECT score FROM {quiz_short_answer_user_answers} WHERE result_id = :result_id AND question_vid = :question_vid', array(':result_id' => $this->result_id, ':question_vid' => $this->question->vid))->fetchField();
      if (!$score) {
        $score = 0;
      }
    }
    // Otherwise, we run the scoring automatically.
    else {
      $shortAnswer = new ShortAnswerQuestion($this->question);
      $score = $shortAnswer->evaluateAnswer($this->getResponse());
    }
    return $score;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues() {
    $data = array();

    $data[] = array(
        'choice'            => '',
        'attempt'           => $this->answer,
        'correct'           => !$this->evaluated ? t('This answer has not yet been scored.') : '',
        'score'             => $this->getScore(),
        'answer_feedback'   => check_markup($this->answer_feedback, $this->answer_feedback_format),
        'question_feedback' => '',
        'solution'          => '',
    );

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportFormSubmit() {
    return 'short_answer_report_submit';
  }

  /**
   * {@inheritdoc}
   */
  public function validateReportForm(&$element, &$form_state) {
    // Check to make sure that entered score is not higher than max allowed score.
    if ($element['score']['#value'] > $this->question->max_score) {
      form_error($element['score'], t('The score needs to be a number between 0 and @max', array(
          '@max' => $this->question->max_score
      )));
    }
  }

}
