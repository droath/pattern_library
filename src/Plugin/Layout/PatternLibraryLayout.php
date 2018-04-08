<?php

namespace Drupal\pattern_library\Plugin\Layout;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\Annotation\Layout;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\pattern_library\Exception\InvalidModifierException;
use Drupal\pattern_library\PatternModifierTypeManager;
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
   * @var RendererInterface
   */
  protected $renderer;

  /**
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
    RendererInterface $renderer,
    RequestStack $request_stack,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    PluginManagerInterface $modifier_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
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
      '#theme' => 'pattern_library_layout',
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
        'render_empty' => FALSE,
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
    $form['render_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render empty'),
      '#description' => $this->t('Regardless if data exist, pattern will be 
      rendered.'),
      '#default_value' => $this->getConfiguration()['render_empty']
    ];

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
      if ((!isset($modifier['value']) && !isset($modifier['field_name']))
        || !isset($pattern_modifiers[$name])) {
        continue;
      }
      $value = NULL;
      $definition = $pattern_modifiers[$name];
      $modifier_type = $definition['type'];

      if (isset($modifier['value']) && !empty($modifier['value'])) {
        $value = $modifier['value'];
      }
      elseif (isset($modifier['field_name']) && isset($modifier['field_override'])
        && $modifier['field_override']) {
        $value = $this->getEntityFieldValue($entity, $modifier['field_name'], $modifier_type);
      }

      $value = !is_array($value)
        ? Html::escape($value)
        : array_map('\Drupal\Component\Utility\Html::escape', $value);

      if (is_array($value) && count($value) === 1) {
        $value = reset($value);
      }

      /** @var PatternModifierTypeManager $modifier_manager */
      $modifiers[$name] = $modifier_manager
        ->processModifierValue($modifier_type, $value);
    }

    return $modifiers;
  }

  /**
   * Get entity field value.
   *
   * @param EntityInterface $entity
   *   The entity to retrieve the value from.
   * @param $field_name
   *   The field name of the property.
   * @param $modifier_type
   *   The field modifier type.
   *
   * @return null|string
   */
  protected function getEntityFieldValue(EntityInterface $entity, $field_name, $modifier_type) {
    if (!isset($entity->{$field_name})) {
      return NULL;
    }
    $field = $entity->{$field_name};

    if ($field->isEmpty()) {
      return NULL;
    }
    $value = $entity->{$field_name}->value;

    /** @var FieldDefinitionInterface $field_definition */
    $definition = $field->getFieldDefinition();
    $field_type = $definition->getType();

    if ($field_type === 'entity_reference' &&
      ($modifier_type === 'image_path' || $modifier_type === 'file_path')) {
      $value = NULL;
      $file_entity = $field->entity;

      if ($file_entity instanceof FileInterface) {
        $value = $file_entity->getFileUri();
      }
      else {
        $target_type = $definition->getSetting('target_type');

        if ($target_type === 'media') {
          $file_type = substr($modifier_type, 0, strpos($modifier_type, '_path'));
          $file_entity = $this->findFileEntity($file_entity, [$file_type]);
        }

        if ($file_entity !== FALSE && $file_entity instanceof FileInterface) {
          $value = $file_entity->getFileUri();
        }
      }
    }

    return $value;
  }

  /**
   * Find file entity in provided entity.
   *
   * @param EntityInterface $entity
   *   The entity on which to search within.
   * @param array $allowed_types
   *   An array of allowed field types.
   *
   * @return File|bool
   *   The file entity object.
   */
  protected function findFileEntity(EntityInterface $entity, array $allowed_types) {
    foreach ($entity->getFieldDefinitions() as $field) {
      if (!$field instanceof FieldConfigInterface) {
        continue;
      }
      $field_type = $field->getType();
      $field_name = $field->getName();

      if (in_array($field_type, $allowed_types)) {
        return $entity->{$field_name}->entity;
      }
    }

    return FALSE;
  }

  /**
   * Get pattern variables.
   *
   * @param array $regions
   *   An array of layout variables.
   *
   * @return array
   *   An array of pattern variables.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getPatternVariables(array $regions) {
    $variables = [];
    $configuration = $this->getConfiguration();

    foreach ($this->getPluginDefinition()->getRegionNames() as $region_name) {
      if (!array_key_exists($region_name, $regions)) {
        continue;
      }
      $reference = &$variables[$region_name];

      $elements = $regions[$region_name];
      $elements_count = count($elements);
      foreach ($elements as $index => $element) {
        // If there is multiple elements contained in a region then index the
        // variables array. When extracting a field value from the variable
        // you're going to have to declare the index, only if it contains
        // multiple values, like so {{ field_element[0].url }}.
        if ($elements_count > 1) {
          $reference = &$variables[$region_name][$index];
        }

        if (isset($configuration['render_empty'])
          && !$configuration['render_empty']) {

          unset($element['#cache']);
          unset($element['#weight']);

          // Verify the reference element has content associated with it. As we
          // don't want to render elements without any content, due to it
          // producing empty markup which can lead to unexpected styling issues.
          $reference = !empty($element) ? $element : NULL;

          // Verity that object are not rendering empty data.
          if (isset($reference['#object']) && isset($reference['#field_name'])
            && strpos($reference['#field_name'], 'field_') !== FALSE) {
            $object = $reference['#object'];
            $field_name = $reference['#field_name'];
            $view_mode = isset($reference['#view_mode'])
              ? $reference['#view_mode']
              : 'default';

            if (!$this->hasEntityFieldData($object, $field_name, $view_mode)) {
              $reference = NULL;
            }
          }

          if (!isset($reference)) {
            continue;
          }
        }

        if ($this->usesRenderType) {
          // Overwrite the variable value based on the selected field render
          // type. Values will not be overwritten if the element doesn't have
          // any children associated with it.
          if (isset($configuration['render_type'])
            && $configuration['render_type'] == 'value_only') {
            $children = Element::children($element);

            if (count($children) !== 0) {
              $reference = [];
              foreach ($children as $delta) {
                $reference[$delta] = $element[$delta];
              }
            }
          }
        }
      }
    }

    return $variables;
  }

  /**
   * Determine if entity field has data.
   *
   * @param ContentEntityInterface $entity
   * @param $field_name
   * @param string $view_mode
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function hasEntityFieldData(ContentEntityInterface $entity, $field_name, $view_mode = 'default') {
    if (!$entity->hasField($field_name)) {
      return FALSE;
    }
    $field = $entity->get($field_name);

    if ($field instanceof EntityReferenceFieldItemListInterface) {
      /** @var ContentEntityInterface $entity_reference */
      $entity_reference = $field->entity;

      /** @var EntityViewDisplay $display */
      $display = $this->getEntityViewDisplay($entity_reference, $view_mode);

      if (isset($display)) {

        // Additional checking for pattern library layout settings in ds.
        if ($ds_layout = $display->getThirdPartySetting('ds', 'layout')) {

          if (isset($ds_layout['settings'])
            && strpos($ds_layout['id'], 'pattern_library:') !== FALSE) {
            $settings = $ds_layout['settings'];

            if (isset($settings['render_empty']) && $settings['render_empty']) {
              return TRUE;
            }
          }
        }

        foreach (array_keys($display->getComponents()) as $name) {
          $component_field = $entity_reference->get($name);

          if (!$component_field->isEmpty()) {
            return TRUE;
          }
        }
      }
      elseif (!$field->isEmpty()) {
        return TRUE;
      }
    }
    elseif (!$field->isEmpty()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get entity view display.
   *
   * @param FieldableEntityInterface $entity
   * @param $view_mode
   * @param bool $default_fallback
   *
   * @return EntityViewDisplayInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntityViewDisplay(FieldableEntityInterface $entity, $view_mode, $default_fallback = TRUE) {
    $display_storage = $this->entityTypeManager
      ->getStorage('entity_view_display');

    $base_id = "{$entity->getEntityTypeId()}.{$entity->bundle()}";
    $display = $display_storage->load("{$base_id}.{$view_mode}");

    // Fallback to the default entity display if not found.
    if (!isset($display)
      && $default_fallback
      && $view_mode !== 'default') {
      $display = $display_storage->load("{$base_id}.default");
    }

    return $display;
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

    if (isset($trigger) && isset($trigger['#modifier_override'])
      && $trigger['#modifier_override']) {
      $parents = array_slice($trigger['#parents'], 0, -2);
      $modifiers = $form_state->getValue($parents, $modifiers);
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
        : ['value' => NULL, 'field_name' => NULL, 'field_override' => FALSE];

      $form['modifiers'][$name] = [
        '#type' => 'details',
        '#title' => $this->t('@name', ['@name' => strtr($name, '_', ' ')]),
        '#open' => FALSE
      ];
      $form['modifiers'][$name]['#prefix'] = "<div id='modifiers-{$name}'>";
      $form['modifiers'][$name]['#suffix'] = '</div>';

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
        $options = $this->getFieldOptions();
        $form['modifiers'][$name]['field_name'] = [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#description' => $this->t('Select the field on which to extract the
          modifier value from.'),
          '#options' => $options,
          '#empty_option' => $this->t('- None -'),
          '#default_value' => isset($modifier['field_name'])
            && isset($options[$modifier['field_name']])
              ? $modifier['field_name']
              : NULL,
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
        '#modifier_override' => TRUE,
        '#limit_validation_errors' => FALSE,
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
