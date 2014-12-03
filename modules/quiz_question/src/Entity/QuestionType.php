<?php

namespace Drupal\quiz_question\Entity;

use Entity;

class QuestionType extends Entity {

  /** @var string */
  public $type;

  /** @var string */
  public $label;

  /** @var string */
  public $plugin;

  /** @var string */
  public $description;

  /** @var string */
  public $help;

  /** @var int */
  public $weight = 0;

  /** @var bool The exportable status of question type. */
  public $status = 1;

  /** @var bool Set to 0 if admin would like disable dis question type. */
  public $disabled = 0;

  /** @var mixed[] Extra info for question type. */
  public $data;

  public function __construct(array $values = array()) {
    parent::__construct($values, 'quiz_question_type');
  }

  /**
   * Returns whether the question type is locked, thus may not be deleted or renamed.
   *
   * Quiz types provided in code are automatically treated as locked, as well
   * as any fixed question type.
   */
  public function isLocked() {
    return isset($this->status) && empty($this->is_new) && (($this->status & ENTITY_IN_CODE) || ($this->status & ENTITY_FIXED));
  }

  public function getConfig($name, $default = NULL) {
    if (isset($this->data['configuration'][$name])) {
      return $this->data['configuration'][$name];
    }
    return $default;
  }

  /**
   * Get module for a question type.
   * @return string
   */
  public function getPluginModule() {
    if ($plugin_info = quiz_question_get_plugin_info($this->plugin)) {
      return $plugin_info['module'];
    }
  }

  public function setConfig($name, $value) {
    $this->data['configuration'][$name] = $value;
    return $this;
  }

}
