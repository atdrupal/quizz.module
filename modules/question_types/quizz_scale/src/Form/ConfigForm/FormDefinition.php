<?php

namespace Drupal\quizz_scale\Form\ConfigForm;

use Drupal\quiz_question\Entity\QuestionType;

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
    global $user;

    $collections = quizz_scale_collection_controller()->getPresetCollections($this->question_type->type, $user->uid);

    // If user is allowed to edit global answer collections he is also allowed
    // to add new global presets
    $collections['new'] = entity_create('scale_collection', array(
        'for_all' => 1,
        'label'   => t('New global collection(available to all users)'),
    ));

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
        '#title'       => $collection->label,
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
        '#group'       => 'scale_manage_collection_form',
    );

    $alternatives = $collection->alternatives;
    $indexes = array_keys($collection->alternatives);

    for ($i = 0; $i < $this->question_type->getConfig('scale_max_num_of_alts', 10); $i++) {
      $form["collection{$id}"]["alternative{$i}"] = array(
          '#title'         => t('Alternative !i', array('!i' => ($i + 1))),
          '#type'          => 'textfield',
          '#default_value' => isset($indexes[$i]) ? $alternatives[$indexes[$i]] : '',
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
          '#default_value' => 'save_safe',
          '#options'       => array(
              'save_safe'   => t('Save changes, do not change questions using this preset'),
              'save'        => t('Save changes, and change your own questions who uses this preset'),
              'delete_safe' => t('Delete this preset(This will not affect existing questions)')
          ),
      );
    }
    else {
      $form["collection{$id}"]["to-do"] = array('#type' => 'value', '#value' => 'save_new');
      $form["collection{$id}"]["for_all"] = array('#type' => 'value', '#value' => 1);
    }
  }

}
