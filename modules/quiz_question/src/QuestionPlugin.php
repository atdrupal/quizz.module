<?php

namespace Drupal\quiz_question;

use Drupal\quizz\Controller\QuestionFeedbackController;
use Drupal\quizz\Entity\QuizEntity;

/**
 * QUESTION IMPLEMENTATION FUNCTIONS
 *
 * This part acts as a contract(/interface) between the question-types and the
 * rest of the system.
 *
 * Question plugins are made by extending these generic methods and abstract
 * methods. Check multichoice question plugin for example.
 *
 * A base implementation of a question plugin, adding a layer of abstraction
 * between the node API, quiz API and the question plugins.
 *
 * It is required that question plugins extend this abstract class.
 *
 * This class has default behaviour that all question types must have. It also
 * handles the node API, but gives the question types oppurtunity to save,
 * delete and provide data specific to the question types.
 *
 * This abstract class also declares several abstract functions forcing
 * question-types to implement required methods.
 */
abstract class QuestionPlugin {

  /**
   * @var \Drupal\quiz_question\Entity\Question
   * The current question entity.
   */
  public $question = NULL;

  /**
   * Extra node properties
   */
  public $properties = NULL;

  /**
   * QuizQuestion constructor stores the node object.
   *
   * @param $question
   *   The node object
   */
  public function __construct(&$question) {
    $this->question = $question;
  }

  /**
   * Allow question types to override the body field title
   *
   * @return string
   *  The title for the body field
   */
  public function getBodyFieldTitle() {
    return t('Question');
  }

  /**
   * Returns a node form to quiz_question_form
   *
   * Adds default form elements, and fetches question type specific elements from their
   * implementation of getCreationForm
   *
   * @param array $form_state
   * @return unknown_type
   */
  public function getEntityForm(array &$form_state = NULL, QuizEntity $quiz = NULL) {
    $obj = new \Drupal\quiz_question\Form\QuestionForm($this->question);
    return $obj->getForm($form_state, $quiz);
  }

  /**
   * Retrieve information relevant for viewing the node.
   *
   * (This data is generally added to the node's extra field.)
   *
   * @return array
   *  Content array
   */
  public function getEntityView() {
    $output['question_type'] = array(
        '#weight' => -2,
        '#prefix' => '<div class="question_type_name">',
        '#suffix' => '</div>',
    );
    $output['#markup'] = $this->question->getQuestionType()->label;
    return $output;
  }

  /**
   * Getter function returning properties to be loaded when the node is loaded.
   *
   * @see load hook in quiz_question.module (quiz_question_load)
   *
   * @return array
   */
  public function load() {
    if (isset($this->properties)) {
      return $this->properties;
    }

    $props['is_quiz_question'] = TRUE;
    $this->properties = $props;

    return $props;
  }

  /**
   * Responsible for handling insert/update of question-specific data.
   * This is typically called from within the Node API, so there is no need
   * to save the node.
   *
   * The $is_new flag is set to TRUE whenever the node is being initially
   * created.
   *
   * A save function is required to handle the following three situations:
   * - A new node is created ($is_new is TRUE)
   * - A new node *revision* is created ($is_new is NOT set, because the
   *   node itself is not new).
   * - An existing node revision is modified.
   *
   * @see hook_update and hook_insert in quiz_question.module
   *
   * @param $is_new
   *  TRUE when the node is initially created.
   */
  public function save($is_new = FALSE) {
    // We call the abstract function saveEntityProperties to save type specific data
    $this->saveEntityProperties($is_new);

    // Save what quizzes this question belongs to.
    $quizzes_kept = $this->saveRelationships();
    if ($quizzes_kept && $this->question->revision) {
      if (user_access('manual quiz revisioning') && !variable_get('quiz_auto_revisioning', 1)) {
        unset($_GET['destination']);
        unset($_REQUEST['edit']['destination']);
        drupal_goto('quiz-question/' . $this->question->qid . '/' . $this->question->vid . '/revision-actions');
      }
      // For users without the 'manual quiz revisioning' permission we submit the revision_actions form
      // silently with its default values set.
      else {
        $form_state = array();
        $form_state['values']['op'] = t('Submit');
        require_once DRUPAL_ROOT . '/' . drupal_get_path('module', 'quiz_question') . '/quiz_question.pages.inc';
        drupal_form_submit('quiz_question_revision_actions', $form_state, $this->question->qid, $this->question->vid);
      }
    }
  }

