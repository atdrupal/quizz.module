<?php

/**
 * Handles the scale collection form.
 */
function scale_manage_collection_form_submit($form, &$form_state) {
  global $user;

  $changed = 0;
  $deleted = 0;

  foreach ($form_state['values'] as $key => $alternatives) {
    if ($col_id = _scale_get_col_id($key)) {
      $s_q = new ScaleQuestion(new stdClass());
      $s_q->initUtil($col_id);
      switch ($alternatives['to-do']) { // @todo: Rename to-do to $op
        case 0: //Save, but don't change
        case 1: //Save and change existing questions
          $new_col_id = $s_q->saveAnswerCollection(FALSE, $alternatives, 1);
          if (isset($alternatives['for_all'])) {
            _scale_set_for_all($new_col_id, $alternatives['for_all']);
          }
          if ($new_col_id == $col_id) {
            break;
          }
          $changed++;
          // We save the changes, but don't change existing questions
          if ($alternatives['to-do'] == 0) {
            // The old version of the collection shall not be a preset anymore
            _scale_unpreset_collection($col_id, $user->uid);
            // If the old version of the collection doesn't belong to any questions it is safe to delete it.
            $s_q->deleteCollectionIfNotUsed($col_id);

            if (isset($alternatives['for_all'])) {
              _scale_set_for_all($new_col_id, $alternatives['for_all']);
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
        case 2: //Delete
          $got_deleted = $s_q->deleteCollectionIfNotUsed($col_id);
          if (!$got_deleted) {
            _scale_unpreset_collection($col_id, $user->uid);
          }
          $deleted++;
          break;
        case 3: //New
          if (drupal_strlen($alternatives['alternative0']) > 0) {
            $new_col_id = $s_q->saveAnswerCollection(FALSE, $alternatives, 1);
            _scale_set_for_all($new_col_id, $alternatives['for_all']);
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
 * Validates the scale collection form
 */
function scale_manage_collection_form_validate($form, &$form_state) {
  // If the user is trying to create a new collection
  if (drupal_strlen($form_state['values']['collectionnew']['alternative0']) > 0) {
    // If the new collection don't have two alternatives
    if (!drupal_strlen($form_state['values']['collectionnew']['alternative1'])) {
      // This can't be replaced by adding #required to the form elements. If we
      // did so we would always have to create a new collection when we press submit
      form_set_error('collectionnew][alternative1', t('New preset must have atleast two alternatives.'));
    }
  }
}
