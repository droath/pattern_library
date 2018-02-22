<?php

namespace Drupal\pattern_library\Plugin\PatternModifierType;

/**
 * Define the pattern file_path modifier type.
 *
 * @PatternModifierType(
 *   id = "file_path"
 * )
 */
class FilePath extends Text {

  /**
   * {@inheritdoc}
   */
  public static function transformValue($value) {
    $path = parent::transformValue($value);
    $file_system = \Drupal::service('file_system');

    if (FALSE === $file_system->uriScheme($path)) {
      return $path;
    }

    return file_create_url($value);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'description' => 'Input a URI or path to an existing file.'
    ];
  }

}