  /**
   * Delete question data from the database.
   *
   * Called by quiz_question_delete (hook_delete).
   * Child classes must call super
   *
   * @param $only_this_version
   *  If the $only_this_version flag is TRUE, then only the particular
   *  qid/vid combo should be deleted. Otherwise, all questions with the
   *  current qid can be deleted.
   */
  public function delete($only_this_version = FALSE) {
    // Delete answeres & properties
    $remove_answer = db_delete('quiz_results_answers')->condition('question_qid', $this->question->qid);
    if ($only_this_version) {
      $remove_answer->condition('question_vid', $this->question->vid);
    }
    $remove_answer->execute();
  }

  /**
   * Provides validation for question before it is created.
   *
   * When a new question is created and initially submited, this is
   * called to validate that the settings are acceptible.
   *
   * @param $form
   *  The processed form.
   */
  public function validate(array &$form) {

  }

  /**
   * Get the form through which the user will answer the question.
   *
   * @param $form_state
   *  The FAPI form_state array
   * @param $result_id
   *  The result id.
   * @return
   *  Must return a FAPI array.
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    return array('#element_validate' => array('quiz_question_element_validate'));
  }

  /**
   * Element validator (for repeat until correct).
   */
  public static function elementValidate(&$element, &$form_state) {
    $quiz = quiz_load(quiz_get_id_from_url());

    $question_qid = $element['#array_parents'][1];
    $answer = $form_state['values']['question'][$question_qid];
    $current_question = quiz_question_entity_load($question_qid);

    // There was an answer submitted.
    $response = quiz_answer_controller()->getInstance($_SESSION['quiz'][$quiz->qid]['result_id'], $current_question, $answer);
    if ($quiz->repeat_until_correct && !$response->isCorrect()) {
      form_set_error('', t('The answer was incorrect. Please try again.'));

      $result = $form_state['build_info']['args'][3];
      $controller = new QuestionFeedbackController($quiz, $result);
      $feedback = $controller->buildRenderArray($current_question);
      $element['feedback'] = array(
          '#weight' => 100,
          '#markup' => drupal_render($feedback),
      );
    }
  }

  /**
   * Get the form used to create a new question.
   * @param array FAPI form state
   * @return array Must return a FAPI array.
   */
  public function getCreationForm(array &$form_state = NULL) {
    return array();
  }

  /**
   * Get the maximum possible score for this question.
   */
  abstract public function getMaximumScore();

  /**
   * Save question type specific node properties
   */
  public function saveEntityProperties($is_new = FALSE) {
    
  }

