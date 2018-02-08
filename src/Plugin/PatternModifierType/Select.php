<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

use Drupal\pattern_library\Annotation\PatternModifierType;
use Drupal\pattern_library\Plugin\PatternModifierTypeBase;
use Drupal\pattern_library\Plugin\PatternModifierTypeInterface;

/**
 * Define the pattern select modifier type.
 *
 * @PatternModifierType(
 *   id = "select"
 * )
 */
class Select extends PatternModifierTypeBase implements PatternModifierTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      '#type' => 'select',
      '#options' => $this->options(),
      '#empty_option' => $this->t(' -Select- '),
    ] + parent::render();
  }

  /**
   * Pattern modifier options.
   *
   * @return array
   *   An array of options.
   */
  public function options() {
    $option = $this->hasConfiguration('options')
      ? $this->getConfiguration()['options']
      : [];

    if (!empty($option)) {
      $option = array_combine(
        array_map('strtolower', $option),
        array_map('ucwords', $option)
      );
    }

    return $option;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultConfiguration() {
    return [
      'description' => 'Select the value to use for the modifier.'
    ];
  }
}
