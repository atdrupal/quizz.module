<?php

namespace Drupal\quiz\Helper;

use Drupal\quiz\Helper\Node\NodeInsertHelper;
use Drupal\quiz\Helper\Node\NodeUpdateHelper;
use Drupal\quiz\Helper\Node\NodeValidateHelper;

class NodeHelper {

  private $nodeValidateHelper;
  private $nodeInsertHelper;
  private $nodeUpdateHelper;

  /**
   * @return NodeValidateHelper
   */
  public function getNodeValidateHelper() {
    if (null === $this->nodeValidateHelper) {
      $this->nodeValidateHelper = new NodeValidateHelper();
    }
    return $this->nodeValidateHelper;
  }

  /**
   * @return NodeInsertHelper
   */
  public function getNodeInsertHelper() {
    if (null === $this->nodeInsertHelper) {
      $this->nodeInsertHelper = new NodeInsertHelper();
    }
    return $this->nodeInsertHelper;
  }

  /**
   * @return NodeUpdateHelper
   */
  public function getNodeUpdateHelper() {
    if (null === $this->nodeUpdateHelper) {
      $this->nodeUpdateHelper = new NodeUpdateHelper();
    }
    return $this->nodeUpdateHelper;
  }

}
