<?php

namespace Drupal\pattern_library\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class PatternLibrarySettings
 *
 * @package Drupal\pattern_library\Form
 */
class PatternLibrarySettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pattern_library_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->getConfig();

    $form['primary_colors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Primary Colors'),
      '#description' => $this->t('Define the primary color pallet for the site.'),
      '#default_value' => $config->get('primary_colors'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state
      ->cleanValues()
      ->getValues();

    $this->getConfig()
      ->setData($values)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get configuration object.
   *
   * @return \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  public function getConfig() {
    return $this->config($this->getEditableConfigNames()[0]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'pattern_library.settings'
    ];
  }
}
