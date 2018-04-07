<?php

namespace Drupal\pattern_library\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\link\LinkItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for a pattern layout formatter.
 *
 * @FieldFormatter(
 *   id = "pattern_library_layout",
 *   label = @Translation("Pattern Library"),
 *   field_types = {},
 * )
 */
class PatternLibraryLayoutFormatter extends FormatterBase implements ContainerFactoryPluginInterface{

  /**
   * @var LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Property data types excluded.
   */
  const PROPERTY_TYPE_EXCLUDED = [
    'map'
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    $third_party_settings,
    EntityFieldManagerInterface $entity_field_manager,
    LayoutPluginManagerInterface $layout_manager
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
    $this->layoutManager = $layout_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.core.layout')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getSettings();

    if (!isset($settings['layout'])) {
      throw new \Exception(
        $this->t('Missing layout plugin identifier.')
      );
    }
    $entity = $items->getEntity();

    $layout_instance = $this->layoutManager
      ->createInstance($settings['layout'], $settings['layout_settings']);

    foreach ($items as $delta => $item) {
      $regions = $this->buildRegionPropertyValues($item);
      if (empty($regions)) {
        continue;
      }
      $elements[$delta] = [
          '#entity' => $entity,
        ] + $layout_instance->build($regions);
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $layout_id = $this->getSettingFromState('layout', $form_state);
    $form['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Pattern Layout'),
      '#options' => $this->getPatternLibraryLayoutOptions(),
      '#empty_option' => $this->t(' -Select -'),
      '#required' => TRUE,
      '#default_value' => $layout_id,
      '#pattern_layout' => TRUE,
      '#ajax' => [
        'wrapper' => 'pattern-library-layout',
        'callback' => [$this, 'triggerElementAjax']
      ]
    ];
    $field_name = $this->getFieldName();

    if (!empty($layout_id)) {
      $layout_settings = $this->getSettingFromState('layout_settings', $form_state);
      $layout = $this->layoutManager
        ->createInstance($layout_id, $layout_settings)
        ->useRenderType(FALSE);

      $form['property_mapping'] = [
        '#type' => 'details',
        '#title' => $this->t('Property Mapping'),
        '#description' => $this->t('Define what field properties should be
        rendered in the appropriate layout region.'),
        '#open' => FALSE,
      ];
      $property_mapping = $this->getSetting('property_mapping');

      foreach ($layout->getPluginDefinition()->getRegions() as $region => $info) {
        $form['property_mapping'][$region] = [
          '#type' => 'details',
          '#title' => Html::escape($info['label']),
          '#open' => FALSE,
        ];
        $property_setting = $property_mapping[$region];

        $form['property_mapping'][$region]['field_property'] = [
          '#type' => 'select',
          '#title' => $this->t('Field Property'),
          '#options' => $this->getFieldPropertyOptions(),
          '#empty_option' => $this->t(' -None- '),
          '#default_value' => isset($property_setting['field_property'])
            ? $property_setting['field_property']
            : NULL,
        ];
        $form['property_mapping'][$region]['field_render'] = [
          '#type' => 'select',
          '#title' => $this->t('Field Render'),
          '#options' => [
            'value_only' => $this->t('Value Only'),
          ],
          '#empty_option' => $this->t('- Default -'),
          '#default_value' => isset($property_setting['field_render'])
            ? $property_setting['field_render']
            : NULL,
        ];
      }

      if ($layout instanceof PluginFormInterface) {
        $form['layout_settings'] = [
          '#parents' => ['fields', $field_name, 'settings_edit_form', 'settings', 'layout_settings'],
          '#pattern_library_formatter' => TRUE,
        ];
        $form['#parents'] = ['fields', $field_name, 'settings_edit_form', 'settings'];

        $subform_state = SubformState::createForSubform($form['layout_settings'], $form, $form_state);
        $form['layout_settings'] = $layout->buildConfigurationForm($form['layout_settings'], $subform_state);
      }
    }
    $form['#prefix'] = '<div id="pattern-library-layout">';
    $form['#suffix'] = '</div>';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'layout' => NULL,
        'layout_settings' => [],
        'property_mapping' => [],
    ] + parent::defaultSettings();
  }

