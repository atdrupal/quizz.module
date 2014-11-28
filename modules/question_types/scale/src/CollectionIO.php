<?php

namespace Drupal\scale;

class CollectionIO {

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

}
