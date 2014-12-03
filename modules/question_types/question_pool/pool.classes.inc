<?php

/**
 * The main classes for the multichoice question type.
 *
 * These inherit or implement code found in quiz_question.classes.inc.
 *
 * Sponsored by: Norwegian Centre for Telemedicine
 * Code: falcon
 *
 * Based on:
 * Other question types in the quiz framework.
 *
 *
 *
 * @file
 * Question type, enabling the creation of multiple choice and multiple answer questions.
 */

/**
 * Extension of QuizQuestion.
 */
class QuestionPool extends QuizQuestion {

  /**
   * Implementation of save
   *
   * Stores the question in the database.
   *
   * @param is_new if - if the node is a new node...
   * (non-PHPdoc)
   * @see sites/all/modules/quiz-HEAD/question_types/quiz_question/QuizQuestion#save()
   */
  public function saveNodeProperties($is_new = FALSE) {

  }

  /**
   * Implementation of validate
   *
   * QuizQuestion#validate()
   */
  public function validateNode(array &$form) {

  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete()
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    $delete_ans = db_delete('quiz_pool_user_answers');
    $delete_ans->condition('question_nid', $this->node->nid);

    if ($only_this_version) {
      $delete_ans->condition('question_vid', $this->node->vid);
    }
    $delete_ans->execute();
  }

  /**
   * Implementation of getNodeProperties
   *
   * @see QuizQuestion#getNodeProperties()
   */
  public function getNodeProperties() {
    $props = parent::getNodeProperties();

    return $props;
  }

  /**
   * Implementation of getNodeView
   *
   * @see QuizQuestion#getNodeView()
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    $wrapper = entity_metadata_wrapper('node', $this->node);
    $markup = '';
    foreach ($wrapper->field_question_reference->getIterator() as $delta => $wrapper_question) {
      $question = $wrapper_question->value();
      $question = _quiz_question_get_instance($question);
      $_content = $question->getNodeView();
      $markup .= "<h3>{$question->node->title}</h3>";
      $markup .= $_content['answers']['#markup'];
    }
    $content['answers'] = array(
      '#markup' => $markup
    );
    return $content;
  }

  /**
   * Generates the question form.
   *
   * This is called whenever a question is rendered, either
   * to an administrator or to a quiz taker.
   */
  public function getAnsweringForm(array $form_state = NULL, $rid) {
    $form = parent::getAnsweringForm($form_state, $rid);
    $quiz = menu_get_object();

    $session = &$_SESSION['quiz_' . $quiz->nid];

    $rid = $session['result_id'];
    $qid = $quiz->nid;

    if (!isset($session['pool_' . $this->node->nid]['delta'])) {
      $session['pool_' . $this->node->nid]['delta'] = 0;
      $session['pool_' . $this->node->nid]['passed'] = false;
//      $session['pool_' . $this->node->nid]['hide_submit'] = true;
      //$session['pool_' . $this->node->nid]['skip_validate'] = 1;
    }

    $wrapper = entity_metadata_wrapper('node', $this->node);
    $total = $wrapper->field_question_reference->count();

    $form['#rid'] = $rid;
    $form['#qid'] = $qid;
    $form['#pool'] = $this->node;

    $form['navigation']['pool_btn'] = array(
      '#type' => 'submit',
      '#value' => 'Next',
      '#name' => 'pool-btn',
      '#ajax' => array(
        'callback' => 'question_pool_ajax_callback',
        'wrapper' => 'quiz-question-answering-form',
      ),
      '#submit' => array('question_pool_answer_submit'),
    );

    if ($session['pool_' . $this->node->nid]['passed']) {
      $form['tries'] = array(
        '#type' => 'hidden',
        '#value' => 1,
        '#attributes' => array('class' => array('tries-pool-value'))
      );
      $form['msg'] = array('#markup' => '<p class="pool-message">Pool is passed.</p>');
      unset($form['navigation']['pool_btn']);
    }
    else {
      if ($session['pool_' . $this->node->nid]['delta'] < $total) {
        $question = $wrapper->field_question_reference[$session['pool_' . $this->node->nid]['delta']]->value();
        $question_form = drupal_get_form($question->type . '_question_pool_form__' . $question->nid, $rid, $question);
        $elements = element_children($question_form);
        foreach ($elements as $element) {
          if (!in_array($element, array('question_nid', 'form_build_id', 'form_token', 'form_id'))) {
            $form[$element] = $question_form[$element];
          }
        }
      }
      else {
        if($quiz->repeat_until_correct){
          $form['navigation']['retry'] = array(
            '#type' => 'submit',
            '#value' => 'Retry',
            '#submit' => array('question_pool_retry_submit'),
          );
        }else{
          $form['tries'] = array(
            '#type' => 'hidden',
            '#value' => 0,
            '#attributes' => array('class' => array('tries-pool-value'))
          );
          $form['msg'] = array('#markup' => '<p class="pool-message">Pool is done.</p>');
        }

        unset($form['navigation']['pool_btn']);
      }
    }

//    $form['tries'] = array(
//      '#type' => 'hidden',
//      '#value' => 0,
//      '#attributes' => array('class' => array('tries-pool-value'))
//    );
    $form['#after_build'][] = 'question_pool_answer_form_rebuild';
    //$form['#attached']['css'][] = drupal_get_path('module', 'question_pool') . '/question_pool.css';
//    $form['#attached']['js'][] = array(drupal_get_path('module', 'question_pool') . '/question_pool.js' => array(
//      'type' => 'file',
//    ));
//    drupal_add_js(drupal_get_path('module', 'question_pool') . '/question_pool.js');
    return $form;
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form = array();
    return $form;
  }

