<?php

declare(strict_types = 1);

namespace Drupal\ewcms_colors;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Config\ImmutableConfig;
use StringTranslationTrait;

/**
 * Class to write duplicated css files.
 */
class EwcmsColorsFactory {
  
  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;
  
  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
  
  protected $final_message;

  /**
   *  EwcmsColorsFactory constructor.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct( ConfigFactoryInterface $config_factory, FileSystemInterface $fileSystem) {
    $this->final_message = "";
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->temp_dir = \Drupal::service('extension.list.module')->getPath('ewcms_colors') . '/assets/css/temp/';
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }
  
  /**
   * Call all Switch methods.
   * 
   * @param array $settings
   */
  public function switchCss($settings) {
    $this->final_message .= "Switch css : ";
    // TODO: Pass it via create() ?
    $config_file = $this->configFactory->get($settings['config_file']);
    // Retrieve all originals css files.
   $origin_css_files = $this->getOriginCssFiles($config_file); 
   // Copy these files into a temp folder
   // in case another replace method will be added.
   $css_files = $this->copyOriginCssFiles($origin_css_files);
   // Switch colors.
   if ($config_file->get('switch_colors') === TRUE) {
      $colors = $this->getColorsMapping($config_file->get('colors_mapping')['mapping']);
      $switchColors = $this->switchColors($css_files, $colors);
   }
  return $this->copyFinalCssFiles($css_files, $settings);
  }
  
  /**
   * Replace colors and write css files in ewcms_colors temp dir.
   * 
   * @param array $css_files
   * 
   * @param array $colors
   */
  protected function switchColors(array &$css_files, array $colors): array {
    foreach ($css_files as $css_file) {
      try {
        $dest_file_name = $css_file->filename;
        $from_css = file_get_contents($css_file->uri);
        $to_css = str_replace($colors['from'], $colors['to'] , $from_css, $count);
        if (!empty($count)) {
          $write = file_put_contents( $css_file->uri, $to_css);
          // Add a property.
          $css_file->switch = TRUE;
          if ($write === FALSE) {
            throw new Exception("$css_file->uri " . t('writing failed'));
          }
        }
      }
      catch (Exception $e) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      }
    }
    return $css_files;
  }
  
  /**
   * Retrieve origin theme css files.
   * 
   * @param ImmutableConfig $config_file
   */
   protected function getOriginCssFiles(ImmutableConfig $config_file): array {
     $theme_base_path = './' . \Drupal::service('extension.list.theme')->getPath($config_file->get('theme'));
     // TODO: Remove ['mapping']
    $styles_path = $config_file->get('styles_paths')['mapping'];
    $css_list = [];
    foreach ($styles_path as $style_path) {
      $path = $theme_base_path . $style_path;
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $styles = $this->fileSystem->scanDirectory(
        $path,
        '/(.css)$/',
        ['key' => 'filename', 'recurse' => TRUE]
        );
        foreach( $styles as $style ) {
          $css_list[] = $style;
        }
    }
    return $css_list;
   }
  
  /**
   * Copy all original theme css files to temp folder.
   * 
   * @param array $origin_css_files
   */
  protected function copyOriginCssFiles(array &$origin_css_files): array {
    $this->fileSystem->prepareDirectory($this->temp_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
    foreach ($origin_css_files as $css) {
      $dest_file_name = $css->filename;
      $copy = $this->fileSystem->copy($css->uri, $this->temp_dir . '/' .$css->filename, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE );
      $css->uri = $this->temp_dir . '/' .$css->filename;
    }
    return $origin_css_files;
  }
  
  /**
  * Copy altered css files to target module dir.
  * 
  * @param array of objects $css_files
  * 
  * @param array $settings
  */
  protected function copyFinalCssFiles(array $css_files, array $settings): string {
    
    if(empty($css_files)){
      return FALSE;
    }
    $destination_url = \Drupal::service('extension.list.module')->getPath($settings['module_target']) . $settings['css_path'];
    $this->fileSystem->prepareDirectory($destination_url, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
    foreach ($css_files as $css) {
      if(property_exists($css, 'switch') && $css->switch === TRUE) {
       $copy = $this->fileSystem->copy($css->uri, $destination_url . '/' .$css->filename, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE );
      }
    }
    $this->final_message .= "css files copied.";
    return $this->final_message;
  }
   
  /**
  * Return apaired colors array.
  * 
  * @param array $colors
  */
  protected function getColorsMapping(array $colors): array {
    $mapping = [
      'from' => [],
      'to' => [],
    ];
    $pattern = '/^#[a-f0-9]{3,6}$/i';
    foreach ($colors as $key => $value) {
      if (!preg_match($pattern, $key) || !preg_match($pattern, $value) ) {
        \Drupal::messenger()->addError(t('%k or %v is not hexadecimal format', ['%k' => $key, '%v' => $value]));
        $mapping = [];
        break;
      } 
        $mapping['from'][] = $key;
        $mapping['to'][] = $value;
    }
    return $mapping;
  }
  
}
