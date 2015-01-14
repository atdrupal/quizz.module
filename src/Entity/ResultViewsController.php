<?php

namespace Drupal\quizz\Entity;

use EntityDefaultViewsController;

class ResultViewsController extends EntityDefaultViewsController {

  public function views_data() {
    $data = parent::views_data();

    $data['quiz_results']['spent_time']['field'] = array(
        'title'   => t('Spent time'),
        'help'    => t('Time user spent on this attemp'),
        'handler' => 'Drupal\quizz\Views\Handler\Field\ResultSpentTime',
    );

    return $data;
  }

}
