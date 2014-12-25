<?php

/**
 * @file
 * This file provides metadata to the Views 2 API for views integration.
 *
 */

/**
 * Implements hook_views_handlers().
 *
 * @todo use hook_views_data_alter to assign these field/filter handlers to the
 * automatically generated Entity API views definitions.
 */
function quizz_views_handlers() {
  return array(
      'info'     => array('path' => dirname(__FILE__) . '/handlers'),
      'handlers' => array(
          // Fields
          'quiz_views_handler_field_takes'                => array(
              'parent' => 'views_handler_field',
          ),
          'quiz_views_handler_field_quiz_link_take'       => array(
              'parent' => 'views_handler_field_node_link',
          ),
          'quiz_views_handler_field_time'                 => array(
              'parent' => 'views_handler_field',
          ),
          'quiz_views_handler_field_question_status'      => array(
              'parent' => 'views_handler_field',
          ),
          'quiz_views_handler_field_user_quiz_state'      => array(
              'parent' => 'views_handler_field',
          ),
          'quiz_views_handler_field_score_aggregate'      => array(
              'parent' => 'views_handler_field_numeric',
          ),
          'quiz_views_handler_field_number_questions'     => array(
              'parent' => 'views_handler_field',
          ),
          // Filters
          'quiz_views_handler_filter_question_status'     => array(
              'parent' => 'views_handler_filter_in_operator',
          ),
          'quiz_views_handler_filter_quiz_qid'            => array(
              'parent' => 'views_handler_filter',
          ),
          'quiz_views_handler_filter_user_quiz_state'     => array(
              'parent' => 'views_handler_filter',
          ),
          'quiz_views_handler_filter_user_nullable'       => array(
              'parent' => 'views_handler_filter',
          ),
          // Relationships
          'quiz_views_handler_relationship_vid_from_qid'  => array(
              'parent' => 'views_handler_relationship',
          ),
          // Arguments
          'quiz_views_handler_argument_quiz_qid'          => array(
              'parent' => 'views_handler_argument_numeric',
          ),
          'quiz_views_handler_argument_user_uid_nullable' => array(
              'parent' => 'views_handler_argument_user_uid',
          ),
          // Filters
          'quiz_views_handler_filter_quiz_question_type'  => array(
              'parent' => 'views_handler_filter_node_type',
          ),
          'quiz_views_handler_filter_quiz_question'       => array(
              'parent' => 'views_handler_filter_boolean_operator',
          ),
      ),
  );
}

/**
 * Implements hook_views_data_alter().
 */
function quizz_views_data_alter(&$data) {
  $data['quiz_entity_revision']['table']['join'] = array(
      'quiz_entity' => array('left_field' => 'vid', 'field' => 'vid', 'type' => 'INNER'),
  );

  $data['quiz_results']['table']['join'] = array(
      'quiz_entity'          => array('left_field' => 'vid', 'field' => 'quiz_vid'),
      'quiz_entity_revision' => array('left_field' => 'vid', 'field' => 'quiz_vid'),
  );

  $data['quiz_relationship']['table']['join'] = array(
      'quiz_entity'          => array('left_field' => 'vid', 'field' => 'quiz_vid', 'type' => 'INNER'),
      'quiz_entity_revision' => array('left_field' => 'vid', 'field' => 'quiz_vid', 'type' => 'INNER'),
  );
}
