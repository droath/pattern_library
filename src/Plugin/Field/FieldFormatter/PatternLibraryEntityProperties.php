<?php

namespace Drupal\pattern_library\Plugin\Field\FieldFormatter;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Annotation\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define pattern library entity properties field formatter.
 *
 * @FieldFormatter(
 *   id = "pattern_library_entity_properties",
 *   label = @Translation("Entity Properties"),
 *   field_types = {
 *     "entity_reference"
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

    $form['property_mapping'] = [
      '#type' => 'details',
      '#title' => $this->t('Property Mapping'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $property_settings = $settings['property_mapping'];

    foreach ($this->getTargetEntityFields() as $bundle_name => $fields) {
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
        '#options' => $fields,
        '#default_value' => isset($bundle_settings['enabled_fields'])
          ? $bundle_settings['enabled_fields']
          : [],
      ];
      $fields_settings = isset($bundle_settings['fields'])
        ? $bundle_settings['fields']
        : [];

      foreach ($fields as $fieldname => $label) {
        $form['property_mapping'][$bundle_name]['fields'][$fieldname] = [
          '#type' => 'details',
          '#title' => $this->t('Field: @label', ['@label' => $label]),
          '#states' => [
            'visible' => [
              ':input[name="fields[' . $parent_fieldname . '][settings_edit_form][settings][property_mapping][' . $bundle_name . '][enabled_fields][' . $fieldname . ']"]' => ['checked' => TRUE]
            ]
          ],
        ];
        $field_settings = isset($fields_settings[$fieldname])
          ? $fields_settings[$fieldname]
          : [];

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
            'value_only' => $this->t('Value only')
          ],
          '#required' => TRUE,
          '#default_value' => isset($field_settings['property_render_type'])
            ? $field_settings['property_render_type']
            : 'default',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    return $this->buildElementsOutput($items);
  }

  /**
   * Get target entity fields.
   *
   * @return array
   */
  protected function getTargetEntityFields() {
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
        if ($definition->isComputed()) {
          continue;
        }
        $fields[$bundle_name][$fieldname] = $definition->getLabel();
      }
    }

    return $fields;
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

          default:
            $value = $element;
        }
        $output[$delta][$property_key] = $value;
      }
    }

    return $output;
  }
}
