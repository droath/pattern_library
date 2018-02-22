<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

/**
 * Define the pattern image_path modifier type.
 *
 * @PatternModifierType(
 *   id = "image_path"
 * )
 */
class ImagePath extends FilePath {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'description' => 'Input a URI or path to an existing image.'
    ];
  }

}
