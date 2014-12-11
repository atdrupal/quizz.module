<?php

namespace Drupal\quizz\Entity;

use DatabaseTransaction;
use Drupal\quizz\Entity\QuizEntity\DefaultPropertiesIO;
use Drupal\quizz\Entity\QuizEntity\MaxScoreWriter;
use Drupal\quizz\Entity\QuizEntity\ResultGenerator;
use Drupal\quizz\Entity\QuizEntity\Stats;
use EntityAPIController;
use stdClass;

class QuizController extends EntityAPIController {

  /** @var DefaultPropertiesIO */
  private $default_properties_io;

  /** @var Stats */
  private $stats;

  /** @var MaxScoreWriter */
  private $max_score_writer;

  /** @var ResultGenerator */
  private $result_generator;

  /**
   * @return DefaultPropertiesIO
   */
  public function getSettingIO() {
    if (NULL === $this->default_properties_io) {
      $this->default_properties_io = new DefaultPropertiesIO();
    }
    return $this->default_properties_io;
  }

  public function getStats() {
    if (NULL === $this->stats) {
      $this->stats = new Stats();
    }
    return $this->stats;
  }

  public function getMaxScoreWriter() {
    if (NULL === $this->max_score_writer) {
      $this->max_score_writer = new MaxScoreWriter();
    }
    return $this->max_score_writer;
  }

  public function getResultGenerator() {
    if (NULL === $this->result_generator) {
      $this->result_generator = new ResultGenerator();
    }
    return $this->result_generator;
  }

  /**
   * Get the feedback options for Quizzes.
   */
  public function getFeedbackOptions() {
    $feedback_options = array();

    $feedback_options += array(
        'attempt'           => t('Attempt'),
        'choice'            => t('Choices'),
        'correct'           => t('Whether correct'),
        'score'             => t('Score'),
        'answer_feedback'   => t('Answer feedback'),
        'question_feedback' => t('Question feedback'),
        'solution'          => t('Correct answer'),
        'quiz_feedback'     => t('@quiz feedback', array('@quiz' => QUIZ_NAME)),
    );

    drupal_alter('quiz_feedback_options', $feedback_options);

    return $feedback_options;
  }

  /**
   * @param QuizEntity $quiz
   */
  public function buildContent($quiz, $view_mode = 'full', $langcode = NULL, $content = array()) {
    global $user;

    $extra_fields = field_extra_fields_get_display($this->entityType, $quiz->type, $view_mode);

    // Render Stats
    if ($extra_fields['stats']['visible']) {
      // Number of questions is needed on the statistics page.
      $quiz->number_of_questions = $quiz->number_of_random_questions;
      $quiz->number_of_questions += $this->getStats()->countAlwaysQuestions($quiz->vid);

      $content['quiz_entity'][$quiz->qid]['stats'] = array(
          '#markup' => theme('quiz_view_stats', array('quiz' => $quiz)),
          '#weight' => $extra_fields['stats']['weight'],
      );
    }

    // Render take button
    if ($extra_fields['take']['visible']) {
      $markup = l(t('Start @quiz', array('@quiz' => QUIZ_NAME)), 'quiz/' . $quiz->qid . '/take');
      if (TRUE !== $checking = $quiz->isAvailable($user)) {
        $markup = $checking;
      }

      $content['quiz_entity'][$quiz->qid]['take'] = array(
          '#prefix' => '<div class="quiz-not-available">',
          '#suffix' => '</div>',
          '#weight' => $extra_fields['take']['weight'],
          '#markup' => $markup,
      );
    }

    return parent::buildContent($quiz, $view_mode, $langcode, $content);
  }

  public function load($ids = array(), $conditions = array()) {
    $entities = parent::load($ids, $conditions);

    // quiz_entity_revision.review_options => serialize = TRUE already, not sure
    // why it's string here
    foreach ($entities as $entity) {
      $vids[] = $entity->vid;
      if (!empty($entity->review_options) && is_string($entity->review_options)) {
        $entity->review_options = unserialize($entity->review_options);
      }
    }

    if (!empty($vids)) {
      $result_options = db_select('quiz_result_options', 'ro')
        ->fields('ro')
        ->condition('ro.quiz_vid', $vids)
        ->execute();
      foreach ($result_options->fetchAll() as $result_option) {
        $entities[$result_option->quiz_qid]->result_options[] = (array) $result_option;
      }
    }

    return $entities;
  }

