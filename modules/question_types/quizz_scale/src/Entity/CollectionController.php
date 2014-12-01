<?php

namespace Drupal\quizz_scale\Entity;

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

  /**
   * Get all available presets for a user.
   *
   * @param int $uid
   * @param bool $with_defaults
   * @return \Drupal\quizz_scale\Entity\Collection[]
   */
  public function getPresetCollections($uid, $with_defaults = FALSE) {
    $select = db_select('quiz_scale_collections', 'collection');
    $select->fields('collection', array('id'));

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
      return entity_load('scale_collection', $collection_ids);
    }

    return array();
  }

  /**
   * Make sure an answer collection isn't a preset for a given user.
   *
   * @param int $collection_id
   */
  public function unpresetCollection($collection_id) {
    $collection = quizz_scale_collection_load($collection_id);
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
    $collection = quizz_scale_collection_load($collection_id);
    $collection->for_all = $for_all;
    $collection->save();
  }

  /**
   * Add a preset for the current user.
   *
   * @param $collection_id - answer collection id of the collection this user wants to have as a preset
   */
  public function changeOwner($collection_id, $uid) {
    $collection = quizz_scale_collection_load($collection_id);
    $collection->uid = $uid;
    $collection->save();
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
  private function findCollectionId(array $alternatives, $collection_id = NULL, $last_id = NULL) {
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

}
