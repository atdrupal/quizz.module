<?php

namespace Drupal\quiz\Form;

class QuizTypeForm {

  public function get($form, &$form_state, $quiz_type, $op) {
    if ($op === 'clone') {
      $quiz_type->label .= ' (cloned)';
      $quiz_type->type = '';
    }

    $form['label'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Label'),
        '#default_value' => $quiz_type->label,
        '#description'   => t('The human-readable name of this !quiz type.', array('!quiz' => QUIZ_NAME)),
        '#required'      => TRUE,
        '#size'          => 30,
    );

    // Multilingual support
    if (module_exists('locale')) {
      $form['multilingual'] = array(
          '#type'          => 'radios',
          '#title'         => t('Multilingual support'),
          '#default_value' => isset($quiz_type->data['multilingual']) ? $quiz_type->data['multilingual'] : 0,
          '#options'       => array(t('Disabled'), t('Enabled')),
          '#description'   => t('Enable multilingual support for this quiz type. If enabled, a language selection field will be added to the editing form, allowing you to select from one of the <a href="!languages">enabled languages</a>. If disabled, new posts are saved with the default language. Existing content will not be affected by changing this option.', array('!languages' => url('admin/config/regional/language'))),
      );
    }

    $form['description'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Description'),
        '#description'   => t('Describe this !quiz type. The text will be displayed on the Add new !quiz page.', array('!quiz' => QUIZ_NAME)),
        '#default_value' => $quiz_type->description,
    );

    $form['help'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Explanation or submission guidelines'),
        '#description'   => t('This text will be displayed at the top of the page when creating or editing !quiz of this type.', array('!quiz' => QUIZ_NAME)),
        '#default_value' => $quiz_type->help,
    );

    // Machine-readable type name.
    $form['type'] = array(
        '#type'          => 'machine_name',
        '#default_value' => isset($quiz_type->type) ? $quiz_type->type : '',
        '#maxlength'     => 32,
        '#disabled'      => $quiz_type->isLocked() && $op !== 'clone',
        '#machine_name'  => array('exists' => 'quiz_type_load', 'source' => array('label')),
        '#description'   => t('A unique machine-readable name for this !quiz type. It must only contain lowercase letters, numbers, and underscores.', array('!quiz' => QUIZ_NAME)),
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save quiz type'), '#weight' => 40);

    if (!$quiz_type->isLocked() && $op != 'add' && $op != 'clone') {
      $form['actions']['delete'] = array(
          '#type'                    => 'submit',
          '#value'                   => t('Delete !quiz type', array('!quiz' => QUIZ_NAME)),
          '#weight'                  => 45,
          '#limit_validation_errors' => array(),
          '#submit'                  => array('quiz_type_form_submit_delete')
      );
    }

    return $form;
  }

  /**
   * Form API submit callback for the type form.
   */
  public function submit(&$form, &$form_state) {
    $quiz_type = entity_ui_form_submit_build_entity($form, $form_state);
    $quiz_type->description = filter_xss_admin($quiz_type->description);
    $quiz_type->help = filter_xss_admin($quiz_type->help);

    if (isset($quiz_type->multilingual)) {
      $quiz_type->data['multilingual'] = (int) $quiz_type->multilingual;
      unset($quiz_type->multilingual);
    }

    $quiz_type->save();
    $form_state['redirect'] = 'admin/structure/quiz';
  }

}
