<?php

namespace Drupal\scale;

use stdClass;

class CollectionIO {

  public function loadQuestionAlternatives($question_id) {
    $properties = array();

    $query = db_query(
      'SELECT answer.id, answer.answer, answer.answer_collection_id
       FROM {quiz_scale_properties} p
         JOIN {quiz_scale_answer} answer ON (p.answer_collection_id = answer.answer_collection_id)
       WHERE vid = :question_vid
         ORDER BY answer.id', array(':question_vid' => $question_id));
    foreach ($query as $property) {
      $properties[] = $property;
    }

    return $properties;
  }

  /**
   * Get all available presets for the current user.
   *
   * @param $with_defaults
   * @return
   *  array holding all the preset collections as an array of objects.
   *  each object in the array has the following properties:
   *   ->alternatives(array)
   *   ->name(string)
   *   ->for_all(int, 0|1)
   */
  public function getPresetCollections($with_defaults = FALSE) {
    global $user;

    $collections = array(); // array holding data for each collection
    $scale_element_names = array();
    $sql = 'SELECT DISTINCT ac.id AS answer_collection_id, a.answer, ac.for_all
            FROM {quiz_scale_user} au
            JOIN {quiz_scale_answer_collection} ac ON(au.answer_collection_id = ac.id)
            JOIN {quiz_scale_answer} a ON(a.answer_collection_id = ac.id)
            WHERE au.uid = :uid';
    if ($with_defaults) {
      $sql .= ' OR ac.for_all = 1';
    }
    $sql .= ' ORDER BY au.answer_collection_id, a.id';
    $res = db_query($sql, array(':uid' => $user->uid));
    $col_id = NULL;

    // Populate the $collections array
    while (true) {
      if (!($res_o = $res->fetch()) || ($res_o->answer_collection_id != $col_id)) {
        // We have gone through all elements for one answer collection,
        // and needs to store the answer collection name and id in the options array…
        if (isset($col_id)) {
          $num_scale_elements = count($collections[$col_id]->alternatives);
          $collections[$col_id]->name = check_plain($collections[$col_id]->alternatives[0] . ' - ' . $collections[$col_id]->alternatives[$num_scale_elements - 1] . ' (' . $num_scale_elements . ')');
        }

        // Break the loop if there are no more answer collections to process
        if (!$res_o) {
          break;
        }

        // Init the next collection in the $collections array
        $col_id = $res_o->answer_collection_id;
        if (!isset($collections[$col_id])) {
          $collections[$col_id] = new stdClass();
          $collections[$col_id]->alternatives = array();
          $collections[$col_id]->for_all = $res_o->for_all;
        }
      }
      $collections[$col_id]->alternatives[] = check_plain($res_o->answer);
    }
    return $collections;
  }

  /**
   * Make sure an answer collection isn't a preset for a given user.
   *
   * @param $column_id
   *  Answer_collection_id
   * @param $user_id
   */
  public function unpresetCollection($column_id, $user_id) {
    db_delete('quiz_scale_user')
      ->condition('answer_collection_id', $column_id)
      ->condition('uid', $user_id)
      ->execute();

    if (user_access('Edit global presets')) {
      db_update('quiz_scale_answer_collection')
        ->fields(array('for_all' => 0))
        ->execute();
    }
  }

  /**
   * Make an answer collection (un)available for all question creators.
   *
   * @param int $new_column_id
   *  Answer collection id
   * @param int $for_all
   *  0 if not for all,
   *  1 if for all
   */
  public function setForAll($new_column_id, $for_all) {
    db_update('quiz_scale_answer_collection')
      ->fields(array('for_all' => $for_all))
      ->condition('id', $new_column_id)
      ->execute();
  }

  public function deleteQuestionProperties(\Drupal\quiz_question\Entity\Question $question, $single_revision) {
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

}
