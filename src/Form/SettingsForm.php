<?php declare(strict_types = 1);

namespace Drupal\ximilar_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Ximilar API settings for this site.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ximilar_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ximilar_api.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['authentication_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authentication Token'),
      '#default_value' => $this->config('ximilar_api.settings')->get('authentication_token'),
    ];
    $form['collection_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Collection ID'),
      '#default_value' => $this->config('ximilar_api.settings')->get('collection_id'),
    ];
    $form['verbose_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verbose Logging'),
      '#default_value' => $this->config('ximilar_api.settings')->get('verbose_logging'),
      '#description' => $this->t('Switch on to log everything - for debugging and testing.'),
    ];
    $form['image_data_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Image data type'),
      '#options' => [
        'url' => $this->t('Pass the absolute URL of images'),
        'base64' => $this->t('Pass the image encoded as base64'),
      ],
      '#default_value' => $this->config('ximilar_api.settings')->get('image_data_type'),
      '#description' => $this->t('How image data should be passed to the API. URL is much faster but requires that your images are publically accessible.'),
    ];
    $form['similarity_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Similarity Threshold'),
      '#default_value' => $this->config('ximilar_api.settings')->get('similarity_threshold'),
      '#description' => $this->t('How similar (0 = identical, 1 = most different) an image must be to be considered a duplicate.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ximilar_api.settings')
      ->set('authentication_token', $form_state->getValue('authentication_token'))
      ->set('collection_id', $form_state->getValue('collection_id'))
      ->set('verbose_logging', boolval($form_state->getValue('verbose_logging')))
      ->set('image_data_type', $form_state->getValue('image_data_type'))
      ->set('similarity_threshold', $form_state->getValue('similarity_threshold'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
