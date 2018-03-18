<?php

namespace Drupal\pattern_library\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define pattern library entity properties field formatter.
 *
 * @FieldFormatter(
 *   id = "pattern_library_entity_properties",
 *   label = @Translation("Entity Properties"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   }
 * )
 */
class PatternLibraryEntityProperties extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Pattern library json constructor.
   *
   * @param $plugin_id
   * @param $plugin_definition
   * @param FieldDefinitionInterface $field_definition
   * @param array $settings
   * @param $label
   * @param $view_mode
   * @param $third_party_settings
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    $third_party_settings,
    EntityFieldManagerInterface $entity_field_manager) {

    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );

    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
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
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'property_mapping' => []
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $settings = $this->getSettings();
    $parent_fieldname = $this->getFieldName();
    $entity_field_options = $this->getTargetEntityFieldOptions();

    $form['property_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Property Mapping'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $property_parents = [
      'fields',
      $parent_fieldname,
      'settings_edit_form',
      'settings',
      'property_mapping',
    ];
    $property_settings = $settings['property_mapping'];

    foreach ($this->getTargetEntityFieldsInfo() as $bundle_name => $fields) {
      $form['property_mapping'][$bundle_name] = [
        '#type' => 'details',
        '#title' => $this->t('Bundle: @bundle_name', ['@bundle_name' => $bundle_name]),
        '#open' => FALSE,
      ];
      $bundle_settings = isset($property_settings[$bundle_name])
        ? $property_settings[$bundle_name]
        : [];

      $form['property_mapping'][$bundle_name]['enabled_fields'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Enabled fields'),
        '#required' => TRUE,
        '#options' => $entity_field_options[$bundle_name],
        '#default_value' => isset($bundle_settings['enabled_fields'])
          ? $bundle_settings['enabled_fields']
          : [],
      ];
      $fields_settings = isset($bundle_settings['fields'])
        ? $bundle_settings['fields']
        : [];
      $property_bundle_parents = array_merge($property_parents, [$bundle_name, 'fields']);

      foreach ($fields as $fieldname => $field_info) {
        $label = $field_info['label'];
        $property_fieldname_parents = array_merge($property_bundle_parents, [$fieldname]);

        $wrapper_id = "property-mapping-{$bundle_name}-fields-{$fieldname}";
        $form['property_mapping'][$bundle_name]['fields'][$fieldname] = [
          '#type' => 'details',
          '#title' => $this->t('Field: @label', ['@label' => $label]),
          '#prefix' => '<div id="' . $wrapper_id . '">',
          '#suffix' => '</div>',
          '#states' => [
            'visible' => [
              ':input[name="fields[' . $parent_fieldname . '][settings_edit_form][settings][property_mapping][' . $bundle_name . '][enabled_fields][' . $fieldname . ']"]' => ['checked' => TRUE]
            ]
          ],
        ];
        $field_settings = $form_state->getValue($property_fieldname_parents, $fields_settings[$fieldname]);

        $form['property_mapping'][$bundle_name]['fields'][$fieldname]['property_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Property name'),
          '#required' => TRUE,
          '#default_value' => isset($field_settings['property_name'])
            ? $field_settings['property_name']
            : $fieldname,
        ];
        $form['property_mapping'][$bundle_name]['fields'][$fieldname]['property_render_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Render Type'),
          '#options' => [
            'default' => $this->t('Default'),
            'property' => $this->t('Property'),
            'value_only' => $this->t('Value only'),
          ],
          '#required' => TRUE,
          '#default_value' => isset($field_settings['property_render_type'])
            ? $field_settings['property_render_type']
            : 'default',
          '#ajax' => [
            'wrapper' => $wrapper_id,
            'callback' => [$this, 'renderTypeAjaxCallback']
          ]
        ];
        if (isset($field_settings['property_render_type'])
          && $field_settings['property_render_type'] === 'property') {
          $form['property_mapping'][$bundle_name]['fields'][$fieldname]['property'] = [
            '#type' => 'select',
            '#title' => $this->t('Property'),
            '#options' => $this->processPropertyOptions($field_info['properties']),
            '#required' => !empty($bundle_settings['enabled_fields'][$fieldname]) && $field_settings['property_render_type'] === 'property',
            '#empty_option' => $this->t('- Select -'),
            '#default_value' => isset($field_settings['property'])
              ? $field_settings['property']
              : NULL,
          ];
        }
      }
    }

    return $form;
  }

  /**
   * Render type ajax callback.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return mixed
   */
  public function renderTypeAjaxCallback(array $form, FormStateInterface $form_state) {
    $trigger_element = $form_state->getTriggeringElement();

    $output = NestedArray::getValue(
      $form, array_slice($trigger_element['#array_parents'], 0, -1)
    );
    $output['#open'] = TRUE;

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->buildElementsOutput($items);
  }

  /**
   * Get target entity fields info.
   *
   * @return array
   */
  protected function getTargetEntityFieldsInfo() {
    $settings = $this->getFieldSettings();
    $target_type = $this->getFieldSetting('target_type');

    // Only support the default handler.
    if ($settings['handler'] !== 'default:' . $target_type) {
      return [];
    }
    $handler = $this->getFieldSetting('handler_settings');

    if (!isset($handler['target_bundles'])) {
      return [];
    }
    $target_bundles = $handler['target_bundles'];

    $fields = [];
    foreach ($target_bundles as $bundle_name) {
      $definitions = $this->entityFieldManager->getFieldDefinitions($target_type, $bundle_name);

      foreach ($definitions as $fieldname => $definition) {
        if (!$definition instanceof ListDataDefinitionInterface
          || $definition->isComputed()) {
          continue;
        }
        $field_type = $definition->getType();

        $properties = $definition instanceof FieldStorageDefinitionInterface
          ? $definition->getPropertyDefinitions()
          : $definition->getItemDefinition()->getPropertyDefinitions();

        // Retrieve the entity reference properties instead.
        if (in_array($field_type, ['image', 'entity_reference'])) {
          $properties = $this->getEntityReferenceProperties($properties);
        }

        $fields[$bundle_name][$fieldname] = [
          'label' => $definition->getLabel(),
          'properties' => $this->formatPropertyOptions($properties),
        ];
      }
    }

    return $fields;
  }

  /**
   * Build elements output.
   *
   * @param FieldItemListInterface $items
   *   An iterator of field items.
   *
   * @return array
   */
  protected function buildElementsOutput(FieldItemListInterface $items) {
    $output = [];
    $property_mapping = $this->getSetting('property_mapping');

    /** @var FieldItemListInterface $item **/
    foreach ($items as $delta => $item) {
      if ($item->isEmpty()) {
        continue;
      }

      /** @var FieldableEntityInterface $entity **/
      $entity = $item->entity;
      $bundle = $entity->bundle();

      if (!isset($property_mapping[$bundle])) {
        continue;
      }
      $bundle_mapping = $property_mapping[$bundle];
      $enabled_fields = array_values(
        array_filter($bundle_mapping['enabled_fields'])
      );
      $bundle_fields = $bundle_mapping['fields'];

      foreach ($enabled_fields as $fieldname) {
        if (!isset($bundle_fields[$fieldname]['property_name'])
        || !$entity->hasField($fieldname)) {
          continue;
        }
        $element = $entity->get($fieldname)->view();
        $field_settings = $bundle_fields[$fieldname];

        $property_key = $field_settings['property_name'];
        $render_type = $field_settings['property_render_type'];

        switch ($render_type) {
          case 'value_only':
            $value = [];
            foreach (Element::children($element) as $delta) {
              $value[$delta] = $element[$delta];
            }
            break;
          case 'property':
            $value = [
              '#markup' => $this->getEntityPropertyValue(
                $entity, $fieldname, $field_settings['property']
              )
            ];
            break;

          default:
            $value = $element;
        }
        $cardinality = $this->getFieldStorage()->getCardinality();

        if ($cardinality === 1) {
          $output[$property_key] = $value;
        }
        else {
          $output[$delta][$property_key] = $value;
        }
      }
    }

    return $output;
  }

  /**
   * Process property options.
   *
   * @param array $properties
   *
   * @return array
   */
  protected function processPropertyOptions(array $properties) {
    $options = [];

    foreach ($properties as $property => $value) {
      if (is_array($value)) {
        $options[$property] = $value;
      }
      else {
        $options[$property] = $properties;
      }
    }

    return $options;
  }

  /**
   * Get target entity field options.
   *
   * @return array
   */
  protected function getTargetEntityFieldOptions() {
    $options = [];

    foreach ($this->getTargetEntityFieldsInfo() as $bundle => $fields) {
      foreach ($fields as $field_name => $field_info) {
        if (!isset($field_info['label'])) {
          continue;
        }
        $options[$bundle][$field_name] = $field_info['label'];
      }
    }

    return $options;
  }

  /**
   * Format properties options.
   *
   * @param $properties
   * @param array $options
   *
   * @return array
   */
  protected function formatPropertyOptions($properties, &$options = []) {
    foreach ($properties as $property => $definition) {
      if (is_array($definition)) {
        $value = $this->formatPropertyOptions($definition, $options[$property]);
        $options[$property] = !empty($value) ? $value : [];
      }
      else {
        if ($definition->isComputed()) {
          continue;
        }
        $options[$property] = $definition->getLabel();
      }
    }

    return $options;
  }

  /**
   * Get entity reference properties.
   *
   * @param array $parent_properties
   *   An array of the parent property definitions.
   *
   * @return array
   */
  protected function getEntityReferenceProperties(array $parent_properties) {
    $properties = [];

    if ($target_info = $this->findEntityDataReferenceTargetInfo($parent_properties)) {
      $definition = $target_info['definition'];
      $properties[$target_info['name']] = $definition->getPropertyDefinitions();
    }

    return $properties;
  }

  /**
   * Find entity data reference target info.
   *
   * @param array $properties
   *   The parent properties.
   *
   * @return array
   */
  protected function findEntityDataReferenceTargetInfo(array $properties) {
    foreach ($properties as $name => $definition) {
      if (!$definition instanceof DataReferenceDefinitionInterface) {
        continue;
      }
      return [
        'name' => $name,
        'definition' => $definition->getTargetDefinition(),
      ];
    }

    return [];
  }

  /**
   * Get entity property value.
   *
   * @param EntityInterface $entity
   * @param $field_name
   * @param $field_property
   *
   * @return string
   */
  protected function getEntityPropertyValue(EntityInterface $entity, $field_name, $field_property) {
    $value = NULL;
    $field = $entity->get($field_name);

    if (!$field->isEmpty()) {
      $property_name = $field->getName();
      $property_value = $field->{$field_property};

      if (is_scalar($property_value)) {
        $value = $property_value;
      }
      else {
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          $property_value = $field->entity->{$field_property};
        }
        $value = $property_value->value;
        $property_name = $property_value->getName();
      }
      $definition = $entity->getFieldDefinition($field_name);

      // Allow for altercations to the field property value.
      if ($property_name === 'uri' &&
        in_array($definition->getType(), ['image', 'file'])) {
        $value = file_create_url($value);
      }
    }

    return $value;
  }

  /**
   * Get formatter field name.
   *
   * @return string
   */
  protected function getFieldName() {
    return $this->fieldDefinition->getName();
  }

  /**
   * Get formatter field storage.
   *
   * @return FieldStorageDefinitionInterface
   */
  protected function getFieldStorage() {
    return $this->fieldDefinition->getFieldStorageDefinition();
  }

}
