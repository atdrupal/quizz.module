<?php

namespace Drupal\question_pool;

use Drupal\quiz_question\Entity\QuestionType;

class QuestionTypeInstaller {

  private $field_name = 'field_question_reference';

  public function setup(QuestionType $question_type) {
    $this->doCreateField();
    $this->doCreateFieldInstance($question_type);

    // Override default weight to make body field appear first
    if ($instance = field_read_instance('quiz_question', 'quiz_question_body', $question_type->type)) {
      $instance['widget']['weight'] = -10;
      $instance['widget']['settings']['rows'] = 6;
      field_update_instance($instance);
    }
  }

  private function doCreateField() {
    if (!field_info_field($this->field_name)) {
      field_create_field(array(
          'active'       => 1,
          'cardinality'  => -1,
          'deleted'      => 0,
          'entity_types' => array(),
          'field_name'   => $this->field_name,
          'foreign keys' => array(
              'quiz_question' => array(
                  'table'   => 'quiz_question',
                  'columns' => array('target_id' => 'qid')
              )
          ),
          'indexes'      => array('target_id' => array(0 => 'target_id')),
          'locked'       => 0,
          'module'       => 'entityreference',
          'translatable' => 0,
          'type'         => 'entityreference',
          'settings'     => array(
              'target_type'      => 'quiz_question',
              'handler'          => 'base',
              'handler_settings' => array(
                  'target_bundles' => array_keys(quiz_question_get_types()),
                  'sort'           => array('type' => 'property', 'property' => 'title', 'direction' => 'ASC'),
                  'behaviors'      => array(),
              ),
          ),
      ));
    }
  }

  private function doCreateFieldInstance(QuestionType $question_type) {
    if (!field_info_instance('quiz_question', $this->field_name, $question_type->type)) {
      field_create_instance(array(
          'field_name'  => $this->field_name,
          'entity_type' => 'quiz_question',
          'bundle'      => $question_type->type,
          'label'       => 'Question reference',
          'description' => 'Question that this pool contains',
          'required'    => TRUE,
          'widget'      => array(
              'type'     => 'entityreference_autocomplete',
              'module'   => 'entityreference',
              'active'   => 1,
              'settings' => array(
                  'match_operator' => 'CONTAINS',
                  'size'           => 60,
                  'path'           => '',
              )
          ),
          'settings'    => array(),
      ));
    }
  }

}