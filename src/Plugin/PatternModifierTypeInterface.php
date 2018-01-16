<?php

namespace Drupal\pattern_library\Plugin;

/**
 * Pattern modifier type interface.
 */
interface PatternModifierTypeInterface {

  /**
   * Pattern modifier render output.
   *
   * @return array
   *   A render array.
   */
  public function render();
}
