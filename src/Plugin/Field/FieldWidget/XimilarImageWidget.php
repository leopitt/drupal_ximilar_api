<?php

namespace Drupal\ximilar_api\Plugin\Field\FieldWidget;

use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
* Plugin implementation of the 'ximilar_image' widget.
*
* @FieldWidget(
*   id = "ximilar_image",
*   label = @Translation("Ximilar image"),
*   field_types = {
*     "image"
*   }
* )
*/
class XimilarImageWidget extends ImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'progress_indicator' => 'throbber',
        'preview_image_style' => 'thumbnail',
        'check_near_duplicates' => FALSE,
      ] + parent::defaultSettings();
  }

  /**
  * {@inheritdoc}
  */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    // Get the parent form element.
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Add a near duplicate validator.
    $element['#upload_validators']['ximilar_api_validate_check_near_duplicates'] = [];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['check_near_duplicates'] = [
      '#title' => $this->t('Check for near duplicates'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('check_near_duplicates'),
      '#description' => $this->t('Ximilar API will be used to check for near duplicates.'),
      '#weight' => 20,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    if ($this->getSetting('check_near_duplicates')) {
      $summary[] = $this->t('Check for near duplicate images');
    }

    return $summary;
  }

}
