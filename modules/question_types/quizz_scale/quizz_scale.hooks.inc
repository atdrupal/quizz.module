<?php

use Drupal\quiz_question\Entity\QuestionType;
use Drupal\quizz_scale\Form\ConfigForm\FormDefinition;

/**
 * Implements hook_help().
 */
function quizz_scale_help($path) {
  if ($path === 'admin/help#quizz_scale') {
    return t('This module provides a scale question type for Quiz. It may be used to construct surveys.');
  }
}

/**
 * Implements hook_ENTITYTYPE_insert()
 * @param \Drupal\quiz_question\Entity\QuestionType $question_type
 */
function quizz_scale_quiz_question_type_insert($question_type) {
  if ('quizz_scale' === $question_type->plugin) {
    quizz_scale_collection_controller()->generateDefaultCollections($question_type);
  }
}

/**
 * Implements hook_theme().
 */
function quizz_scale_theme($existing, $type, $theme, $path) {
  $module_path = drupal_get_path('module', 'quizz_scale');
  return array(
      'scale_creation_form'    => array(
          'render element' => 'form',
          'path'           => $module_path . '/theme',
          'file'           => 'scale.theme.inc',
      ),
      'scale_response_form'    => array(
          'render element' => 'form',
          'path'           => $module_path . '/theme',
          'file'           => 'scale.theme.inc',
      ),
      'scale_answer_node_view' => array(
          'variables' => array(
              'alternatives' => NULL,
          ),
          'path'      => $module_path . '/theme',
          'file'      => 'scale.theme.inc',
      ),
      'scale_answering_form'   => array(
          'render element' => 'form',
          'path'           => $module_path . '/theme',
          'template'       => 'scale-answering-form',
      ),
  );
}

/**
 * Implements hook_quiz_question_info().
 */
function quizz_scale_quiz_question_info() {
  return array(
      'quizz_scale' => array(
          'name'              => t('Scale question'),
          'description'       => t('Quiz questions that allow a user to choose from a scale.'),
          'question provider' => 'Drupal\quizz_scale\ScaleQuestion',
          'response provider' => 'Drupal\quizz_scale\ScaleResponse',
          'module'            => 'quiz_question', // All wrapper functions are in that module.
      ),
  );
}

/**
 * Implements hook_entity_info().
 * @return array
 */
function quizz_scale_entity_info() {
  $info = array();

  $info['scale_collection'] = array(
      'label'            => t('Collection'),
      'plural label'     => t('Collections'),
      'description'      => t('Scale collections'),
      'entity class'     => 'Drupal\quizz_scale\Entity\Collection',
      'controller class' => 'Drupal\quizz_scale\Entity\CollectionController',
      'base table'       => 'quiz_scale_collections',
      'fieldable'        => FALSE,
      'exportable'       => TRUE,
      'entity keys'      => array('id' => 'id', 'name' => 'name', 'label' => 'label'),
      'access callback'  => 'quizz_scale_collection_access_callback',
      'module'           => 'quizz_scale',
  );

  return $info;
}

/**
 * Implements hook_quiz_question_config().
 */
function quizz_scale_quiz_question_config(QuestionType $question_type) {
  require_once dirname(__FILE__) . '/quizz_scale.pages.inc';
  $obj = new FormDefinition($question_type);
  return $obj->get();
}

/**
 * Implements hook_get_answer().
 *
 * @TODO: See #2383553
 */
function quizz_scale_get_answer($question_qid, $question_vid, $result_id) {
  $results = db_query('SELECT answer_id, question_vid, question_qid, result_id
    FROM {quiz_scale_user_answers}
    WHERE question_qid = :question_id AND question_vid = :qvid AND result_id = :rid', array(
      ':question_id' => $question_qid,
      ':qvid'        => $question_vid,
      ':rid'         => $result_id
    ))->fetchAssoc();
  return $results ? $results : FALSE;
}

/**
 * Implements hook_user_cancel().
 *
 * @TODO: See #2383607
 */
function quizz_scale_user_cancel($edit, $account, $method) {
  $query = db_query(
    'SELECT id
     FROM {quiz_scale_collections} collection
       JOIN {quiz_scale_user} u ON collection.id = u.answer_collection_id
     WHERE uid = :uid
       AND collection.for_all = :for_all
       AND collection.id NOT IN (SELECT answer_collection_id FROM {quiz_scale_properties})
       AND collection.id NOT IN (SELECT answer_collection_id FROM {quiz_scale_user} WHERE NOT uid = :uid)', array(
      ':uid'     => $account->uid,
      ':for_all' => 0
  ));

  foreach ($query as $row) {
    $ids[] = $row->id;
  }

  if (!empty($ids)) {
    entity_delete_multiple('scale_collection', $ids);
  }
}
