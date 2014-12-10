<?php

namespace Drupal\quizz_cloze;

use Drupal\quiz_question\Entity\Question;
use Drupal\quiz_question\QuestionHandler;

/**
 * Extension of QuizQuestion.
 *
 * This could have extended long answer, except that that would have entailed
 * adding long answer as a dependency.
 */
class ClozeQuestionHandler extends QuestionHandler {

  /** @var Helper */
  private $clozeHelper;

  public function __construct(Question $question) {
    parent::__construct($question);
    $this->clozeHelper = new Helper();
  }

  public function saveEntityProperties($is_new = FALSE) {
    db_merge('quiz_cloze_question_properties')
      ->key(array(
          'qid' => $this->question->qid,
          'vid' => $this->question->vid,
      ))
      ->fields(array(
          'learning_mode' => isset($this->question->learning_mode) ? $this->question->learning_mode : 0,
      ))
      ->execute()
    ;
  }

  public function validate(array &$form) {
    if (substr_count($this->question->quiz_question_body[LANGUAGE_NONE]['0']['value'], '[') !== substr_count($this->question->quiz_question_body[LANGUAGE_NONE]['0']['value'], ']')) {
      form_set_error('body', t('Please check the question format.'));
    }
  }

  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    $delete_ans = db_delete('quiz_cloze_user_answers');
    $delete_ans->condition('question_qid', $this->question->qid);
    if ($only_this_version) {
      $delete_ans->condition('question_vid', $this->question->vid);
    }
    $delete_ans->execute();
  }

  public function load() {
    if (isset($this->properties)) {
      return $this->properties;
    }
    $properties = parent::load();
    $res_a = db_query(
      'SELECT learning_mode FROM {quiz_cloze_question_properties} WHERE qid = :qid AND vid = :vid', array(
        ':qid' => $this->question->qid,
        ':vid' => $this->question->vid
      ))->fetchAssoc();
    $this->properties = (is_array($res_a)) ? array_merge($properties, $res_a) : $properties;
    return $this->properties;
  }

  public function view() {
    $content = parent::view();
    $content['#attached']['css'][] = drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.css';
    $chunks = $this->clozeHelper->getQuestionChunks($this->question->quiz_question_body[LANGUAGE_NONE][0]['value']);
    if ($this->viewCanRevealCorrect() && !empty($chunks)) {
      $solution = $this->question->quiz_question_body[LANGUAGE_NONE][0]['value'];
      foreach ($chunks as $position => $chunk) {
        if (strpos($chunk, '[') === FALSE) {
          continue;
        }
        $chunk = str_replace(array('[', ']'), '', $chunk);
        $choices = explode(',', $chunk);
        $replace = '<span class="correct answer user-answer">' . $choices[0] . '</span>';
        $solution = str_replace($chunks[$position], $replace, $solution);
      }
      $content['answers'] = array(
          '#markup' => '<div class="quiz-solution cloze-question">' . $solution . '</div>',
          '#weight' => 5,
      );
      if (isset($this->question->learning_mode) && $this->question->learning_mode) {
        $content['learning_mode'] = array(
            '#prefix' => '<div class="">',
            '#markup' => t('Enabled to accept only the right answers.'),
            '#suffix' => '</div>',
            '#weight' => 5,
        );
      }
    }
    else {
      $content['answers'] = array(
          '#prefix' => '<div class="quiz-answer-hidden">',
          '#markup' => t('Answer hidden'),
          '#suffix' => '</div>',
          '#weight' => 2,
      );
    }
    return $content;
  }

  /**
   * @param string $question
   */
  private function includeAnswerJs($question) {
    $answers = array();
    $chunks = $this->clozeHelper->getCorrectAnswerChunks($question);
    foreach ($chunks as $key => $chunk) {
      $answers['answer-' . $key] = $chunk;
    }
    foreach ($chunks as $key => $chunk) {
      $answers_alt['answer-' . ($key - 1)] = $chunk;
    }
    drupal_add_js(array('answer' => array_merge($answers, $answers_alt)), 'setting');
  }

  /**
   * Implementation of getAnsweringForm
   *
   * @see QuizQuestion#getAnsweringForm($form_state, $rid)
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $form = parent::getAnsweringForm($form_state, $result_id);
    $form['#theme'] = 'cloze_answering_form';
    if (!empty($this->question->learning_mode)) {
      $form['#attached']['js'][] = drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.js';
      $question = $this->question->quiz_question_body[LANGUAGE_NONE][0]['safe_value'];
      $this->includeAnswerJs($question);
    }
    $form['#attached']['css'][] = drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.css';
    $form['open_wrapper']['#markup'] = '<div class="cloze-question">';
    foreach ($this->clozeHelper->getQuestionChunks($this->question->quiz_question_body[LANGUAGE_NONE]['0']['value']) as $position => $chunk) {
      if (strpos($chunk, '[') === FALSE) {
        // this "tries[foobar]" hack is needed becaues question handler engine
        // checks for input field with name tries
        $form['tries[' . $position . ']'] = array(
            '#prefix' => '<div class="form-item">',
            '#markup' => str_replace("\n", "<br/>", $chunk),
            '#suffix' => '</div>',
        );
      }
      else {
        $chunk = str_replace(array('[', ']'), '', $chunk);
        $choices = explode(',', $chunk);
        if (count($choices) > 1) {
          $form['tries[' . $position . ']'] = array(
              '#type'     => 'select',
              '#title'    => '',
              '#options'  => $this->clozeHelper->shuffleChoices(drupal_map_assoc($choices)),
              '#required' => FALSE,
          );
        }
        else {
          $form['tries[' . $position . ']'] = array(
              '#type'       => 'textfield',
              '#title'      => '',
              '#size'       => 32,
              '#required'   => FALSE,
              '#attributes' => array(
                  'autocomplete' => 'off',
                  'class'        => array('answer-' . $position),
              ),
          );
        }
      }
    }

    $form['close_wrapper']['#markup'] = '</div>';

    if (isset($result_id)) {
      $handler = quiz_answer_controller()->getHandler($result_id, $this->question);
      $response = $handler->getResponse();
      if (is_array($response)) {
        foreach ($response as $key => $value) {
          $form["tries[$key]"]['#default_value'] = $value;
        }
      }
    }
    return $form;
  }

  /**
   * Implementation of getCreationForm
   *
   * @see QuizQuestion#getCreationForm($form_state)
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['#attached']['css'][] = drupal_get_path('module', 'quizz_cloze') . '/theme/cloze.css';
    $form['instructions'] = array(
        '#prefix' => '<div class="cloze-instruction">',
        '#markup' => t('For free text cloze, mention the correct answer inside the square bracket. For multichoice cloze, provide the options separated by commas with correct answer as first. <br/>Example question: [The] Sun raises in the [east, west, north, south]. <br/>Answer: <span class="answer correct correct-answer">The</span> Sun raises in the <span class="answer correct correct-answer">east</span>.'),
        '#suffix' => '</div>',
        '#weight' => -10,
    );
    $form['learning_mode'] = array(
        '#type'          => 'checkbox',
        '#title'         => t('Allow right answers only'),
        '#default_value' => isset($this->question->learning_mode) ? $this->question->learning_mode : 0,
        '#description'   => t('This is meant to be used for learning purpose. If this option is enabled only the right answers will be accepted.'),
    );
    return $form;
  }

  /**
   * Implementation of getMaximumScore
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    // @TODO: Add admin settings for this
    return 10;
  }

  /**
   * Evaluate the correctness of an answer based on the correct answer and evaluation method.
   */
  public function evaluateAnswer($user_answer) {
    $correct_answer = $this->clozeHelper->getCorrectAnswerChunks($this->question->quiz_question_body[LANGUAGE_NONE]['0']['value']);
    $total_answer = count($correct_answer);
    $correct_answer_count = 0;
    if ($total_answer == 0) {
      return $this->getMaximumScore();
    }
    foreach ($correct_answer as $key => $value) {
      if ($this->clozeHelper->getCleanText($correct_answer[$key]) == $this->clozeHelper->getCleanText($user_answer[$key])) {
        $correct_answer_count++;
      }
    }
    $score = $correct_answer_count / $total_answer * $this->getMaximumScore();
    return round($score);
  }

}
