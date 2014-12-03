<?php

namespace Drupal\question_pool;

use Drupal\question_pool\Form\AnswerForm;
use Drupal\quiz_question\QuestionPlugin;

/**
 * Extension of QuizQuestion.
 */
class PoolQuestion extends QuestionPlugin {

  private $question;

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete()
   */
  public function delete($single_revision = FALSE) {
    parent::delete($single_revision);
    $delete_ans = db_delete('quiz_pool_user_answers');
    $delete_ans->condition('question_nid', $this->question->qid);

    if ($single_revision) {
      $delete_ans->condition('question_vid', $this->question->vid);
    }
    $delete_ans->execute();
  }

  /**
   * Implementation of getNodeView
   *
   * @see QuizQuestion#getNodeView()
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    $wrapper = entity_metadata_wrapper('node', $this->question);
    $markup = '';
    foreach ($wrapper->field_question_reference->getIterator() as $delta => $wrapper_question) {
      $question = $wrapper_question->value();
      $question = _quiz_question_get_instance($question);
      $_content = $question->getNodeView();
      $markup .= "<h3>{$question->question->title}</h3>";
      $markup .= $_content['answers']['#markup'];
    }
    $content['answers']['#mariup'] = $markup;
    return $content;
  }

  /**
   * Generates the question form.
   *
   * This is called whenever a question is rendered, either
   * to an administrator or to a quiz taker.
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $quiz = quiz_result_load($result_id)->getQuiz();
    $form = parent::getAnsweringForm($form_state, $result_id);
    $obj = new AnswerForm($quiz, $this->question, $_SESSION['quiz_' . $quiz->qid]);
    return $obj->get($form);
  }

  /**
   * Implementation of getMaximumScore.
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    $score = 0;
    $question_entity = quiz_question_entity_load($this->question->qid, $this->question->vid);
    $wrapper = entity_metadata_wrapper('quiz_question', $question_entity);
    /* @var $question \Drupal\quiz_question\Entity\Question */
    foreach ($wrapper->field_question_reference->getIterator() as $wrapper_question) {
      // When referencing entity is deleted
      if ($question = $wrapper_question->value()) {
        $score += $question->getPlugin()->getMaximumScore();
      }
    }
    return $score;
  }

}
