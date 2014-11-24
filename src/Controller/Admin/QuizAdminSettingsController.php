<?php

namespace Drupal\quizz\Controller\Admin;

class QuizAdminSettingsController {

  /**
   * This builds the main settings form for the quiz module.
   */
  public function getForm($form, &$form_state) {
    $form['quiz_global_settings'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Global configuration'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
        '#description' => t('Control aspects of the Quiz module\'s display'),
    );

    $form['quiz_global_settings']['quiz_auto_revisioning'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Auto revisioning'),
        '#default_value' => variable_get('quiz_auto_revisioning', 1),
        '#description'   => t('It is strongly recommended that auto revisioning is always on. It makes sure that when a question or quiz is changed a new revision is created if the current revision has been answered. If this feature is switched off result reports might be broken because a users saved answer might be connected to a wrong version of the quiz and/or question she was answering. All sorts of errors might appear.'),
    );

    $form['quiz_global_settings']['quiz_durod'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Delete results when a user is deleted'),
        '#default_value' => variable_get('quiz_durod', 0),
        '#description'   => t('When a user is deleted delete any and all results for that user.'),
    );

    $form['quiz_global_settings']['quiz_index_questions'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Index questions'),
        '#default_value' => variable_get('quiz_index_questions', 1),
        '#description'   => t('If you turn this off, questions will not show up in search results.'),
    );

    $form['quiz_global_settings']['quiz_default_close'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Default number of days before a @quiz is closed', array('@quiz' => QUIZ_NAME)),
        '#default_value' => variable_get('quiz_default_close', 30),
        '#size'          => 4,
        '#maxlength'     => 4,
        '#description'   => t('Supply a number of days to calculate the default close date for new quizzes.'),
    );

    $form['quiz_global_settings']['quiz_use_passfail'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Allow quiz creators to set a pass/fail option when creating a @quiz.', array('@quiz' => strtolower(QUIZ_NAME))),
        '#default_value' => variable_get('quiz_use_passfail', 1),
        '#description'   => t('Check this to display the pass/fail options in the @quiz form. If you want to prohibit other quiz creators from changing the default pass/fail percentage, uncheck this option.', array('@quiz' => QUIZ_NAME)),
    );

    $form['quiz_global_settings']['quiz_max_result_options'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Maximum result options'),
        '#description'   => t('Set the maximum number of result options (categorizations for scoring a quiz). Set to 0 to disable result options.'),
        '#default_value' => variable_get('quiz_max_result_options', 5),
        '#size'          => 2,
        '#maxlength'     => 2,
        '#required'      => FALSE,
    );

    $form['quiz_global_settings']['quiz_remove_partial_quiz_record'] = array(
        '#type'          => 'select',
        '#title'         => t('Remove incomplete quiz records (older than)'),
        '#options'       => $this->removePartialQuizRecordValue(),
        '#description'   => t('Number of days to keep incomplete quiz attempts.'),
        '#default_value' => variable_get('quiz_remove_partial_quiz_record', $this->removePartialQuizRecordValue()),
    );

    $form['quiz_global_settings']['quiz_autotitle_length'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Length of automatically set question titles'),
        '#size'          => 3,
        '#maxlength'     => 3,
        '#description'   => t('Integer between 0 and 128. If the question creator doesn\'t set a question title the system will make a title automatically. Here you can decide how long the autotitle can be.'),
        '#default_value' => variable_get('quiz_autotitle_length', 50),
    );

    $form['quiz_global_settings']['quiz_pager_start'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Pager start'),
        '#size'          => 3,
        '#maxlength'     => 3,
        '#description'   => t('If a quiz has this many questions, a pager will be displayed instead of a select box.'),
        '#default_value' => variable_get('quiz_pager_start', 100),
    );

    $form['quiz_global_settings']['quiz_pager_siblings'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Pager siblings'),
        '#size'          => 3,
        '#maxlength'     => 3,
        '#description'   => t('Number of siblings to show.'),
        '#default_value' => variable_get('quiz_pager_siblings', 5),
    );

    $target = array('attributes' => array('target' => '_blank'));

    $links = array(
        '!jquery_countdown' => l(t('JQuery Countdown'), 'http://drupal.org/project/jquery_countdown', $target),
        '!userpoints'       => l(t('UserPoints'), 'http://drupal.org/project/userpoints', $target),
    );

    $form['quiz_addons'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Addons configuration'),
        '#description' => t('Quiz has built in integration with some other modules. Disabled checkboxes indicates that modules are not enabled.', $links),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
    );

    $form['quiz_addons']['quiz_has_userpoints'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('User Points'),
        '#default_value' => variable_get('quiz_has_userpoints', 0),
        '#description'   => t('!userpoints is an <strong>optional</strong> module for Quiz. It provides ways for users to gain or lose points for performing certain actions on your site like completing a Quiz.', $links),
        '#disabled'      => !module_exists('userpoints'),
    );

    $form['quiz_addons']['quiz_has_timer'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Display timer'),
        '#default_value' => variable_get('quiz_has_timer', 0),
        '#description'   => t("!jquery_countdown is an <strong>optional</strong> module for Quiz. It is used to display a timer when taking a quiz. Without this timer, the user will not know how much time they have left to complete the Quiz", $links),
        '#disabled'      => !function_exists('jquery_countdown_add'),
    );

    $form['quiz_look_feel'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Look and feel'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
        '#description' => t('Control aspects of the Quiz module\'s display'),
    );

    $form['quiz_look_feel']['quiz_name'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Display name'),
        '#default_value' => QUIZ_NAME,
        '#description'   => t('Change the name of the quiz type. Do you call it <em>test</em> or <em>assessment</em> instead? Change the display name of the module to something else. By default, it is called <em>Quiz</em>.'),
        '#required'      => TRUE,
    );

    $form['quiz_email_settings'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Notifications'),
        '#description' => t('Send results to quiz author/attendee via email. Configure email subject and body.'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
    );

    $form['quiz_email_settings']['taker'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Email for Quiz takers'),
        '#collapsible' => FALSE,
    );

    $form['quiz_email_settings']['taker']['quiz_email_results'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Email results to quiz takers'),
        '#default_value' => variable_get('quiz_email_results', 0),
        '#description'   => t('Check this to send users their results at the end of a quiz.')
    );

    $form['quiz_email_settings']['taker']['quiz_email_results_subject_taker'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Configure email subject'),
        '#description'   => t('This format will be used when sending quiz results at the end of a quiz.'),
        '#default_value' => variable_get('quiz_email_results_subject_taker', $this->formatEmailResults('subject', 'taker')),
    );

    $form['quiz_email_settings']['taker']['quiz_email_results_body_taker'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Configure email Format'),
        '#description'   => t('This format will be used when sending @quiz results at the end of a quiz. !title(quiz title), !sitename, !taker(quiz takers username), !date(time when quiz was finished), !minutes(How many minutes the quiz taker spent taking the quiz), !desc(description of the quiz), !correct(points attained), !total(max score for the quiz), !percentage(percentage score), !url(url to the result page) and !author are placeholders.'),
        '#default_value' => variable_get('quiz_email_results_body_taker', $this->formatEmailResults('body', 'taker')),
    );

    $form['quiz_email_settings']['author'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Email for Quiz authors'),
        '#collapsible' => FALSE,
    );

    $form['quiz_email_settings']['author']['quiz_results_to_quiz_author'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Email all results to quiz author.'),
        '#default_value' => variable_get('quiz_results_to_quiz_author', 0),
        '#description'   => t('Check this to send quiz results for all users to the quiz author.'),
    );

    $form['quiz_email_settings']['author']['quiz_email_results_subject'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Configure email subject'),
        '#description'   => t('This format will be used when sending quiz results at the end of a quiz. Authors and quiz takers gets the same format.'),
        '#default_value' => variable_get('quiz_email_results_subject', $this->formatEmailResults('subject', 'author')),
    );

    $form['quiz_email_settings']['author']['quiz_email_results_body'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Configure E-mail Format'),
        '#description'   => t('This format will be used when sending quiz results at the end of a quiz. !title(quiz title), !sitename, !taker(quiz takers username), !date(time when quiz was finished), !minutes(How many minutes the quiz taker spent taking the quiz), !desc(description of the quiz), !correct(points attained), !total(max score for the quiz), !percentage(percentage score), !url(url to the result page) and !author are placeholders.'),
        '#default_value' => variable_get('quiz_email_results_body', $this->formatEmailResults('body', 'author')),
    );

    return system_settings_form($form);
  }

  /**
   * Validation of the Form Settings form.
   *
   * Checks the values for the form administration form for quiz settings.
   */
  public function validate($form, &$form_state) {
    if (!quiz_valid_integer($form_state['values']['quiz_default_close'])) {
      form_set_error('quiz_default_close', t('The default number of days before a quiz is closed must be a number greater than 0.'));
    }

    if (!quiz_valid_integer($form_state['values']['quiz_autotitle_length'], 0, 128)) {
      form_set_error('quiz_autotitle_length', t('The autotitle length value must be an integer between 0 and 128.'));
    }

    if (!quiz_valid_integer($form_state['values']['quiz_max_result_options'], 0, 100)) {
      form_set_error('quiz_max_result_options', t('The number of resultoptions must be an integer between 0 and 100.'));
    }

    if (!$this->isPlain($form_state['values']['quiz_name'])) {
      form_set_error('quiz_name', t('The quiz name must be plain text.'));
    }
  }

  /**
   * Submit the admin settings form
   */
  public function submit($form, &$form_state) {
    if (QUIZ_NAME !== $form_state['values']['quiz_name']) {
      variable_set('quiz_name', $form_state['values']['quiz_name']);
      define(QUIZ_NAME, $form_state['values']['quiz_name']);
      menu_rebuild();
    }
  }

  /**
   * This functions returns the default email subject and body format which will
   * be used at the end of quiz.
   */
  private function formatEmailResults($type, $target) {
    global $user;

    if ($type === 'subject') {
      return quiz()->getMailHelper()->formatSubject($target, $user);
    }

    if ($type === 'body') {
      return quiz()->getMailHelper()->formatBody($target, $user);
    }
  }

  /**
   * Helper function used when validating plain text.
   *
   * @param $value
   *   The value to be validated.
   *
   * @return
   *   TRUE if plain text FALSE otherwise.
   */
  private function isPlain($value) {
    return ($value === check_plain($value));
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
