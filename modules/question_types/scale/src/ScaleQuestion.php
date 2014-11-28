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
      $this->getCollectionIO()->setPreset($answer_collection_id);
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
   * Stores the answer collection to the database, or identifies an existing collection.
   *
   * We try to reuse answer collections as much as possible to minimize the amount of rows in the database,
   * and thereby improving performance when surveys are beeing taken.
   *
   * @param bool $is_new - the question is beeing inserted(not updated)
   * @param $alt_input - the alternatives array to be saved.
   * @param $preset - 1 | 0 = preset | not preset
   * @return
   *  Answer collection id
   */
  public function saveAnswerCollection($is_new, array $alt_input = NULL, $preset = NULL) {
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
        $this->getCollectionIO()->setPreset($answer_collection_id);
      }

      if (!$is_new || $this->util) {
        $col_to_delete = $this->util ? $this->col_id : $this->question->{0}->answer_collection_id;

        // We try to delete the old answer collection
        if ($col_to_delete != $answer_collection_id) {
          $this->getCollectionIO()->deleteCollectionIfNotUsed($col_to_delete, 1);
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
      db_insert('quiz_scale_user')
        ->fields(array(
            'uid'                  => $user->uid,
            'answer_collection_id' => $answer_collection_id,
        ))
        ->execute();
    }

    // Save the alternatives in the answer collection
    //db_lock_table('quiz_scale_answer');
    for ($i = 0; $i < count($alternatives); $i++) {
      $this->getCollectionIO()->saveAlternative($alternatives[$i], $answer_collection_id);
    }
    //db_unlock_tables();

    return $answer_collection_id;
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

    // Filter on alternative id(If we are investigating a specific collection,
    // the alternatives needs to be in a correct order)
    if (isset($last_id)) {
      $sql .= ' AND id = :id';
      $args[':id'] = $last_id + 1;
    }
    $res = db_query($sql, $args);
    if (!$res_o = $res->fetch()) {
      return FALSE;
    }

    /*
     * If all alternatives has matched make sure the collection we are comparing
     * against in the database doesn't have more alternatives.
     */
    if (count($my_alts) == 0) {
      $res_o2 = db_query(
        'SELECT *
          FROM {quiz_scale_answer}
          WHERE answer_collection_id = :answer_collection_id AND id = :id', array(
          ':answer_collection_id' => $answer_collection_id,
          ':id'                   => ($last_id + 2)
        ))->fetch();
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
  public function delete($single_revision = FALSE) {
    parent::delete($single_revision);
    return $this->getCollectionIO()->deleteQuestionProperties($this->question, $single_revision);
  }

  /**
   * Implementation of load
   *
   * @see QuizQuestion#load()
   */
  public function load() {
    if (empty($this->properties)) {
      $this->properties = parent::load();
      foreach ($this->getCollectionIO()->loadQuestionAlternatives($this->question->vid) as $property) {
        $this->properties[] = $property;
      }
    }
    return $this->properties;
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
    $obj = new \Drupal\scale\Form\ScaleQuestionForm($this->question, $this->getCollectionIO());
    return $obj->get($form_state);
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
