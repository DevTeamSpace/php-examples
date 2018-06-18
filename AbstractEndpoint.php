<?php
/**
 * @author Eugene Zhukov <e.zhukov@dunice.net>
 * @date 15.03.18 10:30
 */

namespace Drupal\API;

use GuzzleHttp\Exception\GuzzleException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class AbstractEndpoint.
 *
 * @package Drupal\API
 */
abstract class AbstractEndpoint implements EndpointBaseInterface {

  /**
   * Reference to object, who creates this object by reflection.
   *
   * @var null
   */
  protected $caller;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * A configuration instance.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config;

  /**
   * Base URI.
   *
   * @var string
   */
  protected $baseURL;

  /**
   * API Service Account.
   *
   * @var array
   */
  protected $serviceAccountData = [
    'name' => '',
    'password' => '',
  ];

  /**
   * Current Endpoint full-qualified class name.
   *
   * @var array|string
   */
  protected $className = '';

  /**
   * Requesting endpoint URI.
   *
   * @var string
   */
  protected $path;

  /**
   * Endpoint URI modifiers/variable formatters.
   *
   * @var array
   */
  protected $pathArgs = array();

  /**
   * Current directory absolute path.
   *
   * @var string
   */
  protected $currentDir;

  /**
   * Child Node Endpoints array.
   *
   * @var array|null
   */
  protected $childNodes = array();

  /**
   * Contains an array of child leaves.
   *
   * @var array|null
   */
  protected $childLeaves = array();

  /**
   * Variate endpoints params (like URI placeholders, request data etc.).
   *
   * @var array
   */
  protected $endpointParams = array();

  /**
   * Request array.
   *
   * Contains data for processing via post() / get().
   *
   * @var array
   */
  public $request = [
    'headers' => [],
    'body' => '',
  ];

  /**
   * Updating superclass data by child classes.
   */
  protected function updateCaller() {
    if ($this->caller) {
      $this->caller->request['headers'] = $this->request['headers'];
      $this->caller->request['body'] = NULL;
      $this->caller->updateCaller();
    }
  }

  /**
   * Mapping initial service's arguments to root endpoint params.
   *
   * @param array $endpointParams
   *   Endpoint params, passed to service initialization.
   */
  private function mapParamsToProperties(array $endpointParams) {
    $this->endpointParams = $endpointParams;

    if (array_key_exists('httpClient', $endpointParams)) {
      $this->httpClient = $endpointParams['httpClient'];
    }

    if (array_key_exists('configFactory', $endpointParams)) {
      $config = $endpointParams['configFactory']->get('API.settings');
      $this->baseURL = $config->get('base_uri');
      $this->serviceAccountData['name'] = $config->get('name');
      $this->serviceAccountData['password'] = $config->get('password');
    }

    if (array_key_exists('executingDir', $endpointParams)) {
      $this->currentDir = $endpointParams['executingDir'];
    }
  }

  /**
   * Prepare endpoints with variables in URI to using.
   *
   * @param array ...$vars
   *   Formatting variables.
   */
  protected function mapPathVariablesToPath(...$vars) {
    // Check passed values for emptiness.
    if (sizeof(array_filter($vars, function($value) {
      return !empty($value);
    })) !== 0) {
      $this->path = sprintf($this->path, ...$vars);
    }
    else {
      $this->path = str_replace('%s/', '', $this->path);
    }
  }

  /**
   * Receiving current node/leaf namespace.
   *
   * @return string
   *   Founded namespace.
   */
  protected function getCurrentNamespace() {
    $currentClass = get_class($this);
    $refl = new ReflectionClass($currentClass);
    $namespace = $refl->getNamespaceName();

    return $namespace;
  }

  /**
   * Generate leaf endpoint full qualified class name (namespace + class name).
   *
   * @param string $leafName
   *   Leaf endpoint name.
   *
   * @return string
   *   Full qualified leaf endpoint class name.
   */
  protected function makeLeafFullQualifiedClassName($leafName) {
    $namespace = $this->getCurrentNamespace();
    $className =
      $namespace
      . '\\'
      . $leafName;

    return $className;
  }

