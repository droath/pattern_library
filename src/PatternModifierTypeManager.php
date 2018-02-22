<?php

namespace Drupal\pattern_library;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\pattern_library\Annotation\PatternModifierType;
use Drupal\pattern_library\Plugin\PatternModifierTypeInterface;

/**
 * Pattern library modifier manager.
 */
class PatternModifierTypeManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/PatternModifierType',
      $namespaces,
      $module_handler,
      PatternModifierTypeInterface::class,
      PatternModifierType::class
    );

    $this->alterInfo('pattern_library_modifier');
    $this->setCacheBackend($cache_backend, 'pattern_library_modifier_type');
  }

  /**
   * Determine if the modifier type exists.
   *
   * @param $plugin_id
   *   The modifier type plugin id.
   *
   * @return bool
   */
  public function exists($plugin_id) {
    $definitions = $this->getDefinitions();
    return isset($definitions[$plugin_id]);
  }

  /**
   * Get pattern modifier classname.
   *
   * @param $plugin_id
   *   The modifier type plugin id.
   *
   * @return string|null
   */
  public function getClassname($plugin_id) {
    $definition = $this->getDefinition($plugin_id);

    if (!isset($definition['class'])
      || !class_exists($definition['class'])) {
      return null;
    }

    return $definition['class'];
  }

  /**
   * Process pattern library modifier value.
   *
   * @param $modifier_type
   *   The modifier type plugin id.
   * @param $value
   *   Teh modifier value that should be casted.
   *
   * @return mixed
   */
  public function processModifierValue($modifier_type, $value) {
    $classname = $this->getClassname($modifier_type);

    if (!isset($classname)) {
      return $value;
    }
    $value = $classname::castValue($value);

    return $classname::transformValue($value);
  }

}
