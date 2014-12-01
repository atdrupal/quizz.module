<?php

namespace Drupal\scale\Entity;

use EntityAPIControllerExportable;

class CollectionController extends EntityAPIControllerExportable {

  public function load($ids = array(), $conditions = array()) {
    $collections = parent::load($ids, $conditions);

    if (!empty($collections)) {
      $options = db_select('quiz_scale_answer')
        ->fields('quiz_scale_answer')
        ->condition('answer_collection_id', array_keys($collections))
        ->execute()
        ->fetchAll();

      foreach ($options as $option) {
        $collections[$option->answer_collection_id]->options[$option->id] = $option->answer;
      }
    }

    return $collections;
  }

}
