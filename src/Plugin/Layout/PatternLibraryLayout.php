<?php

namespace Drupal\pattern_library\Plugin\Layout;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\Annotation\Layout;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\pattern_library\Exception\InvalidModifierException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var PluginManagerInterface
   */
  protected $modifierTypeManager;

  /**
   * @var boolean
   */
  protected $usesRenderType = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RequestStack $request_stack,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $modifier_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->requestStack = $request_stack;
    $this->entityFieldManager = $entity_field_manager;
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
      $container->get('request_stack'),
      $container->get('entity_field.manager'),
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
      '#pre_render' => [
        [$this, 'processModifiers']
      ],
    ];

    if ($library = $definition->getLibrary()) {
      $build['#attached']['library'][] = $library;
    }

    return $build;
  }

  /**
   * Use render types.
   *
   * @param bool $value
   *
   * @return PatternLibraryLayout
   */
  public function useRenderType($value = FALSE) {
    $this->usesRenderType = $value;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'modifiers' => [],
        'render_type' => 'default',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->buildModifiersForm($form, $form_state);

    if ($this->usesRenderType) {
      $form['render_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Field Render Type'),
        '#description' => $this->t('Set the render type for field output.'),
        '#options' => [
          'default' => $this->t('Default'),
          'value_only' => $this->t('Value Only')
        ],
        '#default_value' => $this->getConfiguration()['render_type'],
      ];
    }

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
   * Process pattern modifiers.
   *
   * @param $element
   *   An array of form element.
   *
   * @return array
   *   A render array.
   */
  public function processModifiers($element) {
    $entity = isset($element['#entity']) ? $element['#entity'] : NULL;

    if (is_object($entity)) {
      $element['#modifiers'] = $this->getModifiers($entity);
    }

    return $element;
  }

  /**
   * Ajax trigger callback.
   *
   * @param $form
   *   The form elements.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   An array of form elements based on the trigger element parents.
   */
  public function ajaxTriggerCallback($form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    $parent_form = NestedArray::getValue(
      $form, array_slice($trigger['#array_parents'], 0, -1)
    );

    // Keep details type form elements open.
    if ($parent_form['#type'] === 'details') {
      $parent_form['#open'] = TRUE;
    }

    return $parent_form;
  }

  /**
   * Get pattern modifiers.
   *
   * @param EntityInterface $entity
   *   The entity object.
   *
   * @return array
   *   An array of the processed modifiers.
   */
  protected function getModifiers(EntityInterface $entity) {
    $modifiers = [];

    $pattern = $this
      ->getPluginDefinition()
      ->getPatternDefinition();

    $pattern_modifiers = $pattern->getModifiers();
    $modifier_manager = $this->modifierTypeManager;

    foreach ($this->getConfiguration()['modifiers'] as $name => $modifier) {
      if (!isset($modifier['value']) || !isset($pattern_modifiers[$name])) {
        continue;
      }
      $definition = $pattern_modifiers[$name];

      $value = $modifier['value'];
      $modifier_type = $definition['type'];

      if (isset($modifier['field_override']) && $modifier['field_override']) {
        $field_name = $value;
        if (isset($entity->{$field_name})) {
          $value = $entity->{$field_name}->value;
        }
      }
      $value = Html::escape($value);

      $modifiers[$name] = $modifier_manager
        ->castModifierValue($modifier_type, $value);
    }

    return $modifiers;
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
      foreach ($regions[$region_name] as $index => $element) {
        if (!isset($element['#items']) || !isset($element['#field_name'])) {
          continue;
        }
        $field_name = substr($element['#field_name'], 6);
        $variables[$region_name][$field_name] = $regions[$region_name][$index];

        if ($this->usesRenderType) {
          // Set the variables based on the pattern render type. This option is
          // only valid when the regions contain field items.
          if (isset($configuration['render_type'])
            && $configuration['render_type'] == 'value_only') {

            $variables[$region_name][$field_name] = [];
            foreach (Element::children($element) as $delta) {
              $variables[$region_name][$field_name][$delta] = $element[$delta];
            }
          }
        }
      }
    }

    return $variables;
  }

  /**
   * Get context based on request.
   *
   * @return array
   *   An array of context based on the request.
   */
  protected function getContextFromRequest() {
    $context = [];
    $attributes = $this->requestStack
      ->getCurrentRequest()
      ->attributes
      ->all();

    if (isset($attributes['_entity_form'])) {
      $entity_form = $attributes['_entity_form'];

      if ($entity_form === 'entity_view_display.edit') {
        $context = [
          'bundle' => $attributes['bundle'],
          'view_mode' => $attributes['view_mode_name'],
          'entity_id' => $attributes['entity_type_id']
        ];

        // Find the entity type object within the request attributes.
        foreach ($attributes as $attribute => $value) {
          if (! $value instanceof EntityInterface) {
            continue;
          }
          $context['entity_type'] = $value;
        }
      }
    }

    return $context;
  }

  /**
   * Build modifier form.
   *
   * @param $form
   *   An array of form elements.
   *
   * @throws InvalidModifierException
   */
  protected function buildModifiersForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\pattern_library\Plugin\Pattern $pattern */
    $pattern = $this
      ->getPluginDefinition()
      ->getPatternDefinition();

    $config = $this->getConfiguration();
    $trigger = $form_state->getTriggeringElement();
    $modifier_manager = $this->modifierTypeManager;

    $modifiers = $config['modifiers'];

    if (isset($trigger)
      && !isset($form['#pattern_library_formatter'])
      && !$form['#pattern_library_formatter']) {
      $parents = array_slice($trigger['#array_parents'], 0, -2);
      $modifiers = $form_state->getValue($parents, []);
    }
    $form['modifiers'] = [
      '#type' => 'details',
      '#title' => $this->t('Pattern Modifiers'),
      '#open' => FALSE,
      '#group' => '',
    ];
    foreach ($pattern->getModifiers() as $name => $definition) {
      if (!isset($definition['type'])) {
        continue;
      }
      $type = $definition['type'];

      $modifier = isset($modifiers[$name])
        ? $modifiers[$name]
        : ['value' => NULL, 'field_override' => FALSE];

      $form['modifiers'][$name] = [
        '#type' => 'details',
        '#title' => $this->t('@name', ['@name' => strtr($name, '_', ' ')]),
        '#open' => FALSE,
        '#prefix' => "<div id='modifiers-{$name}'>",
        '#sufix' => '</div>',
      ];

      if (!isset($modifier['field_override']) || !$modifier['field_override']) {
        $definition['default_value'] = $modifier['value'];

        try {
          $form['modifiers'][$name]['value'] = $modifier_manager
            ->createInstance($type, $definition)
            ->render();
        } catch (PluginException $e) {
          throw new InvalidModifierException(
            $this->t('Invalid modifier of @type has been given.', ['@type' => $type])
          );
        }
      }
      else {
        $form['modifiers'][$name]['value'] = [
          '#type' => 'select',
          '#title' => $this->t('Field Value'),
          '#required' => TRUE,
          '#description' => $this->t('Select the field on which to extract the
          modifier value from.'),
          '#options' => $this->getFieldOptions(),
          '#empty_option' => $this->t(' -Select- '),
          '#default_value' => $modifier['value']
        ];
      }

      // Allow field overrides for any modifier.
      $form['modifiers'][$name]['field_override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Field Override'),
        '#ajax' => [
          'wrapper' => "modifiers-{$name}",
          'callback' => [$this, 'ajaxTriggerCallback'],
        ],
        '#default_value' => $modifier['field_override'],
      ];
    }

    if (empty(Element::children($form['modifiers']))) {
      unset($form['modifiers']);
    }
  }

  /**
   * Get the field options.
   *
   * @return array
   *   An array of fields keyed by name.
   */
  protected function getFieldOptions() {
    $context = $this->getContextFromRequest();
    if (!isset($context['entity_id']) || !isset($context['bundle'])) {
      return [];
    }
    $options = [];
    $fields =  $this
      ->entityFieldManager
      ->getFieldDefinitions($context['entity_id'], $context['bundle']);

    foreach ($fields as $fieldname => $field) {
      if (! $field instanceof FieldConfig) {
        continue;
      }
      $options[$fieldname] = $field->label();
    }

    return $options;
  }
}
