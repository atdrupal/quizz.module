<?php

namespace Drupal\quizz\Helper\HookImplementation;

class HookUserCancel {

  private $account;
  private $method;

  public function __construct($account, $method) {
    $this->account = $account;
    $this->method = $method;
  }

  /**
   * Deletes all results associated with a given user.
   */
  public function execute() {
    if (variable_get('quiz_durod', 0)) {
      $res = db_query("SELECT result_id FROM {quiz_results} WHERE uid = :uid", array(':uid' => $this->account->uid));
      $result_ids = array();
      while ($result_id = $res->fetchField()) {
        $result_ids[] = $result_id;
      }
      entity_delete_multiple('quiz_result', $result_ids);
    }
  }

}
