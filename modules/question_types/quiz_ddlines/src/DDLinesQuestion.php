<?php

namespace Drupal\quiz_ddlines;

use Drupal\quiz_question\Entity\QuestionType;
use Drupal\quiz_question\QuestionHandler;

/**
 * Extension of QuizQuestion.
 */
class DDLinesQuestion extends QuestionHandler {

  public function onNewQuestionTypeCreated(QuestionType $question_type) {
    parent::onNewQuestionTypeCreated($question_type);

    if (!field_info_instance('quiz_question', 'field_image', $question_type->type)) {
      $bundle = $question_type->type;

      if (!field_info_field('field_image')) {
        field_create_field(array('type' => 'image', 'field_name' => 'field_image'));
      }

      if (!field_info_instance('quiz_question', 'field_image', $bundle)) {
        field_create_instance(array(
            'field_name'  => 'field_image',
            'entity_type' => 'quiz_question',
            'bundle'      => $bundle,
            'label'       => t('Background image'),
            'required'    => TRUE,
            'settings'    => array('no_ui' => TRUE),
            'widget'      => array(
                'settings' => array('no_ui' => TRUE, 'preview_image_style' => 'quiz_ddlines'),
            ),
            'description' => t("<p>Start by uploading a background image. The image is movable within the canvas.<br/>
    				The next step is to add the alternatives, by clicking in the canvas.
    				Each alternative consists of a circular hotspot, a label, and a connecting line. You need
    				to double click the rectangular label to add the text, and move the hotspot to the correct
    				position. When selecting a label, a popup-window is displayed, which gives you the following
    				alternatives:
    				<ul>
    					<li>Set the alternative's feedback (only possible if feedback is enabled)</li>
    					<li>Set the color of each alternative</li>
    					<li>Delete the alternative</li>
    				</ul>
    				</p>")
        ));
      }
    }
  }

  /**
   * Get the form used to create a new question.
   *
   * @param array
   * @return array
   */
  public function getCreationForm(array &$form_state = NULL) {
    $elements = '';

    if (isset($this->question->translation_source)) {
      $elements = $this->question->translation_source->ddlines_elements;
    }
    elseif (isset($this->question->ddlines_elements)) {
      $elements = $this->question->ddlines_elements;
    }

    $form['ddlines_elements'] = array(
        '#type'          => 'hidden',
        '#default_value' => $elements,
    );

    $default_settings = $this->getDefaultAltSettings();

    $form['settings'] = array(
        '#type'        => 'fieldset',
        '#title'       => t('Settings'),
        '#collapsible' => TRUE,
        '#collapsed'   => FALSE,
        '#weight'      => -3,
    );
    $form['settings']['feedback_enabled'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Enable feedback'),
        '#description'   => t('When taking the test, and this option is enabled, a wrong placement of an alternative, will make it jump back. Also, this makes it possible to add comments to both correct and wrong answers.'),
        '#default_value' => isset($this->question->translation_source) ? $this->question->translation_source->feedback_enabled : $default_settings['feedback']['enabled'],
        '#parents'       => array('feedback_enabled'),
    );
    $form['settings']['hotspot_radius'] = array(
        '#type'          => 'textfield',
        '#title'         => t('Hotspot radius'),
        '#description'   => t('The radius of the hotspot in pixels'),
        '#default_value' => isset($this->question->translation_source) ? $this->question->translation_source->hotspot_radius : $default_settings['hotspot']['radius'],
        '#parents'       => array('hotspot_radius'),
    );

    $form['settings']['execution_mode'] = array(
        '#type'          => 'radios',
        '#title'         => t('Execution mode'),
        '#description'   => t('The mode for taking the test.'),
        '#default_value' => isset($this->question->translation_source) ? $this->question->translation_source->execution_mode : $default_settings['execution_mode'],
        '#options'       => array(
            0 => t('With lines'),
            1 => t('Drag label'),
        ),
        '#parents'       => array('execution_mode'),
    );

    $default_settings['mode'] = 'edit';
    $default_settings['editmode'] = isset($this->question->qid) ? 'update' : 'add';
    $form['#attached']['js'][] = array(
        'data' => array('quiz_ddlines' => $default_settings),
        'type' => 'setting'
    );

    drupal_add_library('system', 'ui.resizable');
    _quiz_ddlines_add_js_and_css();

    return $form;
  }

  /**
   * This makes max_score beeing updated for all occurrences of
   * this question in quizzes.
   * @return bool
   */
  protected function autoUpdateMaxScore() {
    return TRUE;
  }

  /**
   * Helper function provding the default settings for the creation form.
   *
   * @return
   *  Array with the default settings
   */
  private function getDefaultAltSettings() {
    $settings = array();

    // If the node exists, use saved value
    if (isset($this->question->qid)) {
      $settings['feedback']['enabled'] = $this->question->feedback_enabled;
      $settings['hotspot']['radius'] = $this->question->hotspot_radius;
      $settings['execution_mode'] = $this->question->execution_mode;
    }
    else {
      $settings['feedback']['enabled'] = 0;
      $settings['hotspot']['radius'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_hotspot_radius', 10);
      $settings['execution_mode'] = 0;
    }

    // Pick these from settings:
    $settings['feedback']['correct'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_feedback_correct', t('Correct'));
    $settings['feedback']['wrong'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_feedback_wrong', t('Wrong'));
    $settings['canvas']['width'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_canvas_width', 700);
    $settings['canvas']['height'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_canvas_height', 500);
    $settings['pointer']['radius'] = $this->question->getQuestionType()->getConfig('quiz_ddlines_pointer_radius', 5);

    return $settings;
  }

  /**
   * Generates the question form.
   *
   * This is called whenever a question is rendered, either
   * to an administrator or to a quiz taker.
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $form = parent::getAnsweringForm($form_state, $result_id);

    $form['helptext'] = array(
        '#markup' => t('Answer this question by dragging each rectangular label to the correct circular hotspot.'),
        '#weight' => 0,
    );

    // Form element containing the correct answers
    $form['ddlines_elements'] = array(
        '#type'          => 'hidden',
        '#default_value' => isset($this->question->ddlines_elements) ? $this->question->ddlines_elements : '',
    );

    // Form element containing the user answers
    // The quiz module requires this element to be named "tries":
    $form['tries'] = array(
        '#type'          => 'hidden',
        '#default_value' => '',
    );

    $image_uri = $this->question->field_image['und'][0]['uri'];
    $image_url = image_style_url('large', $image_uri);

    $form['image'] = array(
        '#prefix' => '<div class="image-preview">',
        '#markup' => theme('image', array('path' => $image_url)),
        '#suffix' => '</div>',
    );

    $default_settings = $this->getDefaultAltSettings();
    $default_settings['mode'] = 'take';
    $form['#attached']['js'][] = array(
        'data' => array('quiz_ddlines' => $default_settings),
        'type' => 'setting'
    );

    _quiz_ddlines_add_js_and_css();

    return $form;
  }

  /**
   * Get the maximum possible score for this question.
   */
  public function getMaximumScore() {
    // 1 point per correct hotspot location
    $ddlines_elements = json_decode($this->question->ddlines_elements);
    return isset($ddlines_elements->elements) ? sizeof($ddlines_elements->elements) : 0;
  }

  /**
   * Save question type specific node properties
   */
  public function onSave($is_new = FALSE) {
    if ($is_new || $this->question->revision == 1) {
      db_insert('quiz_ddlines_question')
        ->fields(array(
            'qid'              => $this->question->qid,
            'vid'              => $this->question->vid,
            'feedback_enabled' => $this->question->feedback_enabled,
            'hotspot_radius'   => $this->question->hotspot_radius,
            'ddlines_elements' => $this->question->ddlines_elements,
            'execution_mode'   => $this->question->execution_mode,
        ))
        ->execute();
    }
    else {
      db_update('quiz_ddlines_question')
        ->fields(array(
            'ddlines_elements' => $this->question->ddlines_elements,
            'hotspot_radius'   => $this->question->hotspot_radius,
            'feedback_enabled' => $this->question->feedback_enabled,
            'execution_mode'   => $this->question->execution_mode,
        ))
        ->condition('qid', $this->question->qid)
        ->condition('vid', $this->question->vid)
        ->execute();
    }
  }

  /**
   * Implementation of load
   *
   * @see QuizQuestion#load()
   */
  public function load() {
    if (isset($this->properties) && !empty($this->properties)) {
      return $this->properties;
    }
    $props = parent::load();

    $res_a = db_query(
      'SELECT feedback_enabled, hotspot_radius, ddlines_elements, execution_mode FROM {quiz_ddlines_question} WHERE qid = :qid AND vid = :vid', array(
        ':qid' => $this->question->qid,
        ':vid' => $this->question->vid))->fetchAssoc();

    if (is_array($res_a)) {
      $props = array_merge($props, $res_a);
    }
    $this->properties = $props;
    return $props;
  }

  /**
   * {@inheritdoc}
   */
  public function delete($single_revision = FALSE) {
    $delete_question = db_delete('quiz_ddlines_question')->condition('qid', $this->question->qid);
    $delete_results = db_delete('quiz_ddlines_user_answers')->condition('question_qid', $this->question->qid);

    if ($single_revision) {
      $delete_question->condition('vid', $this->question->vid);
      $delete_results->condition('question_vid', $this->question->vid);
    }

    // Delete from table quiz_ddlines_user_answer_multi
    $user_answer_ids = array();
    if ($single_revision) {
      $query = db_query('SELECT id FROM {quiz_ddlines_user_answers} WHERE question_qid = :qid AND question_vid = :vid', array(':qid' => $this->question->qid, ':vid' => $this->question->vid));
    }
    else {
      $query = db_query('SELECT id FROM {quiz_ddlines_user_answers} WHERE question_qid = :qid', array(':qid' => $this->question->qid));
    }
    while ($user_answer = $query->fetch()) {
      $user_answer_ids[] = $user_answer->id;
    }

    if (count($user_answer_ids)) {
      db_delete('quiz_ddlines_user_answer_multi')
        ->condition('user_answer_id', $user_answer_ids)
        ->execute();
    }

    $delete_question->execute();
    $delete_results->execute();
    parent::delete($single_revision);
  }

}
