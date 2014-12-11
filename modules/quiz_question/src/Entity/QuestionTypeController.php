<?php

namespace Drupal\quiz_question\Entity;

use EntityAPIControllerExportable;

class QuestionTypeController extends EntityAPIControllerExportable {

  /**
   * {@inheritdoc}
   * @param \Drupal\quiz_question\Entity\QuestionType $question_type
   * @param \Drupal\quiz_question\Entity\DatabaseTransaction $transaction
   */
  public function save($question_type, DatabaseTransaction $transaction = NULL) {
    $return = parent::save($question_type, $transaction);

    if (!field_info_instance('quiz_question', 'quiz_question_body', $question_type->type)) {
      $this->addBodyField($question_type->type);
    }

    if ($question_type->handler === 'quiz_ddlines') {
      if (!field_info_instance('quiz_question', 'field_image', $question_type->type)) {
        $this->addImageField($question_type->type);
      }
    }

    return $return;
  }

  /**
   * Add default body field to a quiz type
   */
  private function addBodyField($bundle) {
    if (!field_info_field('quiz_question_body')) {
      field_create_field(array(
          'field_name'   => 'quiz_question_body',
          'type'         => 'text_with_summary',
          'entity_types' => array('quiz_question'),
      ));
    }

    field_create_instance(array(
        'field_name'  => 'quiz_question_body',
        'entity_type' => 'quiz_question',
        'bundle'      => $bundle,
        'label'       => t('Question'),
        'widget'      => array(
            'type'     => 'text_textarea_with_summary',
            'weight'   => -20,
            'settings' => array('rows' => 5, 'summary_rows' => 3),
        ),
        'settings'    => array('display_summary' => FALSE),
        'display'     => array(
            'teaser' => array('label' => 'hidden', 'type' => 'text_summary_or_trimmed', 'settings' => array('trim_length' => 600)),
            'full'   => array('label' => 'hidden', 'type' => 'text_default'),
        ),
    ));
  }

  private function addImageField($bundle) {
    if (!field_info_field('field_image')) {
      field_create_field(array(
          'field_name' => 'field_image',
          'type'       => 'image'
      ));
    }

    if (!field_info_instance('quiz_question', 'field_image', $bundle)) {
      field_create_instance(array(
          'field_name'  => 'field_image',
          'entity_type' => 'quiz_question',
          'bundle'      => 'quiz_ddlines',
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
