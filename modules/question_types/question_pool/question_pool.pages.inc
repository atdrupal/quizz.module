<?php

function question_pool_retry_submit(&$form, &$form_state) {
  $pool = $form['#pool'];
  $rid = $form['#rid'];
  $qid = $form['#qid'];

  $wrapper = entity_metadata_wrapper('quiz_question', $pool);
  $question = $wrapper->field_question_reference[0]->value();
  $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->qid, $rid, $question);
  $elements = element_children($question_form);
  foreach ($elements as $element) {
    if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id')))
      $form[$element] = $question_form[$element];
  }
  unset($_SESSION['quiz_' . $qid]['pool_' . $pool->qid]);
}
