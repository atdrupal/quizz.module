<?php

namespace Drupal\question_pool;

class Installer {

  private $field_name = 'field_question_reference';

  public function install() {
    $this->createEntityReferenceField();

    // Override default weight to make body field appear first
    $instance = field_read_instance('node', 'body', 'pool');
    $instance['widget']['weight'] = -10;
    $instance['widget']['settings']['rows'] = 6;
    field_update_instance($instance);
  }

  private function createEntityReferenceField() {
    $this->doCreateField();
    $this->doCreateFieldInstance();
  }

  private function doCreateField() {
    if (!field_info_field($this->field_name)) {
      $question_bundles = array();

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
                  'target_bundles' => $question_bundles,
                  'sort'           => array('type' => 'property', 'property' => 'title', 'direction' => 'ASC'),
                  'behaviors'      => array(),
              ),
          ),
      ));
    }
  }

  private function doCreateFieldInstance() {
    if (!field_info_instance('node', $this->field_name, 'pool')) {
      field_create_instance(array(
          'field_name'  => $this->field_name,
          'entity_type' => 'node',
          'bundle'      => 'pool',
          'label'       => 'Question reference',
          'description' => 'Question that this pool contains',
          'required'    => TRUE,
          'settings'    => array('no_ui' => TRUE,),
          'widget'      => array('settings' => array('preview_image_style' => 'quiz_ddlines', 'no_ui' => TRUE,),
          ),
      ));
    }
  }

}
