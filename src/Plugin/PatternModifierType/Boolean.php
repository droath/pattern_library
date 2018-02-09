<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

use Drupal\pattern_library\Annotation\PatternModifierType;
use Drupal\pattern_library\Plugin\PatternModifierTypeBase;
use Drupal\pattern_library\Plugin\PatternModifierTypeInterface;

/**
 * Define boolean pattern modifier type.
 *
 * @PatternModifierType(
 *   id = "boolean"
 * )
 */
class Boolean extends PatternModifierTypeBase implements PatternModifierTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#type' => 'checkbox',
    ] + parent::render();
  }

  /**
   * {@inheritdoc}
   */
  public static function castValue($value) {
    return (bool) $value;
  }
}
