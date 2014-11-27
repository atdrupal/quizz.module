<?php

namespace Drupal\quiz_question\Form;

use Drupal\quiz_question\Entity\Question;
use Drupal\quizz\Entity\QuizEntity;

class QuestionForm {

  private $question;

  public function __construct(Question $question) {
    $this->question = $question;
  }

  public function getForm(array &$form_state = NULL, QuizEntity $quiz = NULL) {
    global $language;

    $form = array(
        // mark this form to be processed by quiz_form_alter. quiz_form_alter will among other things
        // hide the revion fieldset if the user don't have permission to controll the revisioning manually.
        '#quiz_check_revision_access' => TRUE,
        '#quiz'                       => $quiz,
        // Identify this node as a quiz question type so that it can be recognized
        // by other modules effectively.
        'is_quiz_question'            => array('#type' => 'value', '#value' => TRUE),
    );

    if (module_exists('locale') && $this->question->getQuestionType()->data['multilingual']) {
      $language_options = array();
      foreach (language_list() as $langcode => $lang) {
        $language_options[$langcode] = $lang->name;
      }

      $form['language'] = array(
          '#type'          => count($language_options) < 5 ? 'radios' : 'select',
          '#title'         => t('Language'),
          '#options'       => $language_options,
          '#default_value' => isset($this->question->language) ? $this->question->language : $language->language,
      );
    }

    $this->getFormTitle($form);

    $form['feedback'] = array(
        '#type'          => 'text_format',
        '#title'         => t('Question feedback'),
        '#default_value' => !empty($this->question->feedback) ? $this->question->feedback : '',
        '#format'        => !empty($this->question->feedback_format) ? $this->question->feedback_format : filter_default_format(),
        '#description'   => t('This feedback will show when configured and the user answers a question, regardless of correctness.'),
    );

    $this->getFormRevision($form);

    $form['actions']['#weight'] = 50;
    $form['actions']['submit'] = array('#type' => 'submit', '#value' => t('Save question'));
    if (!empty($this->question->qid)) {
      $form['actions']['delete'] = array(
          '#type'   => 'submit',
          '#value'  => t('Delete'),
          '#submit' => array('quiz_question_form_submit_delete')
      );
    }

    $form['question_plugin'] = array(
        '#weight' => 0,
        $this->question->getPlugin()->getCreationForm($form_state)
    );

    // Attach custom fields
    field_attach_form('quiz_question', $this->question, $form, $form_state);

    return $form;
  }

  private function getFormTitle(&$form) {
    $form['title'] = array('#type' => 'value', '#value' => $this->question->title);

    // Allow user to set title?
    if (user_access('edit question titles')) {
      $form['title'] = array(
          '#type'          => 'textfield',
          '#title'         => t('Title'),
          '#maxlength'     => 255,
          '#default_value' => $this->question->title,
          '#required'      => FALSE,
          '#weight'        => -10,
          '#description'   => t('Add a title that will help distinguish this question from other questions. This will not be seen during the @quiz.', array('@quiz' => QUIZ_NAME)),
      );

      $form['title']['#attached']['js'] = array(
          drupal_get_path('module', 'quiz_question') . '/misc/js/quiz-question.auto-title.js',
          array(
              'type' => 'setting',
              'data' => array(
                  'quiz_max_length' => variable_get('quiz_autotitle_length', 50)
              ),
          ),
      );
    }
  }

  private function getFormRevision(&$form) {
    $form['revision_information'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Revision information'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
        '#group'       => 'vtabs',
        '#attributes'  => array('class' => array('node-form-revision-information')),
        '#attached'    => array('js' => array(drupal_get_path('module', 'node') . '/node.js')),
        '#weight'      => 20,
        '#access'      => TRUE,
    );

    $form['revision_information']['revision'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Create new revision'),
        '#default_value' => FALSE,
        '#state'         => array('checked' => array('textarea[name="log"]' => array('empty' => FALSE))),
    );

    $form['revision_information']['log'] = array(
        '#type'          => 'textarea',
        '#title'         => t('Revision log message'),
        '#row'           => 4,
        '#default_value' => '',
        '#description'   => t('Provide an explanation of the changes you are making. This will help other authors understand your motivations.'),
    );

    if ($this->question->getPlugin()->hasBeenAnswered()) {
      $this->question->is_new_revision = 1;
      $this->question->log = t('The current revision has been answered. We create a new revision so that the reports from the existing answers stays correct.');
    }
  }

}
