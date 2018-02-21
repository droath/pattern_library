<?php

namespace Drupal\pattern_library;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Define pattern theme override manager.
 */
class PatternThemeOverrideManager {

  /**
   * Entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface  $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load pattern theme override.
   *
   * @param $id
   *   The override plugin id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function load($id) {
    return $this->getEntityStorage()->load($id);
  }

  /**
   * Load all pattern theme overrides.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function loadAll() {
    return $this->getEntityStorage()->loadMultiple();
  }

  /**
   * Get theme overrides theme hooks.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function getThemeHooks() {
    return array_keys($this->loadAll());
  }

  /**
   * Get entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntityStorage() {
    return $this->entityTypeManager
      ->getStorage('pattern_library_theme_override');
  }

}
