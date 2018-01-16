<?php

namespace Drupal\pattern_library\Plugin;

interface PatternInterface {

  /**
   * Pattern label.
   *
   * @return string
   */
  public function getLabel();

  /**
   * Pattern source.
   *
   * @return string
   */
  public function getSource();

  /**
   * Pattern variables.
   *
   * @return array
   */
  public function getVariables();

  /**
   * Pattern modifiers.
   *
   * @return array
   */
  public function getModifiers();

  /**
   * Pattern provider.
   *
   * @return string
   */
  public function getProvider();

  /**
   * Determine if pattern is valid.
   *
   * @return boolean
   */
  public function validate();

}
