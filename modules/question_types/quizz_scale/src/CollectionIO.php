<?php

namespace Drupal\quizz_scale;

use Drupal\quiz_question\Entity\Question;

class CollectionIO {

  public function deleteQuestionProperties(Question $question, $single_revision) {
    if ($single_revision) {
      db_delete('quiz_scale_user_answers')
        ->condition('question_qid', $question->qid)
        ->condition('question_vid', $question->vid)
        ->execute();

      db_delete('quiz_scale_properties')
        ->condition('qid', $question->qid)
        ->condition('vid', $question->vid)
        ->execute();
    }
    else {
      db_delete('quiz_scale_user_answers')
        ->condition('question_qid', $question->qid)
        ->execute();

      db_delete('quiz_scale_properties')
        ->condition('qid', $question->qid)
        ->execute();
    }
    $this->deleteCollectionIfNotUsed($question->{0}->answer_collection_id, 0);
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
  public function saveAnswerCollection(Question $question, $is_new, array $alt_input = NULL, $preset = NULL) {
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
          $this->deleteCollectionIfNotUsed($col_to_delete, 1);
        }
      }
      return $collection_id;
    }

    // Register a new answer collection
    $collection = entity_create('scale_collection', array('for_all' => 1, 'uid' => 1 == $preset ? $user->uid : NULL));
    $collection->insertAlternatives($alternatives);

    return $collection->id;
  }

}