  /**
   * Save this Question to the specified Quiz.
   *
   * @param int $quiz_qid
   * @param int $quiz_vid
   * @return bool
   *  TRUE if relationship is made.
   */
  function saveRelationships($quiz_qid = NULL, $quiz_vid = NULL) {
    if (!$quiz_qid || !$quiz_vid || !$quiz = quiz_load($quiz_qid, $quiz_vid)) {
      return FALSE;
    }

    // We need to revise the quiz if it has been answered.
    if (quiz_has_been_answered($quiz)) {
      $quiz->is_new_revision = 1;
      $quiz->save();
      drupal_set_message(t('New revision has been created for the @quiz %n', array('%n' => $quiz->title, '@quiz' => QUIZ_NAME)));
    }

    $values = array();
    $values['quiz_qid'] = $quiz_qid;
    $values['quiz_vid'] = $quiz_vid;
    $values['question_qid'] = $this->question->qid;
    $values['question_vid'] = $this->question->vid;
    $values['max_score'] = $this->getMaximumScore();
    $values['auto_update_max_score'] = $this->autoUpdateMaxScore() ? 1 : 0;
    // @TODO: Do not need extra query here
    $values['weight'] = 1 + db_query('SELECT MAX(weight) FROM {quiz_relationship} WHERE quiz_vid = :vid', array(':vid' => $quiz->vid))->fetchField();
    $values['question_status'] = $quiz->randomization == 2 ? QUIZ_QUESTION_RANDOM : QUIZ_QUESTION_ALWAYS;
    entity_create('quiz_relationship', $values)->save();

    // Update max_score for relationships if auto update max score is enabled
    // for question
    $update_quiz_ids = array();
    $sql = 'SELECT quiz_vid as vid FROM {quiz_relationship} WHERE question_qid = :qid AND question_vid = :vid AND auto_update_max_score = 1';
    $result = db_query($sql, array(
        ':qid' => $this->question->qid,
        ':vid' => $this->question->vid));
    foreach ($result as $record) {
      $update_quiz_ids[] = $record->vid;
    }

    db_update('quiz_relationship')
      ->fields(array('max_score' => $this->getMaximumScore()))
      ->condition('question_qid', $this->question->qid)
      ->condition('question_vid', $this->question->vid)
      ->condition('auto_update_max_score', 1)
      ->execute();

    if (!empty($update_quiz_ids)) {
      quiz_update_max_score_properties($update_quiz_ids);
    }

    quiz_update_max_score_properties(array($quiz->vid));

    return TRUE;
  }

  /**
   * Finds out if a question has been answered or not
   *
   * This function also returns TRUE if a quiz that this question belongs to
   * have been answered. Even if the question itself haven't been answered.
   * This is because the question might have been rendered and a user is about
   * to answer it…
   *
   * @return
   *   true if question has been answered or is about to be answered…
   */
  public function hasBeenAnswered() {
    if (!isset($this->question->vid)) {
      return FALSE;
    }

    $answered = db_query_range('SELECT 1 '
      . ' FROM {quiz_results} qnres '
      . ' JOIN {quiz_relationship} qrel ON (qnres.quiz_vid = qrel.quiz_vid) '
      . ' WHERE qrel.question_vid = :question_vid', 0, 1, array(':question_vid' => $this->question->vid))->fetch();

    return $answered ? TRUE : FALSE;
  }

  /**
   * Determines if the user can view the correct answers
   *
   * @todo grabbing the node context here probably isn't a great idea
   *
   * @return boolean
   *   true if the view may include the correct answers to the question
   */
  public function viewCanRevealCorrect() {
    global $user;

    $reveal_correct[] = user_access('view any quiz question correct response');
    $reveal_correct[] = ($user->uid == $this->question->uid);
    if (array_filter($reveal_correct)) {
      return TRUE;
    }
  }

  /**
   * Utility function that returns the format of the node body
   */
  protected function getFormat() {
    $node = isset($this->question) ? $this->question : $this->question;
    $body = field_get_items('node', $node, 'body');
    return isset($body[0]['format']) ? $body[0]['format'] : NULL;
  }

  /**
   * This may be overridden in subclasses. If it returns true,
   * it means the max_score is updated for all occurrences of
   * this question in quizzes.
   */
  protected function autoUpdateMaxScore() {
    return false;
  }

  public function getAnsweringFormValidate(array &$form, array &$form_state = NULL) {

  }

  /**
   * Is this question graded?
   *
   * Questions like Quiz Directions, Quiz Page, and Scale are not.
   *
   * By default, questions are expected to be gradeable
   *
   * @return bool
   */
  public function isGraded() {
    return TRUE;
  }

  /**
   * Does this question type give feedback?
   *
   * Questions like Quiz Directions and Quiz Pages do not.
   *
   * By default, questions give feedback
   *
   * @return bool
   */
  public function hasFeedback() {
    return TRUE;
  }

}
