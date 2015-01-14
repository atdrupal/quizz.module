<?php

namespace Drupal\quizz\Entity;

use EntityDefaultViewsController;

class QuizViewsController extends EntityDefaultViewsController {

  public function views_data() {
    $data = parent::views_data();

    $data['quiz_entity']['view_link']['field'] = array(
        'title'   => t('Link'),
        'help'    => t('Provide a simple link to the !quiz.', array('!quiz' => QUIZZ_NAME)),
        'handler' => 'Drupal\quizz\Views\Handler\Field\QuizEntityLink',
    );

    $data['quiz_entity']['edit_link']['field'] = array(
        'title'   => t('Edit link'),
        'help'    => t('Provide a simple link to edit the !quiz.', array('!quiz' => QUIZZ_NAME)),
        'handler' => 'Drupal\quizz\Views\Handler\Field\QuizEntityEditLink',
    );

    $data['quiz_entity']['delete_link']['field'] = array(
        'title'   => t('Delete link'),
        'help'    => t('Provide a simple link to delete the !quiz.', array('!quiz' => QUIZZ_NAME)),
        'handler' => 'Drupal\quizz\Views\Handler\Field\QuizEntityDeleteLink',
    );

    $data['quiz_entity']['results_link']['field'] = array(
        'title'   => t('Result link'),
        'help'    => t('Provide a simple link to delete the !quiz.', array('!quiz' => QUIZZ_NAME)),
        'handler' => 'Drupal\quizz\Views\Handler\Field\QuizEntityResultsLink',
    );

    $data['quiz_entity']['take_link']['field'] = array(
        'title'   => t('Take link'),
        'help'    => t('Provide "Take" link for user to start taking the quiz'),
        'handler' => 'Drupal\quizz\Views\Handler\Field\QuizEntityTakeLink',
    );

    return $data;
  }

}