  /**
   * Implementation of getMaximumScore.
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    $score = 0;
    $node = node_load($this->node->nid, $this->node->vid);
    $wrapper = entity_metadata_wrapper('node', $node);
    foreach ($wrapper->field_question_reference->getIterator() as $delta => $wrapper_question) {
      $question = $wrapper_question->value();
      //fix entity reference when referencing node is deleted
      if(!$question){
        continue;
      }
      $question = _quiz_question_get_instance($question);
      $score += $question->getMaximumScore();
    }
    return $score;
  }

}

/**
 * Extension of QuizQuestionResponse
 */
class ResponsePool extends QuizQuestionResponse {

  /**
   * ID of the answers.
   */
  protected $user_answer_ids;
  protected $choice_order;
  protected $need_evaluated;

  /**
   * Constructor
   */
  public function __construct($result_id, stdClass $question_node, $answer = NULL) {
    parent::__construct($result_id, $question_node, $answer);
    if (!isset($answer)) {
      $r = $this->getCorrectAnswer();
      if (!empty($r)) {
        $this->answer = $r->answer;
        $this->score = $r->score;
      }
    }
    else {
      $this->answer = $answer;
    }
  }

  /**
   * Implementation of getCorrectAnswer
   */
  public function getCorrectAnswer() {
    return db_query('SELECT answer, score FROM {quiz_pool_user_answers} WHERE question_vid = :qvid AND result_id = :rid', array(':qvid' => $this->question->vid, ':rid' => $this->rid))->fetch();
  }

  /**
   * Implementation of isValid
   *
   * @see QuizQuestionResponse#isValid()
   */
  public function isValid() {
    return ($this->answer == 2) ? t('You haven\'t completed the quiz pool') : TRUE;
  }

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   */
  public function isEvaluated() {
    return TRUE;
  }

  /**
   * Implementation of save
   *
   * @see QuizQuestionResponse#save()
   */
  public function save() {

//    $results = db_select('quiz_pool_user_answers_questions', 'p')
//        ->fields('p', array('question_nid', 'is_correct'))
//        ->condition('pool_nid', $this->question->nid)
//        ->condition('pool_vid', $this->question->vid)
//        ->condition('result_id', $this->rid)
//        ->execute()
//        ->fetchAllKeyed();
//
//    if (empty($results)) {
//      $this->answer = 2;
//    } elseif (in_array(1, $results)) {
//      $this->answer = 1;
//    } else {
//      $this->answer = 0;
//    }

    db_insert('quiz_pool_user_answers')
        ->fields(array(
          'question_nid' => $this->question->nid,
          'question_vid' => $this->question->vid,
          'result_id' => $this->rid,
          'score' => (int) $this->getScore(),
          'answer' => (int) $this->answer,
        ))
        ->execute();
  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestionResponse#delete()
   */
  public function delete() {
    db_delete('quiz_pool_user_answers')
        ->condition('question_nid', $this->question->nid)
        ->condition('question_vid', $this->question->vid)
        ->condition('result_id', $this->rid)
        ->execute();

    // Please view quiz_question.module line 277.
    // $response->delete();
    // $response->saveResult();
    // The quiz question delete and resave instead update
    // We have a difference between $respone update and delete.
    // Question update $this->question is node
    // Question delete $this->question is custom object.
    if (!isset($this->question->created)) {
      db_delete('quiz_pool_user_answers_questions')
          ->condition('pool_nid', $this->question->nid)
          ->condition('pool_vid', $this->question->vid)
          ->condition('result_id', $this->rid)
          ->execute();
    }
  }

  /**
   * Implementation of score
   *
   * @return uint
   *
   * @see QuizQuestionResponse#score()
   */
  public function score() {
    return $this->answer ? $this->getMaxScore() : 0;
  }

  /**
   * If all answers in a question is wrong
   *
   * @return boolean
   *  TRUE if all answers are wrong. False otherwise.
   */
  public function isAllWrong() {

    return FALSE;
  }

  /**
   * Implementation of getResponse
   *
   * @return answer
   *
   * @see QuizQuestionResponse#getResponse()
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Implementation of getReportFormResponse
   *
   * @see getReportFormResponse($showpoints, $showfeedback, $allow_scoring)
   */
  public function getReportFormResponse($showpoints = TRUE, $showfeedback = TRUE, $allow_scoring = FALSE) {
    $result = db_select('quiz_pool_user_answers_questions', 'p')
        ->fields('p', array('question_nid', 'question_nid'))
        ->condition('pool_nid', $this->question->nid)
        ->condition('pool_vid', $this->question->vid)
        ->condition('result_id', $this->rid)
        ->condition('is_correct', 1)
        ->execute()
        ->fetchAllKeyed();

    if (empty($result)) {
      $markup = t('No question passed.');
    }
    else {
      $question_id = reset($result);
      $node = node_load($question_id);
      $markup = t('Passed at @title', array('@title' => $node->title));
    }
    return array('#markup' => $markup);
  }

}
