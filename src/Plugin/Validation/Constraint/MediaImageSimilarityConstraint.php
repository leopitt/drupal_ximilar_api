<?php

namespace Drupal\ximilar_api\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a constraint for validating media image field.
 *
 * @Constraint(
 *   id = "MediaImageSimilarity",
 *   label = @Translation("Media Image Ximilarity Similarity constraint", context = "Validation"),
 * )
 */
class MediaImageSimilarityConstraint extends Constraint {
  public $message = 'Near Duplicate images exist in the media library. Please upload a different image or use existing media.';
}
