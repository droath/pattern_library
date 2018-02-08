<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

use Drupal\Component\Utility\Unicode;
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
      '#empty_option' => $this->t(' -Default- '),
    ] + parent::render();
  }

  /**
   * Pattern modifier options.
   *
   * @return array
   *   An array of options.
   */
  public function options() {
    $options = $this->hasConfiguration('options')
      ? $this->getConfiguration()['options']
      : [];

    $formatted_options = [];
    foreach ($options as $key => $value) {
      if (is_numeric($key)) {
        $key = strtolower($value);
      }
      $formatted_options[$key] = Unicode::ucwords($value);
    }

    return $formatted_options;
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
