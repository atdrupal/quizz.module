<?php

namespace Drupal\scale\Form\ConfigForm;

use Drupal\scale\CollectionIO;
use Drupal\scale\ScaleQuestion;
use stdClass;

class FormSubmit {

  /** @var CollectionIO */
  private $collectionIO;

  public function __construct() {
    $this->collectionIO = new CollectionIO();
  }

  public function submit($form, &$form_state) {
    global $user;

    $changed = 0;
    $deleted = 0;

    foreach ($form_state['values'] as $key => $alternatives) {
      if ($col_id = $this->getColumnId($key)) {
        $this->doSubmit($col_id, $alternatives, $changed, $deleted);
      }
    }

    if ($changed > 0) {
      drupal_set_message(t('!changed presets have been changed.', array('!changed' => $changed)));
    }

    if ($deleted > 0) {
      drupal_set_message(t('!deleted presets have been deleted.', array('!deleted' => $deleted)));
    }
  }

  private function doSubmit($column_id, $alternatives, &$changed, &$deleted) {
    $plugin = new ScaleQuestion(new stdClass());
    $plugin->initUtil($column_id);
    switch ($alternatives['to-do']) { // @todo: Rename to-do to $op
      case 0: // Save, but don't change
      case 1: // Save and change existing questions
        if (FALSE !== $this->doSubmitDelete($plugin, $alternatives, $column_id)) {
          $changed++;
        }
        break;

      // Delete
      case 2:
        if (!$got_deleted = $plugin->deleteCollectionIfNotUsed($column_id)) {
          $this->collectionIO->unpresetCollection($column_id, $user->uid);
        }
        $deleted++;
        break;

      case 3:
        $this->doSubmitNew($plugin, $alternatives);
        break;
    }
  }

  private function doSubmitDelete(ScaleQuestion $plugin, $alternatives, $column_id) {
    global $user;

    $new_col_id = $plugin->saveAnswerCollection(FALSE, $alternatives, 1);
    if (isset($alternatives['for_all'])) {
      $this->collectionIO->setForAll($new_col_id, $alternatives['for_all']);
    }

    if ($new_col_id == $column_id) {
      return FALSE;
    }

    // We save the changes, but don't change existing questions
    if ($alternatives['to-do'] == 0) {
      // The old version of the collection shall not be a preset anymore
      $this->collectionIO->unpresetCollection($column_id, $user->uid);
      // If the old version of the collection doesn't belong to any questions it is safe to delete it.
      $plugin->deleteCollectionIfNotUsed($column_id);

      if (isset($alternatives['for_all'])) {
        $this->collectionIO->setForAll($new_col_id, $alternatives['for_all']);
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
        ->condition('answer_collection_id', $column_id)
        ->condition('uid', $user->uid)
        ->execute();
      $plugin->deleteCollectionIfNotUsed($column_id);
    }
  }

  private function doSubmitNew(ScaleQuestion $plugin, $alternatives) {
    if (drupal_strlen($alternatives['alternative0']) > 0) {
      $new_col_id = $plugin->saveAnswerCollection(FALSE, $alternatives, 1);
      $this->collectionIO->setForAll($new_col_id, $alternatives['for_all']);
      drupal_set_message(t('New preset has been added'));
    }
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

}
