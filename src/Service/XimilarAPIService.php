<?php declare(strict_types = 1);

namespace Drupal\ximilar_api\Service;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Entity\File;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use phpowermove\docblock\tags\AbstractTag;
use Psr\Http\Client\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * @todo Add class description.
 */
final class XimilarAPIService {

  /**
   * The API base URL.
   *
   * @var string
   */
  private string $apiBaseUrl = 'https://api.ximilar.com/photo/search/v2/';

  /**
   * The config factory service object.
   *
   * @var ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The http_client object.
   *
   * @var ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The default logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The authentication token.
   *
   * @var string
   */
  protected string $authenticationToken;

  /**
   * The collection id.
   *
   * @var string
   */
  protected string $collectionId;

  /**
   * Whether verbose logging is on.
   *
   * @var bool
   */
  protected bool $verboseLogging = FALSE;

  /**
   * Image data type to use. 'base64' or 'url'.
   *
   * @var string
   */
  protected string $imageDataType;

  /**
   * Similarity threshold.
   *
   * @var string
   */
  protected string $similarityThreshold;

  /**
   * Constructs a XimilarAPIService object.
   *
   * @param \Psr\Http\Client\ClientInterface $httpClient
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    ClientInterface $httpClient,
    ConfigFactoryInterface $configFactory,
    FileUrlGeneratorInterface $file_url_generator,
    LoggerInterface $logger) {
    $this->httpClient = $httpClient;
    $this->configFactory = $configFactory;
    $this->fileUrlGenerator = $file_url_generator;
    $this->logger = $logger;

    // Fetch the authentication token and collection id from config.
    $this->authenticationToken = $this->configFactory->get('ximilar_api.settings')->get('authentication_token');
    $this->collectionId = $this->configFactory->get('ximilar_api.settings')->get('collection_id');
    // Get our logging settings - verbose or normal.
    $this->verboseLogging = $this->configFactory->get('ximilar_api.settings')->get('verbose_logging');
    // Get the image data type to use with Ximilar (base64 or url).
    $this->imageDataType = $this->configFactory->get('ximilar_api.settings')->get('image_data_type');
    // Get the similarity threshold.
    $this->similarityThreshold = $this->configFactory->get('ximilar_api.settings')->get('similarity_threshold');
  }

  /**
   * Get request headers that are needed in all Ximilar requests.
   *
   * @returns array
   *   An array of header information for the http request.
   */
  private function getHttpRequestHeaders(): array {
    return [
      'Content-Type' => 'application/json;charset=UTF-8',
      'collection-id' => $this->collectionId,
      'Authorization' => 'Token ' . $this->authenticationToken,
    ];
  }

  /**
   * Generic function to make requests to the Ximilar API.
   *
   * @param string $endpoint_path
   *   The endpoint path to make the request to.
   * @param array $data
   *   The data to send.
   *
   * @returns mixed
   */
  private function makeRequest(string $endpoint_path, array $data): mixed {
    // Construct the full endpoint URL.
    $endpoint = $this->apiBaseUrl . $endpoint_path;

    // Try and make the request.
    try {
      $request = [
        'headers' => $this->getHttpRequestHeaders(),
        'json' => $data,
      ];
      // Get the response.
      $response = $this->httpClient->post($endpoint, $request);

      // If verbose logging is on, log the endpoint, request and response.
      if ($this->verboseLogging) {
        $this->logger->info($endpoint);
        $this->logger->info(print_r($request, TRUE));
        $this->logger->info(print_r($response->getBody()->getContents(), TRUE));
      }
    } catch (GuzzleException $e) {
      // Log the error if there was one.
      $response = $e->getResponse();
      $this->logger->error(print_r($response, TRUE));
    }

    // Return the response.
    return $response;
  }

  /**
   * Insert image(s) to a collection.
   *
   * See https://docs.ximilar.com/services/similarity_search/#v2insert.
   *
   * @param FileInterface[] $image_files
   *   An array of media entity objects.
   */
  public function insert(array $image_files): void {
    if ($this->verboseLogging) {
      $this->logger->info('Inserting an image');
    }

    // Prepare the JSON for the request.
    $json_records = $this->convertImagesToArray($image_files);

    // Prepare the requeest data.
    $request_data = [
      'fields_to_return' => [
        '_id',
      ],
      'records' => $json_records,
    ];

    // Make the request.
    $this->makeRequest('insert', $request_data);
  }

