<?php

namespace Drupal\quizz_scale\Entity;

use DatabaseTransaction;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\Entity\QuestionType;
use EntityAPIControllerExportable;

class CollectionController extends EntityAPIControllerExportable {

  public function load($ids = array(), $conditions = array()) {
    $collections = parent::load($ids, $conditions);

    if (!empty($collections)) {
      $alternatives = db_select('quiz_scale_answer')
        ->fields('quiz_scale_answer')
        ->condition('answer_collection_id', array_keys($collections))
        ->execute()
        ->fetchAll();

      foreach ($alternatives as $alternative) {
        $collections[$alternative->answer_collection_id]->alternatives[$alternative->id] = check_plain($alternative->answer);
      }
    }

    return $collections;
  }

  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    $return = parent::delete($ids, $transaction);

    // Delete alternatives
    db_delete('quiz_scale_answer')
      ->condition('answer_collection_id', $ids)
      ->execute();

    return $return;
  }

  /**
   * Get all available presets for a user.
   *
   * @param string $question_type
   * @param int $uid
   * @param bool $with_defaults
   * @return Collection[]
   */
  public function getPresetCollections($question_type, $uid, $with_defaults = FALSE) {
    $select = db_select('quiz_scale_collections', 'collection');
    $select->fields('collection', array('id'));
    $select->condition('question_type', $question_type);

    if (!$with_defaults) {
      $select->condition('collection.uid', $uid);
    }
    else {
      $select->condition(
        db_or()
          ->condition('collection.uid', $uid)
          ->condition('collection.for_all', 1)
      );
    }

    if ($collection_ids = $select->execute()->fetchCol()) {
      return $this->load($collection_ids);
    }

    return array();
  }

  /**
   * Make sure an answer collection isn't a preset for a given user.
   *
   * @param int $collection_id
   */
  public function unpresetCollection($collection_id) {
    $collection = quizz_scale_collection_entity_load($collection_id);
    $collection->for_all = 0;
    $collection->save();
  }

  /**
   * Make an answer collection (un)available for all question creators.
   *
   * @param int $collection_id
   * @param bool $for_all
   */
  public function setForAll($collection_id, $for_all) {
    $collection = quizz_scale_collection_entity_load($collection_id);
    $collection->for_all = $for_all;
    $collection->save();
  }

  /**
   * Add a preset for the current user.
   *
   * @param $collection_id - answer collection id of the collection this user wants to have as a preset
   */
  public function changeOwner($collection_id, $uid) {
    $collection = quizz_scale_collection_entity_load($collection_id);
    if ($uid != $collection->uid) {
      $collection->uid = $uid;
      $collection->save();
    }
  }

  /**
   * Finds out if a collection already exists.
   *
   * @param $alternatives
   *  This is the collection that will be compared with the database.
   * @param int $collection_id
   *  If we are matching a set of alternatives with a given collection that exists in the database.
   * @param int $last_id - The id of the last alternative we compared with.
   * @return bool
   *  TRUE if the collection exists
   *  FALSE otherwise
   */
  public function findCollectionId(array $alternatives, $collection_id = NULL, $last_id = NULL) {
    $my_alts = isset($collection_id) ? $alternatives : array_reverse($alternatives);

    // Find all answers identical to the next answer in $alternatives
    $sql = 'SELECT id, answer_collection_id FROM {quiz_scale_answer} WHERE answer = :answer';
    $args[':answer'] = array_pop($my_alts);
    // Filter on collection id
    if (isset($collection_id)) {
      $sql .= ' AND answer_collection_id = :acid';
      $args[':acid'] = $collection_id;
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


    // If all alternatives has matched make sure the collection we are comparing
    // against in the database doesn't have more alternatives.
    if (count($my_alts) == 0) {
      $res_o2 = db_query(
        'SELECT * FROM {quiz_scale_answer}
          WHERE answer_collection_id = :answer_collection_id AND id = :id', array(
          ':answer_collection_id' => $collection_id,
          ':id'                   => ($last_id + 2)
        ))->fetch();
      return ($res_o2) ? FALSE : $collection_id;
    }

    // Do a recursive call to this function on all answer collection candidates
    do {
      if ($collection_id = $this->findCollectionId($my_alts, $res_o->answer_collection_id, $res_o->id)) {
        return $collection_id;
      }
    } while ($res_o = $res->fetch());

    return FALSE;
  }

  /**
   * Deletes an answer collection if it isn't beeing used.
   *
   * @param $collection_id
   * @param $accept
   *  If collection is used more than this many times we keep it.
   * @return
   *  true if deleted, false if not deleted.
   */
  public function deleteCollectionIfNotUsed($collection_id, $accept = 0) {
    // Check if the collection is someones preset. If it is we can't delete it.
    $sql_1 = 'SELECT 1 FROM {quiz_scale_collections} WHERE id = :cid AND uid <> 0';
    if (db_query($sql_1, array(':id' => $collection_id))->fetchField()) {
      return FALSE;
    }

    // Check if the collection is a global preset. If it is we can't delete it.
    $sql_2 = 'SELECT 1 FROM {quiz_scale_collections} WHERE id = :id AND for_all = 1';
    if (db_query($sql_2, array(':id' => $collection_id))->fetchField()) {
      return FALSE;
    }

    // Check if the collection is used in an existing question. If it is we can't delete it.
    $sql_3 = 'SELECT COUNT(*) FROM {quiz_scale_properties} WHERE answer_collection_id = :acid';
    $count = db_query($sql_3, array(':acid' => $collection_id))->fetchField();

    // We delete the answer collection if it isnt beeing used by enough questions
    if ($count <= $accept) {
      entity_delete('scale_collection', $collection_id);
      return TRUE;
    }

    return FALSE;
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
   * @return int Answer collection id
   */
  public function saveQuestionAlternatives(Question $question, $is_new, array $alt_input = NULL, $preset = NULL) {
    global $user;

    if (!isset($alt_input)) {
      $alt_input = get_object_vars($question);
    }

    if (!isset($preset) && isset($question->save)) {
      $preset = $question->save;
    }

    $alternatives = array();
    for ($i = 0; $i < $question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($alt_input['alternative' . $i]) && drupal_strlen($alt_input['alternative' . $i]) > 0) {
        $alternatives[] = $alt_input['alternative' . $i];
      }
    }

    // If an identical answer collection already exists
    if ($collection_id = quizz_scale_collection_controller()->findCollectionId($alternatives)) {
      if ($preset == 1) {
        quizz_scale_collection_controller()->changeOwner($collection_id, $user->uid);
      }

      if (!$is_new || $this->util) {
        $col_to_delete = $this->util ? $this->col_id : $question->{0}->answer_collection_id;

        // We try to delete the old answer collection
        if ($col_to_delete != $collection_id) {
          quizz_scale_collection_controller()->deleteCollectionIfNotUsed($col_to_delete, 1);
        }
      }
      return $collection_id;
    }

    // Register a new answer collection
    $collection = entity_create('scale_collection', array('for_all' => 1, 'uid' => 1 == $preset ? $user->uid : NULL));
    $collection->insertAlternatives($alternatives);

    return $collection->id;
  }

  public function generateDefaultCollections(QuestionType $question_type) {
    $alternatives = array(
        array('Always', 'Very often', 'Some times', 'Rarely', 'Very rarely', 'Never'),
        array('Excellent', 'Very good', 'Good', 'Ok', 'Poor', 'Very poor'),
        array('Totally agree', 'Agree', 'Not sure', 'Disagree', 'Totally disagree'),
        array('Very important', 'Important', 'Moderately important', 'Less important', 'Least important'),
    );

    /* @var $collection Collection */
    foreach ($alternatives as $_alternatives) {
      $collection = entity_create('scale_collection', array(
          'question_type' => $question_type->type,
          'for_all'       => TRUE,
          'uid'           => 1
      ));
      $collection->save();
      $collection->insertAlternatives($_alternatives);
    }
  }

}
