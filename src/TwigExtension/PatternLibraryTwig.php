<?php

namespace Drupal\pattern_library\TwigExtension;
use Drupal\Core\Render\Element;

/**
 * Define pattern library twig extensions.
 */
class PatternLibraryTwig extends \Twig_Extension {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'pattern_library.twig_extension';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new \Twig_SimpleFilter('children', [$this, 'children'])
    ];
  }

  /**
   * Filter out only children elements.
   *
   * @param $element
   *
   * @return array
   */
  public static function children($elements) {
    $children = [];

    foreach (Element::children($elements) as $delta) {
      $children[] = $elements[$delta];
    }

    return $children;
  }
}
