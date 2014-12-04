<?php

namespace Drupal\question_pool;

use Drupal\question_pool\Form\AnswerForm;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionPlugin;

/**
 * Extension of QuizQuestion.
 */
class PoolQuestion extends QuestionPlugin {

  public function delete($single_revision = FALSE) {
    parent::delete($single_revision);
    $query = db_delete('quiz_pool_user_answers');
    $query->condition('question_qid', $this->question->qid);
    if ($single_revision) {
      $query->condition('question_vid', $this->question->vid);
    }
    $query->execute();
  }

  public function load() {
    $properties = parent::load();

    if (empty($this->question->field_question_reference['und'])) {
      return $properties;
    }

    // Referenced question maybe deleted. Remove them if it was
    /// @TODO: This should be resolved at entityreference module
    $ref_items = field_get_items('quiz_question', $this->question, 'field_question_reference');
    $this->question->field_question_reference['und'] = array();
    $field_items = &$this->question->field_question_reference['und'];
    foreach ($ref_items as $ref_item) {
      if ($ref_question = quiz_question_entity_load($ref_item['target_id'])) {
        $field_items[]['target_id'] = $ref_item['target_id'];
      }
    }

    return $properties;
  }

  /**
   * Implementation of getNodeView
   * @see QuizQuestion#getNodeView()
   */
  public function getEntityView() {
    $build = parent::getEntityView();
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);

    /* @var $sub_question Question */
    $markup = '';
    foreach ($wrapper->field_question_reference->getIterator() as $sub_wrapper) {
      $sub_question = $sub_wrapper->value();
      if ($content = $sub_question->getPlugin()->getEntityView() && !empty($content['answer'])) {
        $markup .= "<h3>{$sub_question->title}</h3>";
        $markup .= $content['answer']['#markup'];
      }
    }
    $build['answers']['#markup'] = $markup;
    return $build;
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
    $session = &$_SESSION['quiz'][$quiz->qid];
    $obj = new AnswerForm($quiz, $this->question, $session);
    return $obj->get($form, $form_state);
  }

  /**
   * Implementation of getMaximumScore.
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    $score = 0;
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    /* @var $question Question */
    foreach ($wrapper->field_question_reference->getIterator() as $wrapper_question) {
      // When referencing entity is deleted
      if ($question = $wrapper_question->value()) {
        $score += $question->getPlugin()->getMaximumScore();
      }
    }
    return $score;
  }

}
