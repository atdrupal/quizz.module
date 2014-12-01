<?php

namespace Drupal\quizz_scale\Entity;

use Entity;

class Collection extends Entity {

  /** @var int */
  public $id;

  /** @var string */
  public $name;

  /** @var string */
  public $label;

  /** @var bool */
  public $for_all;

  /** @var int */
  public $uid;

  /**
   * ID -> Label
   *
   * @var array
   */
  public $alternatives = array();

  public function insertAlternatives($answers) {
    // db_lock_table('quiz_scale_answer');
    foreach ($answers as $answer) {
      $this->insertAlternative($answer);
    }
    // db_unlock_tables();
  }

  /**
   * Insert new answer.
   * @param string $answer
   */
  public function insertAlternative($answer) {
    if (!$this->id) {
      $this->save();
    }

    return db_insert('quiz_scale_answer')
        ->fields(array(
            'answer_collection_id' => $this->id,
            'answer'               => $answer
        ))
        ->execute()
    ;
  }

}
