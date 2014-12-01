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

}
