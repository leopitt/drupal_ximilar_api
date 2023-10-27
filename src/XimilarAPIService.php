<?php declare(strict_types = 1);

namespace Drupal\ximilar_api;

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
   * Image data type to use.
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
    $this->verboseLogging = $this->configFactory->get('ximilar_api.settings')->get('verbose_logging');
    $this->imageDataType = $this->configFactory->get('ximilar_api.settings')->get('image_data_type');
    $this->similarityThreshold = $this->configFactory->get('ximilar_api.settings')->get('similarity_threshold');
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

    try {
      $endpoint = $this->apiBaseUrl . 'insert';
      $request = [
        'headers' => $this->getHttpRequestHeaders(),
        'json' => [
          'fields_to_return' => [
            '_id',
          ],
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
          $file = File::load($id);
          $url_string = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
          $url = URL::fromUri($url_string);
          //\Drupal::messenger()->addMessage(Link::fromTextAndUrl('File ID ' . $id . ' has a distance of ' . $distance, $url));
          $near_duplicates[] = [
            'id' => $id,
            'distance' => $distance,
          ];
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
   *    An array of media entity objects.
   *
   * @returns array
   *   An array of records.
   */
  protected function convertImagesToArray(array $image_files): array {
    $json_records = [];

    foreach ($image_files as $image_file) {
      if ($image_file instanceof FileInterface) {
        // Get the file id.
        $record = [
          '_id' => $image_file->id(),
        ];

        $record = array_merge($record, $this->getImageData($image_file));

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
   *     A media entity objects.
   *
   * @returns array
   *    An array with one key, either _url or _base64, depending upon the data type.
   */
  private function getImageData($image_file): array {
    $record = [];

    switch ($this->imageDataType) {
      case 'base64':
        $data = file_get_contents($image_file->getFileUri());
        $base64 = base64_encode($data);
        $record['_base64'] = $base64;
        break;

      default:
        $record['_url'] = $this->fileUrlGenerator->generateAbsoluteString($image_file->getFileUri());
        break;
    }

    if ($this->verboseLogging) {
      $this->logger->info(print_r($record, TRUE));
    }

    return $record;
  }

  /**
   * Get request headers.
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

}
