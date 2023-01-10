<?php

namespace Drupal\ewcms_colors\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ewcms_colors\EwcmsColorsFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mapping colors configuration form.
 */
 
Class EwcmsColorsForm extends FormBase {
  
  /**
   * @var Drupal\ewcms_colors\EwcmsColorsFactory;
   */
  protected $colors_factory;
  
 /**
   * @param \Drupal\Core\Session\AccountInterface $account
   */
  public function __construct(EwcmsColorsFactory $colors_factory) {
    $this->colors_factory = $colors_factory;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ewcms_colors.factory')
    );
  }
  
 /**
  * {@inheritdoc}
  */
 public function getFormId() {
    return 'ewcms_colors_form';
 }
 
 /**
  * {@inheritdoc}
  */
 public function buildForm(array $form, FormStateInterface $form_state) {
    $form['settings'] = $this->getFields();
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Register'),
      '#button_type' => 'primary',
    );
    return $form;
 }
 
 /**
  * {@inheritdoc}
  */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
    $values = $form_state->getValues();
    foreach($values as $key => $value) {
      
      switch($key) {
        case 'module_target':
          if (preg_match('/[^a-z|_]/', $value)) {
            $form_state->setErrorByName('module_target', $this->t('Module target name: no numeric or uppercases or symbols other than underscore.'));
          } 
          break;
        case 'css_path':
          if (!preg_match('/^\/[a-z|_|\/|-][^\s|^A-Z|$|\'|@]+\/$/', $value)) {
            $form_state->setErrorByName('css_path', $this->t('Path to css folder: path must be surrounded by trailing slashes.'));
          } 
          break;
        case 'config_file':
          if (preg_match('/[^a-z|_|\.]+/', $value)) {
            $form_state->setErrorByName('config_file', $this->t('Config file name: no numeric or uppercases or symbols other than underscore and/or point.'));
          }
          break;
      }
    }
  }
  
 /**
  * {@inheritdoc}
  */
 public function submitForm(array &$form, FormStateInterface $form_state) {
  $settings = $form_state->cleanValues()->getValues(); 
  $switch_colors = $this->colors_factory->switchCss($settings);
  \Drupal::messenger()->addMessage($switch_colors);
 }

  /**
  * Settings.
  * 
  * @return array
  */
  
  protected function getFields() :array {
    return [
      'module_target' => [
        '#type' => 'textfield',
        '#title' => $this->t('Module target name'),
        '#description' => $this->t('The module where the css will be applied'),
        '#default_value' => 'ewcms_extended',
        "#size" => 50,
        '#maxlength' => 50,
        '#required' => TRUE,
      ],
      'css_path' => [
        '#type' => 'textfield',
        '#title' => $this->t('Path to css folder'),
        '#description' => $this->t('Module target css files path'),
        '#default_value' => '/assets/css/switch/',
        "#size" => 50,
        '#maxlength' => 75,
        '#required' => TRUE,
      ],
      'config_file' => [
        '#type' => 'textfield',
        '#title' => $this->t('Config file name'),
        '#description' => $this->t('Configuration files name'),
        '#default_value' => 'ewcms_extended.settings',
        "#size" => 50,
        '#maxlength' => 50,
        '#required' => TRUE,
      ]
    ];
  }
}
