<?php

namespace Drupal\pattern_library\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

abstract class PatternModifierTypeBase extends PluginBase {

  use StringTranslationTrait;

  /**
   * Define the cast value.
   *
   * @param $value
   *   The raw value input.
   *
   * @return mixed
   */
  public static function castValue($value) {
    return $value;
  }

  /**
   * Define the transform value.
   *
   * @param $value
   *   The casted value.
   *
   * @return mixed
   */
  public static function transformValue($value) {
    return $value;
  }

  /**
   * Pattern modifier title.
   *
   * @return string
   */
  protected function title() {
    return $this->hasConfiguration('title')
      ? new TranslatableMarkup($this->getConfiguration()['title'])
      : NULL;
  }

  /**
   * Pattern modifier description.
   *
   * @return string
   */
  protected function description() {
    return $this->hasConfiguration('description')
      ? new TranslatableMarkup($this->getConfiguration()['description'])
      : NULL;
  }

  /**
   * Pattern modifier default value.
   *
   * @return string
   */
  protected function defaultValue() {
    return $this->hasConfiguration('default_value')
      ? $this->getConfiguration()['default_value']
      : NULL;
  }

  /**
   * Default configuration.
   *
   * @return array
   */
  protected function defaultConfiguration() {
    return [];
  }

  /**
   * Has configuration option set.
   *
   * @param $param
   *   The configuration parameter name.
   *
   * @return bool
   */
  protected function hasConfiguration($param) {
    $configuration = $this->getConfiguration();
    return isset($configuration[$param]);
  }

  /**
   * Get configuration.
   *
   * @return array
   */
  protected function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * Pattern modifier base render array.
   *
   * @return array
   */
  protected function render() {
    return [
      '#title' => $this->title(),
      '#description' => $this->description(),
      '#default_value' => $this->defaultValue()
    ];
  }
}
