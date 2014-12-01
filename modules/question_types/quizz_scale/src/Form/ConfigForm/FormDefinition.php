<?php

namespace Drupal\quizz_scale\Form\ConfigForm;

use Drupal\quiz_question\Entity\QuestionType;
use Drupal\quizz_scale\CollectionIO;
use stdClass;

class FormDefinition {

  /** @var QuestionType */
  private $question_type;

  public function __construct(QuestionType $question_type) {
    $this->question_type = $question_type;
  }

  public function get() {
    $form = array('#validate' => array('scale_config_validate'));

    $form['scale_max_num_of_alts'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Maximum number of alternatives allowed'),
        '#default_value' => $this->question_type->getConfig('scale_max_num_of_alts', 10),
    );

    $form['#validate'][] = 'scale_manage_collection_form_validate';
    $form['collections'] = array(
        '#tree'     => TRUE,
        '#type'     => 'vertical_tabs',
        '#prefix'   => '<h3>' . t('Collections') . '</h3>',
        '#attached' => '',
      ) + $this->getCollections();

    return $form;
  }

  /**
   * Form for changing and deleting the current users preset answer collections.
   *
   * Users with the Edit global presets permissions can also add new global
   * presets here.
   */
  private function getCollections() {
    $collectionIO = new CollectionIO();
    $collections = $collectionIO->getPresetCollections();

    // If user is allowed to edit global answer collections he is also allowed
    // to add new global presets
    $_collection = new stdClass();
    $_collection->for_all = 1;
    $_collection->name = t('New global collection(available to all users)');
    $collections['new'] = $_collection;

    if (count($collections) == 0) {
      $form['no_col']['#markup'] = t("You don't have any preset collections.");
      return $form;
    }

    // Populate the form
    foreach (array_keys($collections) as $id) {
      $this->getCollection($form, $collections[$id], $id);
    }

    return $form;
  }

  private function getCollection(&$form, $collection, $id) {
    $form["collection{$id}"] = array(
        '#type'        => 'fieldset',
        '#title'       => $collection->name,
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
        '#group'       => 'scale_manage_collection_form',
    );

    $alternatives = isset($collection->alternatives) ? $collection->alternatives : array();
    for ($i = 0; $i < variable_get('scale_max_num_of_alts', 10); $i++) {
      $form["collection$id"]["alternative{$i}"] = array(
          '#title'         => t('Alternative !i', array('!i' => ($i + 1))),
          '#size'          => 60,
          '#maxlength'     => 256,
          '#type'          => 'textfield',
          '#default_value' => isset($alternatives[$i]) ? $alternatives[$i] : '',
          '#required'      => ($i < 2) && ('new' !== $id),
      );
    }

    if ('new' !== $id) {
      $form["collection{$id}"]['for_all'] = array(
          '#type'          => 'checkbox',
          '#title'         => t('Available to all users'),
          '#default_value' => $collection->for_all,
      );

      $form["collection{$id}"]['to-do'] = array(
          '#type'          => 'radios',
          '#title'         => t('What will you do?'),
          '#default_value' => '0',
          '#options'       => array(
              t('Save changes, do not change questions using this preset'),
              t('Save changes, and change your own questions who uses this preset'),
              t('Delete this preset(This will not affect existing questions)')),
      );
    }
    else {
      $form["collection{$id}"]["to-do"] = array('#type' => 'value', '#value' => 3);
      $form["collection{$id}"]["for_all"] = array('#type' => 'value', '#value' => 1);
    }
  }

}
