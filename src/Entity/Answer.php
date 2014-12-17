<?php

namespace Drupal\quizz\Entity;

use Entity;

class Answer extends Entity {

  public $result_answer_id;
  public $type;
  public $result_id;
  public $question_qid;
  public $question_vid;
  public $tid;
  public $is_correct;
  public $is_skipped;
  public $points_awarded;
  public $answer_timestamp;
  public $number;
  public $is_doubtful;

  public function bundle() {
    if (NULL == $this->type) {
      $sql = 'SELECT type FROM {quiz_question} WHERE vid = :vid';
      $this->type = db_query($sql, array(':vid' => $this->question_vid))->fetchColumn();
    }
    return parent::bundle();
  }

}