  /**
   * Trigger element ajax callback.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   *   The form elements.
   */
  public function triggerElementAjax(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($trigger['#array_parents'], 0, -1));
  }

  /**
   * Get field machine name.
   *
   * @return string
   */
  protected function getFieldName() {
    return $this->fieldDefinition->getName();
  }

  /**
   * Get formatter setting from state.
   *
   * @param $name
   *   The name of the formatter setting.
   * @param FormStateInterface $form_state
   *   The form state object.
   *
   * @return mixed
   *   The value for the given settings.
   */
  protected function getSettingFromState($name, FormStateInterface $form_state) {
    $key  = [
      'fields',
      $this->getFieldName(),
      'settings_edit_form',
      'settings',
      $name
    ];

    return $form_state->hasValue($key)
      ? $form_state->getValue($key)
      : $this->getSetting($name);
  }

  /**
   * Build region property values.
   *
   * @param FieldItemInterface $item
   *
   * @return array
   *   An array of property values; keyed by region.
   */
  protected function buildRegionPropertyValues(FieldItemInterface $item) {
    $property_values = [];

    foreach ($this->getSetting('property_mapping') as $region => $info) {
      if (!isset($info['field_property']) || empty($info['field_property'])) {
        continue;
      }
      $render_type = isset($info['field_render'])
        ? $info['field_render']
        : NULL;

      $property_values[$region][] = $this->getItemPropertyValue(
        $item,
        $info['field_property'],
        $render_type
      );
    }

    return $property_values;
  }

  /**
   * Get item property value.
   *
   * @param FieldItemInterface $item
   * @param $property
   * @param $render_type
   *
   * @return array
   *   A render array of the field property value.
   */
  protected function getItemPropertyValue(FieldItemInterface $item, $property, $render_type) {
    if ($item->isEmpty()) {
      return [];
    }

    if ($item instanceof EntityReferenceItem) {
      $elements = $item->entity->hasField($property)
        ? $item->entity->{$property}->view()
        : [];
      $value = $elements;

      if ($render_type == 'value_only') {
        $value = [];
        foreach (Element::children($elements) as $delta) {
          $value[$delta] = $elements[$delta];
        }
      }
    }
    elseif ($item instanceof LinkItemInterface) {
      $value = [
        '#plain_text' => $property === 'uri'
          ? $item->getUrl()->toString()
          : $item->{$property},
      ];
    }
    else {
      $value = [
        '#markup' => isset($item->{$property})
          ? $item->{$property}
          : NULL
      ];
    }

    return $value;
  }

  /**
   * Get field property options.
   *
   * @return array
   *   An array of field property.
   */
  protected function getFieldPropertyOptions() {
    $options = [];

    foreach ($this->getFieldProperties() as $definitions) {
      foreach ($definitions as $property => $definition) {
        if ($definition->isComputed()
          || in_array($definition->getDataType(), static::PROPERTY_TYPE_EXCLUDED)) {
          continue;
        }
        $options[$property] = $definition->getLabel();
      }
    }

    return $options;
  }

  /**
   * Get field properties.
   *
   * @return array
   *   An array of field properties.
   */
  protected function getFieldProperties() {
    $properties = [];

    $interface = 'Drupal\Core\Field\EntityReferenceFieldItemListInterface';
    $implements = class_implements($this->fieldDefinition->getClass());

    if ($implements !== FALSE
      && in_array($interface, $implements)) {
      $type = $this->getFieldSetting('target_type');
      $settings = $this->getFieldSetting('handler_settings');

      foreach ($settings['target_bundles'] as $bundle) {
        $properties[] = $this->entityFieldManager->getFieldDefinitions($type, $bundle);
      }
    }
    else {
      $properties[] = $this->getFieldPropertyDefinitions();
    }

    return $properties;
  }

  /**
   * Get pattern library layout options.
   *
   * @return array
   *   An array of layout options.
   */
  protected function getPatternLibraryLayoutOptions() {
    $options = $this->layoutManager->getLayoutOptions();

    if (!isset($options['Pattern Library'])) {
      return [];
    }

    return $options['Pattern Library'];
  }

  /**
   * Get field property definitions.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   */
  protected function getFieldPropertyDefinitions() {
    return  $this
      ->fieldDefinition
      ->getFieldStorageDefinition()
      ->getPropertyDefinitions();
  }
}
