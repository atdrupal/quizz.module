<?php

namespace Drupal\question_pool\Form;

use Drupal\quiz_question\Entity\Question;
use Drupal\quizz\Entity\QuizEntity;

class AnswerForm {

  private $quiz;
  private $question;
  private $session;
  private $session_key;
  private $result_id;

  public function __construct(QuizEntity $quiz, Question $question, &$session) {
    $this->quiz = $quiz;
    $this->question = $question;
    $this->session = &$session;
    $this->session_key = "pool_{$this->question->qid}";
    $this->result_id = $this->session['result_id'];

    if (!isset($this->session[$this->session_key]['delta'])) {
      $this->session[$this->session_key]['delta'] = 0;
      $this->session[$this->session_key]['passed'] = FALSE;
    }
  }

  public function get($form, &$form_state) {
    // passed
    if ($this->session[$this->session_key]['passed']) {
      $form['tries'] = array('#type' => 'hidden', '#value' => 1, '#attributes' => array('class' => array('tries-pool-value')));
      $form['msg'] = array('#markup' => t('Pool is passed.'), '#prefix' => '<p class="pool-message">', '#suffix' => '</p>');
      return $form;
    }

    // unpassed
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    if ($wrapper->field_question_reference->count() > $this->session[$this->session_key]['delta']) {
      $delta = $this->session[$this->session_key]['delta'];
      $question = $wrapper->field_question_reference[$delta]->value();
      $form[$question->qid] = $question->getHandler()->getAnsweringForm($form_state, $this->result_id);
    }

    return array(
        '#rid'  => $this->result_id,
        '#qid'  => $this->quiz->qid,
        '#pool' => $this->question
      ) + $form;
  }

}
