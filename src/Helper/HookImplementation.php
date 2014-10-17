<?php

namespace Drupal\quiz\Helper;

use Drupal\quiz\Helper\HookImplementation\HookEntityInfo;
use Drupal\quiz\Helper\HookImplementation\HookFieldExtraFields;
use Drupal\quiz\Helper\HookImplementation\HookMenu;

class HookImplementation {

  private $hookMenu;
  private $hookEntityInfo;
  private $hookFieldExtraFields;

  /**
   * @return HookMenu
   */
  public function getHookMenu() {
    if (null === $this->hookMenu) {
      $this->hookMenu = new HookMenu();
    }
    return $this->hookMenu;
  }

  public function setHookMenu($hookMenu) {
    $this->hookMenu = $hookMenu;
    return $this;
  }

  /**
   * @return HookEntityInfo
   */
  public function getHookEntityInfo() {
    if (null === $this->hookEntityInfo) {
      $this->hookEntityInfo = new HookEntityInfo();
    }
    return $this->hookEntityInfo;
  }

  public function setHookEntityInfo($hookEntityInfo) {
    $this->hookEntityInfo = $hookEntityInfo;
    return $this;
  }

  /**
   * @return HookFieldExtraFields
   */
  public function getHookFieldExtraFields() {
    if (null === $this->hookFieldExtraFields) {
      $this->hookFieldExtraFields = new HookFieldExtraFields();
    }
    return $this->hookFieldExtraFields;
  }

  public function setHookFieldExtraFields($hookFieldExtraFields) {
    $this->hookFieldExtraFields = $hookFieldExtraFields;
    return $this;
  }

}
