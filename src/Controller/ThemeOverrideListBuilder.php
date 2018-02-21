<?php

namespace Drupal\pattern_library\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Define theme override list builder.
 */
class ThemeOverrideListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['theme_hook'] = $this->t('Theme Hook');
    $header['pattern_library'] = $this->t('Pattern Library');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['theme_hook'] = $entity->themeHook();
    $row['pattern_library'] = $entity->patternLibrary();
    return $row + parent::buildRow($entity);
  }

}
