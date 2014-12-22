<?php

use Drupal\multichoice\FormDefinition;
use Drupal\quizz_question\Entity\Question;
use Drupal\quizz_question\QuestionHandler;
use Drupal\quizz_question\ResponseHandler;
use Drupal\quizz\Entity\Answer;

/**
 * The main classes for the multichoice question type.
 *
 * These inherit or implement code found in quiz_question.classes.inc.
 *
 * Sponsored by: Norwegian Centre for Telemedicine
 * Code: falcon
 *
 * Based on:
 * Other question types in the quiz framework.
 *
 * @file
 * Question type, enabling the creation of multiple choice and multiple answer questions.
 */

/**
 * Extension of QuizQuestion.
 */
class MultichoiceQuestion extends QuestionHandler {

  /**
   * Forgive some possible logical flaws in the user input.
   */
  private function forgive() {
    if ($this->question->choice_multi == 1) {
      for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
        $short = &$this->question->alternatives[$i];

        // If the scoring data doesn't make sense, use the data from the
        // "correct" checkbox to set the score data
        if ($short['score_if_chosen'] == $short['score_if_not_chosen'] || !is_numeric($short['score_if_chosen']) || !is_numeric($short['score_if_not_chosen'])) {
          if (!empty($short['correct'])) {
            $short['score_if_chosen'] = 1;
            $short['score_if_not_chosen'] = 0;
          }
          else {
            $short['score_if_chosen'] = -1;
            $short['score_if_not_chosen'] = 0;
            if ($this->question->getQuestionType()->getConfig('multichoice_def_scoring', 0)) {
              $short['score_if_chosen'] = 0;
              $short['score_if_not_chosen'] = 1;
            }
          }
        }
      }
    }
    else {
      // For questions with one, and only one, correct answer, there will be no
      // points awarded for alternatives not chosen.
      for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
        $short = &$this->question->alternatives[$i];
        $short['score_if_not_chosen'] = 0;
        if (isset($short['correct']) && $short['correct'] == 1 && !quiz_valid_integer($short['score_if_chosen'], 1)) {
          $short['score_if_chosen'] = 1;
        }
      }
    }
  }

  /**
   * Warn the user about possible user errors
   */
  private function warn() {
    // Count the number of correct answers
    $num_corrects = 0;
    for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
      $alt = &$this->question->alternatives[$i];
      if ($alt['score_if_chosen'] > $alt['score_if_not_chosen']) {
        $num_corrects++;
      }
    }

    if ($num_corrects == 1 && $this->question->choice_multi == 1 || $num_corrects > 1 && $this->question->choice_multi == 0) {
      $link_options = array();
      if (isset($_GET['destination'])) {
        $link_options['query'] = array('destination' => $_GET['destination']);
      }
      $go_back = l(t('go back'), 'quiz-question/' . $this->question->qid . '/edit', $link_options);
      if ($num_corrects == 1) {
        $msg = t('Your question allows multiple answers. Only one of the alternatives have been marked as correct. If this wasn\'t intended please !go_back and correct it.', array('!go_back' => $go_back));
        drupal_set_message($msg, 'warning');
      }
      else {
        $msg = t('Your question doesn\'t allow multiple answers. More than one of the alternatives have been marked as correct. If this wasn\'t intended please !go_back and correct it.', array('!go_back' => $go_back));
        drupal_set_message($msg, 'warning');
      }
    }
  }

  /**
   * Run check_markup() on the field of the specified choice alternative
   * @param $alternativeIndex
   *  The index of the alternative in the alternatives array.
   * @param $field
   *  The name of the field we want to check markup on
   * @param $check_user_access
   *  Whether or not to check for user access to the filter we're trying to apply
   * @return HTML markup
   */
  private function checkMarkup($alternativeIndex, $field, $check_user_access = FALSE) {
    $alternative = $this->question->alternatives[$alternativeIndex];
    return check_markup($alternative[$field]['value'], $alternative[$field]['format']);
  }

  /**
   * Implementation of save
   *
   * Stores the question in the database.
   *
   * @param is_new if - if the node is a new node…
   * (non-PHPdoc)
   * @see sites/all/modules/quiz-HEAD/question_types/quiz_question/QuizQuestion#save()
   */
  public function onSave($is_new = FALSE) {
    $is_new = $is_new || $this->question->revision == 1;

    // Before we save we forgive some possible user errors
    $this->forgive();

    // We also add warnings on other possible user errors
    $this->warn();

    if ($is_new) {
      $id = db_insert('quiz_multichoice_properties')
        ->fields(array(
            'qid'            => $this->question->qid,
            'vid'            => $this->question->vid,
            'choice_multi'   => $this->question->choice_multi,
            'choice_random'  => $this->question->choice_random,
            'choice_boolean' => $this->question->choice_boolean,
        ))
        ->execute();

      // TODO: utilize the benefit of multiple insert of DBTNG
      for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
        if (drupal_strlen($this->question->alternatives[$i]['answer']['value']) > 0) {
          $this->insertAlternative($i);
        }
      }
    }
    else {
      db_update('quiz_multichoice_properties')
        ->fields(array(
            'choice_multi'   => $this->question->choice_multi,
            'choice_random'  => $this->question->choice_random,
            'choice_boolean' => $this->question->choice_boolean,
        ))
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();

      // We fetch ids for the existing answers belonging to this question
      // We need to figure out if an existing alternative has been changed or deleted.
      $res = db_query('SELECT id FROM {quiz_multichoice_answers}
              WHERE question_qid = :qid AND question_vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid));

      // We start by assuming that all existing alternatives needs to be deleted
      $ids_to_delete = array();
      while ($res_o = $res->fetch()) {
        $ids_to_delete[] = $res_o->id;
      }

      for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
        $short = $this->question->alternatives[$i];
        if (drupal_strlen($this->question->alternatives[$i]['answer']['value']) > 0) {
          // If new alternative
          if (!is_numeric($short['id'])) {
            $this->insertAlternative($i);
          }
          // If existing alternative
          else {
            $this->updateAlternative($i);
            // Make sure this alternative isn't deleted
            $key = array_search($short['id'], $ids_to_delete);
            $ids_to_delete[$key] = FALSE;
          }
        }
      }
      foreach ($ids_to_delete as $id_to_delete) {
        if ($id_to_delete) {
          db_delete('quiz_multichoice_answers')
            ->condition('id', $id_to_delete)
            ->execute();
        }
      }
    }
    $this->saveUserSettings();
  }

  function _normalizeAlternative($alternatives) {
    $copy = $alternatives;
    // answer
    if (is_array($alternatives['answer']) && isset($alternatives['answer']['value'])) {
      $copy['answer'] = $alternatives['answer']['value'];
    }
    // answer format
    if (is_array($alternatives['answer']) && isset($alternatives['answer']['format'])) {
      $copy['answer_format'] = $alternatives['answer']['format'];
    }
    // feedback if choosen
    if (is_array($alternatives['feedback_if_chosen']) && isset($alternatives['feedback_if_chosen']['value'])) {
      $copy['feedback_if_chosen'] = $alternatives['feedback_if_chosen']['value'];
    }
    // feedback if choosen foramt
    if (is_array($alternatives['feedback_if_chosen']) && isset($alternatives['feedback_if_chosen']['format'])) {
      $copy['feedback_if_chosen_format'] = $alternatives['feedback_if_chosen']['format'];
    }
    // feedback if not chosen
    if (is_array($alternatives['feedback_if_not_chosen']) && isset($alternatives['feedback_if_not_chosen']['value'])) {
      $copy['feedback_if_not_chosen'] = $alternatives['feedback_if_not_chosen']['value'];
    }
    // feedback if not chosen foramt
    if (is_array($alternatives['feedback_if_not_chosen']) && isset($alternatives['feedback_if_not_chosen']['format'])) {
      $copy['feedback_if_not_chosen_format'] = $alternatives['feedback_if_not_chosen']['format'];
    }
    return $copy;
  }

  /**
   * Helper function. Saves new alternatives
   *
   * @param $i
   *  The alternative index
   */
  private function insertAlternative($i) {
    $alternatives = $this->_normalizeAlternative($this->question->alternatives[$i]);
    db_insert('quiz_multichoice_answers')
      ->fields(array(
          'answer'                        => $alternatives['answer'],
          'answer_format'                 => $alternatives['answer_format'],
          'feedback_if_chosen'            => $alternatives['feedback_if_chosen'],
          'feedback_if_chosen_format'     => $alternatives['feedback_if_chosen_format'],
          'feedback_if_not_chosen'        => $alternatives['feedback_if_not_chosen'],
          'feedback_if_not_chosen_format' => $alternatives['feedback_if_not_chosen_format'],
          'score_if_chosen'               => $alternatives['score_if_chosen'],
          'score_if_not_chosen'           => $alternatives['score_if_not_chosen'],
          'question_qid'                  => $this->question->qid,
          'question_vid'                  => $this->question->vid,
          'weight'                        => isset($alternatives['weight']) ? $alternatives['weight'] : $i,
      ))
      ->execute();
  }

  /**
   * Helper function. Updates existing alternatives
   *
   * @param $i
   *  The alternative index
   */
  private function updateAlternative($i) {
    $alternatives = $this->_normalizeAlternative($this->question->alternatives[$i]);
    db_update('quiz_multichoice_answers')
      ->fields(array(
          'answer'                        => $alternatives['answer'],
          'answer_format'                 => $alternatives['answer_format'],
          'feedback_if_chosen'            => $alternatives['feedback_if_chosen'],
          'feedback_if_chosen_format'     => $alternatives['feedback_if_chosen_format'],
          'feedback_if_not_chosen'        => $alternatives['feedback_if_not_chosen'],
          'feedback_if_not_chosen_format' => $alternatives['feedback_if_not_chosen_format'],
          'score_if_chosen'               => $alternatives['score_if_chosen'],
          'score_if_not_chosen'           => $alternatives['score_if_not_chosen'],
          'weight'                        => isset($alternatives['weight']) ? $alternatives['weight'] : $i,
      ))
      ->condition('id', $alternatives['id'])
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->execute();
  }

  /**
   * Implementation of validate
   *
   * QuizQuestion#validate()
   */
  public function validate(array &$form) {
    if ($this->question->choice_multi == 0) {
      $found_one_correct = FALSE;
      for ($i = 0; (isset($this->question->alternatives[$i]) && is_array($this->question->alternatives[$i])); $i++) {
        $short = $this->question->alternatives[$i];
        if (drupal_strlen($this->checkMarkup($i, 'answer')) < 1) {
          continue;
        }
        if ($short['correct'] == 1) {
          if ($found_one_correct) {
            // We don't display an error message here since we allow alternatives to be partially correct
          }
          else {
            $found_one_correct = TRUE;
          }
        }
      }
      if (!$found_one_correct) {
        form_set_error('choice_multi', t('You have not marked any alternatives as correct. If there are no correct alternatives you should allow multiple answers.'));
      }
    }
    else {
      for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
        $short = $this->question->alternatives[$i];
        if (strlen($this->checkMarkup($i, 'answer')) < 1) {
          continue;
        }
        if ($short['score_if_chosen'] < $short['score_if_not_chosen'] && $short['correct']) {
          form_set_error("alternatives][$i][score_if_not_chosen", t('The alternative is marked as correct, but gives more points if you don\'t select it.'));
        }
        elseif ($short['score_if_chosen'] > $short['score_if_not_chosen'] && !$short['correct']) {
          form_set_error("alternatives][$i][score_if_chosen", t('The alternative is not marked as correct, but gives more points if you select it.'));
        }
      }
    }
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete()
   */
  public function delete($only_this_version = FALSE) {
    $delete_properties = db_delete('quiz_multichoice_properties')->condition('qid', $this->question->qid);
    $delete_answers = db_delete('quiz_multichoice_answers')->condition('question_qid', $this->question->qid);
    $delete_results = db_delete('quiz_multichoice_user_answers')->condition('question_qid', $this->question->qid);

    if ($only_this_version) {
      $delete_properties->condition('vid', $this->question->vid);
      $delete_answers->condition('question_vid', $this->question->vid);
      $delete_results->condition('question_vid', $this->question->vid);
    }

    // Delete from table quiz_multichoice_user_answer_multi
    if ($only_this_version) {
      $user_answer_id = db_query('SELECT id FROM {quiz_multichoice_user_answers} WHERE question_qid = :qid AND question_vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid))->fetchCol();
    }
    else {
      $user_answer_id = db_query('SELECT id FROM {quiz_multichoice_user_answers} WHERE question_qid = :qid', array(':qid' => $this->question->qid))->fetchCol();
    }

    if (!empty($user_answer_id)) {
      db_delete('quiz_multichoice_user_answer_multi')
        ->condition('user_answer_id', $user_answer_id, 'IN')
        ->execute();
    }
    $delete_properties->execute();
    $delete_answers->execute();
    $delete_results->execute();
    parent::delete($only_this_version);
  }

  /**
   * Implementation of load
   *
   * @see QuizQuestion#load()
   */
  public function load() {
    if (isset($this->properties) && !empty($this->properties)) {
      return $this->properties;
    }
    $props = parent::load();

    $res_a = db_query('SELECT choice_multi, choice_random, choice_boolean FROM {quiz_multichoice_properties}
            WHERE qid = :qid AND vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid))->fetchAssoc();

    if (is_array($res_a)) {
      $props = array_merge($props, $res_a);
    }

    // Load the answers
    $res = db_query('SELECT id, answer, answer_format, feedback_if_chosen, feedback_if_chosen_format,
            feedback_if_not_chosen, feedback_if_not_chosen_format, score_if_chosen, score_if_not_chosen, weight
            FROM {quiz_multichoice_answers}
            WHERE question_qid = :question_qid AND question_vid = :question_vid
            ORDER BY weight', array(
        ':question_qid' => $this->question->qid,
        ':question_vid' => $this->question->vid));
    $props['alternatives'] = array(); // init array so it can be iterated even if empty
    while ($res_arr = $res->fetchAssoc()) {
      $props['alternatives'][] = array(
          'id'                     => $res_arr['id'],
          'answer'                 => array(
              'value'  => $res_arr['answer'],
              'format' => $res_arr['answer_format'],
          ),
          'feedback_if_chosen'     => array(
              'value'  => $res_arr['feedback_if_chosen'],
              'format' => $res_arr['feedback_if_chosen_format'],
          ),
          'feedback_if_not_chosen' => array(
              'value'  => $res_arr['feedback_if_not_chosen'],
              'format' => $res_arr['feedback_if_not_chosen_format'],
          ),
          'score_if_chosen'        => $res_arr['score_if_chosen'],
          'score_if_not_chosen'    => $res_arr['score_if_not_chosen'],
          'weight'                 => $res_arr['weight'],
      );
    }
    $this->properties = $props;
    return $props;
  }

  public function view() {
    $content = parent::view();
    if ($this->question->choice_random) {
      $this->shuffle($this->question->alternatives);
    }
    $content['answers'] = array(
        '#markup' => theme('multichoice_answer_node_view', array('alternatives' => $this->question->alternatives, 'show_correct' => $this->viewCanRevealCorrect())),
        '#weight' => 2,
    );

    return $content;
  }

  /**
   * Generates the question element.
   *
   * This is called whenever a question is rendered, either
   * to an administrator or to a quiz taker.
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $element = parent::getAnsweringForm($form_state, $result_id);

    /* We use an array looking key to be able to store multiple answers in tries.
     * At the moment all user answers have to be stored in tries. This is something we plan
     * to fix in quiz 5.x.
     */
    $element['#theme'] = 'multichoice_alternative';
    if (isset($result_id)) {
      // This question has already been answered. We load the answer.
      $response = new MultichoiceResponse($result_id, $this->question);
    }
    for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
      $short = $this->question->alternatives[$i];
      $answer_markup = check_markup($short['answer']['value'], $short['answer']['format']);
      if (drupal_strlen($answer_markup) > 0) {
        $element['user_answer']['#options'][$short['id']] = $answer_markup;
      }
    }
    if ($this->question->choice_random) {
      // We save the choice order so that the order will be the same in the answer report
      $element['choice_order'] = array(
          '#type'  => 'hidden',
          '#value' => implode(',', $this->shuffle($element['user_answer']['#options'])),
      );
    }
    if ($this->question->choice_multi) {
      $element['user_answer']['#type'] = 'checkboxes';
      $element['user_answer']['#title'] = t('Choose all that apply');
      if (isset($response)) {
        if (is_array($response->getResponse())) {
          $element['#default_value'] = $response->getResponse();
        }
      }
    }
    else {
      $element['user_answer']['#type'] = 'radios';
      $element['user_answer']['#title'] = t('Choose one');
      if (isset($response)) {
        $selection = $response->getResponse();
        if (is_array($selection)) {
          $element['user_answer']['#default_value'] = array_pop($selection);
        }
      }
    }

    $element['#attached']['js'][] = drupal_get_path('module', 'multichoice') . '/js/multichoice.js';

    return $element;
  }

  /**
   * Custom shuffle function. It keeps the array key - value relationship intact
   *
   * @param array $array
   * @return unknown_type
   */
  private function shuffle(array &$array) {
    $newArray = array();
    $toReturn = array_keys($array);
    shuffle($toReturn);
    foreach ($toReturn as $key) {
      $newArray[$key] = $array[$key];
    }
    $array = $newArray;
    return $toReturn;
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    $obj = new FormDefinition($this->question);
    return $obj->get($form_state);
  }

  /**
   * Fetches the users default settings from the creation form
   */
  private function saveUserSettings() {
    global $user;

    db_merge('quiz_multichoice_user_settings')
      ->key(array('uid' => $user->uid))
      ->fields(array(
          'choice_random'  => $this->question->choice_random,
          'choice_multi'   => $this->question->choice_multi,
          'choice_boolean' => $this->question->choice_boolean,
      ))
      ->execute();
  }

  /**
   * Implementation of getMaximumScore.
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    if ($this->question->choice_boolean) {
      return 1;
    }

    $max = 0;
    for ($i = 0; isset($this->question->alternatives[$i]); $i++) {
      $short = $this->question->alternatives[$i];
      if ($this->question->choice_multi) {
        $max += max($short['score_if_chosen'], $short['score_if_not_chosen']);
      }
      else {
        $max = max($max, $short['score_if_chosen'], $short['score_if_not_chosen']);
      }
    }
    return max($max, 1);
  }

  /**
   * Question response validator.
   */
  public function validateAnsweringForm(array &$form, array &$form_state = NULL) {
    if (!$this->question->choice_multi && is_null($form_state['values']['question'][$this->question->qid]['answer']['user_answer'])) {
      form_set_error('', t('You must provide an answer.'));
    }
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class MultichoiceResponse extends ResponseHandler {

  /**
   * ID of the answers.
   */
  protected $user_answer_ids;
  protected $choice_order;

  public function __construct($result_id, Question $question, $input = NULL) {
    parent::__construct($result_id, $question, $input['user_answer']);

    $this->user_answer_ids = array();

    // tries is the tries part of the post data
    if (isset($input['user_answer'])) {
      if (!is_array($input['user_answer'])) {
        // Account for single-select
        $input['user_answer'] = array($input['user_answer']);
      }
      if (isset($input['choice_order'])) {
        $this->choice_order = $input['choice_order'];
      }
      unset($input['choice_order']);
      if (isset($input['user_answer']) && is_array($input['user_answer'])) {
        foreach ($input['user_answer'] as $answer_id) {
          $this->user_answer_ids[] = $answer_id;
          $this->answer = $this->user_answer_ids; // @todo: Stop using user_answer_ids and only use answer instead…
        }
      }
      elseif (isset($input['user_answer'])) {
        $this->user_answer_ids[] = $input['user_answer'];
      }
    }
    else { // We load the answer from the database
      $input_ids = db_query(
        'SELECT answer_id FROM {quiz_multichoice_user_answers} ua
         LEFT OUTER JOIN {quiz_multichoice_user_answer_multi} uam ON(uam.user_answer_id = ua.id)
         WHERE ua.result_id = :result_id AND ua.question_vid = :question_vid', array(
          ':result_id'    => $result_id,
          ':question_vid' => $this->question->vid))
        ->fetchCol();
      if ($input_ids) {
        $this->user_answer_ids = $input_ids;
      }
    }
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {
    $user_answer_id = db_insert('quiz_multichoice_user_answers')
      ->fields(array(
          'result_id'    => $this->result_id,
          'question_vid' => $this->question->vid,
          'question_qid' => $this->question->qid,
          'choice_order' => $this->choice_order
      ))
      ->execute();

    $query = db_insert('quiz_multichoice_user_answer_multi')
      ->fields(array('user_answer_id', 'answer_id'));
    for ($i = 0; $i < count($this->user_answer_ids); $i++) {
      if ($this->user_answer_ids[$i]) {
        $query->values(array($user_answer_id, $this->user_answer_ids[$i]));
      }
    }
    $query->execute();
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestionResponse#delete()
   */
  public function delete() {
    $user_answer_id = array();
    $query = db_query('SELECT id FROM {quiz_multichoice_user_answers} WHERE question_qid = :qid AND question_vid = :vid AND result_id = :result_id', array(':qid' => $this->question->qid, ':vid' => $this->question->vid, ':result_id' => $this->result_id));
    while ($user_answer = $query->fetch()) {
      $user_answer_id[] = $user_answer->id;
    }

    if (!empty($user_answer_id)) {
      db_delete('quiz_multichoice_user_answer_multi')
        ->condition('user_answer_id', $user_answer_id, 'IN')
        ->execute();
    }

    db_delete('quiz_multichoice_user_answers')
      ->condition('result_id', $this->result_id)
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->execute();
  }

  /**
   * Implementation of score
   *
   * @return uint
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    if ($this->question->choice_boolean || $this->isAllWrong()) {
      $score = $this->getQuestionMaxScore();
      foreach ($this->question->alternatives as $key => $alt) {
        if (in_array($alt['id'], $this->user_answer_ids)) {
          if ($alt['score_if_chosen'] <= $alt['score_if_not_chosen']) {
            $score = 0;
          }
        }
        elseif ($alt['score_if_chosen'] > $alt['score_if_not_chosen']) {
          $score = 0;
        }
      }
    }
    else {
      $score = 0;
      foreach ($this->question->alternatives as $key => $alt) {
        if (in_array($alt['id'], $this->user_answer_ids)) {
          $score += $alt['score_if_chosen'];
        }
        else {
          $score += $alt['score_if_not_chosen'];
        }
      }
    }
    return $score;
  }

  /**
   * If all answers in a question is wrong
   *
   * @return boolean
   *  TRUE if all answers are wrong. False otherwise.
   */
  public function isAllWrong() {
    foreach ($this->question->alternatives as $key => $alt) {
      if ($alt['score_if_chosen'] > 0 || $alt['score_if_not_chosen'] > 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Implementation of getResponse
   *
   * @return answer
   *
   * @see QuizQuestionResponse#getResponse()
   */
  public function getResponse() {
    return $this->user_answer_ids;
  }

  /**
   * Implementation of getFeedbackValues.
   */
  public function getFeedbackValues() {
    $this->orderAlternatives($this->question->alternatives);
    $simple_scoring = $this->question->choice_boolean;

    $data = array();
    foreach ($this->question->alternatives as $alternative) {
      $chosen = in_array($alternative['id'], $this->user_answer_ids);
      $not = $chosen ? '' : 'not_';

      $data[] = array(
          'choice'            => check_markup($alternative['answer']['value'], $alternative['answer']['format']),
          'attempt'           => $chosen ? quiz_icon('selected') : '',
          'correct'           => $chosen ? $alternative['score_if_chosen'] > 0 ? quiz_icon('correct') : quiz_icon('incorrect') : '',
          'score'             => $alternative["score_if_{$not}chosen"],
          'answer_feedback'   => check_markup($alternative["feedback_if_{$not}chosen"]['value'], $alternative["feedback_if_{$not}chosen"]['format'], FALSE),
          'question_feedback' => 'Question feedback',
          'solution'          => $alternative['score_if_chosen'] > 0 ? quiz_icon('should') : ($simple_scoring ? quiz_icon('should-not') : ''),
          'quiz_feedback'     => t('@quiz feedback', array('@quiz' => QUIZZ_NAME)),
      );
    }

    return $data;
  }

  /**
   * Order the alternatives according to the choice order stored in the database
   *
   * @param array $alternatives
   *  The alternatives to be ordered
   */
  protected function orderAlternatives(array &$alternatives) {
    if (!$this->question->choice_random) {
      return;
    }

    $result = db_query(
      'SELECT choice_order
          FROM {quiz_multichoice_user_answers}
          WHERE result_id = :result_id
            AND question_qid = :question_qid
            AND question_vid = :question_vid', array(
        ':result_id'    => $this->result_id,
        ':question_qid' => $this->question->qid,
        ':question_vid' => $this->question->vid))->fetchField();
    if (!$result) {
      return;
    }
    $order = explode(',', $result);
    $newAlternatives = array();
    foreach ($order as $value) {
      foreach ($alternatives as $alternative) {
        if ($alternative['id'] == $value) {
          $newAlternatives[] = $alternative;
          break;
        }
      }
    }
    $alternatives = $newAlternatives;
  }

}
