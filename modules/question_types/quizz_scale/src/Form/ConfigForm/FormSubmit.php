<?php

namespace Drupal\quizz_scale\Form\ConfigForm;

use Drupal\quizz_scale\Entity\CollectionController;
use Drupal\quizz_scale\ScaleQuestion;
use stdClass;

class FormSubmit {

  /** @var CollectionController */
  private $controller;

  public function __construct() {
    $this->controller = quizz_scale_collection_controller();
  }

  public function submit($form, &$form_state) {
    $changed = $deleted = 0;
    $plugin = new ScaleQuestion(new stdClass());
    foreach ($form_state['values'] as $key => $alternatives) {
      $matches = array();
      preg_match('/^collection([0-9]{1,}|new)$/', $key, $matches);
      if ($matches && ($collection_id = $matches[1])) {
        $this->doSubmitAlternatives($plugin, $collection_id, $alternatives, $changed, $deleted);
      }
    }

    if ($changed) {
      drupal_set_message(t('!changed presets have been changed.', array('!changed' => $changed)));
    }

    if ($deleted) {
      drupal_set_message(t('!deleted presets have been deleted.', array('!deleted' => $deleted)));
    }
  }

  private function doSubmitAlternatives(ScaleQuestion $plugin, $collection_id, $alternatives, &$changed, &$deleted) {
    switch ($alternatives['to-do']) {
      case 'save_safe': // Save, but don't change
      case 'save': // Save and change existing questions
        if (FALSE !== $this->doSubmitSave($plugin, $alternatives, $collection_id)) {
          $changed++;
        }
        break;

      // Delete
      case 'delete_safe':
        if (!$got_deleted = $this->controller->deleteCollectionIfNotUsed($collection_id)) {
          $this->controller->unpresetCollection($collection_id);
        }
        $deleted++;
        break;

      case 'save_new':
        $this->doSubmitNew($plugin, $alternatives);
        break;
    }
  }

  private function doSubmitSave(ScaleQuestion $plugin, $alternatives, $collection_id) {
    $new_collection_id = $this->controller
      ->getWriting()
      ->write($plugin->question, FALSE, $alternatives, 1, isset($alternatives['for_all']) ? $alternatives['for_all'] : NULL);

    if ($new_collection_id == $collection_id) {
      return FALSE;
    }

    if (FALSE === $this->doSubmitPresave($plugin, $collection_id)) {
      return FALSE;
    }

    // We save the changes, but don't change existing questions
    if ('save_safe' === $alternatives['to-do']) {
      $this->doSubmitSaveSafe($collection_id, $new_collection_id, $alternatives);
    }

    if ('save' === $alternatives['to-do']) {
      $this->doSubmitSaveNormal($collection_id, $new_collection_id);
    }
  }

  private function doSubmitSaveSafe($collection_id, $new_collection_id, $alternatives) {
    // The old version of the collection shall not be a preset anymore
    $this->controller->unpresetCollection($collection_id);

    // If the old version of the collection doesn't belong to any questions it is safe to delete it.
    $this->controller->deleteCollectionIfNotUsed($collection_id);

    if (isset($alternatives['for_all'])) {
      $this->controller->setForAll($new_collection_id, $alternatives['for_all']);
    }
  }

  private function doSubmitSaveNormal($collection_id, $new_collection_id) {
    // Update all the users questions where the collection is used
    $question_ids = db_query('SELECT qid FROM {quiz_question} WHERE uid = :uid', array(':uid' => 1))->fetchCol();

    db_update('quiz_scale_properties')
      ->fields(array('answer_collection_id' => $new_collection_id))
      ->condition('answer_collection_id', $question_ids)
      ->execute();

    $this->controller->deleteCollectionIfNotUsed($collection_id);
  }

  private function doSubmitNew(ScaleQuestion $plugin, $alternatives) {
    if (drupal_strlen($alternatives['alternative0']) > 0) {
      $collection_id = $plugin->saveAnswerCollection($plugin->question, FALSE, $alternatives, 1);
      $this->controller->setForAll($collection_id, $alternatives['for_all']);
      drupal_set_message(t('New preset has been added'));
    }
  }

}
