<?php

namespace Drupal\quizz\Entity;

class QuizExtraFieldsController extends \EntityDefaultExtraFieldsController {

  public function fieldExtraFields() {
    $extra = array();

    // User comes from old version, there's no quiz type yet
    if (!db_table_exists('quiz_entity') || !db_table_exists('quiz_question_type')) {
      return $extra;
    }

    if ($types = quiz_get_types()) {
      foreach (array_keys($types) as $name) {
        $extra['quiz_entity'][$name] = array(
            'display' => $this->getQuizDisplayFields(),
            'form'    => $this->getQuizFormExtraFields(),
        );

        $extra['quiz_result'][$name] = array(
            'display' => array(
                'score'         => array(
                    'label'       => t('Score'),
                    'description' => '',
                    'weight'      => -10,
                ),
                'feedback'      => array(
                    'label'  => t('Feedback'),
                    'weight' => -5,
                ),
                'feedback_form' => array(
                    'label'       => t('Feedback form'),
                    'description' => '',
                    'weight'      => 0,
                ),
            ),
        );
      }
    }

    return $extra;
  }

  private function getQuizDisplayFields() {
    return array(
        'take'  => array(
            'label'       => t('Take @quiz button', array('@quiz' => QUIZ_NAME)),
            'description' => t('The take button.'),
            'weight'      => 10,
        ),
        'stats' => array(
            'label'       => t('@quiz summary', array('@quiz' => QUIZ_NAME)),
            'description' => t('@quiz summary', array('@quiz' => QUIZ_NAME)),
            'weight'      => 9,
        ),
    );
  }

  private function getQuizFormExtraFields() {
    $elements = array(
        'title'     => array(
            'label'  => t('Title'),
            'weight' => 0,
        ),
        'quiz_help' => array(
            'label'  => t('Explanation or submission guidelines'),
            'weight' => -25,
        ),
        'vtabs'     => array(
            'label'  => t('Quiz options'),
            'weight' => 50,
        ),
    );

    if (module_exists('locale')) {
      $elements['language'] = array(
          'label'       => t('Language'),
          'description' => t('Language selector'),
          'weight'      => -20,
      );
    }

    return $elements;
  }

}
