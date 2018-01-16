<?php

namespace Drupal\pattern_library;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\pattern_library\Plugin\Pattern;

/**
 * Class pattern library manager
 *
 * @package Drupal\pattern_library
 */
class PatternLibraryManager extends DefaultPluginManager implements PluginManagerInterface {

  /**
   * Theme handler.
   *
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var array
   */
  protected $defaults = [
    'class' => Pattern::class
  ];

  /**
   * Pattern library manager constructor.
   *
   * @param ModuleHandlerInterface $module_handler
   * @param ThemeHandlerInterface $theme_handler
   * @param CacheBackendInterface $cache_backend
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ThemeHandlerInterface $theme_handler,
    CacheBackendInterface $cache_backend
  ) {
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->alterInfo('pattern_library_info');
    $this->pluginInterface = 'Drupal\pattern_library\Plugin\PatternInterface';
    $this->setCacheBackend($cache_backend, 'pattern_library', ['pattern_library']);
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscovery() {
    if (!$this->discovery) {
      $directories = array_merge(
        $this->themeHandler->getThemeDirectories(),
        $this->moduleHandler->getModuleDirectories()
      );
      $discovery = new YamlDiscovery('pattern_library', $directories);
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($discovery);
    }

    return $this->discovery;
  }

  /**
   * Get definition instances.
   *
   * @return array
   */
  public function getDefinitionInstances() {
    $instances = [];

    foreach (array_keys($this->getDefinitions()) as $plugin_id) {
      $instances[$plugin_id] = $this->createInstance($plugin_id);
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    return parent::providerExists($provider) ||
      $this->themeHandler->themeExists($provider);
  }
}
