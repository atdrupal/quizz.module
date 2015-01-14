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

    $data['quiz_results']['result_state'] = array(
        'real field' => 'time_end',
        'field'      => array(
            'title'   => t('State'),
            'help'    => t('State of result'),
            'handler' => 'Drupal\quizz\Views\Handler\Field\ResultState',
        ),
        'filter'     => array(
            'title'   => t('State'),
            'help'    => t('State of result'),
            'handler' => 'Drupal\quizz\Views\Handler\Filter\ResultState', # views_handler_filter_date
        ),
    );
    return $data;
  }

}
