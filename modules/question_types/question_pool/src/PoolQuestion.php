<?php

namespace Drupal\question_pool;

use Drupal\question_pool\Form\AnswerForm;
use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionPlugin;

/**
 * Extension of QuizQuestion.
 */
class PoolQuestion extends QuestionPlugin {

  public function save($is_new = FALSE) {
    parent::save($is_new);

    db_update('quiz_question_properties')
      ->fields(array('max_score' => $this->question->getPlugin()->getMaximumScore()))
      ->condition('qid', $this->question->qid)
      ->condition('vid', $this->question->vid)
      ->execute();
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
   * Implementation of delete
   * @see QuestionPlugin::delete()
   */
  public function delete($single_revision = FALSE) {
    parent::delete($single_revision);
    $delete_ans = db_delete('quiz_pool_user_answers');
    $delete_ans->condition('question_qid', $this->question->qid);
    if ($single_revision) {
      $delete_ans->condition('question_vid', $this->question->vid);
    }
    $delete_ans->execute();
  }

  /**
   * Implementation of getNodeView
   * @see QuizQuestion#getNodeView()
   */
  public function getNodeView() {
    $build = parent::getNodeView();
    $wrapper = entity_metadata_wrapper('quiz_question', $this->question);
    $markup = '';

    /* @var $question Question */
    foreach ($wrapper->field_question_reference->getIterator() as $wrapper_question) {
      $question = $wrapper_question->value();
      $plugin = $question->getPlugin();
      if ($content = $plugin->getNodeView()) {
        $markup .= "<h3>{$question->title}</h3>";
        $markup .= $content['answers']['#markup'];
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
