<?php

namespace Drupal\pattern_library\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\pattern_library\Plugin\Layout\PatternLayoutDefinition;
use Drupal\pattern_library\Plugin\Layout\PatternLibraryLayout;
use Drupal\pattern_library\Plugin\PatternInterface;

/**
 * Creates a layout based on pattern definitions.
 */
class PatternLibraryLayoutDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern **/
    foreach ($this->getPatternLibraryManager()->getDefinitionInstances() as $plugin_id => $pattern) {
      if (!$pattern->validate()) {
        continue;
      }
      $regions = $this->getRegionsFromVariables($pattern);

      $this->derivatives[$plugin_id] = new PatternLayoutDefinition([
        'id' => $plugin_id,
        'path' => 'templates',
        'class' => PatternLibraryLayout::class,
        'label' => $pattern->getLabel(),
        'regions' => $regions,
        'category' => 'Pattern Library',
        'template' => 'pattern-library-layout',
        'provider' => 'pattern_library',
        'pattern_definition' => $pattern
      ]);
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

  /**
   * Get pattern library manager.
   *
   * @return PluginManagerInterface
   *   The pattern library manager.
   */
  protected function getPatternLibraryManager() {
    return \Drupal::service('plugin.manager.pattern_library');
  }
}
