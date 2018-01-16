<?php

namespace Drupal\pattern_library\Plugin\Layout;

use Drupal\Core\Layout\LayoutDefinition;
use Drupal\pattern_library\Plugin\PatternInterface;

/**
 * Pattern layout definition.
 */
class PatternLayoutDefinition extends LayoutDefinition {

  /**
   * @var PatternInterface
   */
  protected $pattern_definition;

  /**
   * Get patter definition.
   *
   * @return PatternInterface
   *   The pattern definition.
   */
  public function getPatternDefinition() {
    return $this->pattern_definition;
  }
}
