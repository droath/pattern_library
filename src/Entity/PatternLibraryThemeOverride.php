<?php

namespace Drupal\pattern_library\Entity;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\pattern_library\PatternLibraryManagerInterface;

/**
 * Define pattern library theme override config.
 *
 * @ConfigEntityType(
 *   id = "pattern_library_theme_override",
 *   label = @Translation("Theme Override"),
 *   config_prefix = "theme_override",
 *   admin_permission = "administer pattern library",
 *   handlers = {
 *     "form" = {
 *       "add" = "\Drupal\pattern_library\Form\ThemeOverrideForm",
 *       "edit" = "\Drupal\pattern_library\Form\ThemeOverrideForm",
 *       "delete" = "\Drupal\pattern_library\Form\ThemeOverrideDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "\Drupal\pattern_library\Entity\Routing\PatternLibraryRouteProvider"
 *     },
 *     "list_builder" = "\Drupal\pattern_library\Controller\ThemeOverrideListBuilder",
 *   },
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   links = {
 *     "collection" = "/admin/config/user-interface/pattern-library/theme-override",
 *     "add-form" = "/admin/config/user-interface/pattern-library/theme-override/add",
 *     "edit-form" = "/admin/config/user-interface/pattern-library/theme-override/{pattern_library_theme_override}",
 *     "delete-form" = "/admin/config/user-interface/pattern-library/theme-override/{pattern_library_theme_override}/delete"
 *   }
 * )
 */
class PatternLibraryThemeOverride extends ConfigEntityBase {

  /**
   * Theme override identifier.
   *
   * @var string
   */
  public $id;

  /**
   * Theme hook name.
   *
   * @var string
   */
  public $theme_hook;

  /**
   * Pattern library plugin id.
   *
   * @var string
   */
  public $pattern_library;

  /**
   * Pattern library variables mapping.
   *
   * @var array
   */
  public $pattern_mapping = [];

  /**
   * Get theme hook.
   *
   * @return string
   */
  public function themeHook() {
    return $this->theme_hook;
  }

  /**
   * Get pattern library.
   *
   * @return string
   */
  public function patternLibrary() {
    return $this->pattern_library;
  }

  /**
   * Get pattern mapping.
   *
   * @return array
   */
  public function patternMapping() {
    return $this->pattern_mapping;
  }

  /**
   * Determine if entity exits.
   *
   * @param null $id
   *   The unique entity identifier.
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function entityExists($id = NULL) {
    $id = isset($id) ? $id : $this->id();
    return (bool) $this->getEntityStorage()
      ->getQuery()
      ->condition('id', $id)
      ->execute();
  }

  /**
   * Pattern library definition.
   *
   * @return PatternInterface
   */
  public function patternLibraryDefinition() {
    $pattern_manager = $this->getPatternLibraryManager();
    return $pattern_manager->getDefinition($this->pattern_library);
  }

  /**
   * Get entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntityStorage() {
    /** @var EntityStorageInterface $storage */
    return $this->entityTypeManager()
      ->getStorage($this->getEntityTypeId());
  }

  /**
   * Get pattern library manager.
   *
   * @return PatternLibraryManagerInterface
   */
  protected function getPatternLibraryManager() {
    return \Drupal::service('plugin.manager.pattern_library');
  }

}
