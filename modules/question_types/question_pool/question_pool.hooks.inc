<?php

use Drupal\question_pool\QuestionTypeInstaller;
use Drupal\quiz_question\Entity\QuestionType;

/**
 * Implements hook_quiz_question_info().
 */
function question_pool_quiz_question_info() {
  return array(
      'pool' => array(
          'name'              => t('Question pool'),
          'description'       => t('This provides question pool for use by the Quiz module.'),
          'question provider' => 'Drupal\question_pool\PoolQuestion',
          'response provider' => 'Drupal\question_pool\PoolResponse',
          'module'            => 'quiz_question',
      ),
  );
}

/**
 * Implements hook_menu().
 */
function question_pool_menu() {
  $items['question-pool/%node/%/%ctools_js/form'] = array(
      'title'           => t('Question pool'),
      'file'            => 'question_pool.pages.inc',
      'page callback'   => 'question_pool_wizard',
      'page arguments'  => array(1, 2, 3),
      'access callback' => TRUE,
      'type'            => MENU_CALLBACK,
  );
  return $items;
}

/**
 * Implement hook_forms.
 */
function question_pool_forms($form_id, $args) {
  $forms = array();

  $form_ids = explode("__", $form_id);
  if (!empty($form_ids) && count($form_ids) == 2) {
    $question_form_id = reset($form_ids);
  }
  else {
    return $forms;
  }

  $question_types = _quiz_question_get_implementations();
  foreach (array_keys($question_types) as $type) {
    if ($question_form_id == $type . '_question_pool_form') {
      $forms[$form_id]['callback'] = 'question_pool_form';
    }
  }
  return $forms;
}

/**
 * Implements hook_node_load().
 */
function question_pool_node_load($nodes, $types) {
  foreach ($nodes as $node) {
    if ($node->type == 'pool' && !empty($node->field_question_reference['und'])) {
      $questions = field_get_items('node', $node, 'field_question_reference');
      $delta = 0;
      unset($nodes[$node->nid]->field_question_reference['und']);
      foreach ($questions as $q) {
        $check = node_load($q['target_id']);
        if ($check) {
          $nodes[$node->nid]->field_question_reference['und'][$delta]['target_id'] = $q['target_id'];
          $delta++;
        }
      }
    }
  }
}

/**
 * Implement hook_node_insert().
 */
function question_pool_node_insert($node) {
  // Update pool score when insert node.
  if ($node->type == 'pool') {
    $pool = _quiz_question_get_instance($node);
    db_update('quiz_question_properties')
      ->fields(array(
          'max_score' => $pool->getMaximumScore(),
      ))
      ->condition('nid', $pool->node->nid)
      ->condition('vid', $pool->node->vid)
      ->execute();
  }
}

/**
 * Implement hook_node_update().
 */
function question_pool_node_update($node) {
  // Update pool score when update node.
  if ($node->type == 'pool') {
    $pool = _quiz_question_get_instance($node);
    db_update('quiz_question_properties')
      ->fields(array(
          'max_score' => $pool->getMaximumScore(),
      ))
      ->condition('nid', $pool->node->nid)
      ->condition('vid', $pool->node->vid)
      ->execute();
  }
}

/**
 * Implements hook_ENTITYTYPE_insert()
 * @param QuestionType $question_type
 */
function question_pool_quiz_question_type_insert($question_type) {
  if ('question_pool' === $question_type->plugin) {
    $installer = new QuestionTypeInstaller();
    $installer->setup();
  }
}
