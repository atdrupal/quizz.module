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
        $collections[$alternative->answer_collection_id]->alternatives[$alternative->id] = $alternative->answer;
      }
    }

    return $collections;
  }

}
