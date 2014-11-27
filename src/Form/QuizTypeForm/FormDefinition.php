<?php

namespace Drupal\quizz\Form\QuizTypeForm;

class FormDefinition {

  /** @var \Drupal\quizz\Entity\QuizType */
  private $quiz_type;

  public function __construct($quiz_type) {
    $this->quiz_type = $quiz_type;
  }

  public function get($op) {
    if ($op === 'clone') {
      $this->quiz_type->label .= ' (cloned)';
      $this->quiz_type->type = '';
    }

    $form['label'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Label'),
        '#default_value' => $this->quiz_type->label,
        '#description'   => t('The human-readable name of this !quiz type.', array('!quiz' => QUIZ_NAME)),
        '#required'      => TRUE,
        '#size'          => 30,
    );

    // Multilingual support
    if (module_exists('locale')) {
      $form['multilingual'] = array(
          '#type'          => 'radios',
          '#title'         => t('Multilingual support'),
          '#default_value' => isset($this->quiz_type->data['multilingual']) ? $this->quiz_type->data['multilingual'] : 0,
          '#options'       => array(t('Disabled'), t('Enabled')),
          '#description'   => t('Enable multilingual support for this quiz type. If enabled, a language selection field will be added to the editing form, allowing you to select from one of the <a href="!languages">enabled languages</a>. If disabled, new posts are saved with the default language. Existing content will not be affected by changing this option.', array('!languages' => url('admin/config/regional/language'))),
      );
    }

    // Machine-readable type name.
    $form['type'] = array(
        '#type'          => 'machine_name',
        '#default_value' => isset($this->quiz_type->type) ? $this->quiz_type->type : '',
        '#maxlength'     => 32,
        '#disabled'      => $this->quiz_type->isLocked() && $op !== 'clone',
        '#machine_name'  => array('exists' => 'quiz_type_load', 'source' => array('label')),
        '#description'   => t('A unique machine-readable name for this !quiz type. It must only contain lowercase letters, numbers, and underscores.', array('!quiz' => QUIZ_NAME)),
    );

    $form['vtabs'] = array('#type' => 'vertical_tabs', '#weight' => 5);
    $this->basicInformation($form);
    $this->configuration($form);

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save quiz type'), '#weight' => 40);

    if (!$this->quiz_type->isLocked() && $op != 'add' && $op != 'clone') {
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

  private function basicInformation(&$form) {
    $form['vtabs']['basic_information'] = array(
        '#type'       => 'fieldset',
        '#title'      => t('Basic informations'),
        'description' => array(
            '#type'          => 'textarea',
            '#title'         => t('Description'),
            '#description'   => t('Describe this !quiz type. The text will be displayed on the Add new !quiz page.', array('!quiz' => QUIZ_NAME)),
            '#default_value' => $this->quiz_type->description,
        ),
        'help'        => array(
            '#type'          => 'textarea',
            '#title'         => t('Explanation or submission guidelines'),
            '#description'   => t('This text will be displayed at the top of the page when creating or editing !quiz of this type.', array('!quiz' => QUIZ_NAME)),
            '#default_value' => $this->quiz_type->help,
        )
    );
  }

  private function configuration(&$form) {
    $config = isset($this->quiz_type->data['configuration']) ? $this->quiz_type->data['configuration'] : array();

    $form['vtabs']['configuration'] = array(
        '#tree'        => TRUE,
        '#type'        => 'fieldset',
        '#title'       => t('Configuration'),
        '#description' => t('Control aspects of the Quiz module\'s display'),
    );

    $form['vtabs']['configuration']['quiz_auto_revisioning'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Auto revisioning'),
        '#default_value' => isset($config['quiz_auto_revisioning']) ? $config['quiz_auto_revisioning'] : 1,
        '#description'   => t('It is strongly recommended that auto revisioning is always on. It makes sure that when a question or quiz is changed a new revision is created if the current revision has been answered. If this feature is switched off result reports might be broken because a users saved answer might be connected to a wrong version of the quiz and/or question she was answering. All sorts of errors might appear.'),
    );

    $form['vtabs']['configuration']['quiz_default_close'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Default number of days before a @quiz is closed', array('@quiz' => QUIZ_NAME)),
        '#default_value' => isset($config['quiz_default_close']) ? $config['quiz_default_close'] : 30,
        '#size'          => 4,
        '#maxlength'     => 4,
        '#description'   => t('Supply a number of days to calculate the default close date for new quizzes.'),
    );

    $form['vtabs']['configuration']['quiz_use_passfail'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Allow quiz creators to set a pass/fail option when creating a @quiz.', array('@quiz' => strtolower(QUIZ_NAME))),
        '#default_value' => isset($config['quiz_use_passfail']) ? $config['quiz_use_passfail'] : 1,
        '#description'   => t('Check this to display the pass/fail options in the @quiz form. If you want to prohibit other quiz creators from changing the default pass/fail percentage, uncheck this option.', array('@quiz' => QUIZ_NAME)),
    );

    $form['vtabs']['configuration']['quiz_max_result_options'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Maximum result options'),
        '#description'   => t('Set the maximum number of result options (categorizations for scoring a quiz). Set to 0 to disable result options.'),
        '#default_value' => isset($config['quiz_max_result_options']) ? $config['quiz_max_result_options'] : 5,
        '#size'          => 2,
        '#maxlength'     => 2,
        '#required'      => FALSE,
    );

    $form['vtabs']['configuration']['quiz_remove_partial_quiz_record'] = array(
        '#type'          => 'select',
        '#title'         => t('Remove incomplete quiz records (older than)'),
        '#options'       => $this->removePartialQuizRecordValue(),
        '#description'   => t('Number of days to keep incomplete quiz attempts.'),
        '#default_value' => isset($config['quiz_remove_partial_quiz_record']) ? $config['quiz_remove_partial_quiz_record'] : 604800,
    );

    $form['vtabs']['configuration']['quiz_pager_start'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Pager start'),
        '#size'          => 3,
        '#maxlength'     => 3,
        '#description'   => t('If a quiz has this many questions, a pager will be displayed instead of a select box.'),
        '#default_value' => isset($config['quiz_pager_start']) ? $config['quiz_pager_start'] : 100,
    );

    $form['vtabs']['configuration']['quiz_pager_siblings'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Pager siblings'),
        '#size'          => 3,
        '#maxlength'     => 3,
        '#description'   => t('Number of siblings to show.'),
        '#default_value' => isset($config['quiz_pager_siblings']) ? $config['quiz_pager_siblings'] : 5,
    );
  }

  /**
   * Helper function returning number of days as values and corresponding
   * number of milliseconds as array keys.
   *
   * @return
   *   array of options for when we want to delete partial quiz record values.
   */
  private function removePartialQuizRecordValue() {
    return array(
        '0'        => t('Never'),
        '86400'    => t('1 Day'),
        '172800'   => t('2 Days'),
        '259200'   => t('3 Days'),
        '345600'   => t('4 Days'),
        '432000'   => t('5 Days'),
        '518400'   => t('6 Days'),
        '604800'   => t('7 Days'),
        '691200'   => t('8 Days'),
        '777600'   => t('9 Days'),
        '864000'   => t('10 Days'),
        '950400'   => t('11 Days'),
        '1036800'  => t('12 Days'),
        '1123200'  => t('13 Days'),
        '1209600'  => t('14 Days'),
        '1296000'  => t('15 Days'),
        '1382400'  => t('16 Days'),
        '1468800'  => t('17 Days'),
        '1555200'  => t('18 Days'),
        '1641600'  => t('19 Days'),
        '1728000'  => t('20 Days'),
        '1814400'  => t('21 Days'),
        '1900800'  => t('22 Days'),
        '1987200'  => t('23 Days'),
        '2073600'  => t('24 Days'),
        '2160000'  => t('25 Days'),
        '2246400'  => t('26 Days'),
        '2332800'  => t('27 Days'),
        '2419200'  => t('28 Days'),
        '2505600'  => t('29 Days'),
        '2592000'  => t('30 Days'),
        '3024000'  => t('35 Days'),
        '3456000'  => t('40 Days'),
        '3888000'  => t('45 Days'),
        '4320000'  => t('50 Days'),
        '4752000'  => t('55 Days'),
        '5184000'  => t('60 Days'),
        '5616000'  => t('65 Days'),
        '6048000'  => t('70 Days'),
        '6480000'  => t('75 Days'),
        '6912000'  => t('80 Days'),
        '7344000'  => t('85 Days'),
        '7776000'  => t('90 Days'),
        '8208000'  => t('95 Days'),
        '8640000'  => t('100 Days'),
        '9072000'  => t('105 Days'),
        '9504000'  => t('110 Days'),
        '9936000'  => t('115 Days'),
        '10368000' => t('120 Days'),
    );
  }

}
