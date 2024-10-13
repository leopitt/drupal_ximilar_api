<?php

namespace Drupal\ximilar_api\Plugin\Validation\Constraint;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the MediaImageConstraint constraint.
 */
class MediaImageSimilarityConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    foreach ($value as $item) {
      // Get the target_id.
      $target_id = $item->target_id;
      // Get the file.
      $file = File::load($target_id);

      if ($file) {
        // Get the Ximilar API service.
        /** @var \Drupal\ximilar_api\Service\XimilarAPIService $ximilar_api */
        $ximilar_api = \Drupal::service('ximilar_api.service');
        // Find near duplicate images.
        $near_duplicates = $ximilar_api->nearDuplicates($file);

        // If there are near duplicates, add a violation.
        if ($near_duplicates) {
          // Load the image style.
          $image_style = ImageStyle::load('thumbnail');
          // Prepare a string into which will add image markup for each of our duplicates.
          $duplicates_markup = '';

          // Loop over the duplicates.
          foreach ($near_duplicates as $duplicate) {
            // Get the duplicate file.
            $duplicate_file = File::load($duplicate['id']);
            // Get the original file URL.
            $duplicate_original_url = $duplicate_thumbnail_url = $duplicate_file->createFileUrl();

            // Get the thumbnail file URL.
            if ($image_style) {
              // Generate the thumbnail URL.
              $duplicate_thumbnail_url = $image_style->buildUrl($duplicate_file->getFileUri());
            }

            // Concatenate the duplicate image markup.
            $duplicates_markup .= '<a href="' . $duplicate_original_url . '" target="_blank"><img src="' . $duplicate_thumbnail_url . '"/></a>';
          }

          // Add the duplicates markup to the violation message.
          $constraint->message .= '<br>' . $duplicates_markup;

          $this->context->addViolation($constraint->message);
        }
      }
    }
  }
}
