<?php

namespace Drupal\quizz_scale\Form\ConfigForm;

use Drupal\quizz_scale\CollectionIO;
use Drupal\quizz_scale\ScaleQuestion;
use stdClass;

class FormSubmit {

  /** @var CollectionIO */
  private $collectionIO;

  public function __construct() {
    $this->collectionIO = new CollectionIO();
  }

  /**
   * Searches a string for the answer collection id
   *
   * @param $string
   * @return int
   */
  private function findCollectionId($string) {
    $matches = array();
    $success = preg_match('/^collection([0-9]{1,}|new)$/', $string, $matches);
    return ($success > 0) ? $matches[1] : FALSE;
  }

  public function submit($form, &$form_state) {
    $changed = $deleted = 0;
    foreach ($form_state['values'] as $key => $alternatives) {
      if ($col_id = $this->findCollectionId($key)) {
        $this->doSubmitAlternatives($col_id, $alternatives, $changed, $deleted);
      }
    }

    if ($changed > 0) {
      drupal_set_message(t('!changed presets have been changed.', array('!changed' => $changed)));
    }

    if ($deleted > 0) {
      drupal_set_message(t('!deleted presets have been deleted.', array('!deleted' => $deleted)));
    }
  }

  private function doSubmitAlternatives($collection_id, $alternatives, &$changed, &$deleted) {
    $plugin = new ScaleQuestion(new stdClass());
    $plugin->initUtil($collection_id);
    switch ($alternatives['to-do']) { // @todo: Rename to-do to $op
      case 0: // Save, but don't change
      case 1: // Save and change existing questions
        if (FALSE !== $this->doSubmitDelete($plugin, $alternatives, $collection_id)) {
          $changed++;
        }
        break;

      // Delete
      case 2:
        if (!$got_deleted = quizz_scale_collection_controller()->deleteCollectionIfNotUsed($collection_id)) {
          quizz_scale_collection_controller()->unpresetCollection($collection_id);
        }
        $deleted++;
        break;

      case 3:
        $this->doSubmitNew($plugin, $alternatives);
        break;
    }
  }

  private function doSubmitDelete(ScaleQuestion $plugin, $alternatives, $collection_id) {
    global $user;

    $new_collection_id = $this->collectionIO->saveAnswerCollection($plugin->question, FALSE, $alternatives, 1);
    if (isset($alternatives['for_all'])) {
      quizz_scale_collection_controller()->setForAll($new_collection_id, $alternatives['for_all']);
    }

    if ($new_collection_id == $collection_id) {
      return FALSE;
    }

    // We save the changes, but don't change existing questions
    if ($alternatives['to-do'] == 0) {
      // The old version of the collection shall not be a preset anymore
      quizz_scale_collection_controller()->unpresetCollection($collection_id);

      // If the old version of the collection doesn't belong to any questions it is safe to delete it.
      quizz_scale_collection_controller()->deleteCollectionIfNotUsed($collection_id);

      if (isset($alternatives['for_all'])) {
        quizz_scale_collection_controller()->setForAll($new_collection_id, $alternatives['for_all']);
      }
    }
    elseif ($alternatives['to-do'] == 1) {
      // Update all the users questions where the collection is used
      $nids = db_query('SELECT nid FROM {node} WHERE uid = :uid', array(':uid' => 1))->fetchCol();

      db_update('quiz_scale_properties')
        ->fields(array('answer_collection_id' => $new_collection_id))
        ->condition('answer_collection_id', $nids)
        ->execute();

      quizz_scale_collection_controller()->deleteCollectionIfNotUsed($collection_id);
    }
  }

  private function doSubmitNew(ScaleQuestion $plugin, $alternatives) {
    if (drupal_strlen($alternatives['alternative0']) > 0) {
      $collection_id = $plugin->saveAnswerCollection($plugin->question, FALSE, $alternatives, 1);
      quizz_scale_collection_controller()->setForAll($collection_id, $alternatives['for_all']);
      drupal_set_message(t('New preset has been added'));
    }
  }

}
