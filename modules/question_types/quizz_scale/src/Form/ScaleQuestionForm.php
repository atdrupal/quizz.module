<?php

namespace Drupal\quizz_scale\Form;

use Drupal\quiz_question\Entity\Question;
use Drupal\quizz_scale\CollectionIO;

class ScaleQuestionForm {

  /** @var Question */
  private $question;

  /** @var CollectionIO */
  private $collectionIO;

  public function __construct(Question $question, CollectionIO $collectionIO) {
    $this->question = $question;
    $this->collectionIO = $collectionIO;
  }

  public function get(array &$form_state = NULL) {
    $form = array();

    // Getting presets from the database
    $collections = $this->collectionIO->getPresetCollections(TRUE);

    $options = $this->makeOptions($collections);
    $options['d'] = '-'; // Default
    // We need to add the available preset collections as javascript so that
    // the alternatives can be populated instantly when a
    $jsArray = $this->makeJSArray($collections);

    $form['answer'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Answer'),
        '#description' => t('Provide alternatives for the user to answer.'),
        '#collapsible' => TRUE,
        '#collapsed'   => FALSE,
        '#weight'      => -4,
    );
    $form['answer']['#theme'][] = 'scale_creation_form';
    $form['answer']['presets'] = array(
        '#type'          => 'select',
        '#title'         => t('Presets'),
        '#options'       => $options,
        '#default_value' => 'd',
        '#description'   => t('Select a set of alternatives'),
        '#attributes'    => array('onchange' => 'refreshAlternatives(this)'),
    );
    $max_num_alts = $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10);

    // @TODO: use #attached
    $form['jsArray'] = array(
        '#markup' => "<script type='text/javascript'>$jsArray var scale_max_num_of_alts = $max_num_alts;</script>"
    );
    $form['answer']['alternatives'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Alternatives'),
        '#collapsible' => TRUE,
        '#collapsed'   => TRUE,
    );
    for ($i = 0; $i < $max_num_alts; $i++) {
      $form['answer']['alternatives']["alternative$i"] = array(
          '#type'          => 'textfield',
          '#title'         => t('Alternative !i', array('!i' => ($i + 1))),
          '#size'          => 60,
          '#maxlength'     => 256,
          '#default_value' => isset($this->question->{$i}->answer) ? $this->question->{$i}->answer : '',
          '#required'      => $i < 2,
      );
    }
    $form['answer']['alternatives']['save'] = array(// @todo: Rename save to save_as_preset or something
        '#type'          => 'checkbox',
        '#title'         => t('Save as a new preset'),
        '#description'   => t('Current alternatives will be saved as a new preset'),
        '#default_value' => FALSE,
    );

    $form['answer']['manage']['#markup'] = l(t('Manage presets'), 'admin/structure/quiz-questions/manage/' . $this->question->getQuestionType()->type);

    return $form;
  }

  /**
   * Makes a javascript constructing an answer collection array.
   *
   * @param $collections
   *  collections array, from getPresetCollections() for instance…
   * @return string
   *  javascript code
   */
  private function makeJSArray(array $collections = NULL) {
    $jsArray = 'scaleCollections = new Array();';
    foreach ($collections as $col_id => $obj) {
      if (is_array($collections[$col_id]->alternatives)) {
        $jsArray .= "scaleCollections[$col_id] = new Array();";
        foreach ($collections[$col_id]->alternatives as $alt_id => $text) {
          $jsArray .= "scaleCollections[$col_id][$alt_id] = '" . check_plain($text) . "';";
        }
      }
    }
    return $jsArray;
  }

  /**
   * Makes options array for form elements.
   *
   * @param $collections
   *  collections array, from getPresetCollections() for instance…
   * @return
   *  #options array.
   */
  private function makeOptions(array $collections = NULL) {
    $options = array();
    foreach ($collections as $col_id => $obj) {
      $options[$col_id] = $obj->name;
    }
    return $options;
  }

}
