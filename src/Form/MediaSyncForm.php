<?php

declare(strict_types=1);

namespace Drupal\ximilar_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

/**
 * Provides a Ximilar API form.
 */
final class MediaSyncForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ximilar_api_media_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Sync Library to Collection'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Check that a collection is available.
    $collection_id = $this->config('ximilar_api.settings')->get('collection_id');
    if (empty($collection_id)) {
      $form_state->setError($form['actions']['submit'], $this->t('No collection ID is available. Please configure the Ximilar API settings.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Define the batch process.
    $batch = [
      'title' => $this->t('Syncing media library...'),
      'operations' => [
        ['\Drupal\ximilar_api\Form\MediaSyncForm::processMediaImages', []],
      ],
      'finished' => '\Drupal\ximilar_api\Form\MediaSyncForm::batchFinished',
    ];

    // Set the batch.
    batch_set($batch);
  }

  /**
   * Batch operation callback: Process media images.
   *
   * @param array $context
   *   The batch context.
   */
  public static function processMediaImages(array &$context) {
    // Get the Ximilar API service.
    /** @var \Drupal\ximilar_api\Service\XimilarAPIService $ximilar_api */
    $ximilar_api = \Drupal::service('ximilar_api.service');

    // If we haven't already got a total, then we need to initialise
    // the loop.
    if (!isset($context['sandbox']['items_total'])) {
      // Start from 0.
      $context['sandbox']['current_media_id'] = 0;
      // Items processed.
      $context['sandbox']['items_processed'] = 0;
      // Get the total number of media entities.
      $context['sandbox']['items_total'] = count(\Drupal::entityQuery('media')
        ->accessCheck(FALSE)
        ->condition('bundle', 'image')
        ->execute());
    }

    $media_ids = \Drupal::entityQuery('media')
      ->accessCheck(FALSE)
      ->condition('bundle', 'image')
      ->condition('mid', $context['sandbox']['current_media_id'], '>')
      // Order by media ID.
      ->sort('mid', 'ASC')
      ->range(0, 1)
      ->execute();

    // Loop over the media.
    foreach ($media_ids as $media_id) {
      // Load the media entity.
      $media = Media::load($media_id);
      // Update the current media ID.
      $context['sandbox']['current_media_id'] = $media_id;

      if ($media) {
        // Get the associated file entity.
        $file_id = $media->get('field_media_image')->target_id;
        // Load the file entity.
        $file = File::load($file_id);

        if ($file) {
          // Get the file name.
          $file_name = $file->getFilename();
          // Output a debug message.
          $context['message'] = t('Processing file: fid: @fid, filename: @filename', ['@fid' => $file_id, '@filename' => $file_name]);
          // Insert the image to the Ximilar collection.
          $ximilar_api->insert([$file]);
          // Add a results message.
          $context['results']['messages'][] = t('Synced file <em>@filename</em> (@fid)', ['@fid' => $file_id, '@filename' => $file_name]);
        }
      }

      $context['sandbox']['items_processed']++;
      $context['finished'] = $context['sandbox']['items_processed'] / $context['sandbox']['items_total'];

      if ($context['finished'] == 1) {
        // Set some results.
      }
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch finished successfully.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The operations that were performed.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      \Drupal::messenger()->addStatus(t('Media sync completed successfully.'));
      \Drupal::messenger()->addStatus(implode("<br>", $results['messages']));
    }
    else {
      \Drupal::messenger()->addError(t('Media sync encountered an error.'));
    }
  }

}
