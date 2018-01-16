<?php

namespace Drupal\pattern_library\Plugin\Layout;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\Annotation\Layout;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\pattern_library\Exception\InvalidModifierException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pattern library layout.
 *
 * @Layout(
 *   id = "pattern_library",
 *   deriver = "Drupal\pattern_library\Plugin\Deriver\PatternLibraryLayoutDeriver"
 * )
 */
class PatternLibraryLayout extends LayoutDefault implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * @var PluginManagerInterface
   */
  protected $modifierTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PluginManagerInterface $modifier_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->modifierTypeManager = $modifier_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.pattern_modifier_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    /** @var \Drupal\pattern_library\Plugin\Layout\PatternLayoutDefinition $definition */
    $definition = $this->getPluginDefinition();
    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern **/
    $pattern = $definition->getPatternDefinition();

    $build = [
      '#theme' => $definition->getThemeHook(),
      '#source' => $pattern->getSource(),
      '#variables' => $this->getPatternVariables($regions),
      '#modifiers' => $this->getConfiguration()['modifiers'],
    ];

    if ($library = $definition->getLibrary()) {
      $build['#attached']['library'][] = $library;
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'modifiers' => [],
        'render_type' => 'field',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['render_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Pattern Render Type'),
      '#description' => $this->t('Set the render type for field output.'),
      '#options' => [
        'field' => $this->t('Field'),
        'value_only' => $this->t('Value Only')
      ],
      '#default_value' => $this->getConfiguration()['render_type'],
    ];
    $this->buildModifiersForm($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration = $form_state->cleanValues()->getValues();
  }

  /**
   * Get pattern variables.
   *
   * @param array $regions
   *   An array of layout variables.
   *
   * @return array
   *   An array of pattern variables.
   */
  protected function getPatternVariables(array $regions) {
    $variables = [];
    $configuration = $this->getConfiguration();

    foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
      if (!array_key_exists($region_name, $regions)) {
        continue;
      }

      if (isset($configuration['render_type'])
        && $configuration['render_type'] == 'value_only') {

        // Determine how to render region fields.
        foreach ($regions[$region_name] as $field_name => $elements) {
          $variables[$region_name][$field_name] = [];

          foreach (Element::children($elements) as $index) {
            $variables[$region_name][$field_name][$index] = $elements[$index];
          }
        }
      }
      else {
        $variables[$region_name] = $regions[$region_name];
      }
    }

    return $variables;
  }

  /**
   * Build modifier form.
   *
   * @param $form
   *   An array of form elements.
   *
   * @throws InvalidModifierException
   */
  protected function buildModifiersForm(array &$form) {
    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern */
    $pattern = $this
      ->getPluginDefinition()
      ->getPatternDefinition();

    $modifier_manager = $this->modifierTypeManager;

    $form['modifiers'] = [
      '#type' => 'details',
      '#title' => $this->t('Pattern Modifiers'),
      '#open' => TRUE,
      '#group' => '',
    ];
    $modifiers = $this->getConfiguration()['modifiers'];

    foreach ($pattern->getModifiers() as $name => $definition) {
      if (!isset($definition['type'])) {
        continue;
      }
      if (isset($modifiers[$name])) {
        $definition['default_value'] = $modifiers[$name];
      }
      $type = $definition['type'];
      unset($definition['type']);

      try {
        $form['modifiers'][$name] = $modifier_manager
          ->createInstance($type, $definition)
          ->render();
      } catch (PluginException $e) {
        throw new InvalidModifierException(
          $this->t('Invalid modifier of @type has been given.', ['@type' => $type])
        );
      }
    }
  }

  /**
   * Get entity from regions.
   *
   * @param array $regions
   *   An array of regions.
   *
   * @return object|boolean
   *   The entity which the field is attached too; otherwise FALSE.
   */
  protected function getEntityFromRegion(array $regions) {
    foreach ($regions as $region => $fields) {
      foreach ($fields as $field) {
        if (!isset($field['#object'])) {
          continue;
        }
        return $field['#object'];
      }
    }

    return FALSE;
  }
}
