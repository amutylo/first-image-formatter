<?php

/**
 * @file
 * Contains Drupal\simage_formatter\Plugin\Field\FieldFormatter\SimageFormatter.
 */

namespace Drupal\fimage_formatter\Plugin\Field\FieldFormatter;

use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'image_single' formatter.
 *
 * @FieldFormatter(
 *   id = "fingle_image_formatter",
 *   label = @Translation("First Image Formatter"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class FimageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
        'image_num' => '',
      ) + parent::defaultSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['image_num'] = array(
      '#title' => t('Image number'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('image_num'),
      '#prefix' => ' <i>Be aware that image number start from 0</i>'
    );
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    $image_num_setting = intval($this->getSetting('image_num'));
    $items = $this->reduceImageList($items, $image_num_setting);
    $files = $this->getEntitiesToView($items, $langcode);
    // Early opt-out if the field is empty.
    if (empty($files)) {
      drupal_set_message(t('Image sequensial number for single image formatter set wrong. So it might not be rendered.'));
      //incorrect image number so we use first image instead.
      $items = $this->reduceImageList($items, 0, TRUE);
      $files = $this->getEntitiesToView($items, $langcode);
      if (empty($files)){
        return $elements;
      }
    }
    $idx = null;
    if (!empty($files)) {
      $key = array_keys($files);
      $idx = $key[0];
    }
    $cache_contexts = [];
    if (isset($link_file)) {
      $image_uri = $files[$idx]->getFileUri();
      // @todo Wrap in file_url_transform_relative(). This is currently
      // impossible. As a work-around, we currently add the 'url.site' cache
      // context to ensure different file URLs are generated for different
      // sites in a multisite setup, including HTTP and HTTPS versions of the
      // same site. Fix in https://www.drupal.org/node/2646744.
      $url = Url::fromUri(file_create_url($image_uri));
      $cache_contexts[] = 'url.site';
    }
    $cache_tags = $files[$idx]->getCacheTags();

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item = $files[$idx]->_referringItem;
    $item_attributes = $item->_attributes;
    unset($item->_attributes);

    $elements[] = array(
      '#theme' => 'image_formatter',
      '#item' => $item,
      '#item_attributes' => $item_attributes,
      '#cache' => array(
        'tags' => $cache_tags,
        'contexts' => $cache_contexts,
      ),
    );
    return $elements;
  }

  /**
   * Reduce image list to only first image
   * @param $items
   * @return mixed
   */
  protected function reduceImageList($items, $num, $second = FALSE) {
    //
    foreach ($items as $delta => &$item){
      if ($delta !== $num && !$second) {
        unset($item->_loaded);
      }
      elseif ($delta == $num && $second){
        $item->_loaded = true;
      }
    }
    return $items;
  }
}
