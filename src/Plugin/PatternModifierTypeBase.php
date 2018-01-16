<?php

namespace Drupal\pattern_library\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;

abstract class PatternModifierTypeBase extends PluginBase {

  use StringTranslationTrait;

  /**
   * Pattern modifier title.
   *
   * @return string
   */
  protected function title() {
    return $this->hasConfiguration('title')
      ? $this->getConfiguration()['title']
      : NULL;
  }

  /**
   * Pattern modifier description.
   *
   * @return string
   */
  protected function description() {
    return $this->hasConfiguration('description')
      ? $this->getConfiguration()['description']
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
    return $this->configuration;
  }
}