  /**
   * Generate node endpoint full qualified class name (namespace + class name).
   *
   * @param string $nodeName
   *   Node endpoint name.
   *
   * @return string
   *   Full qualified node endpoint class name.
   */
  protected function makeNodeFullQualifiedClassName($nodeName) {
    $namespace = $this->getCurrentNamespace();
    $className = $namespace
      . '\\'
      . array_search($nodeName, $this->childNodes)
      . '\\'
      . $nodeName;

    return $className;
  }

  /**
   * HTTP response decoding.
   *
   * @param string $rawResponse
   *   Raw HTTP response string.
   *
   * @return mixed
   *   Decoded HTTP response.
   */
  protected function decodeResponse($rawResponse) {
    return json_decode($rawResponse);
  }

  /**
   * HTTP request encoding.
   *
   * @param mixed $rawRequest
   *   Raw HTTP request.
   *
   * @return string
   *   Encoded HTTP request string.
   */
  protected function encodeRequest($rawRequest) {
    return json_encode($rawRequest);
  }

  /**
   * {@inheritdoc}
   */
  public function get(...$params) {
    $response = NULL;

    try {
      $request = [
        'headers' => $this->request['headers'],
      ];
      $defaultParams = [
        'timeout' => 35, // Response timeout
        'connect_timeout' => 3,
        'headers' => $this->request['headers'],
      ];

      \Drupal::logger('API')->debug('API v2.0 GET request contents: <br><pre>' . json_encode($request, JSON_PRETTY_PRINT) . '</pre>');

      if (count($params) !== 0) {
        $defaultParams = array_merge($params, $defaultParams);
      }

      $httpRequest = $this->httpClient->request(
        'get',
        $this->baseURL . $this->path,
        $defaultParams
      );

      $response = [
        'object' => $httpRequest,
        'body' => json_decode((string) $httpRequest->getBody()),
      ];

      \Drupal::logger('API::HTTPClient')->notice('Get response: <br><pre>'  . json_encode($response, JSON_PRETTY_PRINT) . '</pre>');
    }
    catch (GuzzleException $exception) {
      \Drupal::logger('API')->error(
        'Error during making GET request to \''
        . $this->path
        . '\' endpoint.'
        . PHP_EOL
        . 'Details: '
        . $exception->getMessage()
        . '. Request details: '
        . json_encode($request)
      );

      throw new \Exception('API GET Error occured. Details: ' . $exception->getMessage());

    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function post() {
    $response = NULL;
    $headers = $this->request['headers'];
    $headers['Content-Type'] = 'application/json';
    try {
      \Drupal::logger('API')->debug('API v2.0 POST request contents: <br><pre>' . json_encode($this->request, JSON_PRETTY_PRINT) . '</pre>');

      $request = [
        'headers' => $headers,
        'body' => $this->encodeRequest($this->request['body']),
      ];

      $httpRequest = $this->httpClient->post(
        $this->baseURL . $this->path,
        $request);

      $response = [
        'object' => $httpRequest,
        'body' => json_decode((string) $httpRequest->getBody()),
      ];

      \Drupal::logger('API::HTTPClient')->notice('Post response: <br><pre>'  . json_encode($response, JSON_PRETTY_PRINT) . '</pre>');
    }
    catch (GuzzleException $exception) {
      \Drupal::logger('API')->error(
        'Error during making POST request to \''
        . $this->path
        . '\' endpoint.'
        . PHP_EOL
        . 'Details: '
        . $exception->getMessage()
      );
      throw new \Exception('API POST Error occured. Details: <br><pre>' . $exception->getMessage() . '</pre>');
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function put() {
    $response = NULL;
    $headers = $this->request['headers'];
    $headers['Content-Type'] = 'application/json';
    try {
      $request = [
        'headers' => $headers,
        'body' => $this->encodeRequest($this->request['body']),
      ];

      \Drupal::logger('API')->debug('API v2.0 PUT request contents: <br><pre>' . json_encode($request, JSON_PRETTY_PRINT) . '</pre>');

      $httpRequest = $this->httpClient->request(
        'put',
        $this->baseURL . $this->path,
        $request);

      $response = [
        'object' => $httpRequest,
        'body' => json_decode((string) $httpRequest->getBody()),
      ];
    }
    catch (GuzzleException $exception) {
      \Drupal::logger('API')->error(
        'Error during making PUT request to \''
        . $this->path
        . '\' endpoint.'
        . PHP_EOL
        . 'Details: '
        . $exception->getMessage()
      );
      throw new \Exception('API PUT Error occured. Details: <br><pre>' . $exception->getMessage() . '</pre>');
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $response = NULL;

    try {
      $request = [
        'headers' => $this->request['headers'],
      ];
      $defaultParams = [
        'connect_timeout' => 3,
        'headers' => $this->request['headers'],
      ];

      \Drupal::logger('API')->debug('API v2.0 GET request contents: <br><pre>' . json_encode($request, JSON_PRETTY_PRINT) . '</pre>');

      if (count($params) !== 0) {
        $defaultParams = array_merge($params, $defaultParams);
      }

      \Drupal::logger('API::HTTPClient')->notice('Get params: <br><pre>' . json_encode($defaultParams, JSON_PRETTY_PRINT) . '</pre>');

      $httpRequest = $this->httpClient->request(
        'delete',
        $this->baseURL . $this->path,
        $defaultParams
      );


      $response = [
        'object' => $httpRequest,
      ];

      \Drupal::logger('API::HTTPClient')->notice('Get response: <br><pre>'  . json_encode($response, JSON_PRETTY_PRINT) . '</pre>');
    }
    catch (GuzzleException $exception) {
      \Drupal::logger('API')->error(
        'Error during making GET request to \''
        . $this->path
        . '\' endpoint.'
        . PHP_EOL
        . 'Details: '
        . $exception->getMessage()
        . '. Request details: '
        . json_encode($request)
      );

      throw new \Exception('API GET Error occured. Details: <br><pre>' . $exception->getMessage() . '</pre>');

    }

    return $response;
  }

  /**
   * Endpoint URI getter.
   *
   * @return string
   *   $this->path value.
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Magic properties setter, based on Reflection.
   *
   * Why? Because reflection is perfection of OOP.
   *
   * @param string $name
   *   Property name.
   * @param mixed $value
   *   New property value.
   *
   * @throws \InvalidArgumentException
   *   If property does not exists.
   */
  public function __set($name, $value) {
    $reflecton = new \ReflectionClass($this);
    try {
      $refProperty = new ReflectionProperty($this, $name);
      if (in_array($refProperty,
        $reflecton->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED))) {
        if (gettype($value) === gettype($this->$name)) {
          $this->$name = $value;
        }
      }
    }
    catch (\ReflectionException $e) {
      throw new \InvalidArgumentException('Error on property value setting. Details: '
        . $e->getMessage());
    }
  }

  /**
   * Add specific headers on the fly.
   *
   * @param array $headers
   *   Headers for adding.
   */
  public function addHeaders(array $headers) {
    foreach ($headers as $header => $value) {
      $this->request['headers'][$header] = $value;
    }
  }

  /**
   * @param $body
   */
  public function addBody($body) {
    if (!empty($body)) {
      $this->request['body'] = $body;
    }
    return $this;
  }

  /**
   * AbstractEndpoint constructor.
   *
   * @param array $endpointParams
   *   Node or Leaf Endpoint params.
   */
  public function __construct(array $endpointParams = []) {
    $this->mapParamsToProperties($endpointParams);
  }

}
