<?php

namespace Drupal\quizz\Entity;

use EntityDefaultViewsController;

class RelationshipViewsController extends EntityDefaultViewsController {

  public function views_data() {
    $data = parent::views_data();
    $data['quiz_relationship']['question_status']['field']['handler'] = 'Drupal\quizz\Views\Handler\Field\RelationshipQuestionStatus';
    return $data;
  }

}
