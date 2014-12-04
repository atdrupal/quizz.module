<?php

namespace Drupal\question_pool\Form;

use Drupal\quiz_question\Entity\Question;
use Drupal\quizz\Entity\QuizEntity;

class AnswerForm {

  private $quiz;
  private $question;
  private $session;
  private $result_id;

  public function __construct(QuizEntity $quiz, Question $question, &$session) {
    require_once drupal_get_path('module', 'question_pool') . '/question_pool.pages.inc';
    $this->quiz = $quiz;
    $this->question = $question;
    $this->session = &$session;
    $this->result_id = $this->session['result_id'];
  }

  public function get(&$form) {
    $quiz_id = $this->quiz->qid;

    if (!isset($this->session['pool_' . $this->question->qid]['delta'])) {
      $this->session['pool_' . $this->question->qid]['delta'] = 0;
      $this->session['pool_' . $this->question->qid]['passed'] = false;
    }

    $form['#rid'] = $this->result_id;
    $form['#qid'] = $quiz_id;
    $form['#pool'] = $this->question;

    $form['navigation']['pool_btn'] = array(
        '#type'   => 'submit',
        '#value'  => 'Next',
        '#name'   => 'pool-btn',
        '#ajax'   => array(
            'callback' => 'question_pool_ajax_callback',
            'wrapper'  => 'quiz-question-answering-form',
        ),
        '#submit' => array('question_pool_answer_submit'),
    );

    if ($this->session['pool_' . $this->question->qid]['passed']) {
      $this->getPassed($form);
    }
    else {
      $this->getUnpassed($form);
    }

    $form['#after_build'][] = 'question_pool_answer_form_rebuild';
  }

  private function getPassed(&$form) {
    $form['tries'] = array(
        '#type'       => 'hidden',
        '#attributes' => array('class' => array('tries-pool-value')),
        '#value'      => 1,
    );
    $form['msg'] = array(
        '#prefix' => '<p class="pool-message">',
        '#markup' => t('Pool is passed.'),
        '#suffix' => '</p>',
    );
    unset($form['navigation']['pool_btn']);
  }

  private function getUnpassed(&$form) {
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    if ($wrapper->field_question_reference->count() > $this->session['pool_' . $this->question->qid]['delta']) {
      $this->getGetUnpassed($wrapper, $form);
    }

    if ($this->quiz->repeat_until_correct) {
      $form['navigation']['retry'] = array('#type' => 'submit', '#submit' => array('question_pool_retry_submit'), '#value' => 'Retry');
    }
    else {
      $form['tries'] = array('#type' => 'hidden', '#value' => 0, '#attributes' => array('class' => array('tries-pool-value')));
      $form['msg'] = array('#markup' => '<p class="pool-message">Pool is done.</p>');
    }

    unset($form['navigation']['pool_btn']);
  }

  private function getGetUnpassed($wrapper, &$form) {
    $sub_question = $wrapper->field_question_reference[$this->session['pool_' . $this->question->qid]['delta']]->value();
    $question_form = drupal_get_form($sub_question->type . '_question_pool_form__' . $sub_question->qid, $this->result_id, $sub_question);
    $elements = element_children($question_form);
    foreach ($elements as $element) {
      if (!in_array($element, array('question_qid', 'form_build_id', 'form_token', 'form_id'))) {
        $form[$element] = $question_form[$element];
      }
    }
    return;
  }

}
