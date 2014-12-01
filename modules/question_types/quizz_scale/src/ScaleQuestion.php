<?php

namespace Drupal\quizz_scale;

use Drupal\quiz_question\QuestionPlugin;
use Drupal\quizz_scale\CollectionIO;
use Drupal\quizz_scale\Form\ScaleQuestionForm;

/**
 * @TODO: We mix the names answer_collection and alternatives. Use either
 * alternative or answer consistently
 */
class ScaleQuestion extends QuestionPlugin {

  /**
   * will be set to true if an instance of this class is used only as a utility.
   *
   * @var bool $util
   */
  protected $util = FALSE;

  /**
   * (answer) Collection id
   * @var int
   */
  protected $col_id = NULL;

  /** @var CollectionIO */
  protected $collection_io;

  public function getCollectionIO() {
    if (NULL === $this->collection_io) {
      $this->collection_io = new CollectionIO();
    }
    return $this->collection_io;
  }

  /**
   * Tells the instance that it is beeing used as a utility.
   *
   * @param $c_id - answer collection id
   */
  public function initUtil($c_id) {
    $this->util = TRUE;
    $this->col_id = $c_id;
  }

  /**
   * Implementation of saveEntityProperties
   *
   * @see QuizQuestion#saveEntityProperties()
   */
  public function saveEntityProperties($is_new = FALSE) {
    global $user;

    if ($this->question->revision == 1) {
      $is_new = TRUE;
    }

    $collection_id = quizz_scale_collection_controller()->saveQuestionAlternatives($this->question, $is_new);

    // Save the answer collection as a preset if the save preset option is checked
    if (!empty($this->question->save)) {
      quizz_scale_collection_controller()->changeOwner($collection_id, $user->uid);
    }

    db_merge('quiz_scale_properties')
      ->key(array('qid' => $this->question->vid, 'vid' => $this->question->vid))
      ->fields(array('answer_collection_id' => $collection_id))
      ->execute()
    ;
  }

  /**
   * Implementation of validateNode
   *
   * @see QuizQuestion#validate()
   */
  public function validate(array &$form) {

  }

  /**
   * Implementation of delete
   *
   * @see QuizQuestion#delete()
   */
  public function delete($single_revision = FALSE) {
    parent::delete($single_revision);

    $qid = $this->question->qid;
    $vid = $this->question->vid;
    $cid = $this->question->{0}->answer_collection_id;

    if ($single_revision) {
      db_delete('quiz_scale_user_answers')->condition('question_qid', $qid)->condition('question_vid', $vid)->execute();
      db_delete('quiz_scale_properties')->condition('qid', $qid)->condition('vid', $vid)->execute();
    }
    else {
      db_delete('quiz_scale_user_answers')->condition('question_qid', $qid)->execute();
      db_delete('quiz_scale_properties')->condition('qid', $qid)->execute();
    }

    quizz_scale_collection_controller()->deleteCollectionIfNotUsed($cid, 0);
  }

  /**
   * Implementation of load
   *
   * @see QuizQuestion#load()
   */
  public function load() {
    if (empty($this->properties)) {
      $this->properties = parent::load();

      $select = db_select('quiz_scale_properties', 'p');
      $select->join('quiz_scale_answer', 'answer', 'p.answer_collection_id = answer.answer_collection_id');
      $properties = $select
          ->fields('answer', array('id', 'answer', 'answer_collection_id'))
          ->condition('p.vid', $this->question->vid)
          ->orderBy('answer.id')->execute()->fetchAll();
      foreach ($properties as $property) {
        $this->properties[] = $property;
      }
    }
    return $this->properties;
  }

  /**
   * Implementation of getEntityView
   *
   * @see QuizQuestion#view()
   */
  public function getEntityView() {
    $content = parent::getEntityView();
    $alternatives = array();
    for ($i = 0; $i < $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->question->{$i}->answer) && drupal_strlen($this->question->{$i}->answer) > 0) {
        $alternatives[] = check_plain($this->question->{$i}->answer);
      }
    }
    $content['answer'] = array(
        '#markup' => theme('scale_answer_node_view', array('alternatives' => $alternatives)),
        '#weight' => 2,
    );
    return $content;
  }

  /**
   * Implementation of getAnsweringForm
   *
   * @see getAnsweringForm($form_state, $result_id)
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $options = array();
    for ($i = 0; $i < $this->question->getQuestionType()->getConfig('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->question->{$i}) && drupal_strlen($this->question->{$i}->answer) > 0) {
        $options[$this->question->{$i}->id] = check_plain($this->question->{$i}->answer);
      }
    }

    $form = array('#type' => 'radios', '#title' => t('Choose one'), '#options' => $options);
    if (isset($result_id)) {
      $response = new ScaleResponse($result_id, $this->question);
      $form['#default_value'] = $response->getResponse();
    }

    return $form;
  }

  /**
   * Question response validator.
   */
  public function getAnsweringFormValidate(array &$form, array &$form_state = NULL) {
    if ($form_state['values']['question'][$this->question->qid] == '') {
      form_set_error('', t('You must provide an answer.'));
    }
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    $obj = new ScaleQuestionForm($this->question, $this->getCollectionIO());
    return $obj->get($form_state);
  }

  /**
   * Implementation of getMaximumScore.
   *
   * @see QuizQuestion#getMaximumScore()
   *
   * In some use-cases we want to reward users for answering a survey question.
   * This is why 1 is returned and not zero.
   */
  public function getMaximumScore() {
    return 1;
  }

}
