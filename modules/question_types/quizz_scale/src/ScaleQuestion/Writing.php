<?php

namespace Drupal\quizz_scale\ScaleQuestion;

use Drupal\quizz_scale\Entity\CollectionController;
use Drupal\quiz_question\Entity\Question;

/**
 * Helper class to write question's alternatives
 */
class Writing {

  private $controller;

  public function __construct(CollectionController $controller) {
    $this->controller = $controller;
  }

  /**
   * Stores the answer collection to the database, or identifies an existing collection.
   *
   * We try to reuse answer collections as much as possible to minimize the amount of rows in the database,
   * and thereby improving performance when surveys are beeing taken.
   *
   * @param bool $is_new - the question is beeing inserted(not updated)
   * @param $in_alternatives - the alternatives array to be saved.
   * @param $preset - 1 | 0 = preset | not preset
   * @return int Answer collection id
   */
  public function write(Question $question, $is_new, array $in_alternatives, $preset = NULL) {
    global $user;

    $alternatives = array();
    for ($i = 0; $i < $question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($in_alternatives['alternative' . $i]) && drupal_strlen($in_alternatives['alternative' . $i]) > 0) {
        $alternatives[] = $in_alternatives['alternative' . $i];
      }
    }

    // If an identical answer collection already exists
    if ($collection_id = $this->findCollectionId($question->type, $alternatives)) {
      if ($preset == 1) {
        $this->controller->changeOwner($collection_id, $user->uid);
      }

      // We try to delete the old answer collection
      if (!$is_new & !empty($question->{0})) {
        $collection_id_to_delete = $question->{0}->answer_collection_id;
        if ($collection_id_to_delete != $collection_id) {
          $this->controller->deleteCollectionIfNotUsed($collection_id_to_delete, 1);
        }
      }

      return $collection_id;
    }

    // Register a new answer collection
    $collection = entity_create('scale_collection', array('for_all' => 1, 'uid' => $preset ? $user->uid : NULL));
    $collection->insertAlternatives($alternatives);

    return $collection->id;
  }

  /**
   * Finds out if a collection already exists.
   *
   * @param string $question_type
   * @param string[] $alternatives
   * @param int $collection_id
   * @param int $last_id - The id of the last alternative we compared with.
   * @return bool
   */
  private function findCollectionId($question_type, array $alternatives, $collection_id = NULL, $last_id = NULL) {
    $_alternatives = isset($collection_id) ? $alternatives : array_reverse($alternatives);

    // Find all answers identical to the next answer in $alternatives
    $select = db_select('quiz_scale_answer', 'answer');
    $select->fields('answer', array('id', 'answer_collection_id'));
    $select->condition('answer.answer', array_pop($_alternatives));

    // Filter on collection id
    if (isset($collection_id)) {
      $select->condition('answer.answer_collection_id', $collection_id);
    }

    // Filter on alternative id(If we are investigating a specific collection,
    // the alternatives needs to be in a correct order)
    if (isset($last_id)) {
      $select->condition('id', $last_id + 1);
    }

    if (!$res_o = $select->execute()->fetch()) {
      return FALSE;
    }

    // If all alternatives has matched make sure the collection we are comparing
    // against in the database doesn't have more alternatives.
    if (count($_alternatives) == 0) {
      $res_o2 = db_query(
        'SELECT * FROM {quiz_scale_answer}
          WHERE answer_collection_id = :answer_collection_id AND id = :id', array(
          ':answer_collection_id' => $collection_id,
          ':id'                   => ($last_id + 2)
        ))->fetch();
      return ($res_o2) ? FALSE : $collection_id;
    }

    // Do a recursive call to this function on all answer collection candidates
//    do {
//      if ($collection_id = $this->findCollectionId($question_type, $_alternatives, $res_o->answer_collection_id, $res_o->id)) {
//        return $collection_id;
//      }
//    } while ($res_o = $res->fetch());

    return FALSE;
  }

}
