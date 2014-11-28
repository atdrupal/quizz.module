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
       WHERE p.vid = :question_vid
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

  /**
   * Saves one alternative to the database
   *
   * @param $alternative - the alternative(String) to be saved.
   * @param $answer_collection_id - the id of the answer collection this alternative shall belong to.
   */
  public function saveAlternative($alternative, $answer_collection_id) {
    db_insert('quiz_scale_answer')
      ->fields(array(
          'answer_collection_id' => $answer_collection_id,
          'answer'               => $alternative,
      ))
      ->execute();
  }

  /**
   * Add a preset for the current user.
   *
   * @param $column_id - answer collection id of the collection this user wants to have as a preset
   */
  public function setPreset($column_id) {
    $uid = $GLOBALS['user']->uid;

    db_merge('quiz_scale_user')
      ->key(array('uid' => $uid, 'answer_collection_id' => $column_id))
      ->fields(array('uid' => $uid, 'answer_collection_id' => $column_id))
      ->execute();
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
    if ($answer_collection_id = $this->existingCollection($alternatives)) {
      if ($preset == 1) {
        $this->setPreset($answer_collection_id);
      }

      if (!$is_new || $this->util) {
        $col_to_delete = $this->util ? $this->col_id : $question->{0}->answer_collection_id;

        // We try to delete the old answer collection
        if ($col_to_delete != $answer_collection_id) {
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
      $this->saveAlternative($alternatives[$i], $answer_collection_id);
    }
    //db_unlock_tables();

    return $answer_collection_id;
  }

}
