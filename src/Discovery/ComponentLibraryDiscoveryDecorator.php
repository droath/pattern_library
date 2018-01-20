<?php


namespace Drupal\pattern_library\Discovery;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Extension\ThemeHandlerInterface;

/**
 * Component library discovery decorator.
 */
class ComponentLibraryDiscoveryDecorator extends ComponentLibraryDiscovery {

  /**
   * The Discovery object being decorated.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
   */
  protected $decorated;

  public function __construct(DiscoveryInterface $decorated, ThemeHandlerInterface $theme_handler) {
    parent::__construct($theme_handler);
    $this->decorated = $decorated;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return parent::getDefinitions() + $this->decorated->getDefinitions();
  }

  /**
   * Passes through all unknown calls onto the decorated object.
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->decorated, $method], $args);
  }
}
