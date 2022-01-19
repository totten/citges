<?php

namespace Civi\Coworker;

class Configuration {

  /**
   * @var int
   */
  public $maxConcurrentWorkers = 2;

  /**
   * @var int
   */
  public $maxWorkerRequests = 10;

  /**
   * @var int
   */
  public $maxWorkerDuration = 10 * 60;

  /**
   * Whenever we hit the maximum, we have to remove some old workers.
   * How many should we try to remove?
   *
   * @var int
   */
  public $gcWorkers = 1;

  /**
   * External command used to start the pipe.
   *
   * @var string
   *   Ex: 'cv ev "Civi::pipe();"'
   */
  public $pipeCommand;

  /**
   * @var string
   */
  public $logFile;

  /**
   * Level of information to write to log file.
   *
   * One of: debug|info|notice|warning|error|critical|alert|emergency
   *
   * @var string
   */
  public $logLevel;

  /**
   * One of: text|json
   *
   * @var string
   */
  public $logFormat;

  public function __construct(array $values = []) {
    foreach ($values as $field => $value) {
      $this->{$field} = $value;
    }
  }

  public function __set($name, $value) {
    throw new \RuntimeException(sprintf('Unrecognized property: %s::$%s', __CLASS__, $name));
  }

}
