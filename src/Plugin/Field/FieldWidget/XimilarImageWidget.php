<?php

namespace Drupal\ximilar_api\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
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
    //$element['#upload_validators']['ximilar_api_validate_check_near_duplicates'] = [];

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

  /**
   * Form API callback: Processes an image_image field element.
   *
   * Expands the image_image type to include the alt and title fields.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $element['#theme'] = 'image_widget';

    // If we have a file uploaded.
    if (!empty($element['#files'])) {
      $file = reset($element['#files']);
      /** @var \Drupal\ximilar_api\XimilarAPIService $ximilar_api */
      $ximilar_api = \Drupal::service('ximilar_api.service');

      // Check for near duplicates.
      if ($near_duplicates = $ximilar_api->nearDuplicates($file)) {
        // Near duplicates found, so output an error message.
        $element['ximilar_api_duplicates'][] = [
          '#markup' => new TranslatableMarkup('<p>Near duplicates for this file already exist in the file system:</p>'),
        ];

        // Loop over near duplicates and display a thumbnail image for each.
        foreach($near_duplicates as $near_duplicate) {
          // Get the file.
          $file = File::load($near_duplicate['id']);
          if (!empty($file)) {
            // Add the image to the element.
            $render = [
              '#theme' => 'image_style',
              '#style_name' => 'thumbnail',
              '#uri' => $file->getFileUri(),
              '#width' => 100,
            ];
            $element['ximilar_api_duplicates'][] = $render;
          }
        }

        $element['ximilar_api_duplicates'][] = [
          '#markup' => new TranslatableMarkup('<p>Are you sure you want to proceed with this upload?</p>'),
        ];
      }
    }
    else {
      // Add the image preview.
      if (!empty($element['#files']) && $element['#preview_image_style']) {
        $file = reset($element['#files']);
        $variables = [
          'style_name' => $element['#preview_image_style'],
          'uri' => $file->getFileUri(),
        ];

        $dimension_key = $variables['uri'] . '.image_preview_dimensions';
        // Determine image dimensions.
        if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
          $variables['width'] = $element['#value']['width'];
          $variables['height'] = $element['#value']['height'];
        }
        elseif ($form_state->has($dimension_key)) {
          $variables += $form_state->get($dimension_key);
        }
        else {
          $image = \Drupal::service('image.factory')->get($file->getFileUri());
          if ($image->isValid()) {
            $variables['width'] = $image->getWidth();
            $variables['height'] = $image->getHeight();
          }
          else {
            $variables['width'] = $variables['height'] = NULL;
          }
        }

        $element['preview'] = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => $variables['width'],
          '#height' => $variables['height'],
          '#style_name' => $variables['style_name'],
          '#uri' => $variables['uri'],
        ];

        // Store the dimensions in the form so the file doesn't have to be
        // accessed again. This is important for remote files.
        $form_state->set($dimension_key, ['width' => $variables['width'], 'height' => $variables['height']]);
      }
      // If we have a default image, show that.
      elseif (!empty($element['#default_image'])) {
        $default_image = $element['#default_image'];
        $file = File::load($default_image['fid']);
        if (!empty($file)) {
          $element['preview'] = [
            '#weight' => -10,
            '#theme' => 'image_style',
            '#width' => $default_image['width'],
            '#height' => $default_image['height'],
            '#style_name' => $element['#preview_image_style'],
            '#uri' => $file->getFileUri(),
          ];
        }
      }
    }

    // Add the additional alt and title fields.
    $element['alt'] = [
      '#title' => new TranslatableMarkup('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $item['alt'] ?? '',
      '#description' => new TranslatableMarkup('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $item['fids'] && $element['#alt_field'],
      '#required' => $element['#alt_field_required'],
      '#element_validate' => $element['#alt_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];
    $element['title'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Title'),
      '#default_value' => $item['title'] ?? '',
      '#description' => new TranslatableMarkup('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $item['fids'] && $element['#title_field'],
      '#required' => $element['#title_field_required'],
      '#element_validate' => $element['#title_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];

    // From ImageWidget::process().
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    $element['#theme'] = 'image_widget';

    // Add the image preview.
    if (!empty($element['#files']) && $element['#preview_image_style']) {
      $file = reset($element['#files']);
      $variables = [
        'style_name' => $element['#preview_image_style'],
        'uri' => $file->getFileUri(),
      ];

      $dimension_key = $variables['uri'] . '.image_preview_dimensions';
      // Determine image dimensions.
      if (isset($element['#value']['width']) && isset($element['#value']['height'])) {
        $variables['width'] = $element['#value']['width'];
        $variables['height'] = $element['#value']['height'];
      }
      elseif ($form_state->has($dimension_key)) {
        $variables += $form_state->get($dimension_key);
      }
      else {
        $image = \Drupal::service('image.factory')->get($file->getFileUri());
        if ($image->isValid()) {
          $variables['width'] = $image->getWidth();
          $variables['height'] = $image->getHeight();
        }
        else {
          $variables['width'] = $variables['height'] = NULL;
        }
      }

      $element['preview'] = [
        '#weight' => -10,
        '#theme' => 'image_style',
        '#width' => $variables['width'],
        '#height' => $variables['height'],
        '#style_name' => $variables['style_name'],
        '#uri' => $variables['uri'],
      ];

      // Store the dimensions in the form so the file doesn't have to be
      // accessed again. This is important for remote files.
      $form_state->set($dimension_key, ['width' => $variables['width'], 'height' => $variables['height']]);
    }
    elseif (!empty($element['#default_image'])) {
      $default_image = $element['#default_image'];
      $file = File::load($default_image['fid']);
      if (!empty($file)) {
        $element['preview'] = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => $default_image['width'],
          '#height' => $default_image['height'],
          '#style_name' => $element['#preview_image_style'],
          '#uri' => $file->getFileUri(),
        ];
      }
    }

    // Add the additional alt and title fields.
    $element['alt'] = [
      '#title' => new TranslatableMarkup('Alternative text'),
      '#type' => 'textfield',
      '#default_value' => $item['alt'] ?? '',
      '#description' => new TranslatableMarkup('Short description of the image used by screen readers and displayed when the image is not loaded. This is important for accessibility.'),
      // @see https://www.drupal.org/node/465106#alt-text
      '#maxlength' => 512,
      '#weight' => -12,
      '#access' => (bool) $item['fids'] && $element['#alt_field'],
      '#required' => $element['#alt_field_required'],
      '#element_validate' => $element['#alt_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];
    $element['title'] = [
      '#type' => 'textfield',
      '#title' => new TranslatableMarkup('Title'),
      '#default_value' => $item['title'] ?? '',
      '#description' => new TranslatableMarkup('The title is used as a tool tip when the user hovers the mouse over the image.'),
      '#maxlength' => 1024,
      '#weight' => -11,
      '#access' => (bool) $item['fids'] && $element['#title_field'],
      '#required' => $element['#title_field_required'],
      '#element_validate' => $element['#title_field_required'] == 1 ? [[static::class, 'validateRequiredFields']] : [],
    ];

    // From FileWidget::process().
    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // Add the display field if enabled.
    if ($element['#display_field']) {
      $element['display'] = [
        '#type' => empty($item['fids']) ? 'hidden' : 'checkbox',
        '#title' => new TranslatableMarkup('Include file in display'),
        '#attributes' => ['class' => ['file-display']],
      ];
      if (isset($item['display'])) {
        $element['display']['#value'] = $item['display'] ? '1' : '';
      }
      else {
        $element['display']['#value'] = $element['#display_default'];
      }
    }
    else {
      $element['display'] = [
        '#type' => 'hidden',
        '#value' => '1',
      ];
    }

    // Add the description field if enabled.
    if ($element['#description_field'] && $item['fids']) {
      $config = \Drupal::config('file.settings');
      $element['description'] = [
        '#type' => $config->get('description.type'),
        '#title' => new TranslatableMarkup('Description'),
        '#value' => $item['description'] ?? '',
        '#maxlength' => $config->get('description.length'),
        '#description' => new TranslatableMarkup('The description may be used as the label of the link to the file.'),
      ];
    }

    // Adjust the Ajax settings so that on upload and remove of any individual
    // file, the entire group of file fields is updated together.
    if ($element['#cardinality'] != 1) {
      $parents = array_slice($element['#array_parents'], 0, -1);
      $new_options = [
        'query' => [
          'element_parents' => implode('/', $parents),
        ],
      ];
      $field_element = NestedArray::getValue($form, $parents);
      $new_wrapper = $field_element['#id'] . '-ajax-wrapper';
      foreach (Element::children($element) as $key) {
        if (isset($element[$key]['#ajax'])) {
          $element[$key]['#ajax']['options'] = $new_options;
          $element[$key]['#ajax']['wrapper'] = $new_wrapper;
        }
      }
      unset($element['#prefix'], $element['#suffix']);
    }

    // Add another submit handler to the upload and remove buttons, to implement
    // functionality needed by the field widget. This submit handler, along with
    // the rebuild logic in file_field_widget_form() requires the entire field,
    // not just the individual item, to be valid.
    foreach (['upload_button', 'remove_button'] as $key) {
      $element[$key]['#submit'][] = [static::class, 'submit'];
      $element[$key]['#limit_validation_errors'] = [array_slice($element['#parents'], 0, -1)];
    }

    return $element;
  }

}
