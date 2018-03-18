<?php

namespace Drupal\pattern_library\Plugin;

use Drupal\Core\Plugin\PluginBase;
use Drupal\pattern_library\Exception\InvalidPatternException;

/**
 * Pattern definition class.
 */
class Pattern extends PluginBase implements PatternInterface {

  const REQUIRED_PARAMETERS = [
    'label',
    'source',
    'variables',
  ];

  /**
   * Pattern label.
   */
  public function getLabel() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * Pattern icon.
   */
  public function getIcon() {
    $definition = $this->getPluginDefinition();
    return isset($definition['icon']) ? $definition['icon'] : NULL;
  }

  /**
   * Pattern source.
   */
  public function getSource() {
    $definition = $this->getPluginDefinition();
    return isset($definition['source']) ? $definition['source'] : NULL;
  }

  /**
   * Pattern variables.
   */
  public function getVariables() {
    $definition = $this->getPluginDefinition();
    return isset($definition['variables']) ? $definition['variables'] : [];
  }

  /**
   * Pattern modifiers.
   */
  public function getModifiers() {
    $definition = $this->getPluginDefinition();
    return isset($definition['modifiers']) ? $definition['modifiers'] : [];
  }

  /**
   * Pattern provider.
   */
  public function getProvider() {
    $definition = $this->getPluginDefinition();
    return isset($definition['provider']) ? $definition['provider'] : NULL;
  }

  /**
   * Pattern libraries.
   *
   * @return array
   */
  public function getLibraries() {
    $definition = $this->getPluginDefinition();
    return isset($definition['libraries']) ? $definition['libraries'] : [];
  }

  /**
   * Pattern library key.
   *
   * @return string
   */
  public function libraryKey() {
    return "{$this->getProvider()}/{$this->getPluginId()}";
  }

  /**
   * Has pattern libraries.
   *
   * @return boolean
   */
  public function hasLibraries() {
    return !empty($this->getLibraries());
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $definition = $this->getPluginDefinition();

    foreach (static::REQUIRED_PARAMETERS as $parameter) {
      if (!isset($definition[$parameter])
      || empty($definition[$parameter])) {
        throw new InvalidPatternException(
          $this->t('Missing required @param parameter.', [
            '@param' => $parameter
          ])
        );
        return FALSE;
      }
    }

    return TRUE;
  }
}
