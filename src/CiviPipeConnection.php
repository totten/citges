<?php

namespace Civi\Coworker;

use Civi\Coworker\Util\IdUtil;
use Civi\Coworker\Util\JsonRpc;
use Monolog\Logger;
use React\Promise\PromiseInterface;
use function React\Promise\reject;

/**
 * Wrapper for PipeConnection which encodes and decodes Civi-specific
 * requests.
 */
class CiviPipeConnection {

  const MINIMUM_VERSION = '5.46.alpha1';
  //  const MINIMUM_VERSION = '5.49.alpha1';

  /**
   * @var \Civi\Coworker\PipeConnection
   */
  protected $pipeConnection;

  /**
   * @var \Monolog\Logger
   */
  protected $logger;

  /**
   * @var array
   */
  protected $welcome;

  /**
   * @param \Civi\Coworker\PipeConnection $pipeConnection
   * @param \Monolog\Logger|NULL $logger
   */
  public function __construct($pipeConnection, ?\Monolog\Logger $logger) {
    $this->pipeConnection = $pipeConnection;
    $this->logger = $logger ?: new Logger(static::CLASS);
  }

  /**
   * @return \React\Promise\PromiseInterface
   *   Promise<array> - List of header flags
   * @see \Civi\Coworker\PipeConnection::start()
   */
  public function start(): PromiseInterface {
    return $this->pipeConnection->start()
      ->then(function (string $welcomeLine) {
        $welcome = json_decode($welcomeLine, 1);
        if (!isset($welcome['Civi::pipe'])) {
          return reject(new \Exception('Malformed header: ' . $welcomeLine));
        }
        if (empty($welcome['Civi::pipe']['v']) || version_compare($welcome['Civi::pipe']['v'], self::MINIMUM_VERSION, '<')) {
          return reject(new \Exception(sprintf("Expected minimum CiviCRM version %s. Received welcome: %s\n", self::MINIMUM_VERSION, $welcomeLine)));
        }
        $this->welcome = $welcome;
        $this->logger->notice('Connected', ['Civi::pipe' => $welcome['Civi::pipe']]);
        return $welcome['Civi::pipe'];
      });
  }

  /**
   * @param float $timeout
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Coworker\PipeConnection::stop
   */
  public function stop(float $timeout = 1.5): PromiseInterface {
    return $this->pipeConnection->stop($timeout);
  }

  public function request(string $method, array $params = [], ?string $caller = NULL): PromiseInterface {
    $id = IdUtil::next(__CLASS__ . '::request');

    $requestLine = JsonRpc::createRequest($method, $params, $id);
    $request = ['method' => $method, 'params' => $params, 'caller' => $caller];
    // $this->logger->debug(sprintf('Send request #%s: %s', $id, $requestLine));
    return $this->pipeConnection->run($requestLine)
      ->then(function(string $responseLine) use ($request, $id) {
        // $this->logger->debug(sprintf('Receive response #%s: %s', $id, $responseLine));
        return JsonRpc::parseResponse($responseLine, $id, $request);
      });
  }

  /**
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::api3()
   */
  public function api3(string $entity, string $action, array $params = []): PromiseInterface {
    return $this->request('api3', [$entity, $action, $params], $this->findCaller(2));
  }

  /**
   * @param string $entity
   * @param string $action
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::api4()
   */
  public function api4(string $entity, string $action, array $params = []): PromiseInterface {
    return $this->request('api4', [$entity, $action, $params], $this->findCaller(2));
  }

  /**
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::login()
   */
  public function login(array $params): PromiseInterface {
    return $this->request('login', $params, $this->findCaller(2));
  }

  /**
   * @param array $params
   * @return \React\Promise\PromiseInterface
   * @see \Civi\Pipe\PublicMethods::options()
   */
  public function options(array $params): PromiseInterface {
    return $this->request('options', $params, $this->findCaller(2));
  }

  protected function findCaller(int $steps): string {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $steps);
    $caller = array_pop($trace);
    $result = sprintf('%s @ %s', $caller['file'], $caller['line']);
    return $result;
  }

}
