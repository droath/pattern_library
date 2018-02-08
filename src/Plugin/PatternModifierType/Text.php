<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

use Drupal\pattern_library\Annotation\PatternModifierType;
use Drupal\pattern_library\Plugin\PatternModifierTypeBase;
use Drupal\pattern_library\Plugin\PatternModifierTypeInterface;

/**
 * Define the pattern text modifier type.
 *
 * @PatternModifierType(
 *   id = "text"
 * )
 */
class Text extends PatternModifierTypeBase implements PatternModifierTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#type' => 'textfield',
    ] + parent::render();
  }
}
