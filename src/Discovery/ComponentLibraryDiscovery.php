<?php

namespace Drupal\pattern_library\Discovery;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Discovery\DiscoveryTrait;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * Component library discovery.
 */
class ComponentLibraryDiscovery implements DiscoveryInterface {
  use DiscoveryTrait;

  /**
   * @var \Drupal\Component\FileCache\FileCacheInterface
   */
  protected $fileCache;

  /**
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var string
   */
  protected $themeName;

  /**
   * Component library based discovery.
   *
   * @param ThemeHandlerInterface $theme_handler
   */
  public function __construct(ThemeHandlerInterface $theme_handler) {
    $this->themeHandler = $theme_handler;
    $this->themeName = $this->themeHandler->getDefault();
    $this->fileCache = FileCacheFactory::get('component_library_discovery:' . $this->themeName);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = [];

    foreach ($this->findAll() as $id => $definition) {
      list($provider, $plugin_id) = explode(':', $id);
      $definitions[$plugin_id] = [
        'id' => $plugin_id,
        'provider' => $provider,
      ] + $definition;
    }

    return $definitions;
  }

  /**
   * Find all component libraries pattern definitions.
   *
   * @return array
   *   An array of all files.
   */
  protected function findAll() {
    $all = [];
    $theme = $this->themeHandler->getTheme($this->themeName);

    if (!isset($theme->info['component-libraries'])) {
      return [];
    }
    $theme_path = $theme->getPath();
    $components = $theme->info['component-libraries'];

    foreach ($components as $group_name => $group_info) {
      if (!isset($group_info['paths']) || empty($group_info['paths'])) {
        continue;
      }
      foreach ($group_info['paths'] as $group_path) {
        $directory = "{$theme_path}/{$group_path}";
        if (!file_exists($directory)) {
          continue;
        }
        $file_paths = glob("{$directory}/*.pattern_library.yml");
        if ($file_paths === FALSE || empty($file_paths)) {
          continue;
        }
        $file = new \SplFileInfo(current($file_paths));
        $filename = $file->getFilename();
        $filepath = $file->getPathname();
        $plugin_id = substr($filename, 0, stripos($filename, '.'));

        if ($cache = $this->fileCache->get($filepath)) {
          $all["{$this->themeName}:{$plugin_id}"] = $cache;
        }
        else {
          $definition = $this->decode($file);
          $definition['group'] = $group_name;

          // Attach source file to the definition if the file exists.
          if (file_exists("{$directory}/{$plugin_id}.twig")) {
            $definition['source_file'] =  "{$group_path}/{$plugin_id}.twig";
          }
          $this->fileCache->set($filepath, $definition);
          $all["{$this->themeName}:{$plugin_id}"] = $definition;
        }
      }
    }

    return $all;
  }

  /**
   * Decode the YAML file content.
   *
   * @param \SplFileInfo $file
   *
   * @return array
   */
  protected function decode(\SplFileInfo $file) {
    return Yaml::decode(file_get_contents($file)) ?: [];
  }
}
