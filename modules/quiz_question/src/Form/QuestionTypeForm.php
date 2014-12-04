<?php

namespace Drupal\quiz_question\Form;

use Drupal\quiz_question\Entity\QuestionType;

class QuestionTypeForm {

  public function get($form, &$form_state, QuestionType $question_type, $op) {
    if ($op === 'clone') {
      $question_type->label .= ' (cloned)';
      $question_type->type = '';
    }

    $this->getTitle($form, $question_type);
    $form['vtabs'] = array('#type' => 'vertical_tabs', '#weight' => 5);
    $this->basicInformation($form, $question_type);

    $fn = $question_type->plugin . '_quiz_question_config';
    if (function_exists($fn) && ($plugin_form = $fn($question_type))) {
      if (!empty($plugin_form['#validate'])) {
        foreach ($plugin_form['#validate'] as $validator) {
          $form['#validate'][] = $validator;
        }
        unset($plugin_form['#validate']);
      }

      if (!empty($plugin_form['#submit'])) {
        foreach ($plugin_form['#submit'] as $validator) {
          $form['#submit'][] = $validator;
        }
        unset($plugin_form['#submit']);
      }

      $form['vtabs']['configuration'] = $plugin_form + array(
          '#type'  => 'fieldset',
          '#title' => t('Configuration'),
          '#tree'  => TRUE,
      );
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save question type'), '#weight' => 40);

    if (!$question_type->isLocked() && $op !== 'add' && $op !== 'clone') {
      $form['actions']['delete'] = array(
          '#type'                    => 'submit',
          '#value'                   => t('Delete question type'),
          '#weight'                  => 45,
          '#limit_validation_errors' => array(),
          '#submit'                  => array('quiz_question_type_form_submit_delete')
      );
    }

    return $form;
  }

  private function getTitle(&$form, QuestionType $question_type) {
    $form['label'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Label'),
        '#default_value' => $question_type->label,
        '#description'   => t('The human-readable name of this question type.'),
        '#required'      => TRUE,
        '#size'          => 30,
    );

    // Machine-readable type name.
    $form['type'] = array(
        '#type'          => 'machine_name',
        '#default_value' => isset($question_type->type) ? $question_type->type : '',
        '#maxlength'     => 32,
        '#disabled'      => $question_type->isLocked() && $op !== 'clone',
        '#machine_name'  => array('exists' => 'quiz_question_type_load', 'source' => array('label')),
        '#description'   => t('A unique machine-readable name for this question type. It must only contain lowercase letters, numbers, and underscores.'),
    );
  }

  private function basicInformation(&$form, QuestionType $question_type) {
    $form['vtabs']['basic_information'] = array(
        '#type'       => 'fieldset',
        '#title'      => t('Basic informations'),
        'description' => array(
            '#type'          => 'textarea',
            '#title'         => t('Description'),
            '#description'   => t('Describe this question type. The text will be displayed on the Add new question page.'),
            '#default_value' => $question_type->description,
        ),
        'help'        => array(
            '#type'          => 'textarea',
            '#title'         => t('Explanation or submission guidelines'),
            '#description'   => t('This text will be displayed at the top of the page when creating or editing question of this type.'),
            '#default_value' => $question_type->help,
        ),
    );

    $provider_options = array();
    foreach (quiz_question_get_plugin_info() as $name => $info) {
      $provider_options[$name] = $info['name'];
    }

    $form['vtabs']['basic_information']['plugin'] = array(
        '#weight'        => -10,
        '#type'          => 'select',
        '#required'      => TRUE,
        '#title'         => t('Question plugin'),
        '#description'   => t('Can not be changed after question type created.'),
        '#options'       => $provider_options,
        '#disabled'      => !empty($question_type->plugin),
        '#default_value' => $question_type->plugin,
    );

    // Multilingual support
    if (module_exists('locale')) {
      $form['vtabs']['basic_information']['multilingual'] = array(
          '#type'          => 'radios',
          '#title'         => t('Multilingual support'),
          '#default_value' => isset($question_type->data['multilingual']) ? $question_type->data['multilingual'] : 0,
          '#options'       => array(t('Disabled'), t('Enabled')),
          '#description'   => t('Enable multilingual support for this quiz type. If enabled, a language selection field will be added to the editing form, allowing you to select from one of the <a href="!languages">enabled languages</a>. If disabled, new posts are saved with the default language. Existing content will not be affected by changing this option.', array('!languages' => url('admin/config/regional/language'))),
      );
    }
  }

  public function submit($form, &$form_state) {
    $question_type = entity_ui_form_submit_build_entity($form, $form_state);
    $question_type->description = filter_xss_admin($question_type->description);
    $question_type->help = filter_xss_admin($question_type->help);

    if (isset($question_type->multilingual)) {
      $question_type->data['multilingual'] = (int) $question_type->multilingual;
      unset($question_type->multilingual);
    }

    if (!empty($question_type->data['configuration'])) {
      $question_type->data['configuration'] = $question_type->configuration;
      unset($question_type->configuration);
    }

    $question_type->save();
    $form_state['redirect'] = 'admin/structure/quiz-questions';
  }

}
