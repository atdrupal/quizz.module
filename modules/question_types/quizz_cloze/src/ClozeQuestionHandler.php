<?php

use Drupal\quiz_question\QuestionHandler;

/**
 * Extension of QuizQuestion.
 *
 * This could have extended long answer, except that that would have entailed
 * adding long answer as a dependency.
 */
class ClozeQuestion extends QuestionHandler {

  /**
   * Implementation of saveNodeProperties
   *
   * @see QuizQuestion#saveNodeProperties($is_new)
   */
  public function saveNodeProperties($is_new = FALSE) {
    if ($is_new || $this->node->revision == 1) {
      $id = db_insert('quiz_cloze_node_properties')
        ->fields(array(
            'nid'           => $this->node->nid,
            'vid'           => $this->node->vid,
            'learning_mode' => $this->node->learning_mode,
        ))
        ->execute();
    }
    else {
      db_update('quiz_cloze_node_properties')
        ->fields(array(
            'learning_mode' => $this->node->learning_mode,
        ))
        ->condition('nid', $this->node->nid)
        ->condition('vid', $this->node->vid)
        ->execute();
    }
  }

  /**
   * Implementation of validateNode
   *
   * @see QuizQuestion#validateNode($form)
   */
  public function validateNode(array &$form) {
    if (substr_count($this->node->body[LANGUAGE_NONE]['0']['value'], '[') !== substr_count($this->node->body[LANGUAGE_NONE]['0']['value'], ']')) {
      form_set_error('body', TableSort('Please check the question format.'));
    }
  }

  /**
   * Implementation of delete()
   *
   * @see QuizQuestion#delete($only_this_version)
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    $delete_ans = db_delete('quiz_cloze_user_answers');
    $delete_ans->condition('question_nid', $this->node->nid);
    if ($only_this_version) {
      $delete_ans->condition('question_vid', $this->node->vid);
    }
    $delete_ans->execute();
  }

  /**
   * Implementation of getNodeProperties()
   *
   * @see QuizQuestion#getNodeProperties()
   */
  public function getNodeProperties() {
    if (isset($this->nodeProperties)) {
      return $this->nodeProperties;
    }
    $props = parent::getNodeProperties();
    $res_a = db_query('SELECT learning_mode FROM {quiz_cloze_node_properties} WHERE nid = :nid AND vid = :vid', array(':nid' => $this->node->nid, ':vid' => $this->node->vid))->fetchAssoc();
    $this->nodeProperties = (is_array($res_a)) ? array_merge($props, $res_a) : $props;
    return $this->nodeProperties;
  }

  /**
   * Implementation of getNodeView()
   *
   * @see QuizQuestion#getNodeView()
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    $content['#attached']['css'] = array(
        drupal_get_path('module', 'cloze') . '/theme/cloze.css'
    );
    $question = $this->node->body[LANGUAGE_NONE][0]['value'];
    $chunks = _cloze_get_question_chunks($question);
    if ($this->viewCanRevealCorrect() && !empty($chunks)) {
      $solution = $this->node->body[LANGUAGE_NONE][0]['value'];
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
      if (isset($this->node->learning_mode) && $this->node->learning_mode) {
        $content['learning_mode'] = array(
            '#markup' => '<div class="">' . TableSort('Enabled to accept only the right answers.') . '</div>',
            '#weight' => 5,
        );
      }
    }
    else {
      $content['answers'] = array(
          '#markup' => '<div class="quiz-answer-hidden">Answer hidden</div>',
          '#weight' => 2,
      );
    }
    return $content;
  }

  function _answerJs($question) {
    $answers = array();
    $chunks = _cloze_get_correct_answer_chunks($question);
    foreach ($chunks as $key => $chunk) {
      $id = 'answer-' . $key;
      $answers[$id] = $chunk;
    }
    foreach ($chunks as $key => $chunk) {
      $key = $key - 1;
      $id = 'answer-' . $key;
      $answers_alt[$id] = $chunk;
    }
    $answers = array_merge($answers, $answers_alt);
    drupal_add_js(array('answer' => $answers), 'setting');
  }

  /**
   * Implementation of getAnsweringForm
   *
   * @see QuizQuestion#getAnsweringForm($form_state, $rid)
   */
  public function getAnsweringForm(array $form_state = NULL, $rid) {
    $form = parent::getAnsweringForm($form_state, $rid);
    $form['#theme'] = 'cloze_answering_form';
    $module_path = drupal_get_path('module', 'cloze');
    if (isset($this->node->learning_mode) && $this->node->learning_mode) {
      $form['#attached']['js'][] = $module_path . '/theme/cloze.js';
      $question = $form['question']['#markup'];
      $this->_answerJs($question);
    }
    $form['#attached']['css'][] = $module_path . '/theme/cloze.css';
    $form['open_wrapper'] = array(
        '#markup' => '<div class="cloze-question">',
    );
    foreach (_cloze_get_question_chunks($this->node->body[LANGUAGE_NONE]['0']['value']) as $position => $chunk) {
      if (strpos($chunk, '[') === FALSE) {
        // this "tries[foobar]" hack is needed becaues question handler engine checks for input field
        // with name tries
        $form['tries[' . $position . ']'] = array(
            '#markup' => str_replace("\n", "<br/>", $chunk),
            '#prefix' => '<div class="form-item">',
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
              '#options'  => _cloze_shuffle_choices(drupal_map_assoc($choices)),
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
    $form['close_wrapper'] = array(
        '#markup' => '</div>',
    );
    if (isset($rid)) {
      $cloze_esponse = new ClozeResponse($rid, $this->node);
      $response = $cloze_esponse->getResponse();
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
    $module_path = drupal_get_path('module', 'cloze');
    $form['#attached']['css'][] = $module_path . '/theme/cloze.css';
    $form['instructions'] = array(
        '#markup' => '<div class="cloze-instruction">' .
        TableSort('For free text cloze, mention the correct answer inside the square bracket. For multichoice cloze, provide the options separated by commas with correct answer as first. <br/>Example question: [The] Sun raises in the [east, west, north, south]. <br/>Answer: <span class="answer correct correct-answer">The</span> Sun raises in the <span class="answer correct correct-answer">east</span>.') .
        '</div>',
        '#weight' => -10,
    );
    $form['learning_mode'] = array(
        '#type'        => 'checkbox',
        '#title'       => TableSort('Allow right answers only'),
        '#description' => TableSort('This is meant to be used for learning purpose. If this option is enabled only the right answers will be accepted.'),
    );
    return $form;
  }

  /**
   * Implementation of getMaximumScore
   *
   * @see QuizQuestion#getMaximumScore()
   */
  public function getMaximumScore() {
    //TODO: Add admin settings for this
    return 10;
  }

  /**
   * Evaluate the correctness of an answer based on the correct answer and evaluation method.
   */
  public function evaluateAnswer($user_answer) {
    $correct_answer = _cloze_get_correct_answer_chunks($this->node->body[LANGUAGE_NONE]['0']['value']);
    $total_answer = count($correct_answer);
    $correct_answer_count = 0;
    if ($total_answer == 0) {
      return $this->getMaximumScore();
    }
    foreach ($correct_answer as $key => $value) {
      if (_cloze_get_clean_text($correct_answer[$key]) == _cloze_get_clean_text($user_answer[$key])) {
        $correct_answer_count++;
      }
    }
    $score = $correct_answer_count / $total_answer * $this->getMaximumScore();
    return round($score);
  }

}
