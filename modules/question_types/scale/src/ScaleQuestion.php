<?php

namespace Drupal\scale;

use Drupal\quiz_question\QuestionPlugin;
use Drupal\quizz\Entity\Answer;
use Drupal\scale\CollectionIO;

/**
 * The main classes for the scale question type.
 *
 * These extend code found in quiz_question.classes.inc.
 *
 * Sponsored by: Norwegian Centre for Telemedicine
 * Code: falcon
 *
 * Based on:
 * Other question types in the quiz framework.
 *
 *
 *
 * @file
 * Question type, enabling rapid creation of simple surveys using the quiz framework.
 */
// @todo: We mix the names answer_collection and alternatives. Use either alternative or answer consistently

/**
 * Extension of QuizQuestion.
 */
class ScaleQuestion extends QuestionPlugin {

  /**
   * will be set to true if an instance of this class is used only as a utility.
   *
   * @var bool $util
   */
  protected $util = FALSE;

  /**
   * (answer) Collection id
   * @var int
   */
  protected $col_id = NULL;

  /** @var CollectionIO */
  protected $collection_io;

  public function getCollectionIO() {
    if (NULL === $this->collection_io) {
      $this->collection_io = new CollectionIO();
    }
    return $this->collection_io;
  }

  /**
   * Tells the instance that it is beeing used as a utility.
   *
   * @param $c_id - answer collection id
   */
  public function initUtil($c_id) {
    $this->util = TRUE;
    $this->col_id = $c_id;
  }

  /**
   * Implementation of saveEntityProperties
   *
   * @see QuizQuestion#saveEntityProperties()
   */
  public function saveEntityProperties($is_new = FALSE) {
    $is_new_node = $is_new || $this->question->revision == 1;
    $answer_collection_id = $this->saveAnswerCollection($is_new_node);
    // Save the answer collection as a preset if the save preset option is checked
    if (!empty($this->question->save)) {
      $this->setPreset($answer_collection_id);
    }
    if ($is_new_node) {
      $id = db_insert('quiz_scale_properties')
        ->fields(array(
            'qid'                  => $this->question->qid,
            'vid'                  => $this->question->vid,
            'answer_collection_id' => $answer_collection_id,
        ))
        ->execute();
    }
    else {
      db_update('quiz_scale_properties')
        ->fields(array(
            'answer_collection_id' => $answer_collection_id,
        ))
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
  }

  /**
   * Add a preset for the current user.
   *
   * @param $col_id - answer collection id of the collection this user wants to have as a preset
   */
  private function setPreset($col_id) {
    db_merge('quiz_scale_user')
      ->key(array(
          'uid'                  => $GLOBALS['user']->uid,
          'answer_collection_id' => $col_id
      ))
      ->fields(array(
          'uid'                  => $GLOBALS['user']->uid,
          'answer_collection_id' => $col_id
      ))
      ->execute();
  }

  /**
   * Stores the answer collection to the database, or identifies an existing collection.
   *
   * We try to reuse answer collections as much as possible to minimize the amount of rows in the database,
   * and thereby improving performance when surveys are beeing taken.
   *
   * @param $is_new_node - the question is beeing inserted(not updated)
   * @param $alt_input - the alternatives array to be saved.
   * @param $preset - 1 | 0 = preset | not preset
   * @return
   *  Answer collection id
   */
  public function saveAnswerCollection($is_new_node, array $alt_input = NULL, $preset = NULL) {
    global $user;
    if (!isset($alt_input)) {
      $alt_input = get_object_vars($this->question);
    }
    if (!isset($preset) && isset($this->question->save)) {
      $preset = $this->question->save;
    }

    $alternatives = array();
    for ($i = 0; $i < $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($alt_input['alternative' . $i]) && drupal_strlen($alt_input['alternative' . $i]) > 0) {
        $alternatives[] = $alt_input['alternative' . $i];
      }
    }

    // If an identical answer collection already exists
    if ($answer_collection_id = $this->existingCollection($alternatives)) {
      if ($preset == 1) {
        $this->setPreset($answer_collection_id);
      }
      if (!$is_new_node || $this->util) {
        $col_to_delete = $this->util ? $this->col_id : $this->question->{0}->answer_collection_id;
        if ($col_to_delete != $answer_collection_id) {
          // We try to delete the old answer collection
          $this->deleteCollectionIfNotUsed($col_to_delete, 1);
        }
      }
      return $answer_collection_id;
    }
    // Register a new answer collection
    $answer_collection_id = db_insert('quiz_scale_answer_collection')
      ->fields(array('for_all' => 1))
      ->execute();

    // Save as preset if checkbox for preset has been checked
    if ($preset == 1) {
      $id = db_insert('quiz_scale_user')
        ->fields(array(
            'uid'                  => $user->uid,
            'answer_collection_id' => $answer_collection_id,
        ))
        ->execute();
    }
    // Save the alternatives in the answer collection
    //db_lock_table('quiz_scale_answer');
    for ($i = 0; $i < count($alternatives); $i++) {
      $this->saveAlternative($alternatives[$i], $answer_collection_id);
    }
    //db_unlock_tables();
    return $answer_collection_id;
  }

