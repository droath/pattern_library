<?php

namespace Drupal\pattern_library\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\Registry;
use Drupal\pattern_library\PatternLibraryManagerInterface;
use Drupal\pattern_library\Plugin\Pattern;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define pattern library theme override add form.
 */
class ThemeOverrideForm extends EntityForm  {

  /**
   * @var Registry
   */
  protected $themeRegistry;

  /**
   * @var PatternLibraryManagerInterface
   */
  protected $patternLibraryManager;

  /**
   * Theme override form constructor.
   *
   * @param Registry $theme_registry
   */
  public function __construct(
    Registry $theme_registry,
    PatternLibraryManagerInterface $pattern_library_manager) {
    $this->themeRegistry = $theme_registry;
    $this->patternLibraryManager = $pattern_library_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('theme.registry'),
      $container->get('plugin.manager.pattern_library')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#prefix'] = '<div id="theme-hook">';
    $form['#suffix'] = '</div>';

    $theme_hook = NULL;
    $theme_info = $this->getThemeHooksInfo();
    $pattern_library = $this->getConfigValue('pattern_library', $form_state);

    $form['pattern_library'] = [
      '#type' => 'select',
      '#title' => $this->t('Pattern Library'),
      '#options' => $this->patternLibraryManager->getDefinitionOptions(),
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'theme-hook',
        'callback' => [$this, 'ajaxCallback']
      ],
      '#default_value' => $pattern_library,
    ];

    if (isset($pattern_library)) {
      $theme_hook = $this->getConfigValue('theme_hook', $form_state);

      $form['theme_hook'] = [
        '#type' => 'select',
        '#title' => $this->t('Theme hook'),
        '#options' => $this->getThemeHookOptions(),
        '#required' => TRUE,
        '#ajax' => [
          'wrapper' => 'theme-hook',
          'callback' => [$this, 'ajaxCallback']
        ],
        '#default_value' => $theme_hook,
      ];
      $theme_hook_info = isset($theme_info[$theme_hook])
        ? $theme_info[$theme_hook]
        : [];

      $empty_message = isset($theme_hook)
        ? $this->t("Theme hook '@theme_hook' is a render element, pattern variables will 
        be passed along to the pattern however they're defined on the render array.", [
          '@theme_hook' => $theme_hook])
        : $this->t('Please select a theme hook.');

      $form['pattern_mapping'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Pattern Variables'),
          $this->t('Theme Variables')
        ],
        '#empty' => $empty_message
      ];

      if (isset($pattern_library) && isset($theme_hook_info['variables'])) {
        $theme_variables = array_keys($theme_hook_info['variables']);

        /** @var Pattern $pattern_instance */
        $pattern_instance = $this->patternLibraryManager->createInstance($pattern_library);

        foreach ($pattern_instance->getVariables() as $variable_name => $info) {
          if (!isset($info['label'])) {
            continue;
          }
          $label = $info['label'];

          $form['pattern_mapping'][$variable_name]['pattern_variable'] = [
            '#plain_text' => $label
          ];
          $form['pattern_mapping'][$variable_name]['theme_variable'] = [
            '#type' => 'select',
            '#empty_option' => $this->t('- None -'),
            '#options' => array_combine($theme_variables, $theme_variables),
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $entity = $this->entity;
    $theme_hook = $form_state->getValue('theme_hook');
    if ($entity->isNew()
      && isset($theme_hook)
      && $entity->entityExists($theme_hook)) {
      $form_state->setError($form['theme_hook'], $this->t(
        "The theme hook @theme_hook can only be overridden once.", ['@theme_hook' => $theme_hook]));
    }
  }

  /**
   * Ajax callback for the form.
   *
   * @param array $form
   *   The form elements.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->id = $form_state->getValue('theme_hook');
    parent::save($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

  /**
   * Get theme hook info.
   *
   * @return array
   */
  protected function getThemeHooksInfo() {
    return $this->themeRegistry->get();
  }

  /**
   * Get configuration value.
   *
   * @param $key
   *   The key value
   * @param FormStateInterface $form_state
   *
   * @return mixed
   */
  protected function getConfigValue($key, FormStateInterface $form_state) {
    return $form_state->hasValue($key)
      ? $form_state->getValue($key)
      : $this->entity->{$key};
  }

  /**
   * Get theme hook options.
   *
   * @return array
   */
  protected function getThemeHookOptions() {
    $theme_hooks = array_keys($this->getThemeHooksInfo());
    $options = array_combine($theme_hooks, $theme_hooks);
    ksort($options);

    return $options;
  }

}
