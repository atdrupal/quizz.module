<?php

namespace Drupal\quizz\Entity;

use EntityDefaultMetadataController;

class RelationshipMetadataController extends EntityDefaultMetadataController {

  public function entityPropertyInfo() {
    $info = parent::entityPropertyInfo();
    $properties = &$info[$this->type]['properties'];

    $properties['question_qid']['type'] = 'node';
    $properties['question_vid']['type'] = 'integer';
    $properties['quiz_qid']['type'] = 'node';
    $properties['quiz_vid']['type'] = 'integer';

    return $info;
  }

}