  /**
   * Saves one alternative to the database
   *
   * @param $alternative - the alternative(String) to be saved.
   * @param $answer_collection_id - the id of the answer collection this alternative shall belong to.
   */
  private function saveAlternative($alternative, $answer_collection_id) {
    $id = db_insert('quiz_scale_answer')
      ->fields(array(
          'answer_collection_id' => $answer_collection_id,
          'answer'               => $alternative,
      ))
      ->execute();
  }

  /**
   * Deletes an answer collection if it isn't beeing used.
   *
   * @param $answer_collection_id
   * @param $accept
   *  If collection is used more than this many times we keep it.
   * @return
   *  true if deleted, false if not deleted.
   */
  public function deleteCollectionIfNotUsed($answer_collection_id, $accept = 0) {
    // Check if the collection is someones preset. If it is we can't delete it.
    $count = db_query('SELECT COUNT(*) FROM {quiz_scale_user} WHERE answer_collection_id = :acid', array(':acid' => $answer_collection_id))->fetchField();
    if ($count > 0) {
      return FALSE;
    }

    // Check if the collection is a global preset. If it is we can't delete it.
    $for_all = db_query('SELECT for_all FROM {quiz_scale_answer_collection} WHERE id = :id', array(':id' => $answer_collection_id))->fetchField();
    if ($for_all == 1) {
      return FALSE;
    }

    // Check if the collection is used in an existing question. If it is we can't delete it.
    $count = db_query('SELECT COUNT(*) FROM {quiz_scale_properties} WHERE answer_collection_id = :acid', array(':acid' => $answer_collection_id))->fetchField();

    // We delete the answer collection if it isnt beeing used by enough questions
    if ($count <= $accept) {
      db_delete('quiz_scale_answer_collection')
        ->condition('id', $answer_collection_id)
        ->execute();

      db_delete('quiz_scale_answer')
        ->condition('answer_collection_id', $answer_collection_id)
        ->execute();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Finds out if a collection already exists.
   *
   * @param $alternatives
   *  This is the collection that will be compared with the database.
   * @param $answer_collection_id
   *  If we are matching a set of alternatives with a given collection that exists in the database.
   * @param $last_id - The id of the last alternative we compared with.
   * @return
   *  TRUE if the collection exists
   *  FALSE otherwise
   */
  private function existingCollection(array $alternatives, $answer_collection_id = NULL, $last_id = NULL) {
    $my_alts = isset($answer_collection_id) ? $alternatives : array_reverse($alternatives);

    // Find all answers identical to the next answer in $alternatives
    $sql = 'SELECT id, answer_collection_id FROM {quiz_scale_answer} WHERE answer = :answer';
    $args[':answer'] = array_pop($my_alts);
    // Filter on collection id
    if (isset($answer_collection_id)) {
      $sql .= ' AND answer_collection_id = :acid';
      $args[':acid'] = $answer_collection_id;
    }
    // Filter on alternative id(If we are investigating a specific collection, the alternatives needs to be in a correct order)
    if (isset($last_id)) {
      $sql .= ' AND id = :id';
      $args[':id'] = $last_id + 1;
    }
    $res = db_query($sql, $args);
    if (!$res_o = $res->fetch()) {
      return FALSE;
    }

    /*
     * If all alternatives has matched make sure the collection we are comparing against in the database
     * doesn't have more alternatives.
     */
    if (count($my_alts) == 0) {
      $res_o2 = db_query('SELECT * FROM {quiz_scale_answer}
              WHERE answer_collection_id = :answer_collection_id
              AND id = :id', array(':answer_collection_id' => $answer_collection_id, ':id' => ($last_id + 2)))->fetch();
      return ($res_o2) ? FALSE : $answer_collection_id;
    }

    // Do a recursive call to this function on all answer collection candidates
    do {
      $col_id = $this->existingCollection($my_alts, $res_o->answer_collection_id, $res_o->id);
      if ($col_id) {
        return $col_id;
      }
    } while ($res_o = $res->fetch());
    return FALSE;
  }

  /**
   * Implementation of validateNode
   *
   * @see QuizQuestion#validate()
   */
  public function validate(array &$form) {

  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete()
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    if ($only_this_version) {
      db_delete('quiz_scale_user_answers')
        ->condition('question_qid', $this->question->qid)
        ->condition('question_vid', $this->question->vid)
        ->execute();

      db_delete('quiz_scale_properties')
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
    else {
      db_delete('quiz_scale_user_answers')
        ->condition('question_qid', $this->question->qid)
        ->execute();

      db_delete('quiz_scale_properties')
        ->condition('qid', $this->question->qid)
        ->execute();
    }
    $this->deleteCollectionIfNotUsed($this->question->{0}->answer_collection_id, 0);
  }

  /**
   * Implementation of load
   *
   * @see QuizQuestion#load()
   */
  public function load() {
    if (isset($this->entityProperties)) {
      return $this->entityProperties;
    }
    $props = parent::load();

    $res = db_query('SELECT id, answer, a.answer_collection_id
            FROM {quiz_scale_properties} p
            JOIN {quiz_scale_answer} a ON (p.answer_collection_id = a.answer_collection_id)
            WHERE qid = :qid AND vid = :vid
            ORDER BY a.id', array(':qid' => $this->question->qid, ':vid' => $this->question->vid));
    foreach ($res as $res_o) {
      $props[] = $res_o;
    }
    $this->entityProperties = $props;
    return $props;
  }

  /**
   * Implementation of getEntityView
   *
   * @see QuizQuestion#view()
   */
  public function getEntityView() {
    $content = parent::getEntityView();
    $alternatives = array();
    for ($i = 0; $i < $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->question->{$i}->answer) && drupal_strlen($this->question->{$i}->answer) > 0) {
        $alternatives[] = check_plain($this->question->{$i}->answer);
      }
    }
    $content['answer'] = array(
        '#markup' => theme('scale_answer_node_view', array('alternatives' => $alternatives)),
        '#weight' => 2,
    );
    return $content;
  }

  /**
   * Implementation of getAnsweringForm
   *
   * @see getAnsweringForm($form_state, $result_id)
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $form = parent::getAnsweringForm($form_state, $result_id);
    //$form['#theme'] = 'scale_answering_form';
    $options = array();
    for ($i = 0; $i < $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->question->{$i}) && drupal_strlen($this->question->{$i}->answer) > 0) {
        $options[$this->question->{$i}->id] = check_plain($this->question->{$i}->answer);
      }
    }

    $form = array(
        '#type'    => 'radios',
        '#title'   => t('Choose one'),
        '#options' => $options,
    );
    if (isset($result_id)) {
      $response = new ScaleResponse($result_id, $this->question);
      $form['#default_value'] = $response->getResponse();
    }
    return $form;
  }

  /**
   * Question response validator.
   */
  public function getAnsweringFormValidate(array &$form, array &$form_state = NULL) {
    if ($form_state['values']['question'][$this->question->qid] == '') {
      form_set_error('', t('You must provide an answer.'));
    }
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form = array();

    // Getting presets from the database
    $collections = $this->getCollectionIO()->getPresetCollections(TRUE);

    $options = $this->makeOptions($collections);
    $options['d'] = '-'; // Default
    // We need to add the available preset collections as javascript so that
    // the alternatives can be populated instantly when a
    $jsArray = $this->makeJSArray($collections);

    $form['answer'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Answer'),
        '#description' => t('Provide alternatives for the user to answer.'),
        '#collapsible' => TRUE,
        '#collapsed'   => FALSE,
        '#weight'      => -4,
    );
    $form['answer']['#theme'][] = 'scale_creation_form';
    $form['answer']['presets'] = array(
        '#type'          => 'select',
        '#title'         => t('Presets'),
        '#options'       => $options,
        '#default_value' => 'd',
        '#description'   => t('Select a set of alternatives'),
        '#attributes'    => array('onchange' => 'refreshAlternatives(this)'),
    );
    $max_num_alts = $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10);

    // @TODO: use #attached
    $form['jsArray'] = array(
        '#markup' => "<script type='text/javascript'>$jsArray var scale_max_num_of_alts = $max_num_alts;</script>"
    );
    $form['answer']['alternatives'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Alternatives'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
    );
    for ($i = 0; $i < $max_num_alts; $i++) {
      $form['answer']['alternatives']["alternative$i"] = array(
          '#type'          => 'textfield',
          '#title'         => t('Alternative !i', array('!i' => ($i + 1))),
          '#size'          => 60,
          '#maxlength'     => 256,
          '#default_value' => isset($this->question->{$i}->answer) ? $this->question->{$i}->answer : '',
          '#required'      => $i < 2,
      );
    }
    $form['answer']['alternatives']['save'] = array(// @todo: Rename save to save_as_preset or something
        '#type'          => 'checkbox',
        '#title'         => t('Save as a new preset'),
        '#description'   => t('Current alternatives will be saved as a new preset'),
        '#default_value' => FALSE,
    );
    $form['answer']['manage'] = array('#markup' => l(t('Manage presets'), 'scale/collection/manage'));
    return $form;
  }

  /**
   * Makes options array for form elements.
   *
   * @param $collections
   *  collections array, from getPresetCollections() for instance…
   * @return
   *  #options array.
   */
  private function makeOptions(array $collections = NULL) {
    $options = array();
    foreach ($collections as $col_id => $obj) {
      $options[$col_id] = $obj->name;
    }
    return $options;
  }

  /**
   * Makes a javascript constructing an answer collection array.
   *
   * @param $collections
   *  collections array, from getPresetCollections() for instance…
   * @return
   *  javascript(string)
   */
  private function makeJSArray(array $collections = NULL) {
    $jsArray = 'scaleCollections = new Array();';
    foreach ($collections as $col_id => $obj) {
      if (is_array($collections[$col_id]->alternatives)) {
        $jsArray .= "scaleCollections[$col_id] = new Array();";
        foreach ($collections[$col_id]->alternatives as $alt_id => $text) {
          $jsArray .= "scaleCollections[$col_id][$alt_id] = '" . check_plain($text) . "';";
        }
      }
    }
    return $jsArray;
  }

  /**
   * Implementation of getMaximumScore.
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    // In some use-cases we want to reward users for answering a survey question.
    // This is why 1 is returned and not zero.
    return 1;
  }

}
