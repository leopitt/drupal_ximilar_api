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
    // Get media entities of type 'image'.
    if (!isset($context['sandbox']['media_ids'])) {
      $query = \Drupal::entityQuery('media')
        ->accessCheck(FALSE)
        ->condition('bundle', 'image')
        ->execute();

      $context['sandbox']['media_ids'] = $query;
      $context['sandbox']['total'] = count($context['sandbox']['media_ids']);
      $context['sandbox']['current'] = 0;
    }

    $media_ids = array_slice($context['sandbox']['media_ids'], $context['sandbox']['current'], 10);
    foreach ($media_ids as $media_id) {
      $media = Media::load($media_id);
      if ($media) {
        // Get the associated file entity.
        $file_id = $media->get('field_media_image')->target_id;
        $file = File::load($file_id);

        if ($file) {
          $file_name = $file->getFilename();
          $context['message'] = t('Processing file: @fid - @filename', ['@fid' => $file_id, '@filename' => $file_name]);

          // Display the message on screen.
          \Drupal::messenger()->addMessage(t('File ID: @fid, File name: @filename', ['@fid' => $file_id, '@filename' => $file_name]));
        }
      }

      $context['sandbox']['current']++;
      $context['finished'] = $context['sandbox']['current'] / $context['sandbox']['total'];
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
    }
    else {
      \Drupal::messenger()->addError(t('Media sync encountered an error.'));
    }
  }

}