  /**
   * Delete image(s) to a collection.
   *
   * See https://docs.ximilar.com/services/similarity_search/#v2delete.
   *
   * @param FileInterface[] $image_files
   *   An array of media entity objects.
   */
  public function delete(array $image_files): void {
    if ($this->verboseLogging) {
      $this->logger->info('Deleting an image');
    }

    // Prepare the JSON for the request.
    $json_records = $this->convertImagesToArray($image_files, FALSE);

    try {
      $endpoint = $this->apiBaseUrl . 'delete';
      $request = [
        'headers' => $this->getHttpRequestHeaders(),
        'json' => [
          'records' => $json_records,
        ],
      ];
      $response = $this->httpClient->post($endpoint, $request);

      if ($this->verboseLogging) {
        $this->logger->info($endpoint);
        $this->logger->info(print_r($request, TRUE));
        $this->logger->info(print_r($response->getBody()->getContents(), TRUE));
      }
    } catch (GuzzleException $e) {
      $response = $e->getResponse();
      $this->logger->error(print_r($response, TRUE));
    }
  }

  /**
   * Search for near duplicate images in the collection.
   *
   * @param FileInterface $image_file
   *   The image file to search for.
   *
   * @returns array|FALSE
   *   An array of if there are near duplicates, or FALSE if there are none. Each array item has two keys, id and
   *   distance.
   */
  public function nearDuplicates(FileInterface $image_file) {
    if ($this->verboseLogging) {
      $this->logger->info('Searching for near duplicates of an image');
    }

    try {
      $endpoint = $this->apiBaseUrl . 'nearDuplicates';
      $request = [
        'headers' => $this->getHttpRequestHeaders(),
        'json' => [
          'fields_to_return' => [
            '_id',
          ],
          'query_record' => $this->getImageData($image_file),
        ],
      ];
      $response = $this->httpClient->post($endpoint, $request);
      $contents = $response->getBody()->getContents();

      if ($this->verboseLogging) {
        $this->logger->info($endpoint);
        $this->logger->info(print_r($request, TRUE));
        $this->logger->info($contents);
      }

      // Get matches.
      $this->logger->info('Getting results');
      $data = json_decode($contents);
      $near_duplicates = [];

      for ($i = 0; $i < $data->answer_count; $i++) {
        $id = $data->answer_records[$i]->_id;
        $distance = $data->answer_distances[$i];

        if ($distance < $this->similarityThreshold) {
          if ($file = File::load($id)) {
            $url_string = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
            $url = URL::fromUri($url_string);
            $near_duplicates[] = [
              'id' => $id,
              'distance' => $distance,
            ];
          }
        }
      }

      if ($this->verboseLogging) {
        $this->logger->info(implode('; ', $near_duplicates));
      }

    } catch (GuzzleException $e) {
      $response = $e->getResponse();
      $this->logger->error(print_r($response, TRUE));
    }

    if (isset($near_duplicates) && is_array($near_duplicates)) {
      return $near_duplicates;
    }

    return FALSE;
  }

  /**
   * Convert an array of images to records.
   *
   * @param FileInterface[] $image_files
   *   An array of media entity objects.
   * @param bool $include_data
   *   Whether to include data in the records.
   *
   * @returns array
   *   An array of records.
   */
  protected function convertImagesToArray(array $image_files, $include_data = TRUE): array {
    $json_records = [];

    foreach ($image_files as $image_file) {
      if ($image_file instanceof FileInterface) {
        // Get the file id.
        $record = [
          '_id' => $image_file->id(),
        ];

        if ($include_data) {
          // Get the file data.
          $record = array_merge($record, $this->getImageData($image_file));
        }

        // Get the file url.
        $json_records[] = $record;

        if ($this->verboseLogging) {
          $this->logger->info(print_r($record, TRUE));
        }
      }
    }

    return $json_records;
  }

  /**
   * Get image data.
   *
   * @param FileInterface $image_file
   *   A media entity object.
   *
   * @returns array
   *   An array of image data.
   */
  public function getImageData($image_file): array {
    $record = [];

    switch ($this->imageDataType) {
      case 'base64':
        $data = file_get_contents($image_file->getFileUri());
        $record['_base64'] = base64_encode($data);
        break;

      default:
        $record['_url'] = $this->fileUrlGenerator->generateAbsoluteString($image_file->getFileUri());
        break;
    }

    return $record;
  }

}