  /**
   * @param QuizEntity $quiz
   * @param DatabaseTransaction $transaction
   */
  public function save($quiz, DatabaseTransaction $transaction = NULL) {
    // QuizFeedbackTest::testFeedback() failed without this, mess!
    if (empty($quiz->is_new_revision)) {
      $quiz->is_new = $quiz->revision = 0;
    }

    if ($return = parent::save($quiz, $transaction)) {
      $this->saveResultOptions($quiz);
      return $return;
    }
  }

  /**
   * Force save revision author ID.
   *
   * @global stdClass $user
   * @param QuizEntity $quiz
   */
  protected function saveRevision($quiz) {
    global $user;
    $quiz->revision_uid = $user->uid;
    $return = parent::saveRevision($quiz);

    if (!empty($quiz->clone_relationships) && ($quiz->vid != $quiz->old_vid)) {
      $this->cloneRelationship($quiz, $quiz->old_vid);
    }

    return $return;
  }

  private function cloneRelationship(QuizEntity $quiz, $previous_vid) {
    // The cloning logic implemented somewhere. This legacy code should be removed later.
    if ($quiz->getQuestionIO()->getQuestionList()) {
      return;
    }

    if (!$revision = quiz_load(NULL, $previous_vid, TRUE)) {
      return;
    }

    foreach ($revision->getQuestionIO()->getQuestionList() as $relationship) {
      if (empty($relationship['random'])) {
        if ($relationship = quiz_relationship_load($relationship['qr_id'])) {
          $relationship->qr_id = NULL;
          $relationship->quiz_vid = $quiz->vid;
          $relationship->save();
        }
      }
    }
  }

  private function saveResultOptions(QuizEntity $quiz) {
    db_delete('quiz_result_options')
      ->condition('quiz_vid', $quiz->vid)
      ->execute();

    $query = db_insert('quiz_result_options')
      ->fields(array('quiz_qid', 'quiz_vid', 'option_name', 'option_summary', 'option_summary_format', 'option_start', 'option_end'));

    foreach ($quiz->result_options as $option) {
      if (empty($option['option_name'])) {
        continue;
      }

      // When this function called direct from node form submit the
      // $option['option_summary']['value'] and $option['option_summary']['format'] are we need
      // But when updating a quiz entity eg. on manage questions page, this values
      // come from loaded node, not from a submitted form.
      if (is_array($option['option_summary'])) {
        $option['option_summary_format'] = $option['option_summary']['format'];
        $option['option_summary'] = $option['option_summary']['value'];
      }

      $query->values(array(
          'quiz_qid'              => $quiz->qid,
          'quiz_vid'              => $quiz->vid,
          'option_name'           => $option['option_name'],
          'option_summary'        => $option['option_summary'],
          'option_summary_format' => $option['option_summary_format'],
          'option_start'          => $option['option_start'],
          'option_end'            => $option['option_end']
      ));
    }

    $query->execute();
  }

  public function delete($ids, DatabaseTransaction $transaction = NULL) {
    $return = parent::delete($ids, $transaction);

    // Delete quiz results
    $query = db_select('quiz_results');
    $query->fields('quiz_results', array('result_id'));
    $query->condition('quiz_qid', $ids);
    if ($result_ids = $query->execute()->fetchCol()) {
      entity_delete_multiple('quiz_result', $result_ids);
    }

    db_delete('quiz_relationship')->condition('quiz_qid', $ids)->execute();
    db_delete('quiz_results')->condition('quiz_qid', $ids)->execute();
    db_delete('quiz_result_options')->condition('quiz_qid', $ids)->execute();

    return $return;
  }

  /**
   * Get latest quiz ID, useful for test cases.
   *
   * @return int|null
   */
  public function getLatestQuizId() {
    return db_select('quiz_entity', 'quiz')
        ->fields('quiz', array('qid'))
        ->orderBy('quiz.qid', 'DESC')
        ->execute()
        ->fetchColumn();
  }

}
