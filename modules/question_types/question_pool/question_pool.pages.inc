<?php

/**
 * @param array $form
 * @param array $form_state
 * @param int $result_id
 * @param \Drupal\quiz_question\Entity\Question $question
 * @return string
 */
function question_pool_form($form, &$form_state, $result_id, $question) {
  $form += $question->getPlugin()->getAnsweringForm($form_state, $result_id);
  $form['#validate'][] = 'question_pool_form_validate';
  return $form;
}

function question_pool_retry_submit(&$form, &$form_state) {
  $pool = $form['#pool'];
  $rid = $form['#rid'];
  $qid = $form['#qid'];

  $wrapper = entity_metadata_wrapper('node', $pool);
  $question = $wrapper->field_question_reference[0]->value();
  $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->qid, $rid, $question);
  $elements = element_children($question_form);
  foreach ($elements as $element) {
    if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id')))
      $form[$element] = $question_form[$element];
  }
  unset($_SESSION['quiz_' . $qid]['pool_' . $pool->qid]);
}

/**
 * @see QuestionPool::getAnsweringForm()
 * ajax callack
 */
function question_pool_ajax_callback($form, $form_state) {
  return $form;
}

/**
 * @see QuestionPool::getAnsweringForm()
 * submit callback
 */
function question_pool_answer_submit(&$form, &$form_state) {
  $result_id = $form['#rid'];
  $pool = $form['#pool'];
  $quiz_id = $form['#qid'];
  $wrapper = entity_metadata_wrapper('node', $pool);
  $total = $wrapper->field_question_reference->count();
  $session = &$_SESSION['quiz_' . $quiz_id];
  $passed = &$session['pool_' . $pool->qid]['passed'];
  $answer = !empty($form_state['input']['tries']) ? $form_state['input']['tries'] : "";
  $question = $wrapper->field_question_reference[$session['pool_' . $pool->qid]['delta']]->value();

  if ($question) {
    $result = _question_pool_evaluate_question($pool, $question, $result_id, $answer);

    if ($result->is_valid !== TRUE) {
      form_set_error('tries', $result->is_valid);
    }
    else {
      if ($result->is_correct) {
        $passed = true;
      }

      if ($session['pool_' . $pool->qid]['delta'] < $total) {
        $session['pool_' . $pool->qid]['delta'] ++;
      }
    }
  }

  if ($passed) {
    $question = $wrapper->field_question_reference[$session['pool_' . $pool->qid]['delta'] - 1]->value();
    $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->qid, $result_id, $question);
    $elements = element_children($question_form);
    foreach ($elements as $element) {
      if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id')))
        unset($form[$element]);
    }
    $form['tries'] = array(
        '#type'       => 'hidden',
        '#value'      => 1,
        '#attributes' => array('class' => array('tries-pool-value')),
        '#name'       => 'tries'
    );
    $form['msg'] = array('#markup' => '<p class="pool-message">Pool is passed.</p>');
    unset($form['navigation']['pool_btn']);
    unset($form['navigation']['submit']['#attributes']['class']['js-hide']);
  }
  else {
    $question = $wrapper->field_question_reference[$session['pool_' . $pool->qid]['delta'] - 1]->value();
    $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->qid, $result_id, $question);
    $elements = element_children($question_form);
    foreach ($elements as $element) {
      if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id'))) {
        unset($form[$element]);
      }
    }
    if ($session['pool_' . $pool->qid]['delta'] < $total) {
      $question = $wrapper->field_question_reference[$session['pool_' . $pool->qid]['delta']]->value();
      $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->qid, $result_id, $question);
      $elements = element_children($question_form);
      foreach ($elements as $element) {
        if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id')))
          $form[$element] = $question_form[$element];
      }
    }
    else {
      $form['tries'] = array(
          '#type'       => 'hidden',
          '#value'      => 0,
          '#attributes' => array('class' => array('tries-pool-value')),
          '#name'       => 'tries'
      );
      $form['msg'] = array('#markup' => '<p class="pool-message">Pool is done.</p>');
      unset($form['navigation']['pool_btn']);
      unset($form['navigation']['submit']['#attributes']['class']['js-hide']);
    }
  }
  $form['navigation']['#weight'] = 100;
}

function question_pool_answer_form_rebuild($form) {
  $qid = $form['#qid'];
  $pool = $form['#pool'];
  $wrapper = entity_metadata_wrapper('node', $pool);
  $total = $wrapper->field_question_reference->count();

  $session = $_SESSION['quiz_' . $qid];
  $passed = $session['pool_' . $pool->qid]['passed'];

  if (!$passed && $session['pool_' . $pool->qid]['delta'] < $total) {
    $form['navigation']['submit']['#attributes']['class']['js-hide'] = 'js-hide';
  }
  return $form;
}

function _question_pool_evaluate_question($pool, $question, $result_id, $answer) {
  $response = _quiz_question_response_get_instance($result_id, $question, $answer);
  // Convert the response to a bare object.
  $result = $response->toBareObject();

  // If a result_id is set, we are taking a quiz.
  if ($result_id && isset($answer)) {
    // We don't know whether or not the user has gone back a question. However,
    // we do know that deleting a question for this result set should be safe in
    // the case where the user has not gone back (since there will be no entries
    // to delete). So we always run the delete.
    db_delete('quiz_pool_user_answers_questions')
      ->condition('pool_qid', $pool->qid)
      ->condition('pool_vid', $pool->vid)
      ->condition('question_qid', $result->qid)
      ->condition('question_vid', $result->vid)
      ->condition('result_id', $result->rid)
      ->execute();

    // fix error with score
    if ($result->score < 0) {
      $result->score = 0;
    }

    // Insert
    db_insert('quiz_pool_user_answers_questions')
      ->fields(array(
          'pool_qid'     => $pool->qid,
          'pool_vid'     => $pool->vid,
          'question_qid' => $result->qid,
          'question_vid' => $result->vid,
          'result_id'    => $result->rid,
          'answer'       => serialize($answer),
          'is_evaluated' => (int) $response->isEvaluated(),
          'is_correct'   => $result->is_correct,
          'score'        => (int) $result->score,
      ))
      ->execute();
  }
  return $result;
}
