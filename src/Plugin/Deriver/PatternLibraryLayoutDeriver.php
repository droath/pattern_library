<?php

namespace Drupal\pattern_library\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\pattern_library\Plugin\Layout\PatternLayoutDefinition;
use Drupal\pattern_library\Plugin\Layout\PatternLibraryLayout;
use Drupal\pattern_library\Plugin\PatternInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a layout based on pattern definitions.
 */
class PatternLibraryLayoutDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * @var PluginManagerInterface
   */
  protected $patternLibraryManager;

  /**
   * Class constructor.
   *
   * @param $base_plugin_id
   * @param PluginManagerInterface $pattern_library_manager
   */
  public function __construct($base_plugin_id, PluginManagerInterface $pattern_library_manager) {
    $this->patternLibraryManager = $pattern_library_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('plugin.manager.pattern_library')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern **/
    foreach ($this->patternLibraryManager->getDefinitionInstances() as $plugin_id => $pattern) {
      if (!$pattern->validate()) {
        continue;
      }
      $definition = [
        'id' => $plugin_id,
        'path' => 'templates',
        'class' => PatternLibraryLayout::class,
        'label' => $pattern->getLabel(),
        'regions' => $this->getRegionsFromVariables($pattern),
        'category' => 'Pattern Library',
        'provider' => 'pattern_library',
        'pattern_definition' => $pattern
      ];

      if ($pattern->hasLibraries()) {
        $definition['library'] = "pattern_library/{$pattern->libraryKey()}";
      }

      $this->derivatives[$plugin_id] = new PatternLayoutDefinition($definition);
    }

    return $this->derivatives;
  }

  /**
   * Get regions form variables.
   *
   * @param PatternInterface $pattern
   *   The pattern definition.
   *
   * @return array
   *   An array of regions defined by pattern variables.
   */
  protected function getRegionsFromVariables(PatternInterface $pattern) {
    $regions = [];

    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern **/
    foreach ($pattern->getVariables() as $variable => $info) {
      $label = isset($info['label']) ? $info['label'] : ucwords($variable);
      $regions[$variable] = [
        'label' => $label,
      ];
    }

    return $regions;
  }

}
