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

}
