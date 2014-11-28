<?php

namespace Drupal\scale\Form\ConfigForm;

class FormSubmit {

  public function submit($form, &$form_state) {
    global $user;

    $changed = 0;
    $deleted = 0;

    foreach ($form_state['values'] as $key => $alternatives) {
      if ($col_id = $this->getColumnId($key)) {
        $s_q = new ScaleQuestion(new stdClass());
        $s_q->initUtil($col_id);
        switch ($alternatives['to-do']) { // @todo: Rename to-do to $op
          case 0: //Save, but don't change
          case 1: //Save and change existing questions
            $new_col_id = $s_q->saveAnswerCollection(FALSE, $alternatives, 1);
            if (isset($alternatives['for_all'])) {
              $this->setForAll($new_col_id, $alternatives['for_all']);
            }
            if ($new_col_id == $col_id) {
              break;
            }
            $changed++;
            // We save the changes, but don't change existing questions
            if ($alternatives['to-do'] == 0) {
              // The old version of the collection shall not be a preset anymore
              $this->unpresetCollection($col_id, $user->uid);
              // If the old version of the collection doesn't belong to any questions it is safe to delete it.
              $s_q->deleteCollectionIfNotUsed($col_id);

              if (isset($alternatives['for_all'])) {
                $this->setForAll($new_col_id, $alternatives['for_all']);
              }
            }
            elseif ($alternatives['to-do'] == 1) {
              // Update all the users questions where the collection is used
              $nids = db_query('SELECT nid FROM {node} WHERE uid = :uid', array(':uid' => 1))->fetchCol();
              db_update('quiz_scale_properties')
                ->fields(array('answer_collection_id' => $new_col_id))
                ->condition('answer_collection_id', $nids)
                ->execute();

              db_delete('quiz_scale_user')
                ->condition('answer_collection_id', $col_id)
                ->condition('uid', $user->uid)
                ->execute();
              $s_q->deleteCollectionIfNotUsed($col_id);
            }
            break;

          case 2: // Delete
            $got_deleted = $s_q->deleteCollectionIfNotUsed($col_id);
            if (!$got_deleted) {
              $this->unpresetCollection($col_id, $user->uid);
            }
            $deleted++;
            break;

          case 3: // New
            if (drupal_strlen($alternatives['alternative0']) > 0) {
              $new_col_id = $s_q->saveAnswerCollection(FALSE, $alternatives, 1);
              $this->setForAll($new_col_id, $alternatives['for_all']);
              drupal_set_message(t('New preset has been added'));
            }
            break;
        }
      }
    }

    if ($changed > 0) {
      drupal_set_message(t('!changed presets have been changed.', array('!changed' => $changed)));
    }

    if ($deleted > 0) {
      drupal_set_message(t('!deleted presets have been deleted.', array('!deleted' => $deleted)));
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
  private function setForAll($new_column_id, $for_all) {
    db_update('quiz_scale_answer_collection')
      ->fields(array('for_all' => $for_all))
      ->condition('id', $new_column_id)
      ->execute();
  }

  /**
   * Searches a string for the answer collection id
   *
   * @param $string
   * @return answer collection id
   */
  private function getColumnId($string) {
    $res = array();
    $success = preg_match('/^collection([0-9]{1,}|new)$/', $string, $res);
    return ($success > 0) ? $res[1] : FALSE;
  }

  /**
   * Make sure an answer collection isn't a preset for a given user.
   *
   * @param $col_id
   *  Answer_collection_id
   * @param $user_id
   */
  private function unpresetCollection($col_id, $user_id) {
    db_delete('quiz_scale_user')
      ->condition('answer_collection_id', $col_id)
      ->condition('uid', $user_id)
      ->execute();
    if (user_access('Edit global presets')) {
      db_update('quiz_scale_answer_collection')
        ->fields(array('for_all' => 0))
        ->execute();
    }
  }

}
