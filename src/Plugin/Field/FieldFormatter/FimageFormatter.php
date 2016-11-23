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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    $items = $this->reduceImageList($items);
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $image_link_setting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($image_link_setting == 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url_new = $entity->toUrl();
        $url = $entity->urlInfo();
      }
    }
    elseif ($image_link_setting == 'file') {
      $link_file = TRUE;
    }

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $base_cache_tags = [];
    if (!empty($image_style_setting)) {
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $base_cache_tags = $image_style->getCacheTags();
    }

      $cache_contexts = [];
      if (isset($link_file)) {
        $image_uri = $files[0]->getFileUri();
        // @todo Wrap in file_url_transform_relative(). This is currently
        // impossible. As a work-around, we currently add the 'url.site' cache
        // context to ensure different file URLs are generated for different
        // sites in a multisite setup, including HTTP and HTTPS versions of the
        // same site. Fix in https://www.drupal.org/node/2646744.
        $url = Url::fromUri(file_create_url($image_uri));
        $cache_contexts[] = 'url.site';
      }
      $cache_tags = Cache::mergeTags($base_cache_tags, $files[0]->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $files[0]->_referringItem;
      $item_attributes = $item->_attributes;
      unset($item->_attributes);

      $elements[0] = array(
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#item_attributes' => $item_attributes,
        '#image_style' => $image_style_setting,
        '#url' => $url,
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
  protected function reduceImageList($items) {
    foreach ($items as $delta => &$item){
      if ($delta) {
        unset($item->_loaded);
      }
    }
    return $items;
  }
}
